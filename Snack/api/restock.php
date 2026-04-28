<?php require_once 'staff_protect.php'; 

require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');
$db = getDB();

// Créer table config si elle n'existe pas
$db->exec("CREATE TABLE IF NOT EXISTS config (cle TEXT PRIMARY KEY, valeur TEXT NOT NULL)");
$db->exec("INSERT OR IGNORE INTO config (cle, valeur) VALUES ('seuil_stock_faible', '5')");

// ── Lecture du seuil depuis la DB ──────────────────────────────────
function getSeuil(PDO $db): int {
    $r = $db->query("SELECT valeur FROM config WHERE cle = 'seuil_stock_faible'")->fetch();
    return max(1, (int)($r['valeur'] ?? 5));
}

// ── PRG : Traitement POST puis REDIRECT ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'restock') {
        $quantites = $_POST['quantites'] ?? [];
        $updated = 0; 
        $stmt = $db->prepare("UPDATE produits SET stock = stock + ? WHERE id = ? AND ? > 0");
        foreach ($quantites as $prod_id => $qty) {
            $qty = max(0, (int)$qty);
            if ($qty > 0) {
                $stmt->execute([$qty, (int)$prod_id, $qty]);
                $updated++;
            }
        }
        $msg  = $updated > 0 ? "ok:$updated produit(s) réapprovisionné(s) !" : "warn:Aucune quantité saisie.";
        header("Location: restock.php?msg=" . urlencode($msg));
        exit;
    }

    elseif ($action === 'set_stock') {
        $prod_id = (int)($_POST['prod_id'] ?? 0);
        $stock   = (int)($_POST['stock_value'] ?? -1);
        if ($prod_id > 0) {
            $db->prepare("UPDATE produits SET stock = ? WHERE id = ?")->execute([$stock, $prod_id]);
        }
        header("Location: restock.php?msg=" . urlencode("ok:Stock mis à jour."));
        exit;
    }

    elseif ($action === 'save_seuil') {
        $seuil = max(1, (int)($_POST['seuil'] ?? 5));
        $db->prepare("UPDATE config SET valeur = ? WHERE cle = 'seuil_stock_faible'")->execute([$seuil]);
        header("Location: restock.php?msg=" . urlencode("ok:Seuil mis à jour à $seuil."));
        exit;
    }

    // ACTION : Basculer la visibilité (Disponible à la vente)
    elseif ($action === 'toggle_dispo') {
        $prod_id = (int)$_POST['prod_id'];
        $db->prepare("UPDATE produits SET disponible = 1 - disponible WHERE id = ?")->execute([$prod_id]);
        header("Location: restock.php");
        exit;
    }
}

// ── Chargement des données ─────────────────────────────────────────
$seuil = getSeuil($db);

$produits = $db->query("
    SELECT p.id, p.nom, p.description, p.stock, p.disponible, c.nom as cat_nom
    FROM produits p
    LEFT JOIN categories c ON c.id = p.categorie_id
    ORDER BY
        CASE WHEN p.stock = 0 THEN 0
             WHEN p.stock = -1 THEN 9999
             ELSE p.stock END ASC,
        c.ordre, p.nom
")->fetchAll();

$rupture   = array_values(array_filter($produits, fn($p) => $p['stock'] !== -1 && (int)$p['stock'] === 0));
$faible    = array_values(array_filter($produits, fn($p) => $p['stock'] !== -1 && (int)$p['stock'] > 0 && (int)$p['stock'] <= $seuil));
$a_acheter = array_merge($rupture, $faible);
$autres    = array_values(array_filter($produits, fn($p) => $p['stock'] == -1 || (int)$p['stock'] > $seuil));

$nb_rupture = count($rupture);
$nb_low     = count($faible);
$nb_ok      = count(array_filter($produits, fn($p) => (int)$p['stock'] > $seuil));
$nb_inf     = count(array_filter($produits, fn($p) => $p['stock'] == -1));

// Message depuis redirect
$msg_raw  = $_GET['msg'] ?? '';
$message  = null;
$msg_type = 'success';
if ($msg_raw) {
    [$msg_type_code, $msg_text] = explode(':', $msg_raw, 2);
    $message  = $msg_text;
    $msg_type = $msg_type_code === 'ok' ? 'success' : 'warning';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réapprovisionnement — Snack Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />

    <style>
        body { background: #f4f7f6; font-family: 'Segoe UI', sans-serif; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.06); margin-bottom: 24px; }
        .card-header { font-weight: 700; font-size: 1rem; background: white; border-radius: 12px 12px 0 0 !important; }

        .table-restock th { font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.05em; color: #888; }
        .table-restock td { vertical-align: middle; }

        .stock-badge-out { background:#fee2e2; color:#dc2626; font-weight:700; border-radius:20px; padding:2px 10px; font-size:.8rem; }
        .stock-badge-low { background:#fef3c7; color:#d97706; font-weight:700; border-radius:20px; padding:2px 10px; font-size:.8rem; }
        .stock-badge-ok  { background:#d1fae5; color:#059669; font-weight:700; border-radius:20px; padding:2px 10px; font-size:.8rem; }
        .stock-badge-inf { background:#e5e7eb; color:#6b7280; font-weight:700; border-radius:20px; padding:2px 10px; font-size:.8rem; }

        .qty-input {
            width: 90px; text-align: center;
            border: 2px solid #e5e7eb; border-radius: 8px;
            padding: 5px 10px; font-weight: 700; font-size: 1rem;
            transition: border-color 0.2s, background 0.2s;
        }
        .qty-input:focus { border-color: #3b82f6; outline: none; }
        tr.rupture td { background: #fff5f5; }
        tr.low td     { background: #fffbeb; }

        .seuil-input {
            width: 64px; text-align: center; font-weight: 700;
            border: 2px solid #d1d5db; border-radius: 8px;
            padding: 4px 8px; font-size: 0.95rem;
        }
        .seuil-input:focus { border-color: #3b82f6; outline: none; }

        .btn-toggle { border:none; background:none; padding:0; cursor:pointer; }

        @media print {
            nav, .no-print { display: none !important; }
            .card { box-shadow: none; border: 1px solid #ddd !important; }
        }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark mb-0 no-print">
    <div class="container">
        <span class="navbar-brand">Réapprovisionnement</span>
        <a href="admin.php" class="btn btn-outline-light btn-sm">Retour Admin</a>
    </div>
</nav>

<div class="container mt-4">

    <?php if ($message): ?>
    <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show no-print">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- BARRE OUTILS -->
    <div class="card no-print">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <form method="POST" class="d-flex align-items-center gap-2">
                <input type="hidden" name="action" value="save_seuil">
                <span class="text-muted small fw-semibold">Seuil stock faible :</span>
                
                <input type="number" name="seuil" id="seuilInput" class="seuil-input" value="<?= $seuil ?>" min="1" max="999">
                
                <button type="submit" class="btn btn-sm btn-primary d-flex align-items-center"><span class="material-symbols-outlined me-2">save</span> Sauvegarder</button>
            </form>

            <div class="vr d-none d-md-block"></div>

            <div class="d-flex gap-2 ms-auto">
                <button class="btn btn-sm btn-outline-dark d-inline-flex align-items-center" onclick="window.print()">
                    <span class="material-symbols-outlined me-1" style="font-size: 18px;">print</span> Imprimer
                </button>
                <button class="btn btn-sm btn-success d-inline-flex align-items-center" data-bs-toggle="modal" data-bs-target="#exportModal" onclick="genererNote()">
                    <span class="material-symbols-outlined me-1" style="font-size: 18px;">assignment</span> Exporter la note
                </button>
            </div>
        </div>
    </div>

    <!-- COMPTEURS (GARDÉS COMME DEMANDÉ) -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card text-center p-3">
                <div style="font-size:1.5rem; font-weight:800; color:#dc2626;"><?= $nb_rupture ?></div>
                <div class="text-muted small">Rupture</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center p-3">
                <div style="font-size:1.5rem; font-weight:800; color:#d97706;"><?= $nb_low ?></div>
                <div class="text-muted small">Stock faible (≤<?= $seuil ?>)</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center p-3">
                <div style="font-size:1.5rem; font-weight:800; color:#059669;"><?= $nb_ok ?></div>
                <div class="text-muted small">OK</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center p-3">
                <div style="font-size:1.5rem; font-weight:800; color:#6b7280;"><?= $nb_inf ?></div>
                <div class="text-muted small">Illimité</div>
            </div>
        </div>
    </div>

    <!-- FORMULAIRE RÉAPPRO -->
    <form method="POST">
        <input type="hidden" name="action" value="restock">

        <!-- LISTE D'ACHAT -->
        <?php if (count($a_acheter) > 0): ?>
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span class="d-inline-flex align-items-center"><span class="material-symbols-outlined me-2">shopping_cart</span> Liste d'achat (<?= count($a_acheter) ?>)</span>
                <button type="button" class="btn btn-outline-secondary btn-sm no-print" onclick="resetAll()">Réinitialiser</button>
            </div>
            <div class="card-body p-0">
                <table class="table table-restock table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Produit</th>
                            <th class="text-center">Vente</th>
                            <th>Stock actuel</th>
                            <th class="no-print">Qté reçue</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($a_acheter as $p):
                        $s = (int)$p['stock'];
                    ?>
                        <tr class="<?= ($s === 0) ? 'rupture' : 'low' ?>">
                            <td class="ps-4 fw-semibold"><?= htmlspecialchars($p['nom']) ?><br><small class="text-muted"><?= htmlspecialchars($p['cat_nom']) ?></small></td>
                            <td class="text-center">
                                <button type="button" class="btn-toggle no-print" onclick="toggleDispo(<?= $p['id'] ?>)">
                                    <span class="material-symbols-outlined <?= $p['disponible'] ? 'text-primary' : 'text-muted' ?>">
                                        <?= $p['disponible'] ? 'visibility' : 'visibility_off' ?>
                                    </span>
                                </button>
                            </td>
                            <td><span class="<?= ($s === 0) ? 'stock-badge-out' : 'stock-badge-low' ?>"><?= $s ?></span></td>
                            <td class="no-print"><input type="number" name="quantites[<?= $p['id'] ?>]" class="qty-input" value="0" min="0" oninput="updatePreview(<?= $p['id'] ?>)"></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- AUTRES PRODUITS -->
        <div class="card no-print">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><span class="material-symbols-outlined me-2">
box
</span> Autres produits (<?= count($autres) ?>)</span>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleAutres()">Afficher / Masquer</button>
            </div>
            <div id="autresBlock" style="display:none;">
                <div class="card-body p-0">
                    <table class="table table-restock table-hover mb-0">
                        <thead class="table-light"><tr><th class="ps-4">Produit</th><th class="text-center">Vente</th><th>Stock actuel</th><th>Qté reçue</th></tr></thead>
                        <tbody>
                        <?php foreach ($autres as $p): $s = (int)$p['stock']; ?>
                            <tr>
                                <td class="ps-4 fw-semibold"><?= htmlspecialchars($p['nom']) ?><br><small class="text-muted"><?= htmlspecialchars($p['cat_nom']) ?></small></td>
                                <td class="text-center">
                                    <button type="button" class="btn-toggle" onclick="toggleDispo(<?= $p['id'] ?>)">
                                        <span class="material-symbols-outlined <?= $p['disponible'] ? 'text-primary' : 'text-muted' ?>">
                                            <?= $p['disponible'] ? 'visibility' : 'visibility_off' ?>
                                        </span>
                                    </button>
                                </td>
                                <td><span class="<?= ($s == -1) ? 'stock-badge-inf' : 'stock-badge-ok' ?>"><?= ($s == -1) ? '∞' : $s ?></span></td>
                                <td><input type="number" name="quantites[<?= $p['id'] ?>]" class="qty-input" value="0" min="0"></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="d-flex gap-3 mb-5 no-print">
            <button type="submit" class="btn btn-success btn-lg px-5 d-inline-flex align-items-center">
                <span class="material-symbols-outlined me-2">check</span> Valider
            </button>
        </div>
    </form>
</div>

<!-- FORM CACHÉ POUR VISIBILITÉ -->
<form id="formToggle" method="POST" style="display:none;">
    <input type="hidden" name="action" value="toggle_dispo">
    <input type="hidden" name="prod_id" id="toggleId">
</form>
<!-- MODAL EXPORT -->
<div class="modal fade no-print" id="exportModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Note d'approvisionnement</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><pre id="exportTexte"></pre></div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" onclick="copierNote()">Copier</button>
                <button class="btn btn-success" onclick="telechargerNote()">Télécharger .txt</button>
            </div>
        </div>
    </div>
</div>
<script>
function toggleDispo(id) {
    document.getElementById('toggleId').value = id;
    document.getElementById('formToggle').submit();
}
function toggleAutres() {
    const el = document.getElementById('autresBlock');
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
function stepSeuil(delta) {
    const input = document.getElementById('seuilInput');
    input.value = Math.max(1, parseInt(input.value) + delta);
}


const produits = <?= json_encode($produits) ?>;
const seuil = <?= $seuil ?>;

function toggleDispo(id) { document.getElementById('toggleId').value = id; document.getElementById('formToggle').submit(); }
function toggleAutres() { const el = document.getElementById('autresBlock'); el.style.display = el.style.display === 'none' ? 'block' : 'none'; }

function genererNote() {
    const now = new Date().toLocaleString('fr-FR');
    let note = "📋 NOTE D'APPROVISIONNEMENT - " + now + "\n";
    note += "------------------------------------------\n";
    
    const aAcheter = produits.filter(p => p.stock !== -1 && p.stock <= seuil);
    
    if (aAcheter.length === 0) {
        note += "✅ Tout est en stock !";
    } else {
        aAcheter.forEach(p => {
            const status = p.stock === 0 ? "[RUPTURE]" : "[FAIBLE: " + p.stock + "]";
            note += status + " " + p.nom + " (" + p.cat_nom + ")\n";
        });
    }
    document.getElementById('exportTexte').textContent = note;
    window._noteValue = note;
}

function copierNote() {
    navigator.clipboard.writeText(window._noteValue).then(() => alert("Copié dans le presse-papier !"));
}

function telechargerNote() {
    const blob = new Blob([window._noteValue], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = 'liste_courses.txt'; a.click();
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>