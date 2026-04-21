CREATE DATABASE IF NOT EXISTS nextsi_auth;
USE nextsi_auth;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    document VARCHAR(20) NOT NULL UNIQUE,
    profile ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO users (name, email, password, document, profile) 
VALUES (
    'Administrador', 
    'admin@nextsi.com.br', 
    '$2y$12$K5.Q0Oj4Hj7aSoaTtUhSxes1RqqUo864KnS2FJ56T8djDFO4FZnqG', 
    '70837980011', 
    'admin'
);