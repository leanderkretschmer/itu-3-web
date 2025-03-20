

<!-- Login Abfrage -->
<?php
session_start();

// Datenbankverbindung
require_once 'db_connect.php';

// Überprüfen, ob der Benutzer eingeloggt ist oder der Cookie gesetzt ist
if (!isset($_SESSION['user_id'])) {
    // Überprüfen, ob das Cookie gesetzt ist
    if (isset($_COOKIE['user_login'])) {
        $cookie_value = $_COOKIE['user_login'];

        // Überprüfen, ob der Cookie-Wert mit einem Benutzer in der Datenbank übereinstimmt
        $query = "SELECT id, cookie_value FROM users WHERE cookie_value = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $cookie_value);
        $stmt->execute();
        $stmt->bind_result($user_id, $db_cookie_value);
        $stmt->fetch();
        $stmt->close();

        if ($user_id) {
            // Benutzer gefunden, setzen der Session-Variable
            $_SESSION['user_id'] = $user_id;
        } else {
            // Ungültiger Cookie, Weiterleitung zur Login-Seite
            header('Location: login/index.php');
            exit;
        }
    } else {
        // Kein Cookie gesetzt, Weiterleitung zur Login-Seite
        header('Location: login/index.php');
        exit;
    }
}

// Abrufen der Gruppen des Benutzers aus der Datenbank
$user_id = $_SESSION['user_id'];
$query = "SELECT groups FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($user_groups);
$stmt->fetch();
$stmt->close();

// Überprüfen, ob der Benutzer zur Gruppe "viewer" gehört
$user_groups = explode(';', $user_groups);
if (!in_array('viewer', $user_groups)) {
    die("Zugriff verweigert. Sie benötigen Viewer-Rechte. <br><br><a href='/home.php' style='padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 5px;'>Zurück zur Startseite</a>");
}
?>
<!--------------------- Login Abfrage Ende -------------------->


<?php
$seite = isset($_GET['seite']) ? $_GET['seite'] : 'startseite';
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meine Themen-Seite</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <?php if ($seite == 'startseite') { ?>
    <div class="startseite">
        <h1>ITu-3</h1>
    </div>
    <?php } else { ?>
    <div class="container">
        <aside class="sidebar">
            <h2>Themen</h2>
            <ul>
                <li><a href="?seite=thema1">Thema 1</a></li>
                <li><a href="?seite=thema2">Thema 2</a></li>
                <li><a href="?seite=thema3">Thema 3</a></li>
            </ul>
        </aside>
        <main class="content">
            <?php
                if ($seite == 'thema1') {
                    echo '<h1>Thema 1</h1><p>Inhalt von Thema 1...</p>';
                } elseif ($seite == 'thema2') {
                    echo '<h1>Thema 2</h1><p>Inhalt von Thema 2...</p>';
                } elseif ($seite == 'thema3') {
                    echo '<h1>Thema 3</h1><p>Inhalt von Thema 3...</p>';
                } else {
                    echo '<h1>Willkommen!</h1><p>Wähle ein Thema aus der Seitenleiste.</p>';
                }
                ?>
        </main>
    </div>
    <?php } ?>
<?php
$school = 'DEINE_SCHULE';
$username = 'DEIN_BENUTZERNAME';
$password = 'DEIN_PASSWORT';
$baseUrl = 'https://webuntisURL/WebUntis/jsonrpc.do?school=' . urlencode($school);

// Login
$session = curl_init();
curl_setopt($session, CURLOPT_URL, $baseUrl);
curl_setopt($session, CURLOPT_POST, 1);
curl_setopt($session, CURLOPT_POSTFIELDS, json_encode([
    'id' => 1,
    'method' => 'authenticate',
    'params' => [
        'user' => $username,
        'password' => $password,
        'client' => 'WebUntis'
    ],
    'jsonrpc' => '2.0'
]));
curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
curl_setopt($session, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = json_decode(curl_exec($session), true);

if (!isset($response['result']['sessionId'])) {
    die('Login fehlgeschlagen!');
}
$sessionId = $response['result']['sessionId'];

// Stundenplan abrufen
curl_setopt($session, CURLOPT_POSTFIELDS, json_encode([
    'id' => 2,
    'method' => 'getTimetable',
    'params' => [
        'studentId' => 12345, // Setze die richtige Schüler-ID
        'startDate' => date('Ymd'),
        'endDate' => date('Ymd', strtotime('+7 days'))
    ],
    'jsonrpc' => '2.0'
]));
curl_setopt($session, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Cookie: JSESSIONID=' . $sessionId
]);
$timetable = json_decode(curl_exec($session), true);
curl_close($session);

// Stundenplan anzeigen
echo "<h1>Stundenplan</h1><table border='1'>";
echo "<tr><th>Datum</th><th>Stunde</th><th>Fach</th><th>Lehrer</th></tr>";
foreach ($timetable['result'] as $lesson) {
    echo "<tr>";
    echo "<td>" . date('d.m.Y', strtotime($lesson['date'])) . "</td>";
    echo "<td>" . $lesson['period'] . "</td>";
    echo "<td>" . $lesson['subject'] . "</td>";
    echo "<td>" . $lesson['teacher'] . "</td>";
    echo "</tr>";
}
echo "</table>";

    <!-- Logout Button -->
    <br>
    <a class="logout-btn" href="logout.php">Logout</a>
</body>

</html>


</html>


