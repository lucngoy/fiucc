<?php
/**
 * Layout d'email — styles en ligne uniquement (obligatoire pour les clients
 * mail, qui ignorent souvent les <style> et les CSS externes ; on ne peut
 * pas réutiliser le design system web tel quel).
 *
 * @param string $heading  Titre affiché dans l'en-tête
 * @param string $content  Corps HTML du message (fragment, pas un document complet)
 */
$logoUrl = rtrim((string) \App\Core\Config::get('app.url', ''), '/') . '/assets/images/fiucc-seal.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($heading) ?></title>
</head>
<body style="margin:0;padding:0;background-color:#F8FAFC;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F8FAFC;padding:24px 0;">
<tr>
<td align="center">
<table role="presentation" width="480" cellpadding="0" cellspacing="0" style="background-color:#FFFFFF;border-radius:12px;overflow:hidden;border:1px solid #E5E7EB;max-width:480px;">
<tr>
<td style="background-color:#0063C6;padding:24px;text-align:center;">
<img src="<?= e($logoUrl) ?>" alt="FIUCC" width="48" height="48" style="display:block;margin:0 auto 8px;border:0;">
<span style="color:#FFFFFF;font-size:18px;font-weight:700;font-family:Arial,Helvetica,sans-serif;">FIUCC</span>
</td>
</tr>
<tr>
<td style="padding:32px 24px;color:#111827;font-size:16px;line-height:1.5;font-family:Arial,Helvetica,sans-serif;">
<h1 style="font-size:20px;color:#111827;margin:0 0 16px;font-family:Arial,Helvetica,sans-serif;"><?= e($heading) ?></h1>
<?= $content ?>
</td>
</tr>
<tr>
<td style="padding:16px 24px;background-color:#F8FAFC;color:#64748B;font-size:12px;text-align:center;font-family:Arial,Helvetica,sans-serif;">
Congolese Community of Final International University
</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>
