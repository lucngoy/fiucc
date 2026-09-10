-- =====================================================================
-- FIUCC Portal — migration 015 : rappel de vote et annonce de clôture
-- (FIUCC-73).
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- last_reminder_sent_at : throttle du rappel manuel, au niveau de
-- l'élection (pas par membre — le rappel s'adresse à tout le monde n'ayant
-- pas encore voté à la fois, un seul délai pour l'ensemble de l'envoi).
-- La garde elle-même (12h) vit dans App\Models\Election::recordReminderSent(),
-- directement dans l'UPDATE — cette colonne n'est qu'un horodatage, pas une
-- règle.
-- ---------------------------------------------------------------------
ALTER TABLE elections
    ADD COLUMN last_reminder_sent_at DATETIME NULL AFTER results_published_at;

-- ---------------------------------------------------------------------
-- closure_notified_at : suivi par membre de l'annonce "le vote est fermé"
-- (§31 du cahier des charges) — permet à l'envoi en masse d'être
-- idempotent, comme sent_at pour le jeton de vote (migration 013) : ne
-- retraite jamais un membre déjà notifié, rejouable pour couvrir les
-- échecs SMTP.
-- ---------------------------------------------------------------------
ALTER TABLE voting_tokens
    ADD COLUMN closure_notified_at DATETIME NULL AFTER receipt_code;
