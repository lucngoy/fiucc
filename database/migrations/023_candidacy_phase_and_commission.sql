-- =====================================================================
-- FIUCC Portal — migration 023 : phase "candidatures closes", date limite
-- de dépôt et Commission électorale (règlement électoral 2026 ; lot 2 de
-- la refonte électorale, décisions du bureau 2026-10-10).
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Nouveau statut 'candidacies_closed', entre 'candidacies' et 'open' :
-- dépôt fermé, vérification des dossiers / régularisation (art. 15),
-- publication de la liste et campagne (art. 16-17), AVANT le vote. Le
-- vote ne s'ouvre plus que depuis cet état (Election::open()).
--
-- candidacy_deadline : date limite de dépôt, annoncée aux membres. Le
-- bureau ne peut clôturer qu'une fois cette date passée (égalité entre
-- candidats, art. 16 : personne ne doit perdre le délai annoncé), et peut
-- la prolonger tant que les candidatures sont ouvertes. NULL pour les
-- élections antérieures à cette migration.
-- ---------------------------------------------------------------------
ALTER TABLE elections
    MODIFY COLUMN status ENUM('draft','candidacies','candidacies_closed','open','closed','published') NOT NULL DEFAULT 'draft',
    ADD COLUMN candidacy_deadline DATETIME NULL AFTER description;

-- ---------------------------------------------------------------------
-- Commission électorale d'une élection (art. 5-7) — désignée par le
-- bureau. Art. 6 : ses membres ne peuvent pas être candidats à l'élection
-- qu'ils organisent ; Candidate::create()/approve() le vérifient en SQL
-- dès que member_id est renseigné. member_id NULL : membre de la
-- Commission sans compte sur le portail (affiché par son nom seulement —
-- il ne peut de toute façon pas déposer de candidature). Un membre
-- supprimé du portail reste nommé dans la Commission (ON DELETE SET NULL).
-- ---------------------------------------------------------------------
CREATE TABLE election_commission_members (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    election_id  BIGINT UNSIGNED NOT NULL,
    member_id    BIGINT UNSIGNED NULL,
    display_name VARCHAR(150)    NOT NULL,
    role         ENUM('president','secretary','member') NOT NULL DEFAULT 'member',
    created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_commission_election_member (election_id, member_id),
    KEY idx_commission_election (election_id),
    CONSTRAINT fk_commission_election FOREIGN KEY (election_id) REFERENCES elections (id) ON DELETE CASCADE,
    CONSTRAINT fk_commission_member FOREIGN KEY (member_id) REFERENCES members (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
