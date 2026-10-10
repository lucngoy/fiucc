-- =====================================================================
-- FIUCC Portal — migration 019 : réinitialisation du mot de passe
-- ("Mot de passe oublié ?", lien déjà présent sur /login). Ne modifie
-- pas 001_initial_schema.sql (CLAUDE.md : "ne pas refondre").
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- password_resets : un lien à usage unique envoyé par email.
-- token_hash = SHA-256 du jeton du lien, jamais le jeton en clair (même
-- règle que les codes OTP et les jetons de vote). SHA-256 et non
-- password_hash() : le jeton fait 256 bits aléatoires, il n'y a rien à
-- ralentir par un hachage coûteux, et un hash déterministe permet de
-- retrouver la ligne directement par index (UNIQUE) sans connaître
-- l'utilisateur.
-- ---------------------------------------------------------------------
CREATE TABLE password_resets (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     BIGINT UNSIGNED NOT NULL,
    token_hash  CHAR(64)        NOT NULL,
    expires_at  DATETIME        NOT NULL,
    used_at     DATETIME        NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_password_resets_token (token_hash),
    KEY idx_password_resets_user (user_id),
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
