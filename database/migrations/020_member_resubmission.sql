-- =====================================================================
-- FIUCC Portal — migration 020 : renvoi d'un dossier refusé.
--
-- Décision utilisateur (2026-10-09) : un membre refusé voit le motif (mail
-- + site), corrige son profil et renvoie son dossier, ce qui prévient les
-- admins. resubmitted_at marque ce renvoi tant qu'aucune nouvelle décision
-- n'a été prise (remis à NULL par Member::validate()/reject()) : il sert à
-- signaler le dossier dans la liste admin, distinct d'une première demande.
--
-- Conservation de la photo du passeport (90 jours après la décision,
-- App\Services\PassportScanRetention) : aucune colonne nécessaire —
-- validated_at est déjà la date de la dernière décision admin (posée par
-- validate(), reject() et suspend()).
-- =====================================================================

SET NAMES utf8mb4;

ALTER TABLE members
    ADD COLUMN resubmitted_at DATETIME NULL AFTER rejection_reason;
