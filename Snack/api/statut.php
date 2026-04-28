<?php
// api/statut.php — appelé par le bot Discord pour mettre à jour une commande
// POST { numero, statut, paiement, est_adherent }

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Méthode non autorisée', 405);
}

// Vérification token bot simple
$auth = $_SERVER['HTTP_X_BOT_TOKEN'] ?? '';
if (DISCORD_BOT_TOKEN && $auth !== DISCORD_BOT_TOKEN) {
    json_error('Non autorisé', 401);
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) json_error('JSON invalide');

$numero       = trim($body['numero'] ?? '');
$statut       = trim($body['statut'] ?? '');
$paiement     = trim($body['paiement'] ?? '');
$est_adherent = isset($body['est_adherent']) ? (int)$body['est_adherent'] : null;

if (!$numero) json_error('Numéro de commande requis');

$allowed_statuts = ['en_attente', 'confirme', 'paye', 'annule'];
if ($statut && !in_array($statut, $allowed_statuts)) {
    json_error('Statut invalide');
}

$db = getDB();

// Récupérer la commande
$stmt = $db->prepare("SELECT * FROM commandes WHERE numero = ?");
$stmt->execute([$numero]);
$cmd = $stmt->fetch();
if (!$cmd) json_error('Commande introuvable', 404);

// Mettre à jour
$updates = [];
$params  = [];

if ($statut) {
    $updates[] = 'statut = ?';
    $params[]  = $statut;
}
if ($paiement) {
    $updates[] = 'paiement = ?';
    $params[]  = $paiement;
}
if ($est_adherent !== null) {
    $updates[] = 'est_adherent = ?';
    $params[]  = $est_adherent;

    // Recalculer le total selon adhérence
    $stmt2 = $db->prepare("SELECT * FROM commande_lignes WHERE commande_id = ?");
    $stmt2->execute([$cmd['id']]);
    $lignes = $stmt2->fetchAll();

    // On récupère les prix adhérent depuis les produits
    $total = 0.0;
    foreach ($lignes as $ligne) {
        $sp = $db->prepare("SELECT prix_adherent FROM produits WHERE id = ?");
        $sp->execute([$ligne['produit_id']]);
        $prod = $sp->fetch();
        $prix = $est_adherent && $prod
            ? (float)$prod['prix_adherent']
            : (float)$ligne['prix_unitaire'];
        $total += $prix * $ligne['quantite'];

        // Mettre à jour la ligne
        $db->prepare("UPDATE commande_lignes SET prix_unitaire=?, sous_total=? WHERE id=?")
           ->execute([$prix, round($prix * $ligne['quantite'], 2), $ligne['id']]);
    }

    $updates[] = 'total = ?';
    $params[]  = round($total, 2);
}

if (empty($updates)) json_error('Rien à mettre à jour');

$params[] = $numero;
$db->prepare("UPDATE commandes SET " . implode(', ', $updates) . " WHERE numero = ?")
   ->execute($params);

// Retourner la commande mise à jour avec ses lignes
$stmt = $db->prepare("SELECT * FROM commandes WHERE numero = ?");
$stmt->execute([$numero]);
$cmd_updated = $stmt->fetch();

$stmt = $db->prepare("SELECT * FROM commande_lignes WHERE commande_id = ?");
$stmt->execute([$cmd_updated['id']]);
$cmd_updated['lignes'] = $stmt->fetchAll();

json_success($cmd_updated);
