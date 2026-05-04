<?php
require 'config.php';
$pdo->exec("CREATE TABLE IF NOT EXISTS invitations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    token TEXT UNIQUE,
    role_attribue TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
echo "Table invitations OK !";