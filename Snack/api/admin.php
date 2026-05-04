<?php require_once 'staff_protect.php'; 
require_once 'config.php'; 

header('Content-Type: text/html; charset=utf-8');
$db = getDB();

// --- 1. TRAITEMENT DES ACTIONS (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    try {
        // --- CATEGORIES ---
        if ($action === 'save_category') {
            if (!empty($_POST['id'])) {
                $stmt = $db->prepare("UPDATE categories SET nom = ?, ordre = ? WHERE id = ?");
                $stmt->execute([$_POST['nom'], $_POST['ordre'], $_POST['id']]);
            } else {
                $stmt = $db->prepare("INSERT INTO categories (nom, ordre) VALUES (?, ?)");
                $stmt->execute([$_POST['nom'], $_POST['ordre']]);
            }
        } 
        elseif ($action === 'delete_category') {
            $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$_POST['id']]);
        }
        // --- PRODUITS ---
        elseif ($action === 'save_product') {
            $dispo = isset($_POST['disponible']) ? 1 : 0;
            $image_nom = $_POST['current_image'] ?? null;
            $code_barre = $_POST['code_barre'] ?? null;
            $code_barre_pack = $_POST['code_barre_pack'] ?? null;
            $unites_par_pack = (int)($_POST['unites_par_pack'] ?? 1);

            if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === 0) {
                $ext = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                    $image_nom = bin2hex(random_bytes(8)) . "." . $ext;
                    move_uploaded_file($_FILES['image_file']['tmp_name'], "uploads/" . $image_nom);
                }
            }

            $stock = isset($_POST['stock']) ? (int)$_POST['stock'] : -1;
            if ($stock < -1) $stock = -1;

            if (!empty($_POST['id'])) {
            $stmt = $db->prepare("UPDATE produits SET categorie_id=?, nom=?, description=?, prix_normal=?, prix_adherent=?, disponible=?, image=?, stock=?, code_barre=?, code_barre_pack=?, unites_par_pack=? WHERE id=?");
            $stmt->execute([$_POST['categorie_id'], $_POST['nom'], $_POST['description'], $_POST['prix_normal'], $_POST['prix_adherent'], $dispo, $image_nom, $stock, $code_barre, $code_barre_pack, $unites_par_pack, $_POST['id']]);
            } else {
                $stmt = $db->prepare("INSERT INTO produits (categorie_id, nom, description, prix_normal, prix_adherent, disponible, image, stock, code_barre, code_barre_pack, unites_par_pack) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$_POST['categorie_id'], $_POST['nom'], $_POST['description'], $_POST['prix_normal'], $_POST['prix_adherent'], $dispo, $image_nom, $stock, $code_barre, $code_barre_pack, $unites_par_pack]);
            }
        }
        elseif ($action === 'delete_product') {
            $stmt = $db->prepare("DELETE FROM produits WHERE id = ?");
            $stmt->execute([$_POST['id']]);
        }
        // --- ADHERENTS ---
        elseif ($action === 'toggle_adherent') {
            $stmt = $db->prepare("UPDATE utilisateurs SET est_adherent = 1 - est_adherent WHERE discord_id = ?");
            $stmt->execute([$_POST['discord_id']]);
        }

        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// --- 2. RÉCUPÉRATION DES DONNÉES (Après traitement) ---
