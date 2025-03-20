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
    <!-- Font Awesome CDN hinzufügen -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>


<body>
    <div id="loading-screen">
        <div class="loader"></div>
    </div>
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

    <!-- Benutzer-Button und Dropdown-Menü oben rechts -->
    <div class="user-menu">
        <button class="user-button">
            <i class="fas fa-user"></i> <!-- Benutzer-Icon von Font Awesome -->
        </button>
        <!-- Dropdown-Menü -->
        <div class="dropdown-content">
            <a href="profile.php">Profil</a>
            <a href="settings.php">Einstellungen</a>
            <a href="logout.php">Ausloggen</a>
        </div>
    </div>




    <div class="content">
        <div class="button-container">
            <!-- 10x6 Buttons -->
            <?php
            // 10x6 = 60 Buttons, also 60 Button-Elemente erstellen
            for ($i = 1; $i <= 60; $i++) {
                echo '<button class="grid-button">Button ' . $i . '</button>';
            }
            ?>
        </div>
    </div>




    <script>
        window.addEventListener('load', function() {
            const loadingScreen = document.getElementById('loading-screen');
            loadingScreen.classList.add('fade-out');
            setTimeout(function() {
                loadingScreen.style.display = 'none';
            }, 500); // Wartezeit muss mit der CSS Transition übereinstimmen
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
