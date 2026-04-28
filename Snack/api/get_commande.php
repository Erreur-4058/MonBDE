<?php
require_once 'config.php';
$db = getDB();
$numero = $_GET['numero'] ?? '';

$stmt = $db->prepare("SELECT * FROM commandes WHERE numero = ?");
$stmt->execute([$numero]);
$commande = $stmt->fetch();

if ($commande) {
    // Récupérer les lignes (produits) de la commande
    $stmt = $db->prepare("SELECT * FROM commande_lignes WHERE commande_id = ?");
    $stmt->execute([$commande['id']]);
    $commande['lignes'] = $stmt->fetchAll();
    echo json_encode(['success' => true, 'data' => $commande]);
} else {
    echo json_encode(['success' => false, 'error' => 'Commande introuvable']);
}