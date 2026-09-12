-- =====================================================================
-- FIUCC Portal — migration 016 : is_public opt-in par défaut (FIUCC-83).
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 001_initial_schema.sql posait `is_public TINYINT(1) NOT NULL DEFAULT 1`
-- avec un commentaire l'exemptant explicitement de la règle "opt-in,
-- jamais activé par défaut" — mais le cahier des charges dit l'inverse
-- sans exception pour l'annuaire : "chaque membre contrôle ce qui est
-- visible publiquement (opt-in, jamais par défaut)" (section Annuaire des
-- membres). La correction réelle du comportement était déjà dans
-- registration/create.php (la case à cocher se présentait pré-cochée pour
-- toute nouvelle inscription, corrigé dans ce même ticket) : aucun code
-- applicatif ne s'appuyait sur le DEFAULT de la colonne (Member::create()
-- fournit toujours explicitement les trois colonnes de confidentialité).
-- Ce DEFAULT est corrigé ici pour que le schéma cesse de documenter une
-- exception qui n'a jamais dû exister — cohérence, pas un changement de
-- comportement en soi.
-- ---------------------------------------------------------------------
ALTER TABLE members
    ALTER COLUMN is_public SET DEFAULT 0;

-- ---------------------------------------------------------------------
-- Rattrapage des membres déjà inscrits (décision explicite, conversation
-- FIUCC-83) : personne ne s'est réellement prononcé sur is_public tant
-- que la case se présentait pré-cochée — remettre à 0 aligne la base sur
-- l'intention réelle du cahier plutôt que de figer un choix que le membre
-- n'a jamais fait lui-même. Un membre déjà inscrit peut se rendre visible
-- en un clic depuis le nouvel écran de confidentialité (/profile/privacy).
-- ---------------------------------------------------------------------
UPDATE members SET is_public = 0;
