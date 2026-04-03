<?php
require '../api/config.php';

$params = [
    'client_id'     => D_ID,
    'redirect_uri'  => D_REDIRECT,
    'response_type' => 'code',
    'scope'         => 'identify email'
];

$url = "https://discord.com/api/oauth2/authorize?" . http_build_query($params);
header('Location: ' . $url);
exit();