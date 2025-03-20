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

// Funktion zum Abrufen der Buttons aus der Datenbank
function get_user_buttons($conn, $user_id)
{
    $buttons = array();
    $query = "SELECT link_text, link_url FROM user_buttons WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $buttons[] = $row;
    }

    $stmt->close();
    return $buttons;
}

// Funktion zum Anzeigen der Buttons
function display_buttons($buttons)
{
    echo '<div class="button-container">';
    foreach ($buttons as $button) {
        echo '<a href="' .
            htmlspecialchars($button['link_url']) .
            '" class="custom-button">' .
            htmlspecialchars($button['link_text']) .
            '</a>';
    }
    echo '</div>';
}
?>
<!--------------------- Login Abfrage Ende -------------------->

<?php
// Array mit den Button-Definitionen (Text, URL, Logo)
$buttons = array(
    array('text' => 'Google', 'url' => 'https://www.google.com', 'logo' => ''),
    array(
        'text' => 'IServ',
        'url' => 'https://bkhaspel.de/iserv',
        'logo' =>
            'https://endoospot.de/wp-content/uploads/sites/1/2020/06/xIServ_Logo.png.pagespeed.ic.vihkecP0at.png',
    ),
    array(
        'text' => 'WebUntis',
        'url' => 'https://perseus.webuntis.com/WebUntis/#/basic/login',
        'logo' => 'https://www.untis.at/fileadmin/user_upload/Icon-1024x1024.png',
    ),
    array('text' => 'Google', 'url' => 'https://www.google.com', 'logo' => ''),
    array(
        'text' => 'IServ',
        'url' => 'https://bkhaspel.de/iserv',
        'logo' =>
            'https://endoospot.de/wp-content/uploads/sites/1/2020/06/xIServ_Logo.png.pagespeed.ic.vihkecP0at.png',
    ),
    array(
        'text' => 'WebUntis',
        'url' => 'https://perseus.webuntis.com/WebUntis/#/basic/login',
        'logo' => 'https://www.untis.at/fileadmin/user_upload/Icon-1024x1024.png',
    ),
);

// Funktion zum Anzeigen der Buttons
function display_buttons($buttons)
{
    echo '<div class="button-container">';
    foreach ($buttons as $button) {
        echo '<a href="' .
            htmlspecialchars($button['url']) .
            '" class="custom-button">';
        if (!empty($button['logo'])) {
            echo '<img src="' .
                htmlspecialchars($button['logo']) .
                '" alt="' .
                htmlspecialchars($button['text']) .
                '" class="button-logo">';
        } else {
            echo htmlspecialchars($button['text']);
        }
        echo '</a>';
    }
    echo '</div>';
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ITU-3</title>
    <link rel="stylesheet" href="style.css">
    <style>
    /* Style für den Button-Container */
    .button-container {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        /* Links ausgerichtet */
        margin-top: 20px;
        /* Abstand nach oben */
    }

    /* Style für die Buttons */
    .custom-button {
        display: block;
        /* Macht den Link zu einem Block-Element, um die volle Breite zu nutzen */
        padding: 10px 20px;
        margin-bottom: 10px;
        /* Abstand zwischen den Buttons */
        background-color: #4CAF50;
        color: white;
        text-decoration: none;
        border-radius: 5px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        /* Schatten für den Button */
        text-align: center;
        /* Zentriert den Text im Button */
        width: 200px;
        /* Feste Breite für alle Buttons */
    }

    /* Style für das Logo im Button */
    .button-logo {
        max-width: 80%;
        /*Anpassen der Logo-Größe */
        max-height: 40px;
        /*Anpassen der Logo-Größe */
        vertical-align: middle;
        /* Zentriert das Logo vertikal */
    }
    </style>
</head>

<body>
    <div class="container">
        <main class="content">
            <h1>Willkommen!</h1>
            <p>Hier sind deine Links:</p>

            <?php
            // Hier werden die Buttons angezeigt
            display_buttons($buttons);
            ?>
        </main>
    </div>

    <!-- Logout Button -->
    <br>
    <a class="logout-btn" href="logout.php">Logout</a>
</body>

</html>
