<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'db_connect.php';

// Alle in der Tabelle `users_pending` stehenden Benutzer abrufen
$query = "SELECT * FROM users_pending";
$result = $conn->query($query);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activate_user'])) {
    $user_id = $_POST['user_id'];

    // Benutzer aus der Tabelle `users_pending` holen
    $query = "SELECT id, username, password, name FROM users_pending WHERE id = ?";
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        die('Fehler bei der Vorbereitung der SQL-Anfrage: ' . $conn->error);
    }
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($id, $username, $password, $name);
    $stmt->fetch();

    // Prüfen, ob der Benutzer gefunden wurde
    if ($stmt->num_rows === 0) {
        echo "<p style='color: red;'>Benutzer nicht gefunden!</p>";
    } else {
        // Den Benutzer in die Tabelle `users` einfügen
        $balance = 0.00;  // Standard-Balance
        $query = "INSERT INTO users (username, password, balance, name) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            die('Fehler bei der Vorbereitung der SQL-Anfrage: ' . $conn->error);
        }

        $stmt->bind_param('ssds', $username, $password, $balance, $name);
        $execute_result = $stmt->execute();

        if ($execute_result) {
            // Benutzer aus der `users_pending` Tabelle löschen
            $query = "DELETE FROM users_pending WHERE id = ?";
            $stmt = $conn->prepare($query);
            if ($stmt === false) {
                die('Fehler bei der Vorbereitung der SQL-Anfrage: ' . $conn->error);
            }

            $stmt->bind_param('i', $user_id);
            $stmt->execute();

            echo "<p style='color: green;'>Benutzer erfolgreich aktiviert!</p>";
        } else {
            echo "<p style='color: red;'>Fehler beim Aktivieren des Benutzers!</p>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Benutzeraktivierung</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1>Benutzer aktivieren</h1>

    <form method="POST">
        <label for="activate_user">Benutzer auswählen:</label>
        <select name="user_id" id="activate_user">
            <?php while ($row = $result->fetch_assoc()): ?>
                <option value="<?php echo $row['id']; ?>"><?php echo $row['name']; ?> (<?php echo $row['username']; ?>)</option>
            <?php endwhile; ?>
        </select>
        <button type="submit" name="activate_user">Benutzer aktivieren</button>
    </form>

    <br>
    <a href="index.php">Zurück zur Startseite</a>
</body>
</html>
