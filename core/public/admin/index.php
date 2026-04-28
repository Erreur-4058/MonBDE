<?php
require '../../server/api/config.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../login/index.html'); exit(); }

// Récupération de mon profil
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$me = $stmt->fetch();

// Récupération des utilisateurs et des rôles
// Récupération des utilisateurs avec leurs rôles groupés
$users = $pdo->query("
    SELECT u.*, GROUP_CONCAT(r.id) as roles_ids
    FROM users u
    LEFT JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN roles r ON ur.role_id = r.id
    GROUP BY u.id
    ORDER BY u.id DESC
")->fetchAll();

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

    </style>
</head>
<body>

 

    <div class="container">
        <a href="/Snack/api/admin.php" style="background: #f59e0b; color: black; padding: 10px; border-radius: 5px; text-decoration: none; font-weight: bold;">
   🍔 Aller au Snack
</a>
<aside class="sidebar" style="margin-bottom: 20px;">
    <h2>🎭 Gérer les Rôles</h2>
    <form action="action.php?action=add_role" method="POST" style="display:flex; gap:5px;">
        <input type="text" name="role_name" placeholder="Nom du rôle (ex: staff)" required style="margin:0;">
        <button type="submit" style="width: 50px;">+</button>
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
        <aside class="sidebar">
    <h2>Ajouter un Membre</h2>
    <p style="font-size: 0.85em; color: #64748b;">Inscrivez manuellement un utilisateur via son mail ou son ID Discord.</p>
    
    <form action="action.php?action=add_user" method="POST">
        <label>Nom complet</label>
        <input type="text" name="username" placeholder="ex: Jean Dupont" required>
        
        <label>Email (pour connexion Google)</label>
        <input type="email" name="email" placeholder="email@gmail.com">
        
        <label>ID Discord (Numérique)</label>
        <input type="text" name="discord_id" placeholder="ex: 1485358310503878848">
        
        <p style="font-size: 0.75em; color: #94a3b8; margin-top: -5px;">Note: Laissez vide si vous ne connaissez pas l'un des deux. L'utilisateur pourra lier le reste plus tard.</p>
        
        <button type="submit">Enregistrer l'utilisateur</button>
    </form>
</aside>

        <!-- Main : Liste -->
        <main>
            <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin:0;">Utilisateurs Enregistrés (<?php echo count($users); ?>)</h2>
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

                        <!-- Gestion multi-rôles -->
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

                        <div class="auth-status">
                            <div class="badge">🌐 Google: <?= $user['google_id'] ? '<span class="linked">Lié</span>' : '<span class="unlinked">Non lié</span>' ?></div>
                            <div class="badge">💬 Discord: <?= $user['discord_id'] ? '<span class="linked">Lié</span>' : '<span class="unlinked">Non lié</span>' ?></div>
                        </div>

                        <div class="action-links">
                            <a href="action.php?action=delete_user&id=<?= $user['id'] ?>" class="btn-link btn-del" style="width: 100%; text-align: center;" onclick="return confirm('Attention !')">
                                🗑️ Supprimer l'utilisateur
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>

    <!-- LOGIQUE WASM (ES6 Module) -->
    <script type="module">
        import createModule from '../js/wasm/logic_engine.js';

        async function initWasm() {
            try {
                const Module = await createModule();
                const userName = "<?php echo addslashes($me['username']); ?>";
                const userRole = "<?php echo addslashes($me['role']); ?>";
                
                // Appel de la fonction C++
                const message = Module.get_welcome_message(userName, userRole);
                document.getElementById('wasm-msg').innerText = "⚡ " + message;
                
                console.log("C++ Engine Ready. Version:", Module.get_version());
            } catch (e) {
                console.error("WASM Error:", e);
                document.getElementById('wasm-msg').innerText = "❌ Erreur critique du moteur C++";
            }
        }
        initWasm();
    </script>
</body>
</html>