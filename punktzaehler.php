<?php
include 'db_connect.php';

$notification = ""; // Variable für Benachrichtigungen

// POST: Spiel schließen
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['close_game'])) {
    $spiel_id = $_POST['spiel_id'];
    $sql = "UPDATE spiele SET closed = 1 WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$spiel_id]);
    $notification = "Das Spiel wurde geschlossen!";
}

// POST: Punkte aktualisieren oder neue Runde per Modal hinzufügen
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['close_game'])) {
    // Punkte vergeben
    if (isset($_POST['spiel_id'], $_POST['team_name'], $_POST['action'], $_POST['runde'])) {
        $spiel_id = $_POST['spiel_id'];
        $team_name = $_POST['team_name'];
        $action = $_POST['action']; // "add_one" oder "add_to_all_except"
        $runde = intval($_POST['runde']);

        $sql = "SELECT teams FROM spiele WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$spiel_id]);
        $spiel = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($spiel) {
            $teams = json_decode($spiel['teams'], true);

            if ($action == "add_one") {
                foreach ($teams as &$team) {
                    if ($team['name'] === $team_name) {
                        foreach ($team['runden'] as &$r) {
                            if ($r['runde'] == $runde && $r['punkte'] !== "X") {
                                $r['punkte'] += 1;
                                break;
                            }
                        }
                        break;
                    }
                }
            } elseif ($action == "add_to_all_except") {
                foreach ($teams as &$team) {
                    if ($team['name'] !== $team_name) {
                        foreach ($team['runden'] as &$r) {
                            if ($r['runde'] == $runde && $r['punkte'] !== "X") {
                                $r['punkte'] += 1;
                                break;
                            }
                        }
                    }
                }
            }
            $teams_json = json_encode($teams);
            $update_sql = "UPDATE spiele SET teams = ? WHERE id = ?";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([$teams_json, $spiel_id]);

            $notification = "Punkte wurden aktualisiert!";
        }
    }
    // Neue Runde über Modal hinzufügen
    elseif (isset($_POST['spiel_id'], $_POST['new_runde_modal'])) {
        $spiel_id = $_POST['spiel_id'];
        $participating = isset($_POST['participating']) ? $_POST['participating'] : [];

        $sql = "SELECT teams FROM spiele WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$spiel_id]);
        $spiel = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($spiel) {
            $teams = json_decode($spiel['teams'], true);
            $max_runde = 0;
            if (!empty($teams[0]['runden'])) {
                foreach ($teams[0]['runden'] as $r) {
                    if ($r['runde'] > $max_runde) {
                        $max_runde = $r['runde'];
                    }
                }
            }
            $new_round = $max_runde + 1;
            foreach ($teams as &$team) {
                // Ist das Team ausgewählt, startet es mit 0 Punkten, ansonsten "X"
                if (in_array($team['name'], $participating)) {
                    $team['runden'][] = [
                        "runde" => $new_round,
                        "punkte" => 0
                    ];
                } else {
                    $team['runden'][] = [
                        "runde" => $new_round,
                        "punkte" => "X"
                    ];
                }
            }
            $teams_json = json_encode($teams);
            $update_sql = "UPDATE spiele SET teams = ? WHERE id = ?";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([$teams_json, $spiel_id]);

            $notification = "Neue Runde {$new_round} wurde hinzugefügt!";
        }
    }
    // Neues Spiel erstellen
    elseif (isset($_POST['create_game'])) {
        $spiel_name = $_POST['spiel_name'];
        $team_names = $_POST['team_names']; // Teamnamen aus dem Formular
        $teams = [];

        // Teamnamen verarbeiten und in die Struktur umwandeln
        foreach (explode(',', $team_names) as $team_name) {
            $teams[] = [
                "name" => trim($team_name),
                "runden" => [] // Leeres Runden-Array
            ];
        }

        $teams_json = json_encode($teams); // JSON-kodierte Teams

        $sql = "INSERT INTO spiele (spiel_name, teams, closed) VALUES (?, ?, 0)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$spiel_name, $teams_json]);

        $notification = "Das Spiel '{$spiel_name}' wurde erstellt!";
    }
}

// Alle Spiele für die Auswahl abrufen
$sql = "SELECT id, spiel_name FROM spiele";
$games = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$selected_game_id = isset($_GET['game_id']) ? intval($_GET['game_id']) : null;
$selected_runde = isset($_GET['runde']) ? intval($_GET['runde']) : 1;
$game = null;
$teams = [];
$rounds = [];

if ($selected_game_id) {
    $sql = "SELECT * FROM spiele WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$selected_game_id]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($game) {
        $teams = json_decode($game['teams'], true);
        if (isset($teams[0]['runden'])) {
            foreach ($teams[0]['runden'] as $r) {
                $rounds[] = $r['runde'];
            }
        }
    }
}

