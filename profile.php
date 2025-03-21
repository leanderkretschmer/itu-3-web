<?php
session_start();
require_once 'db_connect.php';

// Prüfen, ob der Benutzer eingeloggt ist
if (!isset($_SESSION['user_id'])) {
    header('Location: login/index.php');
    exit;
}

// Benutzername aus der Datenbank abrufen
$user_id = $_SESSION['user_id'];
$query = "SELECT username FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($username);
$stmt->fetch();
$stmt->close();

$upload_dir = "Bilder/Benutzer/$username/";
$upload_file = $upload_dir . "Avatar.png";

// Ordner erstellen, falls nicht vorhanden
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Bild hochladen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    $file = $_FILES['avatar'];
    $allowed_types = ['image/png', 'image/jpeg', 'image/jpg'];
    
    if (in_array($file['type'], $allowed_types) && $file['size'] < 5000000) { // Max 5MB
        if (move_uploaded_file($file['tmp_name'], $upload_file)) {
            // Avatar-Pfad in der Datenbank speichern
            $query = "UPDATE users SET avatar = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('si', $upload_file, $user_id);
            $stmt->execute();
            $stmt->close();
            
            echo "<p style='color: green;'>Avatar erfolgreich hochgeladen!</p>";
        } else {
            echo "<p style='color: red;'>Fehler beim Hochladen.</p>";
        }
    } else {
        echo "<p style='color: red;'>Ungültiges Dateiformat oder Datei zu groß!</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil</title>
</head>
<body>
    <h1>Profil von <?php echo htmlspecialchars($username); ?></h1>
    <form action="profile.php" method="POST" enctype="multipart/form-data">
        <label>Profilbild hochladen:</label>
        <input type="file" name="avatar" accept="image/png, image/jpeg">
        <button type="submit">Hochladen</button>
    </form>
    <br>
    <a href="home.php">Zurück</a>
</body>
</html>
