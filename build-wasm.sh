#!/bin/bash

PROJECT_DIR=$(pwd)

echo "🚀 [LOGISIEL] Chargement de l'environnement EMSDK..."
source "/home/jeremi/emsdk/emsdk_env.sh"

# --- Configuration des chemins ---
# CORRECTION ICI : user_logic.cpp au lieu de logic.cpp
SRC_FILE="${PROJECT_DIR}/server/wasm_src/user_logic.cpp"
DEST_WEB="${PROJECT_DIR}/public/js/wasm"
DEST_SERVER="${PROJECT_DIR}/server/api/wasm"

# Vérification si le fichier source existe
if [ ! -f "$SRC_FILE" ]; then
    echo "❌ Erreur : Le fichier source $SRC_FILE est introuvable !"
    exit 1
fi

mkdir -p $DEST_WEB
mkdir -p $DEST_SERVER

echo ""
echo "🔨 [1/2] Compilation pour le Navigateur (Frontend)..."

emcc "$SRC_FILE" \
    -o "${DEST_WEB}/logic_engine.js" \
    -O3 \
    --bind \
    -s MODULARIZE=1 \
    -s EXPORT_ES6=1 \
    -s EXPORT_NAME="createModule" \
    -s ALLOW_MEMORY_GROWTH=1 \
    -s ENVIRONMENT='web' \
    -s WASM=1

echo "✅ Frontend terminé !"

echo ""
echo "🔨 [2/2] Compilation pour le Serveur (Backend)..."

emcc "$SRC_FILE" \
    -o "${DEST_SERVER}/logic_engine.node.js" \
    -O3 \
    --bind \
    -s MODULARIZE=1 \
    -s EXPORT_ES6=1 \
    -s EXPORT_NAME="createModule" \
    -s ALLOW_MEMORY_GROWTH=1 \
    -s ENVIRONMENT='node' \
    -s WASM=1

echo "✅ Backend terminé !"
echo ""
echo "✨ Tous les fichiers sont à jour dans public/js/wasm/ et server/api/wasm/"