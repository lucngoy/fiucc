-- =====================================================================
-- FIUCC Portal — migration 004 : Instagram sur la fiche d'un membre du
-- gouvernement. Ne modifie pas 001_initial_schema.sql (CLAUDE.md : "ne
-- pas refondre").
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- La carte gouvernement (section 12 du cahier des charges) affiche
-- explicitement un lien Instagram par membre — colonne absente du
-- schéma initial. government_members a déjà ses propres display_name/
-- photo, indépendants de members (un poste peut être archivé même sans
-- compte portail lié, member_id est nullable) : même logique ici.
-- ---------------------------------------------------------------------
ALTER TABLE government_members
    ADD COLUMN instagram VARCHAR(60) NULL AFTER photo;
