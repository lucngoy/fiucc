-- =====================================================================
-- FIUCC Portal — migration 011 : colonne "details" sur activity_logs
-- (FIUCC-59). Ne modifie pas 001_initial_schema.sql (CLAUDE.md : "ne pas
-- refondre").
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Nécessaire pour journaliser la prolongation de end_at pendant qu'une
-- élection est 'open' (seule exception au verrouillage de configuration,
-- décision explicite conversation FIUCC-59) : action/entity/entity_id
-- seuls ne suffisent pas à retrouver l'ancienne et la nouvelle date sans
-- cette colonne. Texte libre, pas structuré (JSON) — le seul usage actuel
-- est un message d'audit lisible, pas une donnée requêtée.
-- ---------------------------------------------------------------------
ALTER TABLE activity_logs
    ADD COLUMN details TEXT NULL AFTER entity_id;
