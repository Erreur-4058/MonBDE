<?php
// api/sumup_bridge.php

// --- Config du projet (charge le .env en dev local, variables Docker en prod) ---
require_once __DIR__ . '/config.php';

$affiliate_key = trim(getenv('SUMUP_AFFILIATE_KEY') ?: '');
$app_id        = trim(getenv('SUMUP_APP_ID') ?: '');

if ($affiliate_key === '' || $app_id === '') {
    http_response_code(500);
    exit('Configuration SumUp manquante (SUMUP_AFFILIATE_KEY / SUMUP_APP_ID).');
}

// --- Paramètres de la requête ---
$amount = $_GET['amount'] ?? '0.00';
$ref    = $_GET['ref'] ?? 'Snack';

// Accepte la virgule, force le format 0.00 (point)
$amount = (float) str_replace(',', '.', $amount);
if ($amount <= 0) {
    http_response_code(400);
    exit('Montant invalide.');
}
$amount = number_format($amount, 2, '.', '');

// Titre limité en longueur
$ref = mb_substr(trim($ref), 0, 100);

// --- URL de retour vers le dashboard (base définie par SNACK_BASE_URL dans le .env) ---
// $_snackBase vient de config.php ; adapte le chemin si ton dashboard est ailleurs
$callback_url = $_snackBase . '/Snack/api/dashboard.php';

// --- Construction de l'URL SumUp ---
// total  = champ récent (app SumUp >= 1.88.0), amount = ancien champ conservé en secours
// callback = Android / web ; callbacksuccess + callbackfail = iOS
$sumup_url = 'sumupmerchant://pay/1.0'
           . '?affiliate-key=' . urlencode($affiliate_key)
           . '&app-id='        . urlencode($app_id)
           . '&total='         . $amount
           . '&amount='        . $amount
           . '&currency=EUR'
           . '&title='         . urlencode($ref)
           . '&callback='        . urlencode($callback_url)
           . '&callbacksuccess=' . urlencode($callback_url)
           . '&callbackfail='    . urlencode($callback_url);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lancement SumUp...</title>
    <style>
        body { font-family: sans-serif; text-align: center; padding: 50px; background: #f4f7f6; }
        .btn-manual { display: inline-block; margin-top: 20px; padding: 15px 25px; background: #0066ff; color: white; text-decoration: none; border-radius: 10px; font-weight: bold; }
    </style>
</head>
<body>
    <h2>🚀 Redirection vers SumUp...</h2>
    <p>Montant : <strong><?= htmlspecialchars($amount, ENT_QUOTES) ?> €</strong> — <?= htmlspecialchars($ref, ENT_QUOTES) ?></p>
    <p>L'application devrait s'ouvrir automatiquement.</p>

    <!-- Bouton de secours si l'auto-redirection échoue -->
    <a href="<?= htmlspecialchars($sumup_url, ENT_QUOTES) ?>" class="btn-manual">Ouvrir SumUp manuellement</a>

    <script>
        // On tente d'ouvrir l'application immédiatement
        window.location.href = <?= json_encode($sumup_url) ?>;

        setTimeout(function () {
            console.log("Si l'app ne s'est pas lancée, vérifiez qu'elle est installée et connectée.");
        }, 2000);
    </script>
</body>
</html>