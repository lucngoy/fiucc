-- =====================================================================
-- FIUCC Portal — migration 029 : questions fréquentes modifiables depuis
-- le back office (décision du bureau 2026-10-24).
--
-- Les 5 questions sur le fonctionnement du site (adhésion, recensement,
-- vote, alumni, contact) restent dans resources/lang : elles décrivent des
-- règles du portail et sont traduites avec lui. Celles-ci les complètent
-- sur la page « Nouveau à Final ? » : vie étudiante, bourses, démarches...
-- écrites par le bureau, sans développeur.
--
-- Français obligatoire (langue par défaut, cahier §38), anglais
-- facultatif : sans traduction, la version française s'affiche aussi en
-- anglais plutôt que de masquer la question.
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE faq_items (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    question      VARCHAR(255)    NOT NULL,
    answer        TEXT            NOT NULL,
    question_en   VARCHAR(255)    NULL,
    answer_en     TEXT            NULL,
    sort_order    INT             NOT NULL DEFAULT 0,
    is_published  TINYINT(1)      NOT NULL DEFAULT 1,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_faq_items_published_order (is_published, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
