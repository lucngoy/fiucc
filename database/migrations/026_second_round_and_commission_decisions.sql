-- =====================================================================
-- FIUCC Portal — migration 026 : second tour et décisions de la
-- Commission électorale (lot 5 ; règlement électoral 2026, art. 24 et 30).
--
-- Décisions du bureau (2026-10-12) :
--  - égalité en tête au 1er tour -> second tour, créé après publication
--    des résultats, limité aux postes à égalité et aux seuls ex aequo ;
--  - égalité persistante au second tour, ou candidat seul en tête retiré
--    pendant le vote -> la Commission tranche, l'admin enregistre ;
--  - électeurs du second tour : même règle que pour tout scrutin.
-- Les tables votes/voting_tokens ne sont pas touchées.
-- =====================================================================

SET NAMES utf8mb4;

-- Un second tour est une élection à part entière (jetons, bulletins et
-- dépouillement propres), rattachée à son 1er tour. UNIQUE : un seul
-- second tour par élection, garanti par la base et pas seulement par
-- l'application.
ALTER TABLE elections
    ADD COLUMN first_round_id BIGINT UNSIGNED NULL AFTER rules_document_id,
    ADD UNIQUE KEY uq_elections_first_round (first_round_id),
    ADD CONSTRAINT fk_elections_first_round FOREIGN KEY (first_round_id) REFERENCES elections (id) ON DELETE RESTRICT;

-- Décision de la Commission sur un poste : au plus une par poste
-- (UNIQUE), jamais modifiée une fois enregistrée. reason : référence du
-- procès-verbal de la Commission, obligatoire. Les chiffres du vote ne
-- sont jamais modifiés : la décision s'affiche à côté d'eux.
-- Pas de colonne "décidé par" : une table qui référence à la fois un
-- candidat et un utilisateur est exactement ce que le garde-fou
-- d'anonymat (tests/Elections/SchemaAnonymityTest.php) interdit, sans
-- exception au cas par cas. L'admin qui enregistre est tracé dans
-- activity_logs ('election.commission_decision').
CREATE TABLE election_position_decisions (
    id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    election_position_id BIGINT UNSIGNED NOT NULL,
    candidate_id         BIGINT UNSIGNED NOT NULL,
    reason               TEXT            NOT NULL,
    decided_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_decision_position (election_position_id),
    KEY fk_decision_candidate (candidate_id),
    CONSTRAINT fk_decision_position  FOREIGN KEY (election_position_id) REFERENCES election_positions (id) ON DELETE CASCADE,
    CONSTRAINT fk_decision_candidate FOREIGN KEY (candidate_id) REFERENCES candidates (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