// Berechne den höchsten Punktestand in der aktuellen Runde (nur für aktive Teams)
$max_points = null;
foreach ($teams as $team) {
    foreach ($team['runden'] as $r) {
         if ($r['runde'] == $selected_runde) {
              if ($r['punkte'] !== "X" && is_numeric($r['punkte'])) {
                    if ($max_points === null || $r['punkte'] > $max_points) {
                        $max_points = $r['punkte'];
                    }
              }
              break;
         }
    }
}
if ($max_points === null) {
    $max_points = 0;
}

// Nummer der nächsten Runde für das Modal berechnen
$new_round = 1;
if (!empty($rounds)) {
    $new_round = max($rounds) + 1;
}

// Prüfe, ob das Spiel geschlossen ist (über die Datenbank)
$isClosed = ($game && isset($game['closed']) && $game['closed'] == 1);

// Falls das Spiel geschlossen ist, berechne Rundensieger und Gesamtsieger:
if ($isClosed) {
    // Rundensieger ermitteln:
    $round_summary = [];
    foreach ($rounds as $rnum) {
        $max = -1;
        $winners = [];
        foreach ($teams as $team) {
            $points = null;
            foreach ($team['runden'] as $rounddata) {
                if ($rounddata['runde'] == $rnum) {
                    $points = $rounddata['punkte'];
                    break;
                }
            }
            if ($points === "X" || $points === null) continue;
            if ($points > $max) {
                $max = $points;
                $winners = [$team['name']];
            } elseif ($points == $max) {
                $winners[] = $team['name'];
            }
        }
        $round_summary[$rnum] = ["max" => $max, "winners" => $winners];
    }

    // Gesamtsieger ermitteln (Summe aller Runden, "X" wird als 0 gezählt):
    $overall_scores = [];
    foreach ($teams as $team) {
        $total = 0;
        foreach ($team['runden'] as $rounddata) {
            if ($rounddata['punkte'] !== "X" && is_numeric($rounddata['punkte'])) {
                $total += $rounddata['punkte'];
            }
        }
        $overall_scores[$team['name']] = $total;
    }
    $max_total = max($overall_scores);
    $overall_winners = array_keys($overall_scores, $max_total);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Team Punktestand mit Runden, Teilnahme &amp; Siegerhervorhebung</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 15px;
            text-align: center;
            color: #333;
        }
        h2, h3, label {
            margin-bottom: 10px;
        }
        select, button {
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #ccc;
            margin: 5px 0;
            cursor: pointer;
            width: 100%;
            max-width: 300px;
        }
        button {
            background: #28a745;
            color: #fff;
            font-size: 16px;
            transition: background 0.3s;
        }
        button:hover {
            background: #218838;
        }
        .add-round-btn, .create-game-btn {
            margin-top: 10px;
        }
        .container {
            max-width: 600px;
            margin: auto;
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .table-container {
            overflow-x: auto;
            margin-top: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }
        table, th, td {
            border: 1px solid #ccc;
        }
        th, td {
            padding: 8px;
            text-align: center;
        }
        th {
            background: #28a745;
            color: #fff;
        }
        tr.winner td {
            border: 2px solid gold;
        }
        .modal {
          display: none;
          position: fixed;
          z-index: 1;
          left: 0;
          top: 0;
          width: 100%;
          height: 100%;
          overflow: auto;
          background-color: rgba(0,0,0,0.4);
        }
        .modal-content {
          background-color: #fefefe;
          margin: 15% auto;
          padding: 20px;
          border: 1px solid #888;
          width: 90%;
          max-width: 300px;
          border-radius: 5px;
        }
        .close {
          color: #aaa;
          float: right;
          font-size: 28px;
          font-weight: bold;
          cursor: pointer;
        }
        .notification {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: #4CAF50;
            color: #fff;
            padding: 15px 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
            z-index: 1000;
            font-size: 16px;
            display: none;
        }
        .close-game-btn {
            display: inline-block;
            margin: 10px 0;
            padding: 10px;
            background: #d9534f;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            width: 100%;
            max-width: 300px;
        }
        .eval-table {
            margin-top: 20px;
            width: 100%;
            border-collapse: collapse;
        }
        .eval-table th, .eval-table td {
            border: 1px solid #ccc;
            padding: 6px;
            text-align: center;
        }
        .eval-table th {
            background: #007bff;
            color: #fff;
        }
        @media (max-width: 600px) {
            select, button {
                font-size: 14px;
            }
            th, td {
                padding: 6px;
            }
        }
    </style>
</head>
<body>
    <div id="notification" class="notification"><?php echo htmlspecialchars($notification); ?></div>
    
    <div class="container">
        <h2>Spielergebnisse</h2>
        
        <!-- Spielauswahl -->
        <form method="GET">
            <label for="game_id">Spiel auswählen:</label>
            <select name="game_id" id="game_id" onchange="this.form.submit()">
                <option value="">-- Spiel wählen --</option>
                <?php foreach ($games as $g) { ?>
                    <option value="<?php echo $g['id']; ?>" <?php if ($selected_game_id == $g['id']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($g['spiel_name']); ?>
                    </option>
                <?php } ?>
            </select>
        </form>

        <button type="button" id="openCreateGameModalBtn" class="create-game-btn">+ Neues Spiel erstellen</button>

        <?php if ($game): ?>
            <h3><?php echo htmlspecialchars($game['spiel_name']); ?></h3>
            
            <?php if (!$isClosed): ?>
                <form method="GET">
                    <input type="hidden" name="game_id" value="<?php echo $selected_game_id; ?>">
                    <label for="runde">Runde auswählen:</label>
                    <select name="runde" id="runde" onchange="this.form.submit()">
                        <?php foreach ($rounds as $r) { ?>
                            <option value="<?php echo $r; ?>" <?php if ($selected_runde == $r) echo 'selected'; ?>>
                                Runde <?php echo $r; ?>
                            </option>
                        <?php } ?>
                    </select>
                </form>

                <button type="button" id="openModalBtn" class="add-round-btn">+ Neue Runde hinzufügen</button>

                <div class="table-container">
                    <table>
                        <tr>
                            <th>Team</th>
                            <th>Punkte (Runde <?php echo $selected_runde; ?>)</th>
                            <th>Aktion</th>
                        </tr>
                        <?php foreach ($teams as $team): 
                            $current_points = "N/A";
                            foreach ($team['runden'] as $r) {
                                if ($r['runde'] == $selected_runde) {
                                    $current_points = $r['punkte'];
                                    break;
                                }
                            }
                            $isWinner = ($current_points !== "X" && is_numeric($current_points) && $current_points == $max_points);
                        ?>
                            <tr <?php if($isWinner) echo 'class="winner"'; ?>>
                                <td>
                                    <?php if ($isWinner): ?>
                                        👑 
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($team['name']); ?>
                                </td>
                                <td>
                                    <?php echo ($current_points === "X") ? "Ausgesetzt" : $current_points; ?>
                                </td>
                                <td>
                                    <?php if ($current_points === "X"): ?>
                                        Keine Aktion
                                    <?php else: ?>
                                        <form method="POST" style="display:inline-block; margin-right:5px;">
                                            <input type="hidden" name="spiel_id" value="<?php echo $game['id']; ?>">
                                            <input type="hidden" name="team_name" value="<?php echo htmlspecialchars($team['name']); ?>">
                                            <input type="hidden" name="runde" value="<?php echo $selected_runde; ?>">
                                            <button type="submit" name="action" value="add_one">+1</button>
                                        </form>
                                        <form method="POST" style="display:inline-block;">
                                            <input type="hidden" name="spiel_id" value="<?php echo $game['id']; ?>">
                                            <input type="hidden" name="team_name" value="<?php echo htmlspecialchars($team['name']); ?>">
                                            <input type="hidden" name="runde" value="<?php echo $selected_runde; ?>">
                                            <button type="submit" name="action" value="add_to_all_except">+1 (alle außer)</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>

                <form method="POST" style="margin-top: 15px;">
                    <input type="hidden" name="spiel_id" value="<?php echo $game['id']; ?>">
                    <button type="submit" name="close_game" value="1" class="close-game-btn">Spiel schließen</button>
                </form>

                <div id="roundModal" class="modal">
                  <div class="modal-content">
                    <span class="close">&times;</span>
                    <h3>Runde <?php echo $new_round; ?> starten - Teams auswählen</h3>
                    <form method="POST">
                         <input type="hidden" name="spiel_id" value="<?php echo $selected_game_id; ?>">
                         <input type="hidden" name="new_runde_modal" value="1">
                         <p>Wähle die Teams, die in dieser Runde mitspielen:</p>
                         <?php foreach ($teams as $team): ?>
                              <div>
                                   <label>
                                      <input type="checkbox" name="participating[]" value="<?php echo htmlspecialchars($team['name']); ?>" checked>
                                      <?php echo htmlspecialchars($team['name']); ?>
                                   </label>
                              </div>
                         <?php endforeach; ?>
                         <br>
                         <button type="submit">Runde starten</button>
                         <button type="button" id="cancelModalBtn">Abbrechen</button>
                    </form>
                  </div>
                </div>
            <?php else: ?>
                <h3>Spiel beendet – Auswertung</h3>
                <h4>Ergebnisse aller Runden</h4>
                <div class="table-container">
                    <table class="eval-table">
                        <tr>
                            <th>Team</th>
                            <?php foreach ($rounds as $r): ?>
                                <th>Runde <?php echo $r; ?></th>
                            <?php endforeach; ?>
                            <th>Gesamt</th>
                        </tr>
                        <?php 
                        $overall_scores = [];
                        foreach ($teams as $team): 
                            $total = 0;
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($team['name']); ?></td>
                                <?php 
                                foreach ($rounds as $rnum):
                                    $points = "X";
                                    foreach ($team['runden'] as $rounddata) {
                                        if ($rounddata['runde'] == $rnum) {
                                            $points = $rounddata['punkte'];
                                            break;
                                        }
                                    }
                                    $display = ($points === "X") ? "Ausgesetzt" : $points;
                                    if ($points !== "X" && is_numeric($points)) {
                                        $total += $points;
                                    }
                                ?>
                                    <td><?php echo $display; ?></td>
                                <?php endforeach; ?>
                                <td><?php echo $total; ?></td>
                            </tr>
                        <?php 
                            $overall_scores[$team['name']] = $total;
                        endforeach; 
                        ?>
                    </table>
                </div>
                <h4>Rundensieger:</h4>
                <ul style="list-style: none; padding: 0;">
                    <?php 
                    foreach ($round_summary as $round => $data): 
                    ?>
                        <li>Runde <?php echo $round; ?>: <?php echo implode(", ", $data['winners']); ?> (<?php echo $data['max']; ?> Punkte)</li>
                    <?php endforeach; ?>
                </ul>
                <h4>Gesamtsieger:</h4>
                <?php 
                $max_total = max($overall_scores);
                $overall_winners = array_keys($overall_scores, $max_total);
                ?>
                <p><?php echo implode(", ", $overall_winners); ?> mit <?php echo $max_total; ?> Punkten</p>
            <?php endif; ?>
        <?php else: ?>
            <p>Bitte wähle ein Spiel aus.</p>
        <?php endif; ?>
    </div>

    <!-- Modal für neues Spiel -->
    <div id="createGameModal" class="modal">
      <div class="modal-content">
        <span class="close">&times;</span>
        <h3>Neues Spiel erstellen</h3>
        <form method="POST">
            <label for="spiel_name">Spielname:</label>
            <input type="text" name="spiel_name" id="spiel_name" required>
            <br><br>
            <label for="team_names">Teamnamen (durch Kommas getrennt):</label>
            <input type="text" name="team_names" id="team_names" required>
            <br><br>
            <button type="submit" name="create_game">Spiel erstellen</button>
            <button type="button" id="cancelCreateGameModalBtn">Abbrechen</button>
        </form>
      </div>
    </div>

    <script>
    window.onload = function() {
        var notification = document.getElementById('notification');
        if (notification.innerText.trim() !== "") {
            notification.style.display = 'block';
            setTimeout(function() {
                notification.style.opacity = '0';
            }, 3000);
            setTimeout(function() {
                notification.style.display = 'none';
            }, 3500);
        }
    };

    var roundModal = document.getElementById("roundModal");
    var openModalBtn = document.getElementById("openModalBtn");
    var closeSpan = document.getElementsByClassName("close")[0];
    var cancelModalBtn = document.getElementById("cancelModalBtn");

    if (openModalBtn) {
        openModalBtn.onclick = function() {
          roundModal.style.display = "block";
        };
    }

    closeSpan.onclick = function() {
      roundModal.style.display = "none";
    };

    if (cancelModalBtn) {
        cancelModalBtn.onclick = function() {
          roundModal.style.display = "none";
        };
    }

    window.onclick = function(event) {
      if (event.target == roundModal) {
        roundModal.style.display = "none";
      }
    };

    // Modal für neues Spiel
    var createGameModal = document.getElementById("createGameModal");
    var openCreateGameModalBtn = document.getElementById("openCreateGameModalBtn");
    var cancelCreateGameModalBtn = document.getElementById("cancelCreateGameModalBtn");

    if (openCreateGameModalBtn) {
        openCreateGameModalBtn.onclick = function() {
          createGameModal.style.display = "block";
        };
    }

    if (cancelCreateGameModalBtn) {
        cancelCreateGameModalBtn.onclick = function() {
          createGameModal.style.display = "none";
        };
    }

    window.onclick = function(event) {
      if (event.target == createGameModal) {
        createGameModal.style.display = "none";
      }
    };
    </script>
</body>
</html>
