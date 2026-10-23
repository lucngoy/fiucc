-- =====================================================================
-- FIUCC Portal — migration 027 : date de publication de la liste des
-- candidats (règlement électoral 2026, art. 8 — "publication de la liste
-- des candidats" ; décisions du bureau 2026-10-21).
--
-- Avant cette date, la liste des candidats n'est pas publique (bouton
-- "Voir les candidats" et page /elections/{id}/candidates) ; à partir de
-- cette date, elle s'affiche d'elle-même. Placée après la date limite des
-- candidatures, au plus tard à l'ouverture du vote. Obligatoire pour une
-- nouvelle élection (contrôlé par le formulaire) ; NULL pour les élections
-- antérieures à cette migration, qui gardent leur comportement : liste
-- visible dès qu'un candidat est approuvé.
-- =====================================================================

SET NAMES utf8mb4;

ALTER TABLE elections
    ADD COLUMN candidate_list_at DATETIME NULL AFTER candidacy_deadline;
