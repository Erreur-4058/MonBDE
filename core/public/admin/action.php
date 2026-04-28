<?php
require '../../server/api/config.php';
if (!isset($_SESSION['user_id'])) die("Non autorisé");

$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);

// --- NOUVEAUX BLOCS POUR LES RÔLES ---
if ($action == 'add_role') {
    $role_name = strtolower(trim($_POST['role_name']));
    if (!empty($role_name)) {
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO roles (name) VALUES (?)");
        $stmt->execute([$role_name]);
    }
}
if ($action == 'delete_role') {
    $stmt = $pdo->prepare("DELETE FROM roles WHERE id = ?");
    $stmt->execute([$id]);
}
if ($action == 'update_roles') {
    $roles = $_POST['roles'] ?? []; // Tableau d'IDs de rôles cochés
    
    // 1. On supprime tous les rôles actuels de l'utilisateur
    $stmt = $pdo->prepare("DELETE FROM user_roles WHERE user_id = ?");
    $stmt->execute([$id]);

    // 2. On ajoute les nouveaux rôles cochés
    if (!empty($roles)) {
        $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
        foreach ($roles as $role_id) {
            $stmt->execute([$id, (int)$role_id]);
        }
    }
}


if ($action == 'add_user') {
    $name = $_POST['username'];
    // On récupère les deux champs, s'ils sont vides on met NULL
    $email = !empty($_POST['email']) ? $_POST['email'] : null;
    $discord_id = !empty($_POST['discord_id']) ? $_POST['discord_id'] : null;

    if (!$email && !$discord_id) {
        die("Erreur : Vous devez remplir au moins l'Email ou l'ID Discord.");
    }

    // On insère l'utilisateur avec les infos fournies
    $stmt = $pdo->prepare("INSERT INTO users (username, google_email, discord_id) VALUES (?, ?, ?)");
    $stmt->execute([$name, $email, $discord_id]);
    
    header('Location: index.php?success=user_added');
    exit();
}

if ($action == 'delete_user') {
    // Supprime le compte entier (et donc les liens automatiquement)
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);
    // Si c'est nous-même qu'on supprime, on déconnecte
    if ($id == $_SESSION['user_id']) { session_destroy(); header('Location: ../login/index.html'); exit(); }
}

if ($action == 'unlink_google') {
    $stmt = $pdo->prepare("UPDATE users SET google_id = NULL, google_email = NULL, google_picture = NULL WHERE id = ?");
    $stmt->execute([$id]);
}

if ($action == 'unlink_discord') {
    $stmt = $pdo->prepare("UPDATE users SET discord_id = NULL, discord_email = NULL, discord_picture = NULL WHERE id = ?");
    $stmt->execute([$id]);
}

header('Location: index.php');
exit();