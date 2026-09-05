-- =====================================================================
-- FIUCC Portal — migration 003 : raison de suspension d'un membre.
-- Ne modifie pas 001_initial_schema.sql (CLAUDE.md : "ne pas refondre").
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- rejection_reason (001_initial_schema.sql) ne couvre que le rejet d'une
-- inscription. La suspension d'un membre déjà validé est un cas distinct
-- (ex. comportement contraire à la charte) qui mérite sa propre raison,
-- indépendante d'un éventuel rejet antérieur.
-- ---------------------------------------------------------------------
ALTER TABLE members
    ADD COLUMN suspension_reason VARCHAR(255) NULL AFTER rejection_reason;
