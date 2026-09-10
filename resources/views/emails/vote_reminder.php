<?php
/**
 * Corps de l'email de rappel de vote (FIUCC-73) — mêmes contraintes que
 * resources/views/emails/vote_token.php (styles en ligne uniquement).
 *
 * AUCUN jeton dedans (décision explicite conversation FIUCC-73) : juste un
 * lien vers la page publique de l'élection. Un membre ayant perdu son code
 * passe par la régénération admin, jamais par cet email.
 *
 * @param array $election ['id' => int, 'title' => string]
 * @param string $link
 */
?>
<p style="margin:0 0 16px;"><?= t('mail.vote_reminder.intro', ['election' => e($election['title'])]) ?></p>

<p style="margin:0 0 24px;text-align:center;">
    <a href="<?= e($link) ?>" style="display:inline-block;padding:12px 24px;background-color:#0063C6;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:600;">
        <?= e(t('mail.vote_reminder.cta')) ?>
    </a>
</p>

<p style="margin:0;color:#64748B;font-size:13px;"><?= e(t('mail.vote_reminder.lost_code_note')) ?></p>
