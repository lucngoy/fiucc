-- =====================================================================
-- FIUCC Portal — migration 012 : ballot_number unique par poste
-- (FIUCC-61). Ne modifie pas 001_initial_schema.sql (CLAUDE.md : "ne pas
-- refondre").
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Deux candidats au numéro 3 sur le même poste rendraient un
-- dépouillement contestable — imposé en base, pas seulement en
-- validation applicative (décision explicite, conversation FIUCC-61).
-- MySQL/InnoDB tolère nativement plusieurs NULL sur un index UNIQUE
-- (NULL n'est jamais égal à NULL) : ballot_number reste facultatif sans
-- logique particulière à écrire pour ça.
-- ---------------------------------------------------------------------
ALTER TABLE candidates
    ADD CONSTRAINT uq_candidate_position_ballot UNIQUE (election_position_id, ballot_number);
