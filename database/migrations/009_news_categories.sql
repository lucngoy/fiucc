-- =====================================================================
-- FIUCC Portal — migration 009 : élargit news.category pour couvrir les
-- catégories du cahier des charges (§29) en plus de celles déjà écrites
-- dans 001_initial_schema.sql. Ne modifie pas 001_initial_schema.sql
-- (CLAUDE.md : "ne pas refondre").
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Écart constaté (conversation FIUCC-55/56) entre le schéma déjà écrit
-- (announcement/event/urgent/general) et le cahier des charges §29
-- (Announcement/Community/Events/Sports/Culture/Education), qui fait
-- autorité en cas de contradiction (CLAUDE.md). Décision : fusion, pas
-- remplacement — 004_demo_news.php a déjà des lignes avec 'event' et
-- 'general', remplacer l'ENUM aurait cassé ces lignes existantes ou exigé
-- de les réécrire pour un gain qui n'était pas demandé.
--
-- Ça laisse 'event' (schéma) et 'events' (cahier) coexister, proches sans
-- être identiques : 'event' reste la valeur des lignes seedées avant cette
-- migration, 'events' est la catégorie que le formulaire admin (FIUCC-56)
-- proposera pour les nouveaux articles. Pas une élégance recherchée, un
-- compromis assumé pour ne perdre aucune donnée existante.
-- ---------------------------------------------------------------------
ALTER TABLE news
    MODIFY COLUMN category ENUM(
        'announcement', 'community', 'events', 'sports', 'culture', 'education',
        'event', 'urgent', 'general'
    ) NOT NULL DEFAULT 'general';
