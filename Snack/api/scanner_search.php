<?php
require_once 'config.php';
$db = getDB();
$ean = $_GET['ean'] ?? '';

$stmt = $db->prepare("
    SELECT p.*, c.nom as cat_nom 
    FROM produits p 
    LEFT JOIN categories c ON p.categorie_id = c.id 
    WHERE p.code_barre = ? OR p.code_barre_pack = ?
");
$stmt->execute([$ean, $ean]);
$p = $stmt->fetch();

if ($p) {
    // On détermine si le code scanné correspond au pack ou à l'unité
    $found_as = ($p['code_barre_pack'] === $ean) ? 'pack' : 'unite';
    echo json_encode([
        'success' => true, 
        'data' => $p, 
        'found_as' => $found_as
    ]);
} else {
    echo json_encode(['success' => false]);
}