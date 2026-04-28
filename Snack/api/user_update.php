<?php
require_once 'config.php';
$db = getDB();
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['discord_id'])) {
    $stmt = $db->prepare("INSERT INTO utilisateurs (discord_id, username, est_adherent) 
                          VALUES (?, ?, ?) 
                          ON CONFLICT(discord_id) DO UPDATE SET est_adherent = excluded.est_adherent");
    $stmt->execute([$data['discord_id'], $data['username'], $data['est_adherent']]);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Donnees manquantes']);
}