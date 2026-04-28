<?php require_once 'staff_protect.php'; 
// api/stats.php
require_once 'config.php';
header('Content-Type: text/html; charset=utf-8');
$db = getDB();

// 1. GESTION DES DATES (Filtre)
$date_debut = $_GET['debut'] ?? date('Y-m-d', strtotime('-7 days'));
$date_fin   = $_GET['fin'] ?? date('Y-m-d');

// 2. RÉSUMÉ GLOBAL
$stmt = $db->prepare("
    SELECT 
        COUNT(id) as nb_commandes,
        SUM(total) as ca_total,
        SUM(CASE WHEN paiement = 'especes' THEN total ELSE 0 END) as ca_especes,
        SUM(CASE WHEN paiement LIKE 'sumup%' THEN total ELSE 0 END) as ca_sumup,
        SUM(CASE WHEN est_adherent = 1 THEN 1 ELSE 0 END) as nb_adh
    FROM commandes 
    WHERE statut = 'paye' 
    AND date(created_at) BETWEEN ? AND ?
");
$stmt->execute([$date_debut, $date_fin]);
$global = $stmt->fetch();

// 3. TOP PRODUITS (Les plus vendus)
$stmt = $db->prepare("
    SELECT 
        nom_produit, 
        SUM(quantite) as qte_totale,
        SUM(sous_total) as ca_produit
    FROM commande_lignes cl
    JOIN commandes c ON cl.commande_id = c.id
    WHERE c.statut = 'paye'
    AND date(c.created_at) BETWEEN ? AND ?
    GROUP BY nom_produit
    ORDER BY qte_totale DESC
");
$stmt->execute([$date_debut, $date_fin]);
$top_produits = $stmt->fetchAll();

// 4. VENTES PAR CATÉGORIE
$stmt = $db->prepare("
    SELECT 
        cat.nom as categorie,
        SUM(cl.quantite) as qte
    FROM commande_lignes cl
    JOIN commandes c ON cl.commande_id = c.id
    LEFT JOIN produits p ON cl.produit_id = p.id
    LEFT JOIN categories cat ON p.categorie_id = cat.id
    WHERE c.statut = 'paye'
    AND date(c.created_at) BETWEEN ? AND ?
    GROUP BY cat.nom
");
$stmt->execute([$date_debut, $date_fin]);
$categories_stats = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Snack - Statistiques</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />

    <style>
        body { background: #f4f7f6; font-family: 'Segoe UI', sans-serif; }
        .stat-card { border: none; border-radius: 15px; transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-5px); }
        .icon-box { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .bg-ca { background: rgba(34, 197, 94, 0.1); color: #166534; }
        .bg-cmd { background: rgba(59, 130, 246, 0.1); color: #1e40af; }
        .bg-adh { background: rgba(168, 85, 247, 0.1); color: #6b21a8; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark mb-4">
    <div class="container">
        <span class="navbar-brand">Analyse des Ventes</span>
        <a href="admin.php" class="btn btn-outline-light btn-sm">Retour Admin</a>
    </div>
</nav>

<div class="container">
    
    <!-- FILTRE -->
    <div class="card mb-4 shadow-sm border-0">
        <div class="card-body">
            <form class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Date de début</label>
                    <input type="date" name="debut" class="form-control" value="<?= $date_debut ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Date de fin</label>
                    <input type="date" name="fin" class="form-control" value="<?= $date_fin ?>">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100 fw-bold">Filtrer l'analyse</button>
                </div>
            </form>
        </div>
    </div>

    <!-- CHIFFRES CLÉS -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card stat-card shadow-sm p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="icon-box bg-ca"><span class="material-symbols-outlined">
savings
</span></div>
                    <div>
                        <div class="text-muted small">C.A Total</div>
                        <h3 class="mb-0 fw-bold"><?= number_format($global['ca_total'] ?? 0, 2) ?>€</h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card shadow-sm p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="icon-box bg-cmd"><span class="material-symbols-outlined">
box
</span></div>
                    <div>
                        <div class="text-muted small">Commandes</div>
                        <h3 class="mb-0 fw-bold"><?= $global['nb_commandes'] ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card shadow-sm p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="icon-box bg-adh"><span class="material-symbols-outlined">
person
</span></div>
                    <div>
                        <div class="text-muted small">Adhérents</div>
                        <h3 class="mb-0 fw-bold"><?= $global['nb_adh'] ?> <small style="font-size:0.5em" class="text-muted">ventes</small></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card shadow-sm p-3">
                <div class="text-muted small mb-2">Modes de paiement</div>
                <div class="d-flex justify-content-between align-items-center small mb-1">
    <span class="d-flex align-items-center">
        <span class="material-symbols-outlined me-1" style="font-size: 18px;">payments</span> 
        Espèces :
    </span> 
    <strong><?= number_format($global['ca_especes'] ?? 0, 2) ?>€</strong>
</div>
                <div class="d-flex justify-content-between align-items-center small">
    <span class="d-flex align-items-center">
        <span class="material-symbols-outlined me-1" style="font-size: 18px;">credit_card</span> 
        SumUp :
    </span> 
    <strong><?= number_format($global['ca_sumup'] ?? 0, 2) ?>€</strong>
</div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- TABLEAU TOP PRODUITS -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-bold d-flex align-items-center">
        <span class="material-symbols-outlined me-2 text-danger">local_fire_department</span>
        Top des ventes
    </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Produit</th>
                                <th class="text-center">Ventes</th>
                                <th class="text-end">C.A Généré</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($top_produits as $tp): ?>
                            <tr>
                                <td class="fw-semibold"><?= htmlspecialchars($tp['nom_produit']) ?></td>
                                <td class="text-center"><span class="badge bg-primary rounded-pill"><?= $tp['qte_totale'] ?></span></td>
                                <td class="text-end fw-bold"><?= number_format($tp['ca_produit'], 2) ?>€</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- RÉPARTITION PAR CATÉGORIE -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-bold d-flex align-items-center">
        <span class="material-symbols-outlined me-2 text-primary">folder</span>
        Par catégorie
    </div>
</div>
                <div class="card-body">
                    <?php if (empty($categories_stats)): ?>
                        <p class="text-muted text-center">Aucune donnée</p>
                    <?php endif; ?>
                    <?php foreach($categories_stats as $cs): ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between small mb-1">
                                <span><?= htmlspecialchars($cs['categorie'] ?? 'Sans catégorie') ?></span>
                                <span class="fw-bold"><?= $cs['qte'] ?></span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-info" style="width: <?= min(100, $cs['qte'] * 5) ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>