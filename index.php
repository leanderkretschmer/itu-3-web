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

$seite = isset($_GET['seite']) ? $_GET['seite'] : 'startseite';


// Abrufen des Avatars aus der Datenbank
$query = "SELECT avatar FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($avatar);
$stmt->fetch();
$stmt->close();

// Standardbild, falls kein Avatar gesetzt ist
if (empty($avatar)) {
    $avatar = 'default-avatar.png'; // Pfad zum Standardbild
}

?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meine Themen-Seite</title>
    <link rel="stylesheet" href="style.css">
    <!-- Font Awesome CDN hinzufügen -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>

<body>
    <div class="startseite">
        <h1>ITu-3</h1>
<div class="button-container">
        <a href="https://perseus.webuntis.com/WebUntis/#/basic/login" class="website-button" target="_blank">
            <img src="https://www.untis.at/fileadmin/user_upload/Icon-1024x1024_rund.png"
                alt="WebUntis Logo">
            <span class="button-text">Untis</span>
        </a>
    
        <a href="https://bkhaspel.de/" class="website-button" target="_blank" 
            rel="noopener noreferrer">
            <img src="https://img.icons8.com/fluent-systems-filled/512/iserv.png"
                alt="BKH Logo">
            <span class="button-text">BKH</span>
        </a>
    
        <a href="https://bkb-europaschule.eu/" class="website-button" target="_blank"
            rel="noopener noreferrer">
            <img src="https://img.icons8.com/fluent-systems-filled/512/iserv.png"
                alt="BKB Logo">
            <span class="button-text">BKB</span>
        </a>
        <a href="https://bkb-europaschule.eu/" class="website-button" target="_blank"
            rel="noopener noreferrer">
            <img src="https://th.bing.com/th/id/R.697057d80dd892e17d17f89c0aa8be45?rik=iJ1M0IXdhTjJBA&pid=ImgRaw&r=0"
                alt="BKB Logo">
            <span class="button-text">Classbook</span>
        </a>
        <a href="https://www.gesetze-im-internet.de/bgb/" class="website-button" target="_blank"
            rel="noopener noreferrer">
            <img src="https://www.kerstin-celina.de/wp-content/uploads/2021/04/gesetzbuch.png"
                alt="BGB Logo">
            <span class="button-text">BGB</span>
        </a>
        <a href="https://www.gesetze-im-internet.de/gwb/" class="website-button" target="_blank"
            rel="noopener noreferrer">
            <img src="https://www.kerstin-celina.de/wp-content/uploads/2021/04/gesetzbuch.png"
                alt="GWB Logo">
            <span class="button-text">GWB</span>
        </a>
        <a href="https://dotnetfiddle.net/" class="website-button" target="_blank"
            rel="noopener noreferrer">
            <img src="https://upload.wikimedia.org/wikipedia/commons/0/0e/Microsoft_.NET_logo.png"
                alt="GWB Logo">
            <span class="button-text">C# Web</span>
        </a>
    </div>
    </div>


    <!-- Benutzer-Button und Dropdown-Menü oben rechts -->
<div class="user-menu">
    <button class="user-button" style="background-image: url('<?php echo htmlspecialchars($avatar); ?>');">
    </button>
    <!-- Dropdown-Menü -->
    <div class="dropdown-content">
        <a href="profile.php">Profil</a>
        <a href="settings.php">Einstellungen</a>
        <a href="logout.php">Ausloggen</a>
    </div>
</div>

    <script>
        window.addEventListener('load', function() {
            // Entfernen des Ladebildschirms, da er nicht mehr benötigt wird
            const loadingScreen = document.getElementById('loading-screen');
            loadingScreen.style.display = 'none';
        });

        // Dropdown anzeigen/verstecken bei Button-Klick
        const userButton = document.querySelector('.user-button');
        const dropdownContent = document.querySelector('.dropdown-content');

        userButton.addEventListener('click', function(event) {
            // Verhindert, dass das Klick-Ereignis nach unten durch den Button "propagiert"
            event.stopPropagation();
            dropdownContent.classList.toggle('show');
        });

        // Schließen des Dropdowns, wenn außerhalb des Menüs geklickt wird
        window.addEventListener('click', function(event) {
            if (!event.target.matches('.user-button') && !event.target.matches('.dropdown-content') && !event.target.matches('.dropdown-content a')) {
                if (dropdownContent.classList.contains('show')) {
                    dropdownContent.classList.remove('show');
                }
            }
        });
    </script>
</body>
</html>
