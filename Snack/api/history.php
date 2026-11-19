<?php
require_once 'staff_protect.php'; 
require_once 'config.php';
header('Content-Type: text/html; charset=utf-8');
$db = getDB();

// --- 1. TRAITEMENT DES ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $numero = $_POST['numero'];
    if ($_POST['action'] === 'restaurer') {
        $stmt = $db->prepare("UPDATE commandes SET statut = 'en_attente', paiement = NULL WHERE numero = ?");
        $stmt->execute([$numero]);
        $msg = "success:La commande $numero a été renvoyée au Dashboard.";
    }
    elseif ($_POST['action'] === 'supprimer') {
        $stmt = $db->prepare("DELETE FROM commandes WHERE numero = ?");
        $stmt->execute([$numero]);
        $msg = "success:Commande $numero supprimée.";
    }
    elseif ($_POST['action'] === 'supprimer_bulk') {
        $nums = $_POST['nums'] ?? [];
        if (!empty($nums)) {
            foreach ($nums as $n) {
                $db->prepare("DELETE FROM commandes WHERE numero = ?")->execute([$n]);
            }
            $msg = "success:" . count($nums) . " commande(s) supprimée(s).";
        }
    }
    header("Location: history.php?msg=" . urlencode($msg));
    exit;
}

// --- 2. LOGIQUE DE RECHERCHE ET FILTRE ---
$search = $_GET['q'] ?? '';
$f_statut = $_GET['statut'] ?? '';
$f_paiement = $_GET['paiement'] ?? '';

// Construction de la requête SQL de base
$sql = "SELECT * FROM commandes WHERE statut IN ('paye', 'annule')";
$params = [];

