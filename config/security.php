<?php
declare(strict_types=1);

use App\Core\Env;

return [
    'session_name'         => Env::get('SESSION_NAME', 'fiucc_session'),
    'session_lifetime'     => (int) Env::get('SESSION_LIFETIME', 120),
    'otp_ttl_minutes'      => (int) Env::get('OTP_TTL_MINUTES', 15),
    // Code invalidé après ce nombre d'essais incorrects, même si le bon code
    // est saisi juste après — il faut en redemander un (FIUCC-33).
    'otp_max_attempts'     => (int) Env::get('OTP_MAX_ATTEMPTS', 5),
    'otp_resend_cooldown_seconds' => (int) Env::get('OTP_RESEND_COOLDOWN_SECONDS', 60),
    'otp_max_resends_per_hour'    => (int) Env::get('OTP_MAX_RESENDS_PER_HOUR', 5),
    'vote_token_ttl_hours' => (int) Env::get('VOTE_TOKEN_TTL_HOURS', 72),
    'upload_max_size_mb'   => (int) Env::get('UPLOAD_MAX_SIZE_MB', 5),
    // Plus long côté, en pixels, après redimensionnement (FIUCC-38) — une
    // photo de profil n'a jamais besoin d'être plus grande que ça.
    'upload_photo_max_dimension' => (int) Env::get('UPLOAD_PHOTO_MAX_DIMENSION', 800),
    // Couverture d'événement (FIUCC-51) : affichée en pleine largeur de
    // bande (jusqu'à ~1200px), plus grande qu'une photo de profil carrée.
    'upload_event_cover_max_dimension' => (int) Env::get('UPLOAD_EVENT_COVER_MAX_DIMENSION', 1600),
    // Plafond de dimensions AVANT chargement GD (decompression bomb) : une
    // image bien compressée peut passer le contrôle de taille en octets tout
    // en explosant la mémoire une fois décodée en pixels par GD. Vérifié via
    // getimagesize() (lit l'en-tête, pas les pixels) avant tout appel GD.
    'upload_max_input_dimension' => (int) Env::get('UPLOAD_MAX_INPUT_DIMENSION', 5000),
    // Verrouillage dur — comptes admin uniquement (voir CLAUDE.md : un
    // verrouillage par compte seul permettrait de priver un membre de vote).
    'login_max_attempts'   => (int) Env::get('LOGIN_MAX_ATTEMPTS', 5),
    'login_lockout_minutes'=> (int) Env::get('LOGIN_LOCKOUT_MINUTES', 15),
    // Ralentissement progressif — comptes membre, par (compte, IP).
    'member_login_throttle_step_seconds' => (int) Env::get('MEMBER_LOGIN_THROTTLE_STEP_SECONDS', 5),
    'member_login_throttle_max_seconds'  => (int) Env::get('MEMBER_LOGIN_THROTTLE_MAX_SECONDS', 30),
    'password_min_length' => (int) Env::get('PASSWORD_MIN_LENGTH', 6),
];
