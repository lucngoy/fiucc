-- =====================================================================
-- FIUCC Portal — migration 018 : fiche de recensement étendue.
--
-- Extension du §13 du cahier des charges, décidée explicitement par
-- l'utilisateur (2026-10-09) : aligner le recensement sur la fiche
-- démographique de la Communauté Nationale Congolaise de Chypre du Nord
-- (congolais-chypre.org/recensement). Ne modifie pas 001_initial_schema.sql
-- (CLAUDE.md : "ne pas refondre").
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Nom / Post-nom / Prénom : le post-nom est propre à l'état civil
-- congolais, un champ "nom complet" unique ne permet pas de le retrouver.
-- full_name est conservé tel quel : il reste la colonne affichée partout
-- (annuaire, admin, emails, recherche) et est recalculé à l'inscription à
-- partir des trois parties. NULL pour les membres inscrits avant cette
-- migration — on ne tente pas de découper un full_name existant, l'ordre
-- des mots n'y est pas fiable.
-- ---------------------------------------------------------------------
ALTER TABLE members
    ADD COLUMN last_name   VARCHAR(80) NULL AFTER full_name,
    ADD COLUMN middle_name VARCHAR(80) NULL AFTER last_name,
    ADD COLUMN first_name  VARCHAR(80) NULL AFTER middle_name;

-- ---------------------------------------------------------------------
-- Statut dans la communauté (étudiant, élève, travailleur...) : distinct
-- de status_type (student/alumni/honorary), qui pilote le cycle de vie et
-- le droit de vote (CLAUDE.md, règle 8). community_role n'a AUCUN effet
-- sur l'éligibilité, c'est une information de recensement uniquement.
-- Valeurs : config/registration.php ('community_roles').
-- ---------------------------------------------------------------------
ALTER TABLE members
    ADD COLUMN community_role VARCHAR(20) NULL AFTER status_type;

-- ---------------------------------------------------------------------
-- Résidence en Chypre du Nord : visible par l'admin uniquement, jamais
-- dans l'annuaire ni le profil public. district stocke une clé de
-- config/registration.php ('districts'), pas un libellé.
-- ---------------------------------------------------------------------
ALTER TABLE members
    ADD COLUMN address_street    VARCHAR(150) NULL AFTER country,
    ADD COLUMN address_building  VARCHAR(100) NULL AFTER address_street,
    ADD COLUMN address_apartment VARCHAR(20)  NULL AFTER address_building,
    ADD COLUMN district          VARCHAR(30)  NULL AFTER address_apartment;

-- ---------------------------------------------------------------------
-- Passeport : données d'identité sensibles, admin uniquement.
-- passport_scan est un chemin relatif à storage/ (jamais public/uploads/) :
-- le fichier n'est atteignable que par Admin\MemberController::passportScan(),
-- derrière AdminMiddleware, et chaque consultation est journalisée.
-- ---------------------------------------------------------------------
ALTER TABLE members
    ADD COLUMN passport_number VARCHAR(30)  NULL AFTER district,
    ADD COLUMN passport_expiry DATE         NULL AFTER passport_number,
    ADD COLUMN passport_scan   VARCHAR(255) NULL AFTER passport_expiry;

-- ---------------------------------------------------------------------
-- Culte / confession : facultatif, donnée sensible (catégorie spéciale
-- RGPD/KVKK). Admin uniquement, jamais exportée en CSV, jamais affichée
-- ailleurs. Clé de config/registration.php ('religions').
-- ---------------------------------------------------------------------
ALTER TABLE members
    ADD COLUMN religion VARCHAR(30) NULL AFTER passport_scan;
