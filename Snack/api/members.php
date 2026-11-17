<?php 
require_once 'staff_protect.php'; 
require_once 'config.php'; 

header('Content-Type: text/html; charset=utf-8');
$db = getDB();

// --- 1. TRAITEMENT DES ACTIONS (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    try {
        if ($action === 'toggle_adherent') {
            $db->prepare("UPDATE utilisateurs SET est_adherent = 1 - est_adherent WHERE discord_id = ?")->execute([$_POST['discord_id']]);
        }
        header("Location: members.php?success=1");
        exit;
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// --- 2. RÉCUPÉRATION DES DONNÉES ---
$users = $db->query("SELECT * FROM utilisateurs ORDER BY username ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Snack Admin - Membres</title>
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
        <span class="navbar-brand">Snack Admin - Membres</span>
        <a href="admin.php" class="btn btn-outline-light btn-sm">retour admin</a>
    </div>
</nav>

<div class="container">
    <?php if (isset($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

    <div class="card">
        <div class="card-header bg-white d-flex align-items-center justify-content-between">
            <span><span class="material-symbols-outlined me-2">person</span> Membres Discord (<?= count($users) ?>)</span>
            <a href="settings.php" class="btn btn-sm btn-outline-secondary">Paramètres</a>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Pseudo</th><th>Discord ID</th><th>Statut</th></tr>
                </thead>
                <tbody>
                    <?php foreach($users as $u): ?>
                    <tr>
                        <td class="small"><?= htmlspecialchars($u['username']) ?></td>
                        <td class="small text-muted"><?= htmlspecialchars(substr($u['discord_id'], 0, 15)) ?><?= strlen($u['discord_id']) > 15 ? '...' : '' ?></td>
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

</body>
</html>
