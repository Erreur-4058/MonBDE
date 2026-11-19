<?php
// api/sumup_bridge.php

$amount = $_GET['amount'] ?? '0.00';
$ref    = $_GET['ref'] ?? 'Snack';

// On force le format 0.00 (point au lieu de virgule)
$amount = number_format((float)$amount, 2, '.', '');

// On définit l'URL de retour vers ton dashboard
$callback_url = "http://" . $_SERVER['HTTP_HOST'] . "/api/dashboard.php";

// Construction de l'URL selon le schéma SumUp
// affiliate-key: peut être remplacé par un ID ou laissé vide
$sumup_url = "sumupmerchant://pay/1.0"
           . "?amount=" . $amount
           . "&currency=EUR"
           . "&title=" . urlencode($ref)
           . "&affiliate-key=snack-app-2025" 
           . "&callback=" . urlencode($callback_url);

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
    <p>L'application devrait s'ouvrir automatiquement.</p>
    
    <!-- Bouton de secours si l'auto-redirection échoue -->
    <a href="<?= $sumup_url ?>" class="btn-manual">Ouvrir SumUp manuellement</a>

    <script>
        // On tente d'ouvrir l'application immédiatement
        window.location.href = "<?= $sumup_url ?>";
        
        // Si après 2 secondes rien ne se passe, on peut afficher un message
        setTimeout(function() {
            console.log("Si l'app ne s'est pas lancée, vérifiez qu'elle est installée.");
        }, 2000);
    </script>
</body>
</html>