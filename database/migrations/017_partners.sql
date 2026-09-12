-- =====================================================================
-- FIUCC Portal — migration 017 : espace partenaires (FIUCC-90, section
-- "Mentorat & partenariats" du cahier — "espace partenaires : anciens,
-- entreprises, sponsors d'événements"). Ne modifie pas
-- 001_initial_schema.sql (CLAUDE.md : "ne pas refondre").
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- partners : vitrine gérée par l'admin, pas d'auto-inscription (le
-- cahier ne décrit qu'une liste à afficher, aucun formulaire public, ni
-- de suivi de paiement/niveau de sponsoring — rien de tout ça n'est
-- demandé, ne pas l'inventer). type reprend exactement les trois
-- catégories du cahier ("anciens, entreprises, sponsors d'événements").
-- status/sort_order : mêmes principes que albums/album_photos
-- (migration 006) — brouillon avant publication, tri manuel par
-- l'admin plutôt qu'alphabétique ou chronologique.
-- ---------------------------------------------------------------------
CREATE TABLE partners (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name         VARCHAR(150)    NOT NULL,
    type         ENUM('alumni','company','sponsor') NOT NULL,
    logo         VARCHAR(255)    NULL,
    description  MEDIUMTEXT      NULL,
    website_url  VARCHAR(255)    NULL,
    status       ENUM('draft','published') NOT NULL DEFAULT 'draft',
    sort_order   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_by   BIGINT UNSIGNED NULL,
    created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_partners_status (status),
    KEY idx_partners_type (type),
    CONSTRAINT fk_partners_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
