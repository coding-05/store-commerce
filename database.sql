CREATE DATABASE IF NOT EXISTS estore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE estore;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS lignes_commandes;
DROP TABLE IF EXISTS commandes;
DROP TABLE IF EXISTS visites;
DROP TABLE IF EXISTS produits;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    mot_de_passe VARCHAR(255) NOT NULL,
    role ENUM('admin', 'client') NOT NULL DEFAULT 'client',
    date_inscription DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE produits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(50) NOT NULL UNIQUE,
    designation VARCHAR(180) NOT NULL,
    description TEXT NULL,
    prix DECIMAL(10,2) NOT NULL,
    categorie VARCHAR(50) NOT NULL DEFAULT 'Autre',
    image_url VARCHAR(500) NULL,
    taille VARCHAR(30) NULL,
    couleur VARCHAR(40) NULL,
    stock INT NOT NULL DEFAULT 0,
    date_ajout DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE commandes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    date_commande DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    statut ENUM('en_attente', 'validee', 'expediee', 'annulee') NOT NULL DEFAULT 'validee',
    montant_total DECIMAL(10,2) NOT NULL DEFAULT 0,
    CONSTRAINT fk_commandes_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE lignes_commandes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    commande_id INT NOT NULL,
    produit_id INT NOT NULL,
    quantite INT NOT NULL,
    prix_unitaire DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_lignes_commande
        FOREIGN KEY (commande_id) REFERENCES commandes(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_lignes_produit
        FOREIGN KEY (produit_id) REFERENCES produits(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE visites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    pays VARCHAR(100) NOT NULL DEFAULT 'Local',
    ville VARCHAR(100) NOT NULL DEFAULT 'Developpement',
    date_visite DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


INSERT INTO users (nom, email, mot_de_passe, role) VALUES
('Administrateur', 'admin@estore.com', 'SETUP_ADMIN123_REHASH_REQUIRED', 'admin');

INSERT INTO produits (reference, designation, description, prix, categorie, image_url, taille, couleur, stock) VALUES
('FEM-ROB-001', 'Robe fluide rouge', 'Robe femme elegante pour soiree et sortie.', 349.00, 'Femme', 'https://images.unsplash.com/photo-1595777457583-95e059d581b8?auto=format&fit=crop&w=900&q=80', 'M', 'Rouge', 18),
('FEM-SAC-002', 'Sac a main cuir noir', 'Sac femme pratique avec finition premium.', 499.00, 'Femme', 'https://images.unsplash.com/photo-1594223274512-ad4803739b7c?auto=format&fit=crop&w=900&q=80', 'Unique', 'Noir', 12),
('FEM-JUP-003', 'Jupe plisse beige', 'Jupe moderne pour tenue chic de tous les jours.', 219.00, 'Femme', 'https://images.unsplash.com/photo-1587679484466-1f2b16ba4b90?auto=format&fit=crop&w=900&q=80', 'S', 'Beige', 20),
('HOM-CHE-004', 'Chemise homme blanche', 'Chemise homme coupe droite, ideale bureau.', 279.00, 'Homme', 'https://images.unsplash.com/photo-1602810318383-e386cc2a3ccf?auto=format&fit=crop&w=900&q=80', 'L', 'Blanc', 25),
('HOM-COS-005', 'Costume bleu marine', 'Costume homme deux pieces avec coupe moderne.', 1190.00, 'Homme', 'https://images.unsplash.com/photo-1593030761757-71fae45fa0e7?auto=format&fit=crop&w=900&q=80', 'XL', 'Bleu', 8),
('HOM-MON-006', 'Montre homme acier', 'Montre homme elegante pour style classique.', 650.00, 'Homme', 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=900&q=80', 'Unique', 'Argent', 15);
