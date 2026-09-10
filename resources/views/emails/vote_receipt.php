<?php
/**
 * Corps de l'email de reçu de vote (FIUCC-67) — mêmes contraintes que
 * resources/views/emails/vote_token.php (styles en ligne uniquement).
 *
 * Ne contient RIEN du bulletin : ni poste, ni candidat, ni vote blanc —
 * seulement le fait d'avoir voté et le code de reçu (CLAUDE.md règle 13).
 *
 * @param array $election ['id' => int, 'title' => string]
 * @param string $receipt Code de reçu (App\Services\Base32::encode(), non groupé)
 */
?>
<?= t('mail.vote_receipt.intro', ['election' => e($election['title'])]) ?>

<div style="margin:0 0 20px;padding:20px;background-color:#F8FAFC;border:1px solid #E5E7EB;border-radius:8px;text-align:center;">
    <span style="display:block;font-family:'Courier New',Courier,monospace;font-size:22px;font-weight:700;letter-spacing:2px;color:#111827;word-break:break-all;">
        <?= e($receipt) ?>
    </span>
</div>

<p style="margin:0;color:#64748B;font-size:13px;"><?= e(t('mail.vote_receipt.security_note')) ?></p>
