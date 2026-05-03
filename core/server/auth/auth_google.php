<?php
require '../api/config.php'; // Contient session_start() et la connexion PDO

if (!isset($_GET['code'])) die("Erreur d'authentification Google");

// 1. Échange du code contre un Access Token
$ch = curl_init("https://oauth2.googleapis.com/token");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'code'          => $_GET['code'],
    'client_id'     => G_ID,
    'client_secret' => G_SECRET,
    'redirect_uri'  => G_REDIRECT,
    'grant_type'    => 'authorization_code'
]));
$response = json_decode(curl_exec($ch), true);

if (!isset($response['access_token'])) {
    die("Erreur Google : Impossible de récupérer le token.");
}

// 2. Récupération des infos du profil
$ch = curl_init("https://www.googleapis.com/oauth2/v3/userinfo");
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $response['access_token']]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$userinfo = json_decode(curl_exec($ch), true);

$google_id = $userinfo['sub'];
$google_email = $userinfo['email'];
$google_picture = $userinfo['picture'];

// --- LOGIQUE DE LIAISON OU CONNEXION ---

if (isset($_SESSION['user_id'])) {
    // CAS 1 : L'utilisateur est déjà connecté (LIAISON)
    
    // Vérifier si ce compte Google n'appartient pas déjà à quelqu'un d'autre
    $check = $pdo->prepare("SELECT id FROM users WHERE google_id = ? AND id != ?");
    $check->execute([$google_id, $_SESSION['user_id']]);
    if ($check->fetch()) {
        die("Erreur : Ce compte Google est déjà lié à un autre utilisateur.");
    }

    $stmt = $pdo->prepare("UPDATE users SET google_id = ?, google_email = ?, google_picture = ? WHERE id = ?");
    $stmt->execute([$google_id, $google_email, $google_picture, $_SESSION['user_id']]);
    
    header('Location: ../../public/admin/index.php?success=google_linked');
    exit();
}else {
    // CAS 2 : Tentative de CONNEXION
    $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ? OR google_email = ?");
    $stmt->execute([$google_id, $google_email]);
    $user = $stmt->fetch();

    if ($user) {
        // Connexion normale...
        $_SESSION['user_id'] = $user['id'];
        header('Location: ../../public/admin/index.php');
        exit();
    } 
    // NOUVEAU : Si l'utilisateur n'existe pas MAIS qu'il a un token d'invitation
    elseif (isset($_SESSION['pending_invite_token'])) {
        
        // 1. Créer l'utilisateur
        $stmt = $pdo->prepare("INSERT INTO users (username, google_id, google_email, google_picture) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userinfo['name'], $google_id, $google_email, $google_picture]);
        $new_user_id = $pdo->lastInsertId();

        // 2. Lui attribuer son rôle d'invité
        $role_name = $_SESSION['pending_invite_role'];
        $role_stmt = $pdo->prepare("SELECT id FROM roles WHERE name = ?");
        $role_stmt->execute([$role_name]);
        $role_id = $role_stmt->fetchColumn();

        if ($role_id) {
            $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
            $stmt->execute([$new_user_id, $role_id]);
        }

        // 3. Supprimer l'invitation de la DB et de la session
        $stmt = $pdo->prepare("DELETE FROM invitations WHERE token = ?");
        $stmt->execute([$_SESSION['pending_invite_token']]);
        unset($_SESSION['pending_invite_token']);
        unset($_SESSION['pending_invite_role']);

        // 4. Connecter
        $_SESSION['user_id'] = $new_user_id;
        header('Location: ../../public/admin/index.php?success=account_created');
        exit();
    }
    else {
        die("Accès refusé : Vous n'avez pas d'invitation.");
    }
}