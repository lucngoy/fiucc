<?php
/**
 * Corps de l'email d'annonce de clôture (FIUCC-73, §31 du cahier) — mêmes
 * contraintes que resources/views/emails/vote_receipt.php : ne contient
 * RIEN du bulletin, seulement le fait que le scrutin est fermé (CLAUDE.md
 * règle 13). Envoyé une seule fois par membre (App\Models\VotingToken::
 * markClosureNotified()), jamais lié à ce que ce membre a voté ou non voté.
 *
 * @param array $election ['id' => int, 'title' => string]
 * @param string $link
 */
?>
<p style="margin:0 0 16px;"><?= t('mail.vote_closed.intro', ['election' => e($election['title'])]) ?></p>

<p style="margin:0 0 24px;text-align:center;">
    <a href="<?= e($link) ?>" style="display:inline-block;padding:12px 24px;background-color:#0063C6;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:600;">
        <?= e(t('mail.vote_closed.cta')) ?>
    </a>
</p>

<p style="margin:0;color:#64748B;font-size:13px;"><?= e(t('mail.vote_closed.outro')) ?></p>
