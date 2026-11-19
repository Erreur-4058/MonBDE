<?php
require_once __DIR__ . '/config.php';
$db = getDB();

// 1. Récupérer les produits
// Modifie la ligne WHERE pour ajouter le filtre de visibilité de catégorie
$stmt = $db->query("
    SELECT c.id AS cat_id, c.nom AS categorie, p.id, p.nom, p.description, 
           p.prix_normal, p.prix_adherent, p.disponible, p.image, p.stock
    FROM produits p
    JOIN categories c ON c.id = p.categorie_id
    WHERE p.disponible = 1 
    AND c.visible = 1      /* <--- AJOUTE CETTE LIGNE */
    ORDER BY c.ordre, p.nom
");
$rows = $stmt->fetchAll();

$menu = [];
foreach ($rows as $row) {
    $cat = $row['categorie'];
    if (!isset($menu[$cat])) {
        $menu[$cat] = ['categorie' => $cat, 'produits' => []];
    }
    $menu[$cat]['produits'][] = [
        'id'             => (int)$row['id'],
        'nom'            => $row['nom'],
        'description'    => $row['description'],
        'prix_normal'    => (float)$row['prix_normal'],
        'prix_adherent'  => (float)$row['prix_adherent'],
        'image'          => $row['image'],
        'stock'          => (int)$row['stock'],
        'en_rupture'     => ((int)$row['stock'] === 0),
    ];
}

// 2. Récupérer la configuration de la Vente Flash et show_salle
$res_config = $db->query("SELECT * FROM config WHERE cle LIKE 'special_%' OR cle = 'show_salle'")->fetchAll();
$spec = [];
foreach($res_config as $c) { $spec[$c['cle']] = $c['valeur']; }

// 3. Réponse JSON
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true, 
    'data' => array_values($menu),
    'special' => [
        'active' => $spec['special_active'] ?? '0',
        'title' => $spec['special_title'] ?? '',
        'category_id' => $spec['special_category_id'] ?? null,
        'badge' => $spec['special_badge_text'] ?? ''
    ],
    'show_salle' => ($spec['show_salle'] ?? '0') == '1'
]);




exit;