-- =====================================================================
-- FIUCC Portal — migration 013 : suivi de livraison et tentatives des
-- jetons de vote (FIUCC-64).
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- sent_at : distingue "un jeton existe" de "l'email a réellement abouti"
-- — App\Services\Mail::send() peut échouer (SMTP), et sans cette colonne
-- l'admin n'aurait aucun moyen de le savoir après coup ni de ne relancer
-- que les envois manquants (l'action d'envoi en masse est idempotente :
-- elle ne retraite que les membres sans sent_at).
--
-- attempts : même principe qu'email_verifications.attempts (OTP,
-- migration 001) — compteur de saisies incorrectes du code, plafonné par
-- security.vote_token_max_attempts. Remis à 0 par App\Models\
-- VotingToken::regenerate() : un jeton régénéré repart avec un budget de
-- tentatives neuf.
-- ---------------------------------------------------------------------
ALTER TABLE voting_tokens
    ADD COLUMN sent_at  DATETIME         NULL             AFTER expires_at,
    ADD COLUMN attempts TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER sent_at;
