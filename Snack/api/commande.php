<?php
require_once __DIR__ . '/config.php';

// Gestion globale des erreurs → JSON au lieu de 500
set_exception_handler(function($e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Erreur serveur: ' . $e->getMessage()]);
    exit;
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Méthode non autorisée', 405);
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) json_error('corps JSON invalide');

$prenom  = trim($body['prenom'] ?? '');
$discord_id = trim($body['discord_id'] ?? ''); // Récupéré depuis le site
$salle   = trim($body['salle'] ?? ''); // Optionnel
$panier  = $body['panier'] ?? [];

if (!$prenom) json_error('Prénom requis');
if (empty($panier)) json_error('Panier vide');

$db = getDB();
$today = date('Y-m-d');

// 1. Générer le numéro simple (#1, #2...)
$stmt = $db->prepare("SELECT COUNT(*) as total FROM commandes WHERE date(created_at) = date('now')");
$stmt->execute();
$row = $stmt->fetch();
$next_num = (int)($row['total'] ?? 0) + 1;

// Le numéro unique pour la DB (ex: 20260326-1)
$numero_db = date('Ymd') . "-" . $next_num;

// 2. Récupérer les vrais prix en DB
$ids = array_column($panier, 'id');
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $db->prepare("SELECT * FROM produits WHERE id IN ($placeholders)");
$stmt->execute($ids);

$produits_db = [];
foreach ($stmt->fetchAll() as $p) {
    $produits_db[$p['id']] = $p;
}

// 3. Calculer les totaux et préparer les lignes
$lignes = [];
$total_normal = 0.0;
$total_adherent = 0.0;

foreach ($panier as $item) {
    $pid = (int)$item['id'];
    $qty = max(1, (int)($item['quantite'] ?? 1));
    if (!isset($produits_db[$pid])) continue;

    $p = $produits_db[$pid];

    // Vérification stock (si stock != -1)
    if ((int)$p['stock'] !== -1 && (int)$p['stock'] < $qty) {
        json_error('Stock insuffisant pour : ' . $p['nom'] . ' (disponible : ' . $p['stock'] . ')');
    }

    $pn = (float)$p['prix_normal'];
    $pa = (float)$p['prix_adherent'];

    $lignes[] = [
        'produit_id'   => $pid,
        'nom_produit'  => $p['nom'],
        'quantite'     => $qty,
        'prix_unitaire'=> $pn,
        'sous_total_n' => round($pn * $qty, 2),
        'sous_total_a' => round($pa * $qty, 2),
    ];
    $total_normal += round($pn * $qty, 2);
    $total_adherent += round($pa * $qty, 2);
}

// 4. Insérer en Base de Données (avec salle optionnelle)
$stmt = $db->prepare("INSERT INTO commandes (numero, prenom, discord_id, salle, statut, total) VALUES (?, ?, ?, ?, 'en_attente', ?)");
$stmt->execute([$numero_db, $prenom, $discord_id, $salle ?: null, $total_normal]);
$commande_id = $db->lastInsertId();

$stmt = $db->prepare("INSERT INTO commande_lignes (commande_id, produit_id, nom_produit, quantite, prix_unitaire, sous_total) VALUES (?, ?, ?, ?, ?, ?)");
foreach ($lignes as $l) {
    $stmt->execute([$commande_id, $l['produit_id'], $l['nom_produit'], $l['quantite'], $l['prix_unitaire'], $l['sous_total_n']]);
}

// 4b. Décrémenter le stock (si stock != -1)
$stmt_stock = $db->prepare("UPDATE produits SET stock = stock - ? WHERE id = ? AND stock != -1");
foreach ($lignes as $l) {
    $stmt_stock->execute([$l['quantite'], $l['produit_id']]);
}

// 5. Notifier le Bot Discord (optionnel, ne doit pas bloquer)
try {
    $payload_bot = [
        'numero' => $numero_db,
        'prenom' => $prenom,
        'discord_id' => $discord_id,
        'total_normal' => $total_normal,
        'total_adherent' => $total_adherent,
        'lignes' => $lignes
    ];

    $ch = @curl_init('http://bot:3001/nouvelle-commande');
    if ($ch) {
        @curl_setopt($ch, CURLOPT_POST, true);
        @curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload_bot));
        @curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'X-Bot-Token: snack2025']);
        @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        @curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        @curl_exec($ch);
        @curl_close($ch);
    }
} catch(Exception $e) {
    // Bot indisponible en production hors Docker
}

// 6. Réponse finale au site (on ne renvoie que le petit numéro pour l'affichage)
json_success([
    'numero' => $next_num,
    'numero_complet' => $numero_db
]);