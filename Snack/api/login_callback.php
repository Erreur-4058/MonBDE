<?php
// api/login_callback.php
require_once 'config.php';
header('Content-Type: text/html; charset=utf-8');

$code = $_GET['code'] ?? null;
if (!$code) die("Erreur : Pas de code reçu.");

// --- CONFIGURATION ---
$clientId     = '1485358310503878848';
// COPIE TON SECRET ICI ET RIEN D'AUTRE
$clientSecret = trim('8GHdTAq0ZQC7zcACStQo9qSh2jX9fvPY'); 
$redirectUri  = 'http://127.0.0.1:8080/Snack/api/login_callback.php';

// --- ÉCHANGE ---
$params = [
    'client_id'     => $clientId,
    'client_secret' => $clientSecret,
    'grant_type'    => 'authorization_code',
    'code'          => $code,
    'redirect_uri'  => $redirectUri,
];

$ch = curl_init('https://discord.com/api/oauth2/token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
curl_setopt($ch, CURLOPT_USERAGENT, 'SnackApp (1.0)');

$raw = curl_exec($ch);
$response = json_decode($raw, true);
curl_close($ch);

if (isset($response['error'])) {
    echo "<h2>❌ Erreur Discord</h2>";
    echo "<b>Détail :</b> " . ($response['error_description'] ?? $response['error']) . "<br>";
    echo "<hr>";
    echo "<h4>Vérifications à faire :</h4>";
    echo "1. Le code envoyé était : <code>$code</code><br>";
    echo "2. L'URL de redirection configurée est : <code>$redirectUri</code><br>";
    echo "3. Ton Client Secret fait-il bien environ 32 caractères ? (Actuel : ".strlen($clientSecret)." caractères)<br>";
    echo "<br><a href='../site/index.html'>Réessayer</a>";
    exit;
}

// --- SUCCÈS : RÉCUPÉRATION PROFIL ---
$ch = curl_init('https://discord.com/api/users/@me');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $response['access_token']]);
$user = json_decode(curl_exec($ch), true);
curl_close($ch);

if ($user && isset($user['id'])) {
    $db = getDB();
$stmt = $db->prepare("
    INSERT INTO utilisateurs (discord_id, username, est_adherent) 
    VALUES (?, ?, -1) 
    ON CONFLICT(discord_id) DO UPDATE SET username=excluded.username
");    
$stmt->execute([$user['id'], $user['username']]);
    
    $stmt = $db->prepare("SELECT est_adherent FROM utilisateurs WHERE discord_id = ?");
    $stmt->execute([$user['id']]);
    $u = $stmt->fetch();

    ?>
    <script>
        localStorage.setItem('discord_user', JSON.stringify({
            id: '<?= $user['id'] ?>',
            username: '<?= $user['username'] ?>',
            est_adherent: <?= (int)($u['est_adherent'] ?? 0) ?>
        }));
        window.location.href = '../site/index.html';
    </script>
    <?php
}