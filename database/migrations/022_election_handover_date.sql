-- =====================================================================
-- FIUCC Portal — migration 022 : date de passation d'une élection.
--
-- Règlement électoral 2026, art. 33 : la passation et l'entrée en
-- fonction sont fixées dans le calendrier électoral ; le mandat dure
-- toujours 1 an (décision du bureau, 2026-10-10). Le gouvernement installé
-- à la publication des résultats (Government::syncFromElectionResults())
-- prenait jusqu'ici les dates DU VOTE comme dates de mandat (mandat "2026"
-- du 15 au 20 septembre) : il part désormais de cette date de passation,
-- pour un an. NULL pour les élections créées avant cette migration —
-- repli sur la date de clôture du vote (Government::mandateFor()).
-- =====================================================================

SET NAMES utf8mb4;

ALTER TABLE elections
    ADD COLUMN handover_date DATE NULL AFTER end_at;
