<?php
require '../../server/api/config.php';
$token = $_GET['token'] ?? '';

$stmt = $pdo->prepare("SELECT * FROM invitations WHERE token = ?");
$stmt->execute([$token]);
$inv = $stmt->fetch();

if ($inv) {
    $_SESSION['pending_invite_token'] = $token;
    $_SESSION['pending_invite_role'] = $inv['role_attribue'];
    header('Location: index.html?msg=invite_ok');
} else {
    die("Lien invalide.");
}