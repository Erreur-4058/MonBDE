Voici un rapport technique détaillé expliquant comment utiliser le projet **Core** comme système d'identité centralisé pour sécuriser n'importe quel autre projet (comme le projet **Snack**).

---

# Rapport : Centralisation de la Sécurité via le Projet "Core"

## 1. Introduction
L'objectif est de transformer le projet **Core** en un "fournisseur d'identité" (SSO - Single Sign-On). Au lieu que chaque projet possède son propre système de connexion, tous se basent sur la session et la base de données du Core. Cela permet de gérer tous les accès depuis une interface unique.

## 2. Le concept technique : Le partage de Session
En PHP, les sessions sont stockées via des cookies. Pour que deux projets différents (`/core` et `/Snack`) puissent partager une connexion, ils doivent :
1.  Être sur le même domaine (ex: `localhost`).
2.  Utiliser le même chemin de cookie (`/`).

### Configuration (Fichier `config.php`)
Dans chaque projet, la configuration de la session doit être forcée au tout début du fichier avant le `session_start()` :
```php
ini_set('session.cookie_path', '/'); // Autorise le cookie sur tout le serveur
session_set_cookie_params(0, '/');
if (session_status() === PHP_SESSION_NONE) session_start();
```

---

## 3. Établir le pont entre les bases de données
Le projet esclave (ex: **Snack**) doit pouvoir "lire" la base de données du **Core** pour vérifier les droits de l'utilisateur.

On définit une fonction de connexion spécifique dans le projet à sécuriser :
```php
define('CORE_DB_PATH', realpath(__DIR__ . '/../../core/database/database.sqlite'));

function getCoreDB(): PDO {
    $pdo = new PDO("sqlite:" . CORE_DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}
```

---

## 4. Le garde-barrière : `staff_protect.php`
C'est le fichier le plus important. On le place à la racine de l'API du projet à sécuriser. Il effectue deux vérifications cruciales :

1.  **Authentification** : L'utilisateur possède-t-il une session `user_id` ouverte par le Core ?
2.  **Autorisation** : L'utilisateur possède-t-il le rôle nécessaire (`admin`, `staff`, etc.) dans la table `user_roles` du Core ?

### Logique du fichier :
```php
require_once 'config.php';

// 1. Vérifier si l'utilisateur est passé par le login du Core
if (!isset($_SESSION['user_id'])) {
    header("Location: /core/public/login/index.html");
    exit();
}

// 2. Vérifier les rôles dans la DB du Core
$coreDb = getCoreDB();
$stmt = $coreDb->prepare("
    SELECT r.name FROM roles r
    JOIN user_roles ur ON r.id = ur.role_id
    WHERE ur.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$user_roles = $stmt->fetchAll(PDO::FETCH_COLUMN);

// 3. Autoriser ou bloquer
$roles_autorises = ['admin', 'staff-snack'];
if (empty(array_intersect($user_roles, $roles_autorises))) {
    die("Accès refusé : Rôle insuffisant.");
}
```

---

## 5. Mise en place sur les pages sensibles
Pour protéger une page (ex: `admin.php` ou `dashboard.php`), il suffit d'ajouter une seule ligne tout en haut du fichier :

```php
<?php require_once 'staff_protect.php'; ?>
<!-- Le reste du code HTML/PHP ne s'exécutera que si l'utilisateur est validé -->
```

---

## 6. Avantages de cette méthode

1.  **Sécurité centralisée** : Si vous supprimez un utilisateur dans le projet **Core**, il perd instantanément l'accès au **Snack** et à tous les autres projets liés.
2.  **Expérience utilisateur** : L'administrateur se connecte une seule fois (via Google ou Discord) et peut naviguer entre tous les outils sans se reconnecter.
3.  **Gestion des rôles flexible** : Vous pouvez créer un rôle "Stagiaire" dans le Core qui donne accès au Scanner du Snack, mais pas aux Statistiques financières.

## 7. Conclusion
Cette architecture est la plus proche de ce qui est utilisé en entreprise. Elle sépare **l'identité** (qui êtes-vous ?) de **l'application** (qu'avez-vous le droit de faire ?). Votre système est maintenant prêt à accueillir de nouveaux projets qui pourront être sécurisés en quelques minutes en suivant ce même schéma.