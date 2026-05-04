<?php
require '../../server/api/config.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../login/index.html'); exit(); }

// 1. Récupération de mon profil (pour le bloc de liaison)
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$me = $stmt->fetch();

$stmt_roles = $pdo->prepare("
    SELECT r.name FROM roles r
    JOIN user_roles ur ON r.id = ur.role_id
    WHERE ur.user_id = ?
");
$stmt_roles->execute([$_SESSION['user_id']]);
$my_current_roles = $stmt_roles->fetchAll(PDO::FETCH_COLUMN);

// 2. Récupération des utilisateurs avec leurs rôles groupés
$users = $pdo->query("
    SELECT u.*, GROUP_CONCAT(r.id) as roles_ids
    FROM users u
    LEFT JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN roles r ON ur.role_id = r.id
    GROUP BY u.id
    ORDER BY u.id DESC
")->fetchAll();
if (!in_array('admin', $my_current_roles)) {
    // Mais que je suis staff ou vendeur du snack
    if (in_array('vendeur', $my_current_roles) || in_array('staff', $my_current_roles)) {
        header('Location: /Snack/api/admin.php');
        exit();
    } else {
        // Si l'utilisateur n'a aucun rôle autorisé, on le déconnecte ou on affiche une erreur
        die("Accès restreint : Vous n'avez pas les droits d'administration.");
    }
}

// 3. Récupération de tous les rôles existants
$all_roles = $pdo->query("SELECT * FROM roles ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logisiel - Admin Panel</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .sidebar-column { display: flex; flex-direction: column; gap: 20px; }
        .btn-link { text-decoration: none; padding: 8px 12px; border-radius: 6px; font-weight: bold; font-size: 0.85em; display: inline-flex; align-items: center; gap: 5px; transition: opacity 0.2s; }
        .btn-link:hover { opacity: 0.8; }
        .profile-card { background: var(--card); border-radius: 12px; padding: 20px; margin-bottom: 25px; border-left: 5px solid var(--primary); box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); }
    </style>
</head>
<body>

    <div class="container">
        <!-- ================= COLONNE GAUCHE ================= -->
        <div class="sidebar-column">
            <a href="/Snack/api/admin.php" style="background: #f59e0b; color: black; padding: 12px; border-radius: 8px; text-decoration: none; font-weight: bold; display: block; text-align: center;">
               🍔 Aller au Snack
            </a>

            <a href="action.php?action=logout" style="background: #ef4444; color: white; padding: 10px; border-radius: 8px; text-decoration: none; font-weight: bold; display: block; text-align: center;" onclick="return confirm('Déconnexion ?')">
               🚪 Déconnexion
            </a>

            <!-- GESTION DES RÔLES -->
            <aside class="sidebar">
                <h2>🎭 Rôles</h2>
                <form action="action.php?action=add_role" method="POST" style="display:flex; gap:5px;">
                    <input type="text" name="role_name" placeholder="Nom du rôle" required style="margin:0;">
                    <button type="submit" style="width: 45px;">+</button>
                </form>
                <div style="margin-top: 10px; display: flex; flex-wrap: wrap; gap: 5px;">
                    <?php foreach($all_roles as $r): ?>
                        <span class="badge" style="background:#e2e8f0; font-size: 0.7em;">
                            <?= htmlspecialchars($r['name']) ?>
                            <a href="action.php?action=delete_role&id=<?= $r['id'] ?>" style="color:var(--danger); margin-left:5px; text-decoration:none;">×</a>
                        </span>
                    <?php endforeach; ?>
                </div>
            </aside>

            <!-- AJOUT MANUEL -->
            <aside class="sidebar">
                <h2>Ajouter Membre</h2>
                <form action="action.php?action=add_user" method="POST">
                    <input type="text" name="username" placeholder="Nom complet" required>
                    <input type="email" name="email" placeholder="Email Google">
                    <input type="text" name="discord_id" placeholder="ID Discord">
                    <button type="submit">Enregistrer</button>
                </form>
            </aside>

            <!-- INVITATIONS -->
            <aside class="sidebar">
                <h2>🔗 Invitations</h2>
                <form action="action.php?action=generate_invite" method="POST" style="display:flex; gap:5px;">
                    <select name="role_invite" style="margin:0;">
                        <?php foreach($all_roles as $r): ?>
                            <option value="<?= $r['name'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" style="background: #6366f1; width: 60px;">OK</button>
                </form>

                <div style="margin-top: 15px; max-height: 200px; overflow-y: auto;">
                    <?php
                    $invites = [];
                    try { $invites = $pdo->query("SELECT * FROM invitations ORDER BY created_at DESC")->fetchAll(); } catch(Exception $e) {}
                    foreach($invites as $inv): 
                        $url = "http://" . $_SERVER['HTTP_HOST'] . "/core/public/login/invite.php?token=" . $inv['token'];
                    ?>
                        <div style="font-size: 0.7em; background: #f1f5f9; padding: 8px; border-radius: 6px; margin-bottom: 8px; border: 1px solid #e2e8f0;">
                            <strong>Rôle: <?= htmlspecialchars($inv['role_attribue']) ?></strong>
                            <a href="action.php?action=delete_invite&id=<?= $inv['id'] ?>" style="color:red; text-decoration:none; float:right;">🗑️</a>
                            <input type="text" value="<?= $url ?>" readonly onclick="this.select(); document.execCommand('copy'); alert('Lien copié !');" style="margin-top:5px; font-size: 0.8em; cursor: pointer;">
                        </div>
                    <?php endforeach; ?>
                </div>
            </aside>
        </div>

        <!-- ================= ZONE PRINCIPALE ================= -->
        <main>
            <!-- BLOC MON PROFIL (LIAISON) -->
            <div class="profile-card">
                <div class="user-header">
                    <img src="<?= $me['google_picture'] ?: ($me['discord_picture'] ?: 'https://via.placeholder.com/45') ?>" class="avatar">
                    <div>
                        <h2 style="margin:0; font-size: 1.2em;">Bonjour, <?= htmlspecialchars($me['username']) ?></h2>
                        <small style="color: #64748b;">Gérez vos accès et liaisons de compte</small>
                    </div>
                </div>
                
                <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 15px;">
                    <!-- Liaison Discord -->
                    <?php if (!$me['discord_id']): ?>
                        <a href="../../server/login/login_discord.php" class="btn-link" style="background: #7289da; color: white;">
                            💬 Lier mon compte Discord
                        </a>
                    <?php else: ?>
                        <span class="badge" style="background: #dcfce7; color: #166534; padding: 8px 12px; border: 1px solid #bbf7d0;">✅ Discord Lié</span>
                    <?php endif; ?>

                    <!-- Liaison Google -->
                    <?php if (!$me['google_id']): ?>
                        <a href="../../server/login/login_google.php" class="btn-link" style="background: #db4437; color: white;">
                            🌐 Lier mon compte Google
                        </a>
                    <?php else: ?>
                        <span class="badge" style="background: #dcfce7; color: #166534; padding: 8px 12px; border: 1px solid #bbf7d0;">✅ Google Lié</span>
                    <?php endif; ?>
                </div>
            </div>

            <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin:0;">Utilisateurs Enregistrés (<?= count($users) ?>)</h2>
            </div>

            <div class="user-grid">
                <?php foreach($users as $user): ?>
                    <div class="user-card">
                        <?php if($user['id'] == $me['id']): ?>
                            <span class="me-badge">VOUS</span>
                        <?php endif; ?>

                        <div class="user-header">
                            <img src="<?= $user['google_picture'] ?: ($user['discord_picture'] ?: 'https://via.placeholder.com/45') ?>" class="avatar">
                            <div>
                                <strong><?= htmlspecialchars($user['username']) ?></strong><br>
                                <small style="color:#64748b;">ID: #<?= $user['id'] ?></small>
                            </div>
                        </div>

                        <!-- Gestion des rôles -->
                        <div style="margin-top: 10px; border-top: 1px dashed #ddd; padding-top: 10px;">
                            <span style="font-size: 0.8em; color: #64748b; font-weight: bold;">🎭 Rôles attribués :</span>
                            <form action="action.php?action=update_roles&id=<?= $user['id'] ?>" method="POST" style="margin-top: 5px;">
                                <div style="display: flex; flex-wrap: wrap; gap: 5px;">
                                    <?php 
                                    $my_roles = explode(',', $user['roles_ids'] ?? ''); 
                                    foreach($all_roles as $role): 
                                    ?>
                                        <label style="font-size: 0.75em; background: #f1f5f9; padding: 2px 8px; border-radius: 4px; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                                            <input type="checkbox" name="roles[]" value="<?= $role['id'] ?>" 
                                                onchange="this.form.submit()"
                                                <?= in_array($role['id'], $my_roles) ? 'checked' : '' ?>>
                                            <?= htmlspecialchars($role['name']) ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </form>
                        </div>

                        <div class="auth-status" style="margin-top: 15px;">
                            <div class="badge">🌐 Google: <?= $user['google_id'] ? '<span class="linked">Lié</span>' : '<span class="unlinked">Non lié</span>' ?></div>
                            <div class="badge">💬 Discord: <?= $user['discord_id'] ? '<span class="linked">Lié</span>' : '<span class="unlinked">Non lié</span>' ?></div>
                        </div>

                        <div class="action-links">
                            <a href="action.php?action=delete_user&id=<?= $user['id'] ?>" class="btn-link btn-del" style="width: 100%; text-align: center; justify-content: center;" onclick="return confirm('Attention ! Supprimer cet utilisateur ?')">
                                🗑️ Supprimer l'utilisateur
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>

    <!-- LOGIQUE WASM -->
    <script type="module">
        import createModule from '../js/wasm/logic_engine.js';
        async function initWasm() {
            try {
                const Module = await createModule();
                console.log("C++ Engine Ready.");
            } catch (e) { console.error("WASM Error:", e); }
        }
        initWasm();
    </script>
</body>
</html>