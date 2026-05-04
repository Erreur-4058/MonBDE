<?php

ini_set('session.cookie_path', '/');
session_set_cookie_params(0, '/');
session_start();

define('DB_PATH', __DIR__ . '/../../database/database.sqlite');

// Google
define('G_ID', getenv('GOOGLE_CLIENT_ID'));
define('G_SECRET', getenv('GOOGLE_CLIENT_SECRET'));

// Discord
define('D_ID', getenv('DISCORD_CLIENT_ID'));
define('D_SECRET', getenv('DISCORD_CLIENT_SECRET'));


$_appBase = rtrim(getenv('APP_BASE_URL') ?: 'http://localhost', '/');
if (!defined('G_REDIRECT')) define('G_REDIRECT', $_appBase . '/core/server/auth/auth_google.php');
if (!defined('D_REDIRECT')) define('D_REDIRECT', $_appBase . '/core/server/auth/auth_discord.php');

// Connexion DB
try {
    $pdo = new PDO("sqlite:" . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur DB : " . $e->getMessage());
}