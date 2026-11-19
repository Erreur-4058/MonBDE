<?php 
require_once 'staff_protect.php'; 
require_once 'config.php'; 

header('Content-Type: text/html; charset=utf-8');
$db = getDB();

// --- 1. TRAITEMENT DES ACTIONS (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    try {
        // --- ÉVÉNEMENT SPÉCIAL ---
        if ($action === 'save_special') {
            $active = isset($_POST['special_active']) ? '1' : '0';
            $db->prepare("REPLACE INTO config (cle, valeur) VALUES ('special_active', ?)")->execute([$active]);
            $db->prepare("INSERT OR REPLACE INTO config (cle, valeur) VALUES ('special_title', ?)")->execute([$_POST['special_title']]);
            $db->prepare("INSERT OR REPLACE INTO config (cle, valeur) VALUES ('special_category_id', ?)")->execute([$_POST['special_category_id']]);
            $db->prepare("INSERT OR REPLACE INTO config (cle, valeur) VALUES ('special_badge_text', ?)")->execute([$_POST['special_badge_text']]);
        }
        // --- CATEGORIES ---
        elseif ($action === 'save_category') {
            if (!empty($_POST['id'])) {
                $db->prepare("UPDATE categories SET nom = ?, ordre = ? WHERE id = ?")->execute([$_POST['nom'], $_POST['ordre'], $_POST['id']]);
            } else {
                $db->prepare("INSERT INTO categories (nom, ordre) VALUES (?, ?)")->execute([$_POST['nom'], $_POST['ordre']]);
            }
        }
        elseif ($action === 'delete_category') {
            $db->prepare("DELETE FROM categories WHERE id = ?")->execute([$_POST['id']]);
        }
        // --- CONFIGURATION COMMANDS (salle + ouverture) ---
        elseif ($action === 'save_command_config') {
            $active = isset($_POST['show_salle']) ? '1' : '0';
            $db->prepare("REPLACE INTO config (cle, valeur) VALUES ('show_salle', ?)")->execute([$active]);
            $orders_open = isset($_POST['orders_open']) ? '1' : '0';
            $db->prepare("REPLACE INTO config (cle, valeur) VALUES ('orders_open', ?)")->execute([$orders_open]);
        }

        header("Location: settings.php?success=1");
        exit;

    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// --- TOGGLE CATEGORY VISIBILITY (GET) ---
if (isset($_GET['action']) && $_GET['action'] === 'toggle_category_visibility') {
    $id = (int)$_GET['id'];
    $db->prepare("UPDATE categories SET visible = 1 - IFNULL(visible, 1) WHERE id = ?")->execute([$id]);
    header("Location: settings.php?success=visibility_updated");
    exit;
}

// --- 2. RÉCUPÉRATION DES DONNÉES ---
$spec = [];
try {
    $res_spec = $db->query("SELECT * FROM config WHERE cle LIKE 'special_%'")->fetchAll();
    foreach($res_spec as $s) { $spec[$s['cle']] = $s['valeur']; }
} catch(Exception $e) {}

$cmd_config = [];
try {
    $res_cmd = $db->query("SELECT * FROM config WHERE cle IN ('show_salle', 'orders_open')")->fetchAll();
    foreach($res_cmd as $c) { $cmd_config[$c['cle']] = $c['valeur']; }
} catch(Exception $e) {}

$show_salle = ($cmd_config['show_salle'] ?? '0') == '1';
$orders_open = ($cmd_config['orders_open'] ?? '1') == '1';

$categories = $db->query("SELECT * FROM categories ORDER BY ordre ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Snack Admin - Paramètres</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <style>
        body { background-color: #f4f7f6; font-family: 'Segoe UI', sans-serif; }
        .card { border: none; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 20px; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark mb-4">
    <div class="container">
        <span class="navbar-brand">Snack Admin - Paramètres</span>
        <a href="admin.php" class="btn btn-outline-light btn-sm">retour admin</a>
    </div>
</nav>

<div class="container">
    <?php if (isset($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

    <div class="row">
        <!-- GAUCHE : Événement Spécial -->
        <div class="col-lg-8">
            <div class="card border-primary">
                <div class="card-header bg-primary text-white d-flex align-items-center">
                    <span class="material-symbols-outlined me-2">celebration</span> Événement Spécial
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="save_special">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="special_active" <?= ($spec['special_active'] ?? '0') == '1' ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold">Activer l'événement</label>
                        </div>
                        <label class="small">Nom de l'événement (ex: Crêpe Party)</label>
                        <input type="text" name="special_title" class="form-control form-control-sm mb-2" value="<?= htmlspecialchars($spec['special_title'] ?? '') ?>">
                        
                        <label class="small">Catégorie concernée</label>
                        <select name="special_category_id" class="form-select form-select-sm mb-2">
                            <?php foreach($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ($spec['special_category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <label class="small">Description</label>
                        <input type="text" name="special_badge_text" class="form-control form-control-sm mb-3" placeholder="Petit texte badge" value="<?= htmlspecialchars($spec['special_badge_text'] ?? '') ?>">

                        <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold">Mettre à jour l'événement</button>
                    </form>
                </div>
            </div>

            <div class="card border-primary mt-3">
                <div class="card-header bg-primary text-white py-1 px-2 d-flex align-items-center">
                    <span class="material-symbols-outlined me-1" style="font-size:18px;">room</span> Config Commande
                </div>
                <div class="card-body p-2">
                    <form method="POST">
                        <input type="hidden" name="action" value="save_command_config">
                        
                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input" type="checkbox" name="show_salle" <?= $show_salle ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold small">Afficher le champ "Salle"</label>
                        </div>

                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="orders_open" id="orders_open_check" <?= $orders_open ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold small" for="orders_open_check">Commandes ouvertes</label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-sm w-100 mt-1">Mettre à jour</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
