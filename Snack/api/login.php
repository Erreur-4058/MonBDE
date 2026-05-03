<?php
// api/login.php
require_once 'config.php';

$clientId = '1485358310503878848';
$redirectUri = 'https://monbde.duckdns.org/Snack/api/login_callback.php';
$scope = 'identify';

$params = [
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => $scope
];

header('Location: https://discord.com/api/oauth2/authorize?' . http_build_query($params));
exit;