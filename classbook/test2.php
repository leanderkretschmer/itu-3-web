<?php
// DB-Verbindung (z. B. über mysqli)
require_once '../db_connect.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/*
Unterscheidung der API-Anfragen:
- POST:
  • Wenn "eintrag_md" gesendet wird: neuer Wiki‑Eintrag
  • Wenn "comment_text" gesendet wird: neuer Kommentar für einen Wiki‑Eintrag
- GET:
  • action=getWiki → Liefert alle Wiki‑Einträge als hierarchische JSON-Struktur zurück.
  • action=getComments & entry_id=… → Liefert alle Kommentare zu einem Wiki‑Eintrag.
*/

// Wiki‑Einträge verarbeiten (Markdown parsen)
function processWikiEntry($conn, $markdown) {
    // Den Markdown-Text zeilenweise zerlegen:
    $lines = preg_split('/\r\n|\r|\n/', $markdown);
    $entries = [];
    $currentEntry = null;
    foreach ($lines as $line) {
        if (preg_match('/^(#+)\s*(.*)$/', $line, $matches)) {
            // Überschrift gefunden: Anzahl der Hashtags = Hierarchielevel
            $level = strlen($matches[1]);
            $title = trim($matches[2]);
            if ($currentEntry !== null) {
                $entries[] = $currentEntry;
            }
            $currentEntry = [
                "level"   => $level,
                "title"   => $title,
                "content" => ""
            ];
        } else {
            // Zeile ohne Hashtag: als Inhalt des aktuellen Eintrags anhängen
            if ($currentEntry !== null) {
                $currentEntry["content"] .= $line . "\n";
            }
        }
    }
    if ($currentEntry !== null) {
        $entries[] = $currentEntry;
    }

    // Mit Hilfe eines Arrays wird für jedes Level der zuletzt eingefügte Eintrag gespeichert,
    // um die Parent‑Beziehung herzustellen.
    $lastEntryOfLevel = [];

    foreach ($entries as $entry) {
        $level   = $entry["level"];
        $title   = $entry["title"];
        $content = trim($entry["content"]);

        // Parent bestimmen: Level‑1 hat keinen Parent, bei tieferen Levels wird der zuletzt
        // eingefügte Eintrag der übergeordneten Ebene verwendet.
        $parent_id = null;
        if ($level > 1 && isset($lastEntryOfLevel[$level - 1])) {
            $parent_id = $lastEntryOfLevel[$level - 1];
        }

        // Prüfen, ob bereits ein Eintrag mit gleichem Titel, Level und (falls vorhanden)
        // gleicher parent_id existiert.
        if ($parent_id === null) {
            $stmt = $conn->prepare("SELECT id FROM wiki_entries 
                WHERE title = ? AND level = ? AND parent_id IS NULL");
            $stmt->bind_param("si", $title, $level);
        } else {
            $stmt = $conn->prepare("SELECT id FROM wiki_entries 
                WHERE title = ? AND level = ? AND parent_id = ?");
            $stmt->bind_param("sii", $title, $level, $parent_id);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            // Eintrag existiert bereits – Inhalt anhängen
            $entry_id = $row["id"];
            $stmtUpdate = $conn->prepare(
                "UPDATE wiki_entries SET content = CONCAT(content, ?) 
                 WHERE id = ?"
            );
            $additional = "\n" . $content;
            $stmtUpdate->bind_param("si", $additional, $entry_id);
            $stmtUpdate->execute();
        } else {
            // Neuer Eintrag anlegen:
            if ($parent_id === null) {
                $stmtInsert = $conn->prepare(
                    "INSERT INTO wiki_entries (parent_id, level, title, content) 
                     VALUES (NULL, ?, ?, ?)"
                );
                $stmtInsert->bind_param("iss", $level, $title, $content);
            } else {
                $stmtInsert = $conn->prepare(
                    "INSERT INTO wiki_entries (parent_id, level, title, content) 
                     VALUES (?, ?, ?, ?)"
                );
                $stmtInsert->bind_param("iiss", $parent_id, $level, $title, $content);
            }
            $stmtInsert->execute();
            $entry_id = $stmtInsert->insert_id;
        }

        $lastEntryOfLevel[$level] = $entry_id;
        // Entferne ggf. Einträge von tieferen Ebenen
        for ($i = $level + 1; $i <= count($lastEntryOfLevel) + 1; $i++) {
            if (isset($lastEntryOfLevel[$i])) {
                unset($lastEntryOfLevel[$i]);
            }
        }
    }
}

// Liefert alle Wiki‑Einträge als hierarchische JSON-Struktur
function getWikiEntries($conn) {
    $query = "SELECT id, parent_id, level, title, content FROM wiki_entries";
    $result = $conn->query($query);
    $entries = [];
    while ($row = $result->fetch_assoc()) {
        $entries[] = $row;
    }
    // Aufbau einer Baumstruktur anhand der Parent‑Beziehungen
    $tree = [];
    $byId = [];
    foreach ($entries as $entry) {
        $entry['children'] = [];
        $byId[$entry['id']] = $entry;
    }
    foreach ($byId as $id => &$entry) {
        if ($entry['parent_id']) {
            $byId[$entry['parent_id']]['children'][] = &$entry;
        } else {
            $tree[] = &$entry;
        }
    }
    // Rekursive alphabetische Sortierung der Einträge auf jeder Ebene
    function sortEntries(&$entries) {
        usort($entries, function ($a, $b) {
            return strcmp($a['title'], $b['title']);
        });
        foreach ($entries as &$entry) {
            if (!empty($entry['children'])) {
                sortEntries($entry['children']);
            }
        }
    }
    sortEntries($tree);
    return $tree;
}

// Kommentare lesen für einen bestimmten Wiki‑Eintrag
function getComments($conn, $entry_id) {
    $stmt = $conn->prepare("SELECT id, entry_id, username, comment, selected_text, 
        created_at FROM wiki_comments WHERE entry_id = ? ORDER BY created_at ASC");
    $stmt->bind_param("i", $entry_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $comments = [];
    while ($row = $result->fetch_assoc()) {
        $comments[] = $row;
    }
    return $comments;
}

// Neuen Kommentar speichern
function processComment($conn, $entry_id, $username, $comment, $selected_text = "") {
    $stmt = $conn->prepare(
        "INSERT INTO wiki_comments (entry_id, username, comment, selected_text, created_at) 
         VALUES (?, ?, ?, ?, NOW())"
    );
    $stmt->bind_param("isss", $entry_id, $username, $comment, $selected_text);
    $stmt->execute();
    return $conn->insert_id;
}

// API-Endpoints abarbeiten:
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (isset($_POST['eintrag_md'])) {
        // Verarbeitung eines neuen Wiki‑Eintrags
        $markdown = $_POST['eintrag_md'];
        processWikiEntry($conn, $markdown);
        echo json_encode(["message" => "Wiki-Eintrag erfolgreich verarbeitet!"]);
        exit;
    } elseif (isset($_POST['comment_text'])) {
        // Verarbeitung eines neuen Kommentars
        $entry_id     = (int) $_POST['entry_id'];
        $username     = $_POST['username'];
        $comment      = $_POST['comment_text'];
        $selected_text= isset($_POST['selected_text']) ? $_POST['selected_text'] : "";
        processComment($conn, $entry_id, $username, $comment, $selected_text);
        echo json_encode(["message" => "Kommentar erfolgreich gespeichert!"]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    if (isset($_GET['action']) && $_GET['action'] === 'getWiki') {
        echo json_encode(getWikiEntries($conn));
        exit;
    } elseif (
        isset($_GET['action']) &&
        $_GET['action'] === 'getComments' &&
        isset($_GET['entry_id'])
    ) {
        $entry_id = (int) $_GET['entry_id'];
        echo json_encode(getComments($conn, $entry_id));
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Wiki OnePager</title>
  <!-- Einbindung der externen CSS-Datei -->
  <link rel="stylesheet" type="text/css" href="style.css" />
</head>
<body>
  <div class="container">
    <!-- Linke Sidebar -->
    <div class="sidebar">
      <h2>Wiki Übersicht</h2>
      <ul id="wikiList">
        <!-- Wiki-Einträge werden hier per JavaScript geladen -->
      </ul>
    </div>

    <!-- Hauptbereich -->
    <div class="content">
      <h1 id="contentTitle">Willkommen im Wiki</h1>
      <div id="contentArea"></div>
      <!-- Kommentarbereich -->
      <div id="commentSection"></div>
    </div>
  </div>

  <!-- Fixierter Button "Eintrag" unten rechts -->
  <button id="openEntryButton">Eintrag</button>

  <!-- Modal für neuen Wiki-Eintrag -->
  <div id="entryModal" class="modal">
    <div class="modal-content">
      <h2>Neuer Wiki Eintrag</h2>
      <textarea id="markdownInput" placeholder="Gib deinen Markdown Text ein..."></textarea>
      <div>
        <button id="closeEntryModal">Schließen</button>
        <button id="saveEntryButton">Speichern</button>
      </div>
    </div>
  </div>

  <!-- Modal für Kommentare -->
  <div id="commentModal" class="modal">
    <div class="modal-content">
      <h2>Neuer Kommentar</h2>
      <textarea id="commentInput" placeholder="Schreibe deinen Kommentar..."></textarea>
      <div>
        <button id="closeCommentModal">Schließen</button>
        <button id="submitCommentBtn">Kommentar absenden</button>
      </div>
    </div>
  </div>

  <script>
    // Global speichern wir die ID des aktuell gewählten Wiki-Eintrags (für Kommentare)
    let currentEntryId = null;

    document.addEventListener("DOMContentLoaded", () => {
      loadWikiEntries();

      // Handling Wiki-Eintrag-Modal
      const openEntryButton = document.getElementById("openEntryButton");
      const entryModal = document.getElementById("entryModal");
      const closeEntryModal = document.getElementById("closeEntryModal");
      const saveEntryButton = document.getElementById("saveEntryButton");

      openEntryButton.addEventListener("click", () => {
        entryModal.classList.add("active");
      });

      closeEntryModal.addEventListener("click", () => {
        entryModal.classList.remove("active");
      });

      saveEntryButton.addEventListener("click", () => {
        const markdown = document.getElementById("markdownInput").value;
        if (markdown.trim() === "") {
          alert("Bitte einen Eintrag eingeben.");
          return;
        }
        fetch("<?php echo $_SERVER['PHP_SELF']; ?>", {
          method: "POST",
          headers: {
            "Content-Type":
              "application/x-www-form-urlencoded;charset=UTF-8"
          },
          body: "eintrag_md=" + encodeURIComponent(markdown)
        })
          .then((response) => response.json())
          .then((data) => {
            alert(data.message);
            document.getElementById("markdownInput").value = "";
            entryModal.classList.remove("active");
            loadWikiEntries();
          })
          .catch((error) =>
            console.error("Fehler beim Speichern des Eintrags:", error)
          );
      });

      // Handling Kommentar-Modal
      const commentModal = document.getElementById("commentModal");
      const closeCommentModal = document.getElementById("closeCommentModal");
      const submitCommentBtn = document.getElementById("submitCommentBtn");

      closeCommentModal.addEventListener("click", () => {
        commentModal.classList.remove("active");
      });

      submitCommentBtn.addEventListener("click", () => {
        const commentText = document.getElementById("commentInput").value;
        if (commentText.trim() === "") {
          alert("Bitte einen Kommentar eingeben.");
          return;
        }
        // Ausgewählten Text (falls vorhanden) ermitteln
        let selectedText = "";
        const selection = window.getSelection().toString();
        if (selection) {
          selectedText = selection;
        }
        // Für das Beispiel verwenden wir einen festen Benutzernamen.
        const username = "DemoUser";
        fetch("<?php echo $_SERVER['PHP_SELF']; ?>", {
          method: "POST",
          headers: {
            "Content-Type":
              "application/x-www-form-urlencoded;charset=UTF-8"
          },
          body:
            "comment_text=" +
            encodeURIComponent(commentText) +
            "&entry_id=" +
            encodeURIComponent(currentEntryId) +
            "&username=" +
            encodeURIComponent(username) +
            "&selected_text=" +
            encodeURIComponent(selectedText)
        })
          .then((response) => response.json())
          .then((data) => {
            alert(data.message);
            document.getElementById("commentInput").value = "";
            commentModal.classList.remove("active");
            loadComments(currentEntryId);
          })
          .catch((error) =>
            console.error("Fehler beim Speichern des Kommentars:", error)
          );
      });
    });

    // Wiki-Einträge in der Sidebar laden
    function loadWikiEntries() {
      fetch("<?php echo $_SERVER['PHP_SELF']; ?>?action=getWiki")
        .then((response) => response.json())
        .then((data) => {
          const wikiList = document.getElementById("wikiList");
          wikiList.innerHTML = "";
          renderEntries(data, wikiList);
        })
        .catch((error) =>
          console.error("Fehler beim Laden der Wiki Einträge:", error)
        );
    }

    // Rekursive Darstellung der Wiki-Einträge in der Sidebar
    function renderEntries(entries, container) {
      entries.forEach((entry) => {
        const li = document.createElement("li");
        li.style.marginLeft = (entry.level - 1) * 20 + "px";
        const entryButton = document.createElement("button");
        entryButton.textContent = entry.title;
        entryButton.addEventListener("click", () => {
          loadContent(entry);
          currentEntryId = entry.id;
        });
        li.appendChild(entryButton);

        // Kommentar-Button (erscheint per CSS beim Hover)
        const commentBtn = document.createElement("button");
        commentBtn.textContent = "Kommentar";
        commentBtn.className = "comment-btn";
        commentBtn.addEventListener("click", (e) => {
          e.stopPropagation();
          currentEntryId = entry.id;
          document.getElementById("commentModal").classList.add("active");
        });
        li.appendChild(commentBtn);

        container.appendChild(li);
        if (entry.children && entry.children.length > 0) {
          renderEntries(entry.children, container);
        }
      });
    }

    // Inhalt des Wiki-Eintrags laden und im Hauptbereich anzeigen
    function loadContent(entry) {
      document.getElementById("contentTitle").textContent = entry.title;
      document.getElementById("contentArea").textContent = entry.content;
      loadComments(entry.id);
    }

    // Kommentare zu einem Wiki-Eintrag laden und anzeigen
    function loadComments(entryId) {
      fetch(
        "<?php echo $_SERVER['PHP_SELF']; ?>?action=getComments&entry_id=" +
          entryId
      )
        .then((response) => response.json())
        .then((data) => {
          const commentSection = document.getElementById("commentSection");
          commentSection.innerHTML = "";
          if (data.length === 0) {
            commentSection.innerHTML =
              "<p>Keine Kommentare vorhanden.</p>";
            return;
          }
          data.forEach((comment) => {
            const commentDiv = document.createElement("div");
            commentDiv.className = "comment";

            const userIconDiv = document.createElement("div");
            userIconDiv.className = "user-icon";
            const userImg = document.createElement("img");
            userImg.src = "https://via.placeholder.com/30";
            userIconDiv.appendChild(userImg);

            const commentContentDiv = document.createElement("div");
            commentContentDiv.className = "comment-content";
            commentContentDiv.textContent = comment.comment;
            // Beim Hover soll der (vom Benutzer ausgewählte) Text angezeigt werden,
            // wenn dieser vorhanden ist.
            commentContentDiv.setAttribute(
              "data-selected",
              comment.selected_text
            );

            commentDiv.appendChild(userIconDiv);
            commentDiv.appendChild(commentContentDiv);
            commentSection.appendChild(commentDiv);
          });
        })
        .catch((error) =>
          console.error("Fehler beim Laden der Kommentare:", error)
        );
    }
  </script>
</body>
</html>
