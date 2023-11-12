-- MySQL table for nonce_login

CREATE TABLE IF NOT EXISTS login_nonces (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nonce VARCHAR(255) NOT NULL,
    expires DATETIME NOT NULL,
    user VARCHAR(255) NOT NULL,
    pass VARCHAR(255) NOT NULL,
    host VARCHAR(255) NOT NULL
);