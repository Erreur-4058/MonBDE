<?php 
require_once 'staff_protect.php'; 
require_once 'config.php';

// Force l'heure française
date_default_timezone_set('Europe/Paris');

header('Content-Type: text/html; charset=utf-8'); 
$db = getDB();

// --- 1. TRAITEMENT DES ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $numero = $_POST['numero'] ?? '';
    $action = $_POST['action'] ?? '';

    if ($action === 'paye_especes') {
        $stmt = $db->prepare("UPDATE commandes SET statut = 'paye', paiement = 'especes' WHERE numero = ?");
        $stmt->execute([$numero]);
    } elseif ($action === 'annuler') {
        $stmt = $db->prepare("UPDATE commandes SET statut = 'annule' WHERE numero = ?");
        $stmt->execute([$numero]);
    }
    header("Location: dashboard.php");
    exit;
}

if (isset($_GET['pay_sumup']) && isset($_GET['numero'])) {
    $numero = $_GET['numero'];
    $type = $_GET['type']; 
    $amount = $_GET['amount'];
    $paiement_label = ($type === 'adh') ? 'sumup_adh' : 'sumup_ext';
    $est_adherent = ($type === 'adh') ? 1 : 0;

    $stmt = $db->prepare("UPDATE commandes SET statut = 'paye', paiement = ?, est_adherent = ? WHERE numero = ?");
    $stmt->execute([$paiement_label, $est_adherent, $numero]);

    header("Location: sumup_bridge.php?amount=$amount&ref=$numero-$type");
    exit;
}

// --- 2. RÉCUPÉRATION DES COMMANDES (Anciennes en haut) ---
$commandes = $db->query("SELECT * FROM commandes WHERE statut IN ('en_attente', 'confirme') ORDER BY created_at ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff - Commandes en cours</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <style>
        body { background: #f4f7f6; font-family: 'Segoe UI', sans-serif; }
        .card-commande { border: none; border-radius: 12px; border-left: 6px solid #f59e0b; box-shadow: 0 4px 6px rgba(0,0,0,0.05); transition: all 0.3s ease; }
        .badge-at { background: #fffbeb; color: #92400e; border: 1px solid #fef3c7; }
        .total-price { font-size: 1.4rem; font-weight: 800; color: #1e40af; }
        .item-list { background: #f9fafb; border-radius: 8px; padding: 10px; margin: 10px 0; }
        .btn-sumup-adh { border-color: #22c55e; color: #166534; }
        .fade-out { opacity: 0; transform: scale(0.9); pointer-events: none; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark mb-4">
    <div class="container">
        <span class="navbar-brand">Snack Staff <span class="badge bg-warning text-dark ms-2">LIVE</span></span>
        <div>
            <a href="history.php" class="btn btn-sm btn-outline-light me-2">Historique</a>
            <a href="admin.php" class="btn btn-sm btn-outline-light me-2">Administration</a>
            <button onclick="location.reload()" class="btn btn-sm btn-primary d-inline-flex align-items-center">
                <span class="material-symbols-outlined me-1" style="font-size: 18px;">refresh</span> Actualiser
            </button>
        </div>
    </div>
</nav>

<div class="container">
    <div class="mb-4">
        <h2>Commandes à traiter</h2>
        <span class="badge bg-secondary"><?php echo count($commandes); ?> en cours</span>
    </div>
    
    <div class="row" id="container-commandes">
        <?php foreach ($commandes as $c): 
            $stmt = $db->prepare("SELECT * FROM commande_lignes WHERE commande_id = ?");
            $stmt->execute([$c['id']]);
            $lignes = $stmt->fetchAll();

            $total_normal = 0; $total_adh = 0;
            foreach($lignes as $l) {
                $sp = $db->prepare("SELECT prix_normal, prix_adherent FROM produits WHERE id = ?");
                $sp->execute([$l['produit_id']]);
                $p = $sp->fetch();
                if ($p) {
                    $total_normal += ((float)$p['prix_normal'] * (int)$l['quantite']);
                    $total_adh += ((float)$p['prix_adherent'] * (int)$l['quantite']);
                } else {
                    $total_normal += ((float)$l['prix_unitaire'] * (int)$l['quantite']);
                    $total_adh += ((float)$l['prix_unitaire'] * (int)$l['quantite']);
                }
            }
            $num_display = explode('-', $c['numero'])[1] ?? $c['numero'];
            $card_id = "card-" . str_replace('-', '', $c['numero']);
        ?>
        <div class="col-md-6 col-lg-4 mb-4" id="<?= $card_id ?>">
            <div class="card card-commande h-100">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h4 class="fw-bold mb-0">#<?= $num_display ?></h4>
                        <span class="badge badge-at">EN ATTENTE</span>
                    </div>
                    <!-- DATE ET HEURE CORRIGÉES ICI -->
                    <h5 class="text-muted small mb-3">
                        <?= htmlspecialchars($c['prenom']) ?> • 
                        <?= date('d/m H:i', strtotime($c['created_at'] . ' UTC')) ?>
                    </h5>
                    
                    <div class="item-list flex-grow-1">
                        <ul class="list-unstyled mb-0 small">
                            <?php foreach ($lignes as $l): ?>
                                <li class="mb-1"><span class="fw-bold">x<?= $l['quantite'] ?></span> <?= htmlspecialchars($l['nom_produit']) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <div class="d-flex justify-content-between align-items-center my-3">
                        <span class="text-muted small">Total :</span>
                        <span class="total-price"><?= number_format($c['total'], 2) ?>€</span>
                    </div>

                    <div class="d-grid gap-2">
                        <form method="POST" onsubmit="return confirm('Payer en espèces ?')">
                            <input type="hidden" name="numero" value="<?= $c['numero'] ?>">
                            <input type="hidden" name="action" value="paye_especes">
                            <button type="submit" class="btn btn-success fw-bold w-100 py-2 d-flex align-items-center justify-content-center">
                                <span class="material-symbols-outlined me-2">payments</span> ESPÈCES
                            </button>
                        </form>

                        <div class="row g-2">
                            <div class="col-6">
                                <a href="javascript:void(0)" onclick="encaisser('<?= $card_id ?>', 'dashboard.php?pay_sumup=1&type=adh&numero=<?= $c['numero'] ?>&amount=<?= $total_adh ?>')" class="btn btn-outline-success btn-sm w-100 py-2 fw-bold d-flex align-items-center justify-content-center">
                                    <span class="material-symbols-outlined me-2">card_membership</span> Adhérent
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="javascript:void(0)" onclick="encaisser('<?= $card_id ?>', 'dashboard.php?pay_sumup=1&type=ext&numero=<?= $c['numero'] ?>&amount=<?= $total_normal ?>')" class="btn btn-outline-primary btn-sm w-100 py-2 fw-bold d-flex align-items-center justify-content-center">
                                    <span class="material-symbols-outlined me-2">credit_card</span> Non Adhérent
                                </a>
                            </div>
                        </div>

                        <form method="POST" onsubmit="return confirm('Annuler ?')" class="text-center mt-1">
                            <input type="hidden" name="numero" value="<?= $c['numero'] ?>">
                            <input type="hidden" name="action" value="annuler">
                            <button type="submit" class="btn btn-link text-danger btn-sm p-0 text-decoration-none">Annuler</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
    function encaisser(cardId, url) {
        const card = document.getElementById(cardId);
        if (card) {
            card.classList.add('fade-out');
            setTimeout(() => { card.style.display = 'none'; }, 300);
        }
        window.location.href = url;
    }
    setTimeout(() => { location.reload(); }, 30000);
</script>
</body>
</html>