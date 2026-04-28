<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . CORE_LOGIN_URL);
    exit();
}

// On cherche si l'utilisateur possède le rôle 'admin' OU 'staff' dans la table user_roles
$coreDb = getCoreDB();
$stmt = $coreDb->prepare("
    SELECT r.name 
    FROM roles r
    JOIN user_roles ur ON r.id = ur.role_id
    WHERE ur.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$user_roles = $stmt->fetchAll(PDO::FETCH_COLUMN); // Récupère une liste simple : ['admin', 'staff']

// On définit les rôles qui ont le droit d'entrer dans le Snack
$authorized = ['admin', 'staff', 'vendeur']; 

// On vérifie si au moins UN des rôles de l'utilisateur est dans la liste autorisée
$has_access = !empty(array_intersect($user_roles, $authorized));

if (!$has_access) {
    die("<h1>Accès refusé</h1><p>Vous n'avez pas un rôle autorisé pour gérer le Snack.</p>");
}