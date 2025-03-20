<?php
$host = 'servercampus.xyz';
$dbname = 's7_itu3';
$username = 'u7_HASBJBcL22';
$password = 'DWtvviP1=XB1D!!a8uYR2sUM';

// MySQLi-Verbindung
$conn = new mysqli($host, $username, $password, $dbname);

// Überprüfen, ob die Verbindung erfolgreich war
if ($conn->connect_error) {
    die("MySQLi-Verbindung fehlgeschlagen: " . $conn->connect_error);
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Verbindungsfehler: " . $e->getMessage());
}



?>
