<?php
require 'config.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT,
        role TEXT DEFAULT 'user',
        google_id TEXT UNIQUE,
        google_email TEXT,
        google_picture TEXT,
        discord_id TEXT UNIQUE,
        discord_email TEXT,
        discord_picture TEXT
    );";

    $pdo->exec($sql);
    echo "Base de données initialisée avec succès !<br>";

    // ON S'AJOUTE SOI-MEME EN ADMIN (Remplace par ton vrai mail Google) 
    $email_admin = "rouxjeremi48@gmail.com";
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO users (username, google_email, role) VALUES ('Admin', ?, 'admin')");
    $stmt->execute([$email_admin]);

    echo "Utilisateur admin ($email_admin) ajouté !";
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage();
}