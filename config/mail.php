<?php
declare(strict_types=1);

use App\Core\Env;

return [
    // 'log' (défaut) : aucun email réel, écrit dans storage/logs/mail.log.
    // 'smtp' : envoi réel via PHPMailer. À positionner explicitement en prod.
    'driver'       => Env::get('MAIL_DRIVER', 'log'),
    'host'         => Env::get('MAIL_HOST'),
    'port'         => (int) Env::get('MAIL_PORT', 587),
    'encryption'   => Env::get('MAIL_ENCRYPTION', 'tls'),
    'username'     => Env::get('MAIL_USERNAME'),
    'password'     => Env::get('MAIL_PASSWORD'),
    'from_address' => Env::get('MAIL_FROM_ADDRESS'),
    'from_name'    => Env::get('MAIL_FROM_NAME', 'FIUCC Portal'),
];
