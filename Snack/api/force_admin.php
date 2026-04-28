<?php
require_once 'config.php';

// On vérifie si tu es au moins passé par le login
if (!isset($_SESSION['user_id'])) {
    die("Connecte-toi d'abord sur le site pour que je sache qui tu es.");
}

$id = $_SESSION['user_id'];
$db = getCoreDB();

// On te passe admin de force
$stmt = $db->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
$stmt->execute([$id]);

echo "✅ Succès ! L'utilisateur ID #$id est maintenant ADMIN dans le Core.<br>";
echo "<a href='dashboard.php'>Accéder au Dashboard</a>";