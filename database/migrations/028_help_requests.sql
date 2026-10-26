-- =====================================================================
-- FIUCC Portal — migration 028 : demandes d'aide (entraide, cahier des
-- charges §1 « s'entraider » ; décision du bureau 2026-10-24, inspirée du
-- service BOUSSOLE de la communauté mère).
--
-- Un membre connecté écrit au gouvernement pour un problème concret
-- (logement, santé, démarches, difficulté financière...). Le bureau la
-- traite depuis /admin/help : statut, réponse envoyée au membre par email.
--
-- Données sensibles : lues uniquement par les admins (AdminMiddleware) et
-- par le membre lui-même. L'email d'alerte au bureau ne contient ni le
-- sujet ni le message, seulement la référence et la catégorie.
--
-- Référence affichée (« AIDE-2026-0042 ») calculée depuis id et
-- created_at, jamais stockée : rien à garder synchronisé.
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE help_requests (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    member_id   BIGINT UNSIGNED NOT NULL,
    category    ENUM('housing','health','administrative','financial','academic','safety','other') NOT NULL,
    subject     VARCHAR(150)    NOT NULL,
    message     TEXT            NOT NULL,
    status      ENUM('open','in_progress','closed') NOT NULL DEFAULT 'open',
    -- Dernière réponse du bureau, visible par le membre sur /help et
    -- envoyée par email. Les notes internes n'ont pas leur place ici.
    response    TEXT            NULL,
    handled_by  BIGINT UNSIGNED NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    closed_at   DATETIME        NULL,
    PRIMARY KEY (id),
    KEY idx_help_requests_status_created (status, created_at),
    KEY idx_help_requests_member_created (member_id, created_at),
    -- Supprimer un membre supprime ses demandes : elles n'ont pas de sens
    -- sans lui, et ce sont ses données personnelles.
    CONSTRAINT fk_help_requests_member FOREIGN KEY (member_id) REFERENCES members (id) ON DELETE CASCADE,
    CONSTRAINT fk_help_requests_handler FOREIGN KEY (handled_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
