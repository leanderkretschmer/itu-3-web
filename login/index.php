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
        header('Location: ../index.php');
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
    <title>Login – ITU3 Schulwebsite</title>
    <style>
        /* Modernes, schulisches Styling */
        body {
            background-color: #f4f7f9;
            color: #333;
            font-family: Arial, sans-serif;
            font-size: 16px;
            text-align: center;
            margin: 0;
            padding: 0;
        }

        h1 {
            margin-top: 50px;
            font-size: 32px;
            color: #00529b; /* Schul-/Klassenfarbe */
        }

        .login-container {
            max-width: 400px;
            margin: 30px auto;
            padding: 20px;
            background-color: #ffffff;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .login-container label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .login-container input[type="text"],
        .login-container input[type="password"] {
            width: 90%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 16px;
        }

        .login-container input[type="checkbox"] {
            margin-right: 5px;
        }

        .login-container button {
            background-color: #00529b;
            color: #fff;
            padding: 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
        }

        .login-container button:hover {
            background-color: #003d73;
        }

        .error-message {
            color: #d8000c;
            margin-bottom: 15px;
        }

        .login-container p {
            margin-top: 15px;
            font-size: 14px;
            color: #555;
        }

        /* Dezenter Hintergrundeffekt (optional) */
        .background-pattern {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('pattern.png'); /* Ein dezentes Muster, ggf. anpassen */
            opacity: 0.1;
            z-index: -1;
        }
    </style>
</head>
<body>
    <div class="background-pattern"></div>
    <h1>Willkommen ITU3</h1>

    <?php if (isset($error_message)): ?>
        <p class="error-message"><?= htmlspecialchars($error_message) ?></p>
    <?php endif; ?>

    <div class="login-container">
        <form action="index.php" method="POST">
            <label for="username">Benutzername:</label>
            <input type="text" id="username" name="username" required placeholder="Geben Sie Ihren Benutzernamen ein">

            <label for="password">Passwort:</label>
            <input type="password" id="password" name="password" required placeholder="Geben Sie Ihr Passwort ein">

            <label>
                <input type="checkbox" id="remember_me" name="remember_me">
                Angemeldet bleiben
            </label>

            <button type="submit">Einloggen</button>
        </form>
        <p><a href="passwort_vergeben.php">Passwort vergessen?</a></p>
    </div>
</body>
</html>
