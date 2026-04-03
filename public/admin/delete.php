<?php
require '../../server/api/config.php';
if (!isset($_SESSION['user_id'])) die("Non autorisé");

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? null;

if (!$id) header('Location: index.php');

if ($action == 'delete_user') {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);
} 
elseif ($action == 'unlink_discord') {
    $stmt = $pdo->prepare("UPDATE users SET discord_id = NULL, discord_email = NULL, discord_picture = NULL WHERE id = ?");
    $stmt->execute([$id]);
}
elseif ($action == 'unlink_google') {
    $stmt = $pdo->prepare("UPDATE users SET google_id = NULL, google_email = NULL, google_picture = NULL WHERE id = ?");
    $stmt->execute([$id]);
}

header('Location: index.php');