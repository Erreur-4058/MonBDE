#!/bin/bash
# Initialise la base de données Core si elle n'existe pas encore

DB_PATH="/var/www/html/core/database/database.sqlite"
SCHEMA_PATH="/var/www/html/core/database/schema.sql"
ADMIN_EMAIL="rouxjeremi48@gmail.com"

if [ ! -f "$DB_PATH" ]; then
    echo "🗄️ Initialisation de la base de données Core..."
    sqlite3 "$DB_PATH" < "$SCHEMA_PATH"
    sqlite3 "$DB_PATH" "INSERT OR IGNORE INTO users (username, google_email, role) VALUES ('Admin', '$ADMIN_EMAIL', 'admin');"
    chown www-data:www-data "$DB_PATH"
    echo "✅ Base de données Core initialisée."
else
    echo "✅ Base de données Core déjà existante."
fi
