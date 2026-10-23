-- =====================================================================
-- FIUCC Portal — migration 024 : dépôt de candidature par le membre
-- (lot 3 de la refonte électorale ; règlement électoral 2026, art. 14).
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Réglages de l'élection liés au dépôt (configurés en brouillon, figés
-- ensuite comme le reste de la configuration) :
-- - candidacy_fee_required / candidacy_fee_details : frais de candidature
--   (art. 14, "si des frais sont officiellement adoptés") — la preuve de
--   paiement n'est exigée que si le bureau les active ;
-- - rules_document_id : le règlement électoral publié dans l'espace
--   Documents, lié depuis le formulaire de dépôt ("J'ai lu le règlement").
-- ---------------------------------------------------------------------
ALTER TABLE elections
    ADD COLUMN candidacy_fee_required TINYINT(1)      NOT NULL DEFAULT 0 AFTER candidacy_deadline,
    ADD COLUMN candidacy_fee_details  VARCHAR(255)    NULL AFTER candidacy_fee_required,
    ADD COLUMN rules_document_id      BIGINT UNSIGNED NULL AFTER candidacy_fee_details,
    ADD CONSTRAINT fk_elections_rules_document FOREIGN KEY (rules_document_id) REFERENCES documents (id) ON DELETE SET NULL;

-- ---------------------------------------------------------------------
-- Candidature déposée par le membre lui-même (vs créée par un admin) et
-- date du dépôt. NULL pour les candidatures créées depuis le back office.
-- ---------------------------------------------------------------------
ALTER TABLE candidates
    ADD COLUMN submitted_at DATETIME NULL AFTER status;

-- ---------------------------------------------------------------------
-- candidate_documents : pièces du dossier (art. 14). Fichiers sous
-- storage/candidacies/ (jamais public/), lisibles par l'admin seul,
-- conservés sans limite de durée (art. 34, décision du bureau
-- 2026-10-10). slot distingue les deux photos d'un même type.
-- ---------------------------------------------------------------------
CREATE TABLE candidate_documents (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    candidate_id  BIGINT UNSIGNED NOT NULL,
    type          ENUM('id_photo','pro_photo','cv','action_plan','payment_proof') NOT NULL,
    slot          TINYINT UNSIGNED NOT NULL DEFAULT 1,
    file_path     VARCHAR(255)    NOT NULL,
    mime_type     VARCHAR(100)    NOT NULL,
    uploaded_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_candidate_document_slot (candidate_id, type, slot),
    CONSTRAINT fk_candidate_documents_candidate FOREIGN KEY (candidate_id) REFERENCES candidates (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
