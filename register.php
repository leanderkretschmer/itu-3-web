<?php
session_start();
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Passwort verschlüsseln
    $name = $_POST['name'];

    // Überprüfen, ob der Benutzername schon existiert
    $query = "SELECT id FROM users_pending WHERE username = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo "<p style='color: red;'>Benutzername ist bereits vergeben!</p>";
    } else {
        // Benutzerdaten in die Tabelle users_pending einfügen
        $query = "INSERT INTO users_pending (username, password, name) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('sss', $username, $password, $name);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            echo "<p style='color: green;'>Registrierung erfolgreich! Bitte warten Sie auf die Aktivierung.</p>";
        } else {
            echo "<p style='color: red;'>Fehler bei der Registrierung.</p>";
        }
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrierung</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            color: #333;
            margin: 0;
            padding: 0;
        }

        h1 {
            text-align: center;
            margin-top: 50px;
            color: #4CAF50;
        }

        .container {
            max-width: 500px;
            margin: 0 auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        label {
            font-size: 1.1em;
            margin-bottom: 8px;
            display: block;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        button {
            background-color: #4CAF50;
            color: white;
            padding: 15px;
            width: 100%;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1.1em;
        }

        button:hover {
            background-color: #45a049;
        }

        .message {
            text-align: center;
            font-size: 1.2em;
        }

        .message a {
            color: #4CAF50;
            text-decoration: none;
        }

        .message a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <h1>Benutzerregistrierung</h1>

    <div class="container">
        <form action="register.php" method="POST">
            <label for="name">Name:</label>
            <input type="text" name="name" required><br>

            <label for="username">Benutzername:</label>
            <input type="text" name="username" required><br>

            <label for="password">Passwort:</label>
            <input type="password" name="password" required><br>

            <button type="submit">Registrieren</button>
        </form>

        <div class="message">
            <br>
            <a href="index.php">Zurück zur Startseite</a>
        </div>
    </div>

</body>
</html>
