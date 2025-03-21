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
            
            echo "<p class='success'>Avatar erfolgreich hochgeladen!</p>";
        } else {
            echo "<p class='error'>Fehler beim Hochladen.</p>";
        }
    } else {
        echo "<p class='error'>Ungültiges Dateiformat oder Datei zu groß!</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            margin: auto;
        }
        h1 {
            color: #333;
        }
        .success {
            color: green;
        }
        .error {
            color: red;
        }
        form {
            margin-top: 20px;
        }
        input[type="file"] {
            margin-bottom: 10px;
        }
        button {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
        }
        button:hover {
            background: #45a049;
        }
        .back-link {
            display: block;
            margin-top: 20px;
            text-decoration: none;
            color: #007BFF;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Profil von <?php echo htmlspecialchars($username); ?></h1>
        <form action="profile.php" method="POST" enctype="multipart/form-data">
            <label>Profilbild hochladen:</label><br>
            <input type="file" name="avatar" accept="image/png, image/jpeg"><br>
            <button type="submit">Hochladen</button>
        </form>
        <a href="index.php" class="back-link">Zurück zur Startseite</a>
    </div>
</body>
</html>
