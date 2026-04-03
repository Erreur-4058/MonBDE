<?php
require '../api/config.php';

if (!isset($_GET['code'])) die("Erreur de connexion Discord");

// 1. Récupération du Token
$ch = curl_init("https://discord.com/api/oauth2/token");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'client_id'     => D_ID,
    'client_secret' => D_SECRET,
    'grant_type'    => 'authorization_code',
    'code'          => $_GET['code'],
    'redirect_uri'  => D_REDIRECT
]));
$response = json_decode(curl_exec($ch), true);

if (!isset($response['access_token'])) {
    die("Erreur Discord : Impossible de récupérer le token.");
}

// 2. Récupération des infos utilisateur (@me)
$ch = curl_init("https://discord.com/api/users/@me");
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $response['access_token']]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$userinfo = json_decode(curl_exec($ch), true);

$discord_id = $userinfo['id'];
$discord_email = $userinfo['email'];
$avatar_hash = $userinfo['avatar'];
$avatar_url = "https://cdn.discordapp.com/avatars/{$discord_id}/{$avatar_hash}.png";

// --- LOGIQUE DE LIAISON OU CONNEXION ---

if (isset($_SESSION['user_id'])) {
    // CAS 1 : L'utilisateur est déjà connecté (LIAISON)
    // On met à jour la ligne de l'utilisateur actuel avec ses infos Discord
    $stmt = $pdo->prepare("UPDATE users SET discord_id = ?, discord_email = ?, discord_picture = ? WHERE id = ?");
    $stmt->execute([$discord_id, $discord_email, $avatar_url, $_SESSION['user_id']]);
    
    header('Location: ../../public/admin/index.php?success=discord_linked');
    exit();

} else {
    // CAS 2 : L'utilisateur n'est pas connecté (TENTATIVE DE CONNEXION)
    // On cherche si un compte existe avec cet ID Discord ou cet Email
    $stmt = $pdo->prepare("SELECT * FROM users WHERE discord_id = ? OR discord_email = ?");
    $stmt->execute([$discord_id, $discord_email]);
    $user = $stmt->fetch();

    if ($user) {
        // Utilisateur trouvé ! On crée la session
        $_SESSION['user_id'] = $user['id'];
        
        // On met à jour ses infos au passage (au cas où il ait changé d'avatar ou d'id)
        $update = $pdo->prepare("UPDATE users SET discord_id = ?, discord_picture = ? WHERE id = ?");
        $update->execute([$discord_id, $avatar_url, $user['id']]);
        
        header('Location: ../../public/admin/index.php');
        exit();
    } else {
        // Ni connecté, ni déjà inscrit en base
        die("Accès refusé : Votre compte Discord ($discord_email) n'est pas autorisé par l'administrateur. Demandez à l'admin de vous ajouter via votre mail.");
    }
}