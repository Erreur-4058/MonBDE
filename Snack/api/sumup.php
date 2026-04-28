<?php
// api/sumup.php
$amount = $_GET['amount'] ?? '0.00';
$ref = $_GET['ref'] ?? 'Commande';

// On construit le lien pour l'application mobile
$sumup_url = "sumupmerchant://pay/1.0?amount=$amount&currency=EUR&title=" . urlencode($ref);

// On redirige immédiatement vers l'application
header("Location: " . $sumup_url);
?>
<!DOCTYPE html>
<html>
<head><title>Ouverture SumUp...</title></head>
<body>
    <p>Redirection vers l'application SumUp...</p>
    <!-- Fallback si la redirection header échoue -->
    <script>window.location.href = "<?= $sumup_url ?>";</script>
</body>
</html>