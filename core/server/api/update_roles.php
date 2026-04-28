<?php
require 'config.php';
$pdo->exec("CREATE TABLE IF NOT EXISTS roles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT UNIQUE NOT NULL
)");

// On ajoute les rôles de base par défaut
$pdo->exec("INSERT OR IGNORE INTO roles (name) VALUES ('admin'), ('user'), ('staff')");
echo "Table des rôles créée et initialisée !";