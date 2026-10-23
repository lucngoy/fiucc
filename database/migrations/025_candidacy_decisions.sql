-- =====================================================================
-- FIUCC Portal — migration 025 : décisions sur les candidatures (lot 4 ;
-- règlement électoral 2026, art. 15 — "demande de régularisation").
--
-- Décisions du bureau (2026-10-10) : approuver, demander une correction
-- (avec motif et délai) ou rejeter (avec motif, définitif).
-- =====================================================================

SET NAMES utf8mb4;

-- 'correction' : correction demandée — le candidat peut compléter son
-- dossier jusqu'à correction_deadline, même après la clôture du dépôt ;
-- son envoi remet la candidature en 'pending' (corrected_at).
-- decision_reason : motif de la correction ou du rejet, montré au candidat
-- (mail + page "Ma candidature"). decided_at : dernière décision du bureau.
ALTER TABLE candidates
    MODIFY COLUMN status ENUM('pending','correction','approved','rejected','withdrawn') NOT NULL DEFAULT 'pending',
    ADD COLUMN decision_reason     TEXT     NULL AFTER submitted_at,
    ADD COLUMN correction_deadline DATETIME NULL AFTER decision_reason,
    ADD COLUMN decided_at          DATETIME NULL AFTER correction_deadline,
    ADD COLUMN corrected_at        DATETIME NULL AFTER decided_at;
