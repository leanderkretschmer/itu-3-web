<?php
session_start();

// Datenbankverbindung
require_once '../db_connect.php';

// Überprüfen, ob die Verbindung erfolgreich war
if ($conn->connect_error) {
    die("MySQLi-Verbindung fehlgeschlagen: " . $conn->connect_error);
}
// Überprüfen, ob das Formular gesendet wurde
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']); // Cookie speichern

    // Benutzer validieren
    $query = "SELECT id, username, password, cookie_value FROM users WHERE username = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $stmt->bind_result($user_id, $db_username, $db_password, $cookie_value);
    $stmt->fetch();
    $stmt->close();

    if ($db_username && password_verify($password, $db_password)) {
        // Benutzer erfolgreich eingeloggt
        $_SESSION['user_id'] = $user_id;

        if ($remember_me) {
            // Cookie setzen, wenn "Remember me" aktiviert ist
            $cookie_value = bin2hex(random_bytes(16)); // Zufälliger Wert für das Cookie
            setcookie('user_login', $cookie_value, time() + 86400 * 30, '/'); // Cookie für 30 Tage setzen

            // Speichere den Cookie-Wert in der Datenbank
            $update_query = "UPDATE users SET cookie_value = ? WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param('si', $cookie_value, $user_id);
            $stmt->execute();
            $stmt->close();
        }

        // Weiterleitung zur Startseite
        header('Location: home.php');
        exit;
    } else {
        $error_message = 'Benutzername oder Passwort ist falsch.';
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <style>
        /* Grundlegendes Styling für das Schul-Theme */
        body {
            background-color: #f0f8ff; /* Helles Blau für den Hintergrund */
            color: #333; /* Dunkle Schriftfarbe */
            font-family: 'Arial', sans-serif; /* Sans-serif Schriftart */
            font-size: 18px;
            text-align: center;
            margin: 0;
            padding: 0;
        }

        h1 {
            margin-top: 50px;
            font-size: 36px;
            color: #2c3e50; /* Dunkelblau für die Überschrift */
        }

        .login-container {
            width: 400px; /* Feste Breite für das Container */
            margin: 50px auto; /* Zentrieren */
            padding: 20px;
            background-color: #ffffff; /* Weißer Hintergrund für das Formular */
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
        }

        .login-container label {
            display: block;
            margin-bottom: 10px;
            font-weight: bold; /* Fettdruck für Labels */
        }

        .login-container input[type="text"],
        .login-container input[type="password"] {
            width: 100%; /* Volle Breite */
            padding: 12px;
            background-color: #ecf0f1; /* Helles Grau für Eingabefelder */
            color: #333;
            border: 1px solid #bdc3c7; /* Graue Umrandung */
            border-radius: 5px;
            margin-bottom: 10px;
            font-size: 16px;
        }

        .login-container input[type="checkbox"] {
            margin-right: 10px;
        }

        .login-container button {
            background-color: #3498db; /* Blau für den Button */
            color: #ffffff; /* Weiße Schriftfarbe */
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 18px;
            width: 100%;
        }

        .login-container button:hover {
            background-color: #2980b9; /* Dunkleres Blau beim Hover */
        }

        .error-message {
            color: red;
            font-size: 16px;
        }

        .login-container p {
            margin-top: 20px;
            font-size: 16px;
        }
    </style>
</head>
<body>
    <h1>Willkommen beim Schul-Login</h1>

    <?php if (isset($error_message)): ?>
        <p class="error-message"><?= htmlspecialchars($error_message) ?></p>
    <?php endif; ?>

    <div class="login-container">
        <form action="login.php" method="POST">
            <label for="username">Benutzername:</label>
            <input type="text" id="username" name="username" required placeholder="Geben Sie Ihren Benutzernamen ein"><br>

            <label for="password">Passwort:</label>
            <input type="password" id="password" name="password" required placeholder="Geben Sie Ihr Passwort ein"><br>

            <label for="remember_me">Angemeldet bleiben:</label>
            <input type="checkbox" id="remember_me" name="remember_me"><br>

            <button type="submit">Einloggen</button>
        </form>
    </div>
</body>
</html>
