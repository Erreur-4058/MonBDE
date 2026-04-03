<?php
require '../../server/api/config.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../login/index.html'); exit(); }

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$me = $stmt->fetch();
$users = $pdo->query("SELECT * FROM users ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logisiel - Admin Panel</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        :root {
            --primary: #2563eb;
            --danger: #ef4444;
            --success: #22c55e;
            --bg: #f8fafc;
            --card: #ffffff;
            --text: #1e293b;
        }

        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--bg); color: var(--text); margin: 0; display: flex; flex-direction: column; min-height: 100vh; }
        
        /* Barre de statut WASM */
        #wasm-header { background: #1e293b; color: #f1f5f9; padding: 12px 24px; font-size: 0.9em; display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid var(--primary); }
        .engine-badge { background: var(--primary); padding: 4px 10px; border-radius: 4px; font-weight: bold; font-size: 0.8em; }

        .container { display: grid; grid-template-columns: 350px 1fr; gap: 24px; padding: 24px; max-width: 1400px; margin: 0 auto; width: 100%; box-sizing: border-box; }

        /* Colonne de gauche : Formulaire */
        .sidebar { background: var(--card); padding: 24px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); height: fit-content; }
        h2 { margin-top: 0; font-size: 1.25rem; color: var(--primary); }
        input, select, button { width: 100%; padding: 10px; margin: 8px 0; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; }
        button { background: var(--primary); color: white; border: none; font-weight: bold; cursor: pointer; transition: opacity 0.2s; }
        button:hover { opacity: 0.9; }

        /* Grille d'utilisateurs */
        .user-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; }
        .user-card { background: var(--card); border-radius: 12px; padding: 20px; box-shadow: 0 2px 4px rgb(0 0 0 / 0.05); border: 1px solid #e2e8f0; position: relative; }
        .user-header { display: flex; align-items: center; gap: 12px; margin-bottom: 15px; }
        .user-header strong { font-size: 1.1em; }
        
        .avatar { width: 45px; height: 45px; border-radius: 50%; border: 2px solid #eee; }
        .no-avatar { width: 45px; height: 45px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 20px; }

        /* Badges de liaison */
        .auth-status { display: flex; flex-direction: column; gap: 8px; font-size: 0.85em; }
        .badge { display: flex; align-items: center; gap: 8px; padding: 8px; border-radius: 6px; background: #f1f5f9; }
        .linked { color: var(--success); font-weight: 600; }
        .unlinked { color: #94a3b8; }
        
        .action-links { margin-top: 15px; padding-top: 15px; border-top: 1px solid #f1f5f9; display: flex; flex-wrap: wrap; gap: 10px; }
        .btn-link { font-size: 0.8em; text-decoration: none; padding: 4px 8px; border-radius: 4px; background: #f1f5f9; color: var(--text); }
        .btn-del { color: var(--danger); background: #fee2e2; }
        
        .me-badge { position: absolute; top: 10px; right: 10px; background: #dcfce7; color: #166534; padding: 2px 8px; border-radius: 10px; font-size: 0.7em; font-weight: bold; }
    </style>
</head>
<body>

    <div id="wasm-header">
        <span id="wasm-msg">Initialisation du moteur...</span>
        <span class="engine-badge">CORE WASM v1.0</span>
    </div>

    <div class="container">
        <!-- Sidebar : Ajout -->
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
                            <?php if($user['google_picture']): ?>
                                <img src="<?php echo $user['google_picture']; ?>" class="avatar">
                            <?php elseif($user['discord_picture']): ?>
                                <img src="<?php echo $user['discord_picture']; ?>" class="avatar">
                            <?php else: ?>
                                <div class="no-avatar">👤</div>
                            <?php endif; ?>
                            <div>
                                <strong><?php echo htmlspecialchars($user['username']); ?></strong><br>
                                <small style="color:#64748b;">ID: #<?php echo $user['id']; ?></small>
                            </div>
                        </div>

                        <div class="auth-status">
                            <!-- Google -->
                            <div class="badge">
                                <span>🌐 Google:</span>
                                <?php if($user['google_id']): ?>
                                    <span class="linked">Lié</span>
                                    <a href="action.php?action=unlink_google&id=<?php echo $user['id']; ?>" class="btn-link btn-del">Délier</a>
                                <?php else: ?>
                                    <span class="unlinked">Non lié</span>
                                    <?php if($user['id'] == $me['id']): ?>
                                        <a href="../../server/login/login_google.php" class="btn-link">Lier maintenant</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <!-- Discord -->
                            <div class="badge">
                                <span>💬 Discord:</span>
                                <?php if($user['discord_id']): ?>
                                    <span class="linked">Lié</span>
                                    <a href="action.php?action=unlink_discord&id=<?php echo $user['id']; ?>" class="btn-link btn-del">Délier</a>
                                <?php else: ?>
                                    <span class="unlinked">Non lié</span>
                                    <?php if($user['id'] == $me['id']): ?>
                                        <a href="../../server/login/login_discord.php" class="btn-link">Lier maintenant</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="action-links">
                            <a href="action.php?action=delete_user&id=<?php echo $user['id']; ?>" 
                               class="btn-link btn-del" 
                               style="width: 100%; text-align: center; font-weight: bold;"
                               onclick="return confirm('Attention: suppression définitive !')">
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