-- =====================================================================
-- FIUCC Portal — migration 008 : miniature générée à l'upload pour
-- chaque photo d'album (FIUCC-54). Ne modifie pas 001_initial_schema.sql
-- (CLAUDE.md : "ne pas refondre").
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Un vrai fichier miniature, généré une fois à l'upload (App\Services\
-- Upload::storeImageWithThumbnail()), pas un redimensionnement à la volée
-- à chaque affichage — une grille de plusieurs dizaines de photos en
-- pleine résolution serait inutilisable sur mobile/4G. NULL, pas NOT
-- NULL : une colonne obligatoire bloquerait cette migration si des lignes
-- existaient déjà sans miniature (pas le cas ici — aucun CRUD admin
-- n'écrit encore dans album_photos avant ce ticket — mais l'ALTER doit
-- rester sûr indépendamment de ça). Les vues retombent sur image_path
-- quand thumbnail_path est vide.
-- ---------------------------------------------------------------------
ALTER TABLE album_photos
    ADD COLUMN thumbnail_path VARCHAR(255) NULL AFTER image_path;
