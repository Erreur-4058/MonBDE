<?php
/**
 * load_env.php — Chargeur de variables d'environnement pour le dev local (php -S)
 *
 * - En production (Docker) : les variables sont déjà injectées → ce fichier ne fait rien.
 * - En dev local (php -S)  : lit le fichier .env.dev à la racine du projet et appelle putenv().
 *
 * @param string $projectRoot Chemin absolu vers la racine du projet
 */
function loadEnvDev(string $projectRoot): void
{
    // Si APP_BASE_URL est déjà défini (Docker / système), on ne fait rien
    if (getenv('APP_BASE_URL') !== false) {
        return;
    }

    $envFile = rtrim($projectRoot, '/') . '/.env';
    if (!file_exists($envFile)) {
        return;
    }

    foreach (file($envFile) as $line) {
        $line = trim($line);
        // Ignorer les lignes vides et les commentaires
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);
        // Ne pas écraser une variable déjà définie
        if ($key !== '' && getenv($key) === false) {
            putenv("$key=$value");
        }
    }
}
