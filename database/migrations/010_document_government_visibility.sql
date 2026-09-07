-- =====================================================================
-- FIUCC Portal — migration 010 : ajoute la visibilité "government" aux
-- documents (FIUCC-57/58, suite). Ne modifie pas 001_initial_schema.sql
-- (CLAUDE.md : "ne pas refondre").
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Un document peut être réservé aux membres du gouvernement en cours
-- (governments.is_current, déjà utilisé par Government::currentWithMembers()
-- pour /community/government) — un PV de réunion du bureau, par exemple.
-- Un membre du gouvernement reste par ailleurs un membre validé ordinaire :
-- 'government' est un niveau de plus, pas un remplacement de 'members'
-- (DocumentController::canAccess() — un membre du gouvernement voit aussi
-- tout ce qui est en visibility 'members').
-- ---------------------------------------------------------------------
ALTER TABLE documents
    MODIFY COLUMN visibility ENUM('public', 'members', 'government', 'admin') NOT NULL DEFAULT 'members';
