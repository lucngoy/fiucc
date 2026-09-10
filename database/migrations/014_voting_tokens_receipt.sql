-- =====================================================================
-- FIUCC Portal — migration 014 : code de reçu de vote (FIUCC-66).
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- receipt_code : preuve remise au membre que son vote a été enregistré,
-- sans rien dire de son contenu (§21 du cahier des charges). Stocké EN
-- CLAIR, volontairement — contrairement à token_hash (règle 3), ce code
-- n'ouvre aucun droit : le connaître ne permet ni de voter à la place de
-- quelqu'un, ni de savoir pour qui il a voté. Ce n'est pas un secret à
-- protéger, seulement un identifiant à ne pas confondre entre deux
-- membres (d'où l'UNIQUE).
--
-- Écrit dans voting_tokens, jamais dans votes — la table dont le contenu
-- (candidate_id) ne doit jamais pouvoir se relier à un membre (règle 1).
-- Posé dans la même écriture que used_at (App\Models\Vote::castBallot()),
-- à l'instant précis où le jeton est consommé.
-- ---------------------------------------------------------------------
ALTER TABLE voting_tokens
    ADD COLUMN receipt_code VARCHAR(20) NULL AFTER used_at,
    ADD UNIQUE KEY uq_voting_tokens_receipt (receipt_code);
