<?php
session_start();

// Zerstöre die Session
session_unset();    // Entfernt alle Session-Variablen
session_destroy();  // Zerstört die Session selbst

// Überprüfen, ob das "remember me"-Cookie gesetzt ist und lösche es
if (isset($_COOKIE['user_login'])) {
    // Lösche das Cookie, indem wir ein abgelaufenes Datum setzen
    setcookie('user_login', '', time() - 3600, '/'); // Der Cookie wird sofort ungültig
}

// Weiterleitung zur Login-Seite oder einer anderen Seite nach dem Logout
header('Location: login');
exit;
