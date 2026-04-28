<?php
// api/produits.php — liste tous les produits groupés par catégorie

require_once __DIR__ . '/config.php';

$db = getDB();

$stmt = $db->query("
    SELECT
        c.id   AS cat_id,
        c.nom  AS categorie,
        p.id,
        p.nom,
        p.description,
        p.prix_normal,
        p.prix_adherent,
        p.disponible,
        p.image,
        p.stock
    FROM produits p
    JOIN categories c ON c.id = p.categorie_id
    WHERE p.disponible = 1
    ORDER BY c.ordre, p.nom
");

$rows = $stmt->fetchAll();

// Grouper par catégorie
$menu = [];
foreach ($rows as $row) {
    $cat = $row['categorie'];
    if (!isset($menu[$cat])) {
        $menu[$cat] = ['categorie' => $cat, 'produits' => []];
    }
    $stock = (int)$row['stock'];
    $menu[$cat]['produits'][] = [
        'id'             => (int)$row['id'],
        'nom'            => $row['nom'],
        'description'    => $row['description'],
        'prix_normal'    => (float)$row['prix_normal'],
        'prix_adherent'  => (float)$row['prix_adherent'],
        'image'          => $row['image'],
        'stock'          => $stock,           // -1 = illimité
        'en_rupture'     => ($stock === 0),   // true si stock épuisé
    ];
}

json_success(array_values($menu));
