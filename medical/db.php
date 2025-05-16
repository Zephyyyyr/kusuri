<?php
// config.php
$host = '45.145.164.37';
$db   = 'jnc_ticket';
$user = 'critical';      // Remplacez par votre nom d'utilisateur MySQL
$pass = 'cjMbMG2BK7v';      // Remplacez par votre mot de passe MySQL
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}
?>
