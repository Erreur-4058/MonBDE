-- ==========================================================
-- Base de données Snack - Initialisation SQLite
-- ==========================================================

-- Activation des contraintes de clés étrangères
PRAGMA foreign_keys = ON;

-- Suppression des tables si elles existent (pour réinitialisation propre)
DROP TABLE IF EXISTS commande_lignes;
DROP TABLE IF EXISTS commandes;
DROP TABLE IF EXISTS produits;
DROP TABLE IF EXISTS categories;

-- 1. Table des Catégories de produits
CREATE TABLE categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nom TEXT NOT NULL,
    ordre INTEGER DEFAULT 0
);

-- 2. Table des Produits du snack
CREATE TABLE produits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    -- ON DELETE CASCADE : Si on supprime la catégorie, on supprime ses produits
    categorie_id INTEGER NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
    nom TEXT NOT NULL,
    description TEXT,
    prix_normal REAL NOT NULL,       -- Prix non-adhérent
    prix_adherent REAL NOT NULL,     -- Prix adhérent
    disponible INTEGER DEFAULT 1,    -- 1 = oui, 0 = rupture
    image TEXT,                      -- nom du fichier image
    stock INTEGER DEFAULT -1         -- -1 = illimité, 0+ = quantité en stock
);

-- 3. Table des Commandes
CREATE TABLE commandes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    numero TEXT UNIQUE NOT NULL,     -- ex: CMD-20240325-001
    prenom TEXT NOT NULL,
    discord_id TEXT,
    est_adherent INTEGER DEFAULT 0,
    statut TEXT DEFAULT 'en_attente', -- en_attente | confirme | paye | annule
    paiement TEXT,                   -- especes | sumup | null
    total REAL,
    message_discord_id TEXT,         -- ID du message Discord pour l'éditer
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 4. Table des Lignes de commande (Détails)
CREATE TABLE commande_lignes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    -- ON DELETE CASCADE : Si on supprime la commande, on supprime ses lignes
    commande_id INTEGER REFERENCES commandes(id) ON DELETE CASCADE,
    -- ON DELETE SET NULL : Si on supprime le produit du catalogue, on garde l'historique de la ligne
    produit_id INTEGER REFERENCES produits(id) ON DELETE SET NULL,
    nom_produit TEXT NOT NULL,       -- snapshot du nom au moment de l'achat
    quantite INTEGER NOT NULL DEFAULT 1,
    prix_unitaire REAL NOT NULL,
    sous_total REAL NOT NULL
);


CREATE TABLE IF NOT EXISTS utilisateurs (
    discord_id TEXT PRIMARY KEY,
    username TEXT,
    est_adherent INTEGER DEFAULT 0
);
-- On change le défaut à -1 (Inconnu)
ALTER TABLE utilisateurs COLUMN est_adherent INTEGER DEFAULT -1;
-- Si la table existe déjà, on met tout le monde à -1 pour le test
UPDATE utilisateurs SET est_adherent = -1;

-- 5. Table de configuration (paramètres de l'application)
CREATE TABLE IF NOT EXISTS config (
    cle   TEXT PRIMARY KEY,
    valeur TEXT NOT NULL
);
INSERT OR IGNORE INTO config (cle, valeur) VALUES ('seuil_stock_faible', '5');

-- =====================
-- Données de démo
-- =====================

INSERT INTO categories (nom, ordre) VALUES
    ('Sandwichs', 1),
    ('Boissons', 2),
    ('Snacks', 3),
    ('Desserts', 4);

INSERT INTO produits (categorie_id, nom, description, prix_normal, prix_adherent) VALUES
    (1, 'Jambon-Beurre',    'Baguette, jambon, beurre',           2.50, 2.00),
    (1, 'Poulet-Crudités',  'Baguette, poulet, salade, tomate',   3.00, 2.50),
    (1, 'Végétarien',       'Baguette, fromage, crudités',         2.80, 2.30),
    (2, 'Eau 50cl',         'Eau minérale',                        1.00, 0.80),
    (2, 'Coca-Cola 33cl',   'Canette',                             1.50, 1.20),
    (2, 'Jus d''orange',    'Brique 20cl',                         1.20, 1.00),
    (3, 'Chips Nature',     'Paquet 30g',                          0.80, 0.60),
    (3, 'Barre chocolatée', 'Mars / Twix / Snickers',              1.20, 1.00),
    (4, 'Yaourt',           'Yaourt nature ou fruité',             0.90, 0.70),
    (4, 'Fruit',            'Pomme ou banane',                     0.60, 0.50);