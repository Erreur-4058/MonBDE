<?php
require '../api/config.php';

$params = [
    'client_id'     => G_ID,
    'redirect_uri'  => G_REDIRECT,
    'response_type' => 'code', // Ajout des guillemets ici
    'scope'         => 'https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email',
    'access_type'   => 'offline'
];

$url = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query($params);
header('Location: ' . $url);
exit();