#!/bin/bash
# Point d'entrée du conteneur : initialise la DB puis lance Apache

set -e

# Initialiser la base de données si nécessaire
/usr/local/bin/init-db.sh

# Lancer Apache en avant-plan (comportement par défaut de php:apache)
exec apache2-foreground
