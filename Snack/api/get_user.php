<?php
require_once 'config.php';
$db = getDB();
$id = $_GET['id'] ?? '';

$stmt = $db->prepare("SELECT * FROM utilisateurs WHERE discord_id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if ($user) {
    echo json_encode(['success' => true, 'data' => $user]);
} else {
    echo json_encode(['success' => false, 'error' => 'Inconnu']);
}