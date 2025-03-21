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
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1>Benutzerregistrierung</h1>
    <form action="register.php" method="POST">
        <label for="name">Name:</label>
        <input type="text" name="name" required><br><br>

        <label for="username">Benutzername:</label>
        <input type="text" name="username" required><br><br>

        <label for="password">Passwort:</label>
        <input type="password" name="password" required><br><br>

        <button type="submit">Registrieren</button>
    </form>
    <br>
    <a href="index.php">Zurück zur Startseite</a>
</body>
</html>
