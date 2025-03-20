
<?php
$host = 'servercampus.xyz';
$dbname = 's5_startpage';
$username = 'u5_0kqGQBSAzE';
$password = 'SY7MCOKCsaSQ98oJ@By=E=cj';

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
