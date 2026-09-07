-- =====================================================================
-- FIUCC Portal — migration 007 : un album peut être rattaché à un
-- événement, et event_images disparaît au profit d'albums/album_photos.
-- Ne modifie pas 001_initial_schema.sql (CLAUDE.md : "ne pas refondre").
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Décision (conversation FIUCC-52/54) : le cahier des charges ne décrit
-- pas deux systèmes de photos distincts. §26 liste "Gallery" comme un
-- champ de l'événement ; §27 affiche "Photos après l'événement" sur sa
-- page ; §28 donne des exemples d'albums qui sont des noms d'événements
-- ("Congolese Day 2026"...). Un seul système : les photos d'un événement
-- SONT son album, présenté à la fois sur la page de l'événement et dans
-- la galerie parcourable par album (/gallery). event_id nullable : un
-- album reste possible sans événement précis (ex. "Football Match", une
-- partie informelle jamais créée comme Event). UNIQUE : au plus un album
-- par événement — "Gallery" est un champ singulier au §26, pas une liste.
-- ON DELETE SET NULL, pas CASCADE : supprimer la fiche d'un événement ne
-- doit pas emporter les photos avec elle — l'album survit, détaché,
-- toujours visible dans la galerie générale.
-- ---------------------------------------------------------------------
ALTER TABLE albums
    ADD COLUMN event_id BIGINT UNSIGNED NULL AFTER id,
    ADD UNIQUE KEY uq_albums_event (event_id),
    ADD CONSTRAINT fk_albums_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE SET NULL;

-- ---------------------------------------------------------------------
-- event_images n'a jamais eu de chemin d'écriture réel (aucun seeder,
-- aucun contrôleur admin construit avant cette migration) : la
-- supprimer ne perd aucune photo réelle. Event::images()/recentPhotos()
-- lisent désormais album_photos via albums.event_id.
-- ---------------------------------------------------------------------
DROP TABLE event_images;
