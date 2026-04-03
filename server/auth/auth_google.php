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
    // CAS 1 : L'utilisateur est DÉJÀ CONNECTÉ (il clique sur "Lier Google")
    // On met à jour le compte existant (celui qui a été créé via Discord ID par exemple)
    $stmt = $pdo->prepare("UPDATE users SET google_id = ?, google_email = ?, google_picture = ? WHERE id = ?");
    $stmt->execute([$google_id, $google_email, $google_picture, $_SESSION['user_id']]);
    
    header('Location: ../../public/admin/index.php?success=google_linked');
    exit();

} else {
    // CAS 2 : Tentative de CONNEXION classique (Login)
    $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ? OR google_email = ?");
    $stmt->execute([$google_id, $google_email]);
    $user = $stmt->fetch();

    if ($user) {
        // On connecte l'utilisateur
        $_SESSION['user_id'] = $user['id'];
        
        // On met à jour ses infos au cas où
        $update = $pdo->prepare("UPDATE users SET google_id = ?, google_picture = ? WHERE id = ?");
        $update->execute([$google_id, $google_picture, $user['id']]);
        
        header('Location: ../../public/admin/index.php');
        exit();
    } else {
        die("Accès refusé : L'administrateur ne vous a pas encore ajouté (Email: $google_email).");
    }
}