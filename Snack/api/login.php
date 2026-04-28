<?php
// api/login.php
require_once 'config.php';

$clientId = '1485358310503878848';
// On utilise l'adresse IP pour éviter les conflits localhost
$redirectUri = 'http://127.0.0.1:8080/Snack/api/login_callback.php';
$scope = 'identify';

$params = [
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => $scope
];

header('Location: https://discord.com/api/oauth2/authorize?' . http_build_query($params));
exit;