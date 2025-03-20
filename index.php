

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


<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meine Themen-Seite</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <aside class="sidebar">
            <h2>Themen</h2>
            <ul>
                <li><a href="#">Thema 1</a></li>
                <li><a href="#">Thema 2</a></li>
                <li><a href="#">Thema 3</a></li>
            </ul>
        </aside>
        <main class="content">
            <h1>Willkommen!</h1>
            <p>Hier werden die Inhalte der Themen angezeigt.</p>
        </main>
    </div>
</body>
</html>

