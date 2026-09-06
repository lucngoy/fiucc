-- =====================================================================
-- FIUCC Portal — migration 005 : faculté sur la fiche d'un membre du
-- gouvernement. Ne modifie pas 001_initial_schema.sql (CLAUDE.md : "ne
-- pas refondre").
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Utilisée par le bandeau de statistiques ("Faculties Represented") et
-- affichée sur la carte, comme members.faculty pour le recensement —
-- même logique, colonne propre à government_members (aucun lien
-- obligatoire à members, voir migration 004).
-- ---------------------------------------------------------------------
ALTER TABLE government_members
    ADD COLUMN faculty VARCHAR(120) NULL AFTER role;