// Filtre Recherche (Nom ou Numéro)
if ($search) {
    $sql .= " AND (prenom LIKE ? OR numero LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Filtre Statut
if ($f_statut) {
    $sql .= " AND statut = ?";
    $params[] = $f_statut;
}

// Filtre Paiement
if ($f_paiement) {
    $sql .= " AND paiement = ?";
    $params[] = $f_paiement;
}

$sql .= " ORDER BY created_at DESC LIMIT 100";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$commandes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des Ventes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <style>
        body { background: #f4f7f6; font-family: 'Segoe UI', sans-serif; }
        .table-container { background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .badge-paye { background: #dcfce7; color: #166534; font-weight: 700; }
        .badge-annule { background: #fee2e2; color: #991b1b; font-weight: 700; }
        .filter-card { background: white; border-radius: 15px; border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .btn-restore { font-size: 0.8rem; font-weight: bold; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark mb-4">
    <div class="container">
        <span class="navbar-brand">Historique des Ventes</span>
        <div>
            <a href="dashboard.php" class="btn btn-outline-light btn-sm">Retour Dashboard</a>
        </div>
    </div>
</nav>

<div class="container">
    <?php if (isset($_GET['msg'])): list($type, $txt) = explode(':', $_GET['msg']); ?>
        <div class="alert alert-<?= $type ?> alert-dismissible fade show"><?= htmlspecialchars($txt) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <!-- BARRE DE RECHERCHE ET FILTRES -->
    <div class="card filter-card">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="small fw-bold text-muted">Rechercher</label>
                    <input type="text" name="q" class="form-control" placeholder="Client ou n° commande..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <label class="small fw-bold text-muted">Paiement</label>
                    <select name="paiement" class="form-select">
                        <option value="">Tous les types</option>
                        <option value="especes" <?= $f_paiement == 'especes' ? 'selected' : '' ?>>💵 Espèces</option>
                        <option value="sumup_adh" <?= $f_paiement == 'sumup_adh' ? 'selected' : '' ?>>🎫 SumUp Adhérent</option>
                        <option value="sumup_ext" <?= $f_paiement == 'sumup_ext' ? 'selected' : '' ?>>💳 SumUp Non Adhérent</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="small fw-bold text-muted">Statut</label>
                    <select name="statut" class="form-select">
                        <option value="">Tous les statuts</option>
                        <option value="paye" <?= $f_statut == 'paye' ? 'selected' : '' ?>>✅ Payé</option>
                        <option value="annule" <?= $f_statut == 'annule' ? 'selected' : '' ?>>❌ Annulé</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100 fw-bold">Filtrer</button>
                </div>
            </form>
        </div>
    </div>

    <div class="table-container">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th><input type="checkbox" id="checkAll"></th>
                    <th>Date / Heure</th>
                    <th>Commande</th>
                    <th>Client</th>
                    <th>Total</th>
                    <th>Paiement</th>
                    <th>Statut</th>
                    <th>Salle</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($commandes as $c): 
                    $date = date('d/m H:i', strtotime($c['created_at']));
                    $num_parts = explode('-', $c['numero']);
                    $num_display = isset($num_parts[1]) ? "#".$num_parts[1] : $c['numero'];

                    // Prix réellement payé selon est_adherent
                    $price_paid = (float)$c['total'];
                    if (($c['est_adherent'] ?? null) == 1) {
                        // Re-calculer le prix adhérent depuis les lignes
                        $stmt_lignes = $db->prepare("SELECT * FROM commande_lignes WHERE commande_id = ?");
                        $stmt_lignes->execute([$c['id']]);
                        $lignes_h = $stmt_lignes->fetchAll();
                        $total_adh_h = 0;
                        foreach($lignes_h as $l) {
                            $sp = $db->prepare("SELECT prix_adherent FROM produits WHERE id = ?");
                            $sp->execute([$l['produit_id']]);
                            $p = $sp->fetch();
                            if ($p) {
                                $total_adh_h += ((float)$p['prix_adherent'] * (int)$l['quantite']);
                            } else {
                                $total_adh_h += ((float)$l['prix_unitaire'] * (int)$l['quantite']);
                            }
                        }
                        $price_paid = $total_adh_h;
                    }
                ?>
                <tr>
                    <td><input type="checkbox" class="row-check" value="<?= $c['numero'] ?>"></td>
                    <td class="small text-muted"><?= $date ?></td>
                    <td class="fw-bold"><?= $num_display ?></td>
                    <td><?= htmlspecialchars($c['prenom']) ?></td>
                    <td class="fw-bold text-primary"><?= number_format($price_paid, 2) ?>€</td>
                    <td>
                        <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">
                            <?= $c['paiement'] ? str_replace('_', ' ', $c['paiement']) : '-' ?>
                        </small>
                    </td>
                    <td>
                        <span class="badge <?= $c['statut'] === 'paye' ? 'badge-paye' : 'badge-annule' ?>">
                            <?= $c['statut'] === 'paye' ? 'PAYÉ' : 'ANNULÉ' ?>
                        </span>
                    </td>
                    <td class="small text-muted">
                        <?php if (!empty($c['salle'])): ?>
                            <span class="badge bg-info">📍 <?= htmlspecialchars($c['salle']) ?></span>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <form method="POST" onsubmit="return confirm('Rétablir cette commande dans le Dashboard ?');" style="display:inline;">
                            <input type="hidden" name="numero" value="<?= $c['numero'] ?>">
                            <input type="hidden" name="action" value="restaurer">
                            <button type="submit" class="btn btn-outline-primary btn-sm btn-restore d-inline-flex align-items-center">
                                <span class="material-symbols-outlined me-1" style="font-size: 18px;">replay</span>
                                Rétablir
                            </button>
                        </form>
                        &nbsp;
                        <form method="POST" onsubmit="return confirm('⚠️ Supprimer cette commande définitivement ?');" style="display:inline;">
                            <input type="hidden" name="numero" value="<?= $c['numero'] ?>">
                            <input type="hidden" name="action" value="supprimer">
                            <button type="submit" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center">
                                <span class="material-symbols-outlined" style="font-size: 18px;">delete</span>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($commandes)): ?>
                <tr>
                    <td colspan="9" class="text-center py-5 text-muted">
                        <span class="material-symbols-outlined d-block mb-2" style="font-size: 3rem; opacity: 0.3;">search_off</span>
                        Aucune commande trouvée avec ces filtres.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- SUPPRIMER SÉLECTION -->
    <div class="mt-2">
        <form method="POST" id="bulkForm" style="display:none;">
            <input type="hidden" name="action" value="supprimer_bulk">
            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('⚠️ Supprimer toutes les commandes sélectionnées définitivement ?');">
                🗑️ Supprimer la sélection
            </button>
        </form>
    </div>
</div>

<script>
document.getElementById('checkAll').addEventListener('change', function() {
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
    updateBulkBtn();
});
document.querySelectorAll('.row-check').forEach(cb => {
    cb.addEventListener('change', updateBulkBtn);
});
function updateBulkBtn() {
    const checked = document.querySelectorAll('.row-check:checked');
    var form = document.getElementById('bulkForm');
    form.querySelectorAll('.bulk-num').forEach(el => el.remove());
    checked.forEach(cb => {
        var inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'nums[]';
        inp.value = cb.value;
        inp.className = 'bulk-num';
        form.appendChild(inp);
    });
    form.style.display = checked.length > 0 ? 'block' : 'none';
}
</script>