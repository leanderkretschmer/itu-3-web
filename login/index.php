<?php
session_start();

// Datenbankverbindung
require_once 'db_connect.php';

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
        /* Grundlegendes Styling für den Hacker-Look */
        body {
            background-color: #1e1e1e;
            color: #33FF00; /* Grüne Schrift für den "Hacker"-Look */
            font-family: 'Courier New', monospace; /* Monospaced Schriftart */
            font-size: 18px;
            text-align: center;
            margin: 0;
            padding: 0;
            overflow: hidden;
        }

        h1 {
            margin-top: 50px;
            font-size: 36px;
        }

        .login-container {
            width: 100vh;
            margin-top: 50px;
            display: inline-block;
            text-align: left;
            padding: 20px;
            background-color: #2c2c2c;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.7);
        }

        .login-container label {
            display: block;
            margin-bottom: 10px;
        }

        .login-container input[type="text"],
        .login-container input[type="password"] {
            width: 98%;
            padding: 12px;
            background-color: #1a1a1a;
            color: #33FF00;
            border: 1px solid #33FF00;
            border-radius: 5px;
            margin-bottom: 10px;
            font-size: 18px;
        }

        .login-container input[type="checkbox"] {
            margin-right: 10px;
        }

        .login-container button {
            background-color: #33FF00;
            color: #1e1e1e;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 18px;
            width: 100%;
        }

        .login-container button:hover {
            background-color: #29cc00;
        }

        .error-message {
            color: red;
            font-size: 16px;
        }

        .login-container p {
            margin-top: 20px;
            font-size: 16px;
        }

        /* Animationen für ein echtes "Hacker"-Feeling */
        .blinking-cursor {
            font-size: 24px;
            font-weight: bold;
            animation: blink-caret 0.8s step-end infinite;
        }

        @keyframes blink-caret {
            50% {
                border-color: transparent;
            }
        }

        /* Hintergrund-Code-Effekt */
        .background-code {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            color: #33FF00;
            font-family: 'Courier New', monospace;
            font-size: 16px;
            white-space: nowrap;
            z-index: -1; /* Hintergrund */
            overflow: hidden;
            pointer-events: none;
        }

        .code-line {
            position: absolute;
            animation: code-scroll 3s infinite linear;
            opacity: 0;
        }

        @keyframes code-scroll {
            0% {
                top: -20px;
                opacity: 1;
            }
            100% {
                top: 100%;
                opacity: 0;
            }
        }

    </style>
</head>
<body>
    <h1>Willkommen beim Login Terminal</h1>

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

    <!-- Hintergrund-Code -->
    <div class="background-code" id="background-code"></div>

    <script>
        // Beispiel für den simulierten Code, der durch das Terminal läuft
        const codeLines = [
            "Initializing system...",
            "Connection established...",
            "Loading files...",
            "System compromised!",
            "Access granted.",
            "Verifying credentials...",
            "Login successful!",
            "Updating security protocols...",
            "Security breach detected!",
            "Shutdown imminent..."
        ];

        function generateCodeLine() {
            const line = codeLines[Math.floor(Math.random() * codeLines.length)];
            const codeLine = document.createElement("div");
            codeLine.className = "code-line";
            codeLine.textContent = line;
            document.getElementById("background-code").appendChild(codeLine);

            // Setze eine zufällige Animation-Dauer
            const duration = Math.random() * 4 + 2; // zufällige Dauer zwischen 2 und 6 Sekunden
            codeLine.style.animationDuration = `${duration}s`;

            // Entferne die Codezeile nach der Animation
            setTimeout(() => {
                codeLine.remove();
            }, duration * 1000);
        }

        // Erstelle alle 300ms neue Codezeilen
        setInterval(generateCodeLine, 300);
    </script>
</body>
</html>
