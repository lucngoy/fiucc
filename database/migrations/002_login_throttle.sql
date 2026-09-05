-- =====================================================================
-- FIUCC Portal — migration 002 : ralentissement progressif des tentatives
-- de connexion pour les membres, par (compte, IP).
-- Ne modifie pas 001_initial_schema.sql (CLAUDE.md : "ne pas refondre").
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- login_throttle : remplace le verrouillage dur pour les membres.
-- Un verrouillage par compte seul (users.failed_attempts/locked_until,
-- toujours utilisé pour les admins) permet à n'importe qui de bloquer le
-- compte d'un électeur pendant la période de vote en saisissant 5 mauvais
-- mots de passe. Ici : pas de blocage total, seulement un délai croissant
-- avant la réponse, borné par IP pour qu'une même IP ne pénalise pas tout
-- le monde derrière elle (réseau du campus).
-- ---------------------------------------------------------------------
CREATE TABLE login_throttle (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         BIGINT UNSIGNED NOT NULL,
    ip_address      VARBINARY(16)   NOT NULL,
    failed_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    last_attempt_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_throttle_user_ip (user_id, ip_address),
    CONSTRAINT fk_throttle_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
