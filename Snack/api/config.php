<?php
// Snack/api/config.php

// --- 0. CHARGEMENT .env.dev (dev local php -S uniquement) ---
if (file_exists(__DIR__ . '/../../load_env.php')) {
    require_once __DIR__ . '/../../load_env.php';
    loadEnvDev(__DIR__ . '/../../');
}

// --- 1. DEBUG & ERREURS ---
error_reporting(E_ALL);
ini_set('display_errors', 0);

// --- 2. SESSIONS PARTAGÉES (Core + Snack) ---
ini_set('session.cookie_path', '/');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- 3. CHEMINS DES BASES DE DONNÉES ---
define('DB_PATH', __DIR__ . '/../db/snack.sqlite');
// On remonte de Snack/api (2 niveaux) pour aller vers core/database
define('CORE_DB_PATH', realpath(__DIR__ . '/../../core/database/database.sqlite'));

// --- 4. CONFIGURATION DISCORD & LOGIN ---
$_snackBase = rtrim(getenv('SNACK_BASE_URL') ?: 'http://localhost', '/');
define('CORE_LOGIN_URL', $_snackBase . '/Snack/api/login_callback.php');
define('DISCORD_WEBHOOK', getenv('DISCORD_WEBHOOK_URL') ?: '');
define('DISCORD_BOT_TOKEN', getenv('API_BOT_TOKEN') ?: 'snack2025');
define('DISCORD_CHANNEL_ID', getenv('DISCORD_CHANNEL_COMMANDES') ?: '1486310299559526492');

// --- 5. HEADERS (CORS) ---
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// --- 6. FONCTION : CONNEXION DB SNACK ---
function getDB(): PDO {
    static $db = null;
    if ($db === null) {
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->exec('PRAGMA foreign_keys = ON');
        $db->exec("CREATE TABLE IF NOT EXISTS config (cle TEXT PRIMARY KEY, valeur TEXT)");
        // Création table utilisateurs si manquante
        $db->exec("CREATE TABLE IF NOT EXISTS utilisateurs (
            discord_id TEXT PRIMARY KEY,
            username TEXT,
            est_adherent INTEGER DEFAULT 0
        )");
        try { $db->exec("ALTER TABLE commandes ADD COLUMN salle TEXT"); } catch (Exception $e) {}
        try { $db->exec("ALTER TABLE categories ADD COLUMN visible INTEGER DEFAULT 1"); } catch (Exception $e) {}
        try { $db->exec("ALTER TABLE produits ADD COLUMN visible INTEGER DEFAULT 1"); } catch (Exception $e) {}
        // Ajout des colonnes produits (Code-barre, Pack et Stock)
        try { $db->exec("ALTER TABLE produits ADD COLUMN code_barre TEXT"); } catch (Exception $e) {}
        try { $db->exec("ALTER TABLE produits ADD COLUMN code_barre_pack TEXT"); } catch (Exception $e) {}
        try { $db->exec("ALTER TABLE produits ADD COLUMN unites_par_pack INTEGER DEFAULT 1"); } catch (Exception $e) {}
        try { $db->exec("ALTER TABLE produits ADD COLUMN stock INTEGER DEFAULT -1"); } catch (Exception $e) {}
    }
    return $db;
}

// --- 7. FONCTION : CONNEXION DB CORE (IDENTITÉ) ---
function getCoreDB(): PDO {
    if (!CORE_DB_PATH || !file_exists(CORE_DB_PATH)) {
        die("Erreur critique : La base de données du Core est introuvable à l'adresse : " . CORE_DB_PATH);
    }
    $pdo = new PDO("sqlite:" . CORE_DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}

// --- 8. HELPERS JSON ---
function json_success($data): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

function json_error(string $msg, int $code = 400): void {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

// --- 9. INITIALISATION ---
function initDB(): void {
    if (!file_exists(DB_PATH)) {
        $db = getDB();
        if (file_exists(__DIR__ . '/../db/init.sql')) {
            $sql = file_get_contents(__DIR__ . '/../db/init.sql');
            $db->exec($sql);
        }
    }
}

initDB();