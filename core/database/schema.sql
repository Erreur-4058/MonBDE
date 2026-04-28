CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT,
    role TEXT DEFAULT 'user', -- 'admin' ou 'user'
    
    -- Infos Google
    google_id TEXT UNIQUE,
    google_email TEXT,
    google_picture TEXT,
    
    -- Infos Discord
    discord_id TEXT UNIQUE,
    discord_email TEXT,
    discord_picture TEXT
);