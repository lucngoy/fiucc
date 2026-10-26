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
    // Formulaire /contact (§39, "rate limiting") : au plus N messages par
    // IP sur la fenêtre glissante (contact_messages, migration 021).
    // Dossier de candidature (migration 024) : taille maximale par pièce,
    // et dimension maximale des photos ré-encodées.
    'candidacy_file_max_size_mb'   => (int) Env::get('CANDIDACY_FILE_MAX_SIZE_MB', 8),
    'candidacy_photo_max_dimension' => (int) Env::get('CANDIDACY_PHOTO_MAX_DIMENSION', 2000),
    'contact_max_messages'       => (int) Env::get('CONTACT_MAX_MESSAGES', 3),
    'contact_window_minutes'     => (int) Env::get('CONTACT_WINDOW_MINUTES', 15),
    // Demandes d'aide (/help, migration 028) : au plus N par membre sur 24 h.
    'help_max_requests_per_day'  => (int) Env::get('HELP_MAX_REQUESTS_PER_DAY', 3),
    'vote_token_ttl_hours' => (int) Env::get('VOTE_TOKEN_TTL_HOURS', 72),
    // Tentatives de saisie du code de vote (FIUCC-64) avant verrouillage —
    // même principe qu'otp_max_attempts : au-delà, même un code correct est
    // refusé, il faut passer par une régénération admin.
    'vote_token_max_attempts' => (int) Env::get('VOTE_TOKEN_MAX_ATTEMPTS', 5),
    'upload_max_size_mb'   => (int) Env::get('UPLOAD_MAX_SIZE_MB', 15),
    // Plus long côté, en pixels, après redimensionnement (FIUCC-38) — une
    // photo de profil n'a jamais besoin d'être plus grande que ça.
    'upload_photo_max_dimension' => (int) Env::get('UPLOAD_PHOTO_MAX_DIMENSION', 800),
    // Couverture d'événement (FIUCC-51) : affichée en pleine largeur de
    // bande (jusqu'à ~1200px), plus grande qu'une photo de profil carrée.
    'upload_event_cover_max_dimension' => (int) Env::get('UPLOAD_EVENT_COVER_MAX_DIMENSION', 1600),
    // Galerie (FIUCC-54) : taille "grande" servie par la visionneuse
    // (FIUCC-53) — même ordre de grandeur qu'une couverture d'événement.
    'upload_gallery_photo_max_dimension' => (int) Env::get('UPLOAD_GALLERY_PHOTO_MAX_DIMENSION', 1600),
    // Miniature générée en même temps que la photo, un seul décodage —
    // sert la grille d'albums/photos, jamais l'original redimensionné en
    // CSS (inutilisable sur une grille de plusieurs dizaines de photos en
    // 4G).
    'upload_gallery_thumbnail_dimension' => (int) Env::get('UPLOAD_GALLERY_THUMBNAIL_DIMENSION', 400),
    // Couverture d'article (FIUCC-56) : même ordre de grandeur qu'une
    // couverture d'événement — affichée en pleine largeur sur la page
    // détail d'un article.
    'upload_news_cover_max_dimension' => (int) Env::get('UPLOAD_NEWS_COVER_MAX_DIMENSION', 1600),
    // Photo de candidat (FIUCC-61) : même format qu'une photo de profil
    // (portrait, affiché en carte sur la page des candidats), pas une
    // couverture pleine largeur.
    'upload_candidate_photo_max_dimension' => (int) Env::get('UPLOAD_CANDIDATE_PHOTO_MAX_DIMENSION', 800),
    // Logo partenaire (FIUCC-90) : même ordre de grandeur qu'une photo de
    // candidat — un logo carré affiché en carte, jamais une bannière pleine
    // largeur.
    'upload_partner_logo_max_dimension' => (int) Env::get('UPLOAD_PARTNER_LOGO_MAX_DIMENSION', 800),
    // Documents (FIUCC-58) : plafond distinct de upload_max_size_mb, même
    // valeur ici — un règlement ou un rapport scanné a les mêmes contraintes
    // de taille qu'une photo (envoi mobile, mémoire du script).
    'upload_document_max_size_mb' => (int) Env::get('UPLOAD_DOCUMENT_MAX_SIZE_MB', 15),
    // Plafond de dimensions AVANT chargement GD (decompression bomb) : une
    // image bien compressée peut passer le contrôle de taille en octets tout
    // en explosant la mémoire une fois décodée en pixels par GD. Vérifié via
    // getimagesize() (lit l'en-tête, pas les pixels) avant tout appel GD.
    'upload_max_input_dimension' => (int) Env::get('UPLOAD_MAX_INPUT_DIMENSION', 5000),
    // Dossiers de destination d'App\Services\Upload — absents (null) partout
    // sauf .env.testing (FIUCC-79) : sans ça, un test d'upload écrirait dans
    // le vrai public/uploads/ servi par Apache. Chemin relatif à BASE_PATH
    // si non absolu (voir Upload::imagesBasePath()/storageBasePath()) ; la
    // valeur par défaut (BASE_PATH . '/public/uploads' ou '/storage') reste
    // inchangée en dev et en production tant que ces variables ne sont pas
    // définies.
    'upload_images_base_path'  => Env::get('UPLOAD_IMAGES_BASE_PATH'),
    'upload_storage_base_path' => Env::get('UPLOAD_STORAGE_BASE_PATH'),
    // Même principe pour App\Services\Backup (FIUCC-80) : sans ça, un test
    // de sauvegarde écrirait/lirait dans le vrai storage/backups/.
    'backup_base_path' => Env::get('BACKUP_BASE_PATH'),
    // Verrouillage dur — comptes admin uniquement (voir CLAUDE.md : un
    // verrouillage par compte seul permettrait de priver un membre de vote).
    'login_max_attempts'   => (int) Env::get('LOGIN_MAX_ATTEMPTS', 5),
    'login_lockout_minutes'=> (int) Env::get('LOGIN_LOCKOUT_MINUTES', 15),
    // Ralentissement progressif — comptes membre, par (compte, IP).
    'member_login_throttle_step_seconds' => (int) Env::get('MEMBER_LOGIN_THROTTLE_STEP_SECONDS', 5),
    'member_login_throttle_max_seconds'  => (int) Env::get('MEMBER_LOGIN_THROTTLE_MAX_SECONDS', 30),
    // 8, pas 6 (FIUCC-77) : plancher NIST SP 800-63B pour un mot de passe
    // sans exigence de composition — un compte membre donne accès à des
    // données personnelles (annuaire) et, pour un électeur, à un jeton de
    // vote envoyé par email au même compte.
    'password_min_length' => (int) Env::get('PASSWORD_MIN_LENGTH', 8),
    // Lien "mot de passe oublié" (migration 019) : usage unique, durée
    // courte. Plafond horaire par compte — au-delà, la demande est ignorée
    // en silence (même réponse affichée), pour qu'on ne puisse pas inonder
    // la boîte d'un membre de liens.
    'password_reset_ttl_minutes'   => (int) Env::get('PASSWORD_RESET_TTL_MINUTES', 60),
    'password_reset_max_per_hour'  => (int) Env::get('PASSWORD_RESET_MAX_PER_HOUR', 3),
];
