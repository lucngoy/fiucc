<?php
declare(strict_types=1);

use App\Core\Env;

return [
    'session_name'         => Env::get('SESSION_NAME', 'fiucc_session'),
    'session_lifetime'     => (int) Env::get('SESSION_LIFETIME', 120),
    'otp_ttl_minutes'      => (int) Env::get('OTP_TTL_MINUTES', 15),
    'vote_token_ttl_hours' => (int) Env::get('VOTE_TOKEN_TTL_HOURS', 72),
    'upload_max_size_mb'   => (int) Env::get('UPLOAD_MAX_SIZE_MB', 5),
    // Verrouillage dur — comptes admin uniquement (voir CLAUDE.md : un
    // verrouillage par compte seul permettrait de priver un membre de vote).
    'login_max_attempts'   => (int) Env::get('LOGIN_MAX_ATTEMPTS', 5),
    'login_lockout_minutes'=> (int) Env::get('LOGIN_LOCKOUT_MINUTES', 15),
    // Ralentissement progressif — comptes membre, par (compte, IP).
    'member_login_throttle_step_seconds' => (int) Env::get('MEMBER_LOGIN_THROTTLE_STEP_SECONDS', 5),
    'member_login_throttle_max_seconds'  => (int) Env::get('MEMBER_LOGIN_THROTTLE_MAX_SECONDS', 30),
    'password_min_length' => (int) Env::get('PASSWORD_MIN_LENGTH', 6),
];
