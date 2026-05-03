-- ─── Utilisateurs ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT,
    role TEXT DEFAULT 'user',
    google_id TEXT UNIQUE,
    google_email TEXT,
    google_picture TEXT,
    discord_id TEXT UNIQUE,
    discord_email TEXT,
    discord_picture TEXT
);

-- ─── Rôles ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS roles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT UNIQUE NOT NULL
);

-- ─── Liaison utilisateurs / rôles ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS user_roles (
    user_id INTEGER NOT NULL,
    role_id INTEGER NOT NULL,
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
);

-- ─── Invitations ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS invitations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    token TEXT UNIQUE NOT NULL,
    role_attribue TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    used INTEGER DEFAULT 0
);

-- ─── Rôles par défaut ─────────────────────────────────────────────────────────
INSERT OR IGNORE INTO roles (name) VALUES ('admin');
INSERT OR IGNORE INTO roles (name) VALUES ('staff');
INSERT OR IGNORE INTO roles (name) VALUES ('vendeur');
INSERT OR IGNORE INTO roles (name) VALUES ('membre');