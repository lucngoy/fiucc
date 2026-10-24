<?php
/**
 * Corps de l'email du jeton de vote (FIUCC-64) — fragment injecté dans
 * resources/views/emails/layout.php, mêmes contraintes (styles en ligne
 * uniquement, aucun client mail ne garantit le CSS externe).
 *
 * Le code est affiché en texte simple, sélectionnable, pas dans un bouton
 * "copier" scripté : `<script>`/`onclick` sont filtrés par la quasi-totalité
 * des clients mail (webmail compris, pas seulement mobile) — un bouton actif
 * ne ferait rien silencieusement. La sélection manuelle native (appui long /
 * triple-clic) fonctionne partout, sans JavaScript.
 *
 * Le lien vers la page de vote ne porte jamais le code en paramètre — voir
 * CLAUDE.md, règle 3, et la discussion FIUCC-64 : le code ne doit jamais
 * apparaître dans une URL (historique, logs Apache, Referer). Le membre le
 * saisit lui-même sur une page rendue dans sa propre session, où un jeton
 * CSRF valide peut exister — impossible à produire à l'avance dans un email
 * statique.
 *
 * @param array $election  ['id' => int, 'title' => string]
 * @param string $code     Code Base32 déjà groupé (App\Services\Base32::group())
 * @param string $link     URL de /elections/{id}/authenticate, sans requête
 * @param string $expiresAt Date lisible (local_date()), déjà formatée par l'appelant
 * @param array{date: string, time: string}|null $opensAt Ouverture du vote si le code est envoyé à l'avance, sinon null
 */
$opensAt ??= null;
?>
<p style="margin:0 0 16px;"><?= t('mail.vote_token.intro', ['election' => e($election['title'])]) ?></p>
<?php if ($opensAt !== null): ?>
<p style="margin:0 0 16px;font-weight:700;"><?= e(t('mail.vote_token.opens_at', $opensAt)) ?></p>
<?php endif; ?>

<div style="margin:0 0 20px;padding:20px;background-color:#F8FAFC;border:1px solid #E5E7EB;border-radius:8px;text-align:center;">
    <span style="display:block;font-family:'Courier New',Courier,monospace;font-size:22px;font-weight:700;letter-spacing:2px;color:#111827;word-break:break-all;">
        <?= e($code) ?>
    </span>
</div>

<p style="margin:0 0 20px;color:#64748B;font-size:14px;"><?= e(t('mail.vote_token.expiry', ['date' => $expiresAt])) ?></p>

<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 20px;">
<tr>
<td style="border-radius:8px;background-color:#0063C6;">
<a href="<?= e($link) ?>" style="display:inline-block;padding:12px 24px;color:#FFFFFF;font-size:16px;font-weight:700;text-decoration:none;font-family:Arial,Helvetica,sans-serif;">
<?= e(t('mail.vote_token.link_label')) ?>
</a>
</td>
</tr>
</table>

<p style="margin:0;color:#64748B;font-size:13px;"><?= e(t('mail.vote_token.security_note')) ?></p>
