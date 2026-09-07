-- =====================================================================
-- FIUCC Portal — migration 006 : galerie photo par albums (FIUCC-52,
-- section 28 du cahier des charges). Ne modifie pas 001_initial_schema.sql
-- (CLAUDE.md : "ne pas refondre").
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- albums : l'admin crée librement un album ("Congolese Day 2026",
-- "Football Match"...), sans lien obligatoire à un event précis au moment
-- de cette migration (le lien optionnel event_id arrive en 007, une fois
-- la question tranchée avec l'équipe — voir CLAUDE.md). status suit le
-- même principe que events.status : un album reste 'draft' pendant
-- l'upload, publié quand l'admin est prêt à le montrer.
-- ---------------------------------------------------------------------
CREATE TABLE albums (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title       VARCHAR(150)    NOT NULL,
    slug        VARCHAR(170)    NOT NULL,
    description MEDIUMTEXT      NULL,
    status      ENUM('draft','published') NOT NULL DEFAULT 'draft',
    created_by  BIGINT UNSIGNED NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_albums_slug (slug),
    KEY idx_albums_status (status),
    CONSTRAINT fk_albums_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- album_photos : image_path/caption/sort_order — tri manuel par l'admin
-- (FIUCC-54). Pas de colonne "cover" séparée sur albums : la couverture
-- d'un album est sa première photo par sort_order (comme
-- events.cover_image, mais dérivé plutôt que dupliqué, puisqu'une photo
-- de couverture EST déjà une photo de l'album).
-- ---------------------------------------------------------------------
CREATE TABLE album_photos (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    album_id    BIGINT UNSIGNED NOT NULL,
    image_path  VARCHAR(255)    NOT NULL,
    caption     VARCHAR(255)    NULL,
    sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_albumphotos_album (album_id),
    CONSTRAINT fk_albumphotos_album FOREIGN KEY (album_id) REFERENCES albums (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