$categories = $db->query("SELECT * FROM categories ORDER BY ordre ASC")->fetchAll();
$produits = $db->query("SELECT p.*, c.nom as cat_nom FROM produits p LEFT JOIN categories c ON p.categorie_id = c.id ORDER BY c.ordre, p.nom")->fetchAll();
$users = $db->query("SELECT * FROM utilisateurs ORDER BY username ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Snack - Gestion</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <style>
        body { background-color: #f4f7f6; font-family: 'Segoe UI', sans-serif; }
        .card { border: none; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .table img { width: 50px; height: 50px; object-fit: cover; border-radius: 5px; background: #eee; }
        .btn-edit { color: #ffc107; cursor: pointer; font-size: 1.2rem; }
        .btn-delete { color: #dc3545; cursor: pointer; border: none; background: none; }
        #preview-img { width: 100px; height: 100px; object-fit: cover; border-radius: 10px; display: none; margin-top: 10px; border: 2px solid #ddd; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark mb-4">
    <div class="container">
        <span class="navbar-brand">Snack Admin</span>
        <div>
           <?php if (isset($user_roles) && in_array('admin', $user_roles)): ?>
                <a href="/core/public/admin/index.php" class="btn btn-outline-secondary btn-sm fw-bold d-inline-flex align-items-center me-3" style="color: #94a3b8; border-color: #475569;">
                    <span class="material-symbols-outlined" style="font-size: 18px; margin-right: 5px;">settings_accessibility</span>
                    Panel Core
                </a>
            <?php endif; ?>
            <a href="dashboard.php" class="btn btn-primary btn-sm fw-bold d-inline-flex align-items-center">
            <span class="material-symbols-outlined" style="font-size: 18px; margin-right: 5px;">assignment</span>
            Commandes
            </a>
            <a href="history.php" class="btn btn-light btn-sm fw-bold d-inline-flex align-items-center"><span class="material-symbols-outlined" style="font-size: 18px; margin-right: 5px;">
history
</span> Historique</a> 
            <a href="scanner.php" class="btn btn-secondary btn-sm fw-bold d-inline-flex align-items-center"><span class="material-symbols-outlined" style="font-size: 18px; margin-right: 5px;">
scan
</span> Scanner</a> 
            <a href="stats.php" class="btn btn-info btn-sm fw-bold text-white d-inline-flex align-items-center"><span class="material-symbols-outlined" style="font-size: 18px; margin-right: 5px;">
bar_chart
</span> Stats</a>
            <a href="restock.php" class="btn btn-warning btn-sm fw-bold d-inline-flex align-items-center"><span class="material-symbols-outlined" style="font-size: 18px; margin-right: 5px;">
inventory_2
</span> Stock</a>
        </div>
    </div>
</nav>

<div class="container">
    <?php if (isset($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

    <div class="row">
        <!-- COLONNE GAUCHE : CATEGORIES ET MEMBRES -->
        <div class="col-lg-4">
            <!-- CATEGORIES -->
            <div class="card">
                <div class="card-header bg-white d-flex align-items-center"> <span class="material-symbols-outlined me-2">category</span> Catégories</div>
                <div class="card-body">
                    <form method="POST" id="form-category">
                        <input type="hidden" name="action" value="save_category">
                        <input type="hidden" name="id" id="cat_id">
                        <input type="text" name="nom" id="cat_nom" class="form-control mb-2" placeholder="Nom" required>
                        <input type="number" name="ordre" id="cat_ordre" class="form-control mb-2" value="0">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100" id="btn-cat-submit">Ajouter</button>
                            <button type="button" onclick="resetCatForm()" class="btn btn-light btn-sm">X</button>
                        </div>
                    </form>
                    <hr>
                    <table class="table table-sm">
                        <?php foreach ($categories as $cat): ?>
<tr>
    <td><?= $cat['ordre'] ?></td>
    <td><?= htmlspecialchars($cat['nom']) ?></td>
    <td class="text-end">
        <!-- Bouton Modifier -->
        <span class="btn-edit me-2" onclick="editCategory(<?= htmlspecialchars(json_encode($cat)) ?>)"><span class="material-symbols-outlined">
edit
</span></span>
        
        <!-- NOUVEAU : Bouton Supprimer -->
        <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer cette catégorie ? Attention : cela supprimera aussi tous les produits à l\'intérieur !');">
            <input type="hidden" name="action" value="delete_category">
            <input type="hidden" name="id" value="<?= $cat['id'] ?>">
            <button type="submit" class="btn-delete" style="border:none; background:none; color:#dc3545;">
                <span class="material-symbols-outlined">delete</span>
            </button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
                    </table>
                </div>
            </div>

            <!-- MEMBRES -->
            <div class="card">
                <div class="card-header bg-white">Membres Discord</div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr><th>Pseudo</th><th>Statut</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($users as $u): ?>
                            <tr>
                                <td class="small"><?= htmlspecialchars($u['username']) ?></td>
                                <td>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="toggle_adherent">
                                        <input type="hidden" name="discord_id" value="<?= $u['discord_id'] ?>">
                                        <button type="submit" class="btn btn-sm <?= $u['est_adherent'] ? 'btn-success' : 'btn-outline-secondary' ?>">
                                            <?= $u['est_adherent'] ? 'Adhérent' : 'Visiteur' ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- COLONNE DROITE : PRODUITS -->
        <!-- COLONNE DROITE : PRODUITS -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-white d-flex align-items-center"><span class="material-symbols-outlined me-2">
cookie
</span>  Produits</div>
                <div class="card-body">
                    <form method="POST" id="form-product" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="save_product">
                        <input type="hidden" name="id" id="prod_id">
                        <input type="hidden" name="current_image" id="prod_current_image">
                        
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="small">Nom</label>
                                <input type="text" name="nom" id="prod_nom" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-6">
                                <label class="small">Catégorie</label>
                                <select name="categorie_id" id="prod_cat" class="form-select form-select-sm">
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>"><?= $cat['nom'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="small">Description</label>
                                <input type="text" name="description" id="prod_desc" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-4">
                                <label class="small">Prix Normal (€)</label>
                                <input type="number" step="0.01" name="prix_normal" id="prod_prix_n" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-4">
                                <label class="small">Prix Adhérent (€)</label>
                                <input type="number" step="0.01" name="prix_adherent" id="prod_prix_a" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-4">
                                <label class="small">Image</label>
                                <input type="file" name="image_file" class="form-control form-control-sm" accept="image/*">
                            </div>
                            
                            <!-- NOUVEAU : LES CODES BARRES ET STOCK DANS LA GRILLE -->
                            <div class="col-md-4">
                                <label class="small">Code-barre (Unité)</label>
                                <input type="text" name="code_barre" id="prod_code_barre" class="form-control form-control-sm" placeholder="Code unité">
                            </div>
                            <div class="col-md-4">
                                <label class="small">Code-barre (PACK)</label>
                                <input type="text" name="code_barre_pack" id="prod_code_barre_pack" class="form-control form-control-sm" placeholder="Code carton">
                            </div>
                            <div class="col-md-2">
                                <label class="small">Unités/Pack</label>
                                <input type="number" name="unites_par_pack" id="prod_unites_par_pack" class="form-control form-control-sm" value="1">
                            </div>
                            <div class="col-md-2">
                                <label class="small">
  Stock (<code>-1</code> = <span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">all_inclusive</span>)
</label>
                                <input type="number" name="stock" id="prod_stock" class="form-control form-control-sm" value="-1" min="-1">
                            </div>

                            <div class="col-12 text-center">
                                <img id="preview-img" src="">
                            </div>
                            
                            <div class="col-12">
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" name="disponible" id="prod_dispo" checked>
                                    <label class="form-check-label">Disponible à la vente</label>
                                </div>
                            </div>
                        </div> <!-- Fin du Row -->

                        <div class="mt-3">
                            <button type="submit" class="btn btn-success" id="btn-prod-submit">Enregistrer le produit</button>
                            <button type="button" onclick="resetProdForm()" class="btn btn-light">Annuler</button>
                        </div>
                    </form>
                </div>
            </div>

            
        </div>

            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Produit</th>
                                <th>Prix</th>
                                <th>Stock</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($produits as $p): ?>
                            <tr>
                                <td><img src="<?= $p['image'] ? 'uploads/'.$p['image'] : 'https://via.placeholder.com/50' ?>"></td>
                                <td>
                                    <strong><?= htmlspecialchars($p['nom']) ?></strong><br>
                                    <span class="badge bg-light text-dark"><?= htmlspecialchars($p['cat_nom']) ?></span>
                                </td>
                                <td>
                                    <span title="Prix Normal"><?= number_format($p['prix_normal'], 2) ?>€</span><br>
                                    <small class="text-success d-inline-flex align-items-center" title="Prix Adhérent">
    <span class="material-symbols-outlined me-1" style="font-size: 16px;">person</span>
    <?= number_format($p['prix_adherent'], 2) ?>€
</small>
                                </td>
                                                                <td>
                                <?php
                                    $s = (int)$p['stock'];
                                    if ($s === -1) {
                                        echo '<span class="badge bg-secondary d-inline-flex align-items-center">
        <span class="material-symbols-outlined me-1" style="font-size: 18px;">all_inclusive</span> 
        Illimité
      </span>';
                                    } elseif ($s === 0) {
                                        echo '<span class="badge bg-danger">Rupture</span>';
                                    } elseif ($s <= 5) {
                                        echo '<span class="badge bg-warning text-dark">' . $s . ' restant(s)</span>';
                                    } else {
                                        echo '<span class="badge bg-success">' . $s . ' en stock</span>';
                                    }
                                ?>
                                </td>
                                <td class="text-end">
                                    <span class="btn-edit me-2" onclick="editProduct(<?= htmlspecialchars(json_encode($p)) ?>)"><span class="material-symbols-outlined">
edit
</span></span>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ce produit ?');">
                                        <input type="hidden" name="action" value="delete_product">
                                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                        <button type="submit" class="btn-delete">
                                            <span class="material-symbols-outlined">delete</span>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function editCategory(data) {
        document.getElementById('cat_id').value = data.id;
        document.getElementById('cat_nom').value = data.nom;
        document.getElementById('cat_ordre').value = data.ordre;
        document.getElementById('btn-cat-submit').innerText = "Modifier";
    }
    function resetCatForm() { document.getElementById('form-category').reset(); document.getElementById('cat_id').value = ""; }

    function editProduct(data) {
        document.getElementById('prod_id').value = data.id;
        document.getElementById('prod_nom').value = data.nom;
        document.getElementById('prod_cat').value = data.categorie_id;
        document.getElementById('prod_desc').value = data.description;
        document.getElementById('prod_prix_n').value = data.prix_normal;
        document.getElementById('prod_prix_a').value = data.prix_adherent;
        document.getElementById('prod_dispo').checked = (data.disponible == 1);
        document.getElementById('prod_current_image').value = data.image || "";
        document.getElementById('prod_stock').value = (data.stock !== undefined) ? data.stock : -1;
        
        // CORRECTION : On remplit bien les 3 nouveaux champs
        document.getElementById('prod_code_barre').value = data.code_barre || "";
        document.getElementById('prod_code_barre_pack').value = data.code_barre_pack || "";
        document.getElementById('prod_unites_par_pack').value = data.unites_par_pack || 1;
        
        const preview = document.getElementById('preview-img');
        if(data.image) {
            preview.src = 'uploads/' + data.image;
            preview.style.display = 'inline-block';
        } else {
            preview.style.display = 'none';
        }
        document.getElementById('btn-prod-submit').innerText = "Mettre à jour";
        window.scrollTo(0, 0);
    }

    function resetProdForm() {
        document.getElementById('form-product').reset();
        document.getElementById('prod_id').value = "";
        document.getElementById('prod_stock').value = -1;
        document.getElementById('prod_unites_par_pack').value = 1;
        document.getElementById('preview-img').style.display = 'none';
        document.getElementById('btn-prod-submit').innerText = "Enregistrer le produit";
    }
</script>
</body>
</html>