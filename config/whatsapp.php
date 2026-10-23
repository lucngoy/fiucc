<?php
declare(strict_types=1);

use App\Core\Env;

/**
 * Copie WhatsApp du code de vote envoyé par email (App\Services\WhatsApp), via
 * l'API WhatsApp de MailerSend. Même principe que config/mail.php : le
 * driver est explicite, et un oubli retombe sur 'log', jamais sur un envoi
 * réel.
 */
return [
    // 'log' (défaut) : aucun message réel, écrit dans storage/logs/whatsapp.log.
    // 'mailersend' : envoi réel via l'API MailerSend.
    'driver'  => Env::get('WHATSAPP_DRIVER', 'log'),
    // Jeton API MailerSend avec la permission "whatsapp_full" — jamais en dur.
    'api_key' => Env::get('MAILERSEND_API_KEY'),
    // Numéro expéditeur WhatsApp connecté dans MailerSend (chiffres seuls).
    'from'    => Env::get('WHATSAPP_FROM'),
    // Modèle "Utility" avec {{1}} pour le code de vote (26 caractères,
    // trop long pour un modèle "Authentication"). Vide = pas de copie
    // WhatsApp du code de vote, l'email reste le seul canal.
    'vote_template_id' => Env::get('WHATSAPP_VOTE_TEMPLATE_ID'),
    'timeout_seconds'  => (int) Env::get('WHATSAPP_TIMEOUT_SECONDS', 10),
];
