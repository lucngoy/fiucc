-- =====================================================================
-- FIUCC Portal — schéma initial
-- MySQL 8.0+ / MariaDB 10.6+  ·  InnoDB  ·  utf8mb4
-- Cahier des charges, section 33 (+ champs de la vision hub, section 1.3)
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ---------------------------------------------------------------------
-- users : comptes d'authentification
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email             VARCHAR(190)    NOT NULL,
    password_hash     VARCHAR(255)    NOT NULL,
    role              ENUM('admin','member') NOT NULL DEFAULT 'member',
    last_login_at     DATETIME        NULL,
    failed_attempts   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until      DATETIME        NULL,
    created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- members : fiche de recensement
-- status       = état administratif du dossier
-- status_type  = position dans le cycle de vie (étudiant / alumni)
-- ---------------------------------------------------------------------
CREATE TABLE members (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id           BIGINT UNSIGNED NOT NULL,
    membership_number VARCHAR(20)     NOT NULL,
    full_name         VARCHAR(150)    NOT NULL,
    student_id        VARCHAR(50)     NOT NULL,
    date_of_birth     DATE            NULL,
    gender            ENUM('male','female','other') NULL,
    city_of_origin    VARCHAR(100)    NULL,
    faculty           VARCHAR(120)    NULL,
    department        VARCHAR(120)    NULL,
    year              TINYINT UNSIGNED NULL,
    arrival_year      SMALLINT UNSIGNED NULL,
    graduation_year   SMALLINT UNSIGNED NULL,
    phone             VARCHAR(30)     NULL,
    whatsapp          VARCHAR(30)     NULL,
    instagram         VARCHAR(60)     NULL,
    photo             VARCHAR(255)    NULL,

    -- cycle de vie (section 1.3 du cahier des charges)
    status_type       ENUM('student','alumni','honorary') NOT NULL DEFAULT 'student',
    profession        VARCHAR(150)    NULL,
    company           VARCHAR(150)    NULL,
    city              VARCHAR(100)    NULL,
    country           VARCHAR(100)    NULL,

    -- confidentialité : opt-in, jamais activé par défaut sauf is_public
    is_public         TINYINT(1)      NOT NULL DEFAULT 1,
    show_email        TINYINT(1)      NOT NULL DEFAULT 0,
    show_phone        TINYINT(1)      NOT NULL DEFAULT 0,

    -- mentorat (phase 6)
    is_mentor         TINYINT(1)      NOT NULL DEFAULT 0,
    mentor_field      VARCHAR(120)    NULL,
    mentor_availability VARCHAR(60)   NULL,
    mentor_bio        TEXT            NULL,

    status            ENUM('pending','validated','rejected','suspended') NOT NULL DEFAULT 'pending',
    rejection_reason  VARCHAR(255)    NULL,
    validated_by      BIGINT UNSIGNED NULL,
    validated_at      DATETIME        NULL,
    email_verified_at DATETIME        NULL,
    created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_members_user (user_id),
    UNIQUE KEY uq_members_student_id (student_id),
    UNIQUE KEY uq_members_number (membership_number),
    KEY idx_members_status (status),
    KEY idx_members_status_type (status_type),
    KEY idx_members_faculty (faculty),
    KEY idx_members_graduation (graduation_year),
    CONSTRAINT fk_members_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_members_validator FOREIGN KEY (validated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- email_verifications : codes OTP (hashés, jamais en clair)
-- ---------------------------------------------------------------------
CREATE TABLE email_verifications (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id       BIGINT UNSIGNED NOT NULL,
    code_hash     VARCHAR(255)    NOT NULL,
    attempts      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    consumed_at   DATETIME        NULL,
    expires_at    DATETIME        NOT NULL,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ev_user (user_id),
    CONSTRAINT fk_ev_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- governments / government_members : mandats et bureaux successifs
-- ---------------------------------------------------------------------
CREATE TABLE governments (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    mandate     VARCHAR(20)     NOT NULL,          -- ex. "2025-2026"
    start_date  DATE            NULL,
    end_date    DATE            NULL,
    is_current  TINYINT(1)      NOT NULL DEFAULT 0,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_gov_mandate (mandate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE government_members (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    government_id  BIGINT UNSIGNED NOT NULL,
    member_id      BIGINT UNSIGNED NULL,
    role           VARCHAR(100)    NOT NULL,
    display_name   VARCHAR(150)    NOT NULL,
    photo          VARCHAR(255)    NULL,
    sort_order     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_govmem_gov (government_id),
    CONSTRAINT fk_govmem_gov FOREIGN KEY (government_id) REFERENCES governments (id) ON DELETE CASCADE,
    CONSTRAINT fk_govmem_member FOREIGN KEY (member_id) REFERENCES members (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- events / event_images / event_registrations
-- ---------------------------------------------------------------------
CREATE TABLE events (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title        VARCHAR(200)    NOT NULL,
    slug         VARCHAR(220)    NOT NULL,
    description  MEDIUMTEXT      NULL,
    category     VARCHAR(60)     NULL,
    location     VARCHAR(200)    NULL,
    start_date   DATETIME        NOT NULL,
    end_date     DATETIME        NULL,
    cover_image  VARCHAR(255)    NULL,
    capacity     SMALLINT UNSIGNED NULL,
    status       ENUM('draft','published','archived','cancelled') NOT NULL DEFAULT 'draft',
    created_by   BIGINT UNSIGNED NULL,
    created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_events_slug (slug),
    KEY idx_events_start (start_date),
    KEY idx_events_status (status),
    CONSTRAINT fk_events_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE event_images (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id    BIGINT UNSIGNED NOT NULL,
    image_path  VARCHAR(255)    NOT NULL,
    caption     VARCHAR(255)    NULL,
    sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_evimg_event (event_id),
    CONSTRAINT fk_evimg_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE event_registrations (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id    BIGINT UNSIGNED NOT NULL,
    member_id   BIGINT UNSIGNED NOT NULL,
    status      ENUM('going','cancelled','attended') NOT NULL DEFAULT 'going',
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_evreg (event_id, member_id),
    CONSTRAINT fk_evreg_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE,
    CONSTRAINT fk_evreg_member FOREIGN KEY (member_id) REFERENCES members (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- news / documents
-- ---------------------------------------------------------------------
CREATE TABLE news (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title         VARCHAR(200)    NOT NULL,
    slug          VARCHAR(220)    NOT NULL,
    excerpt       VARCHAR(500)    NULL,
    content       MEDIUMTEXT      NULL,
    cover_image   VARCHAR(255)    NULL,
    category      ENUM('announcement','event','urgent','general') NOT NULL DEFAULT 'general',
    author_id     BIGINT UNSIGNED NULL,
    published_at  DATETIME        NULL,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_news_slug (slug),
    KEY idx_news_published (published_at),
    CONSTRAINT fk_news_author FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE documents (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title         VARCHAR(200)    NOT NULL,
    description   VARCHAR(500)    NULL,
    file_path     VARCHAR(255)    NOT NULL,
    file_size     INT UNSIGNED    NULL,
    mime_type     VARCHAR(100)    NULL,
    category      VARCHAR(60)     NULL,
    visibility    ENUM('public','members','admin') NOT NULL DEFAULT 'members',
    uploaded_by   BIGINT UNSIGNED NULL,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_docs_uploader FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- ÉLECTIONS
-- ---------------------------------------------------------------------
CREATE TABLE elections (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title         VARCHAR(200)    NOT NULL,
    description   TEXT            NULL,
    start_at      DATETIME        NOT NULL,
    end_at        DATETIME        NOT NULL,
    status        ENUM('draft','candidacies','open','closed','published') NOT NULL DEFAULT 'draft',
    results_published_at DATETIME NULL,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_elections_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE election_positions (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    election_id  BIGINT UNSIGNED NOT NULL,
    name         VARCHAR(120)    NOT NULL,
    description  TEXT            NULL,
    max_votes    TINYINT UNSIGNED NOT NULL DEFAULT 1,
    sort_order   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_pos_election (election_id),
    CONSTRAINT fk_pos_election FOREIGN KEY (election_id) REFERENCES elections (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE candidates (
    id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    election_position_id  BIGINT UNSIGNED NOT NULL,
    member_id             BIGINT UNSIGNED NOT NULL,
    ballot_number         TINYINT UNSIGNED NULL,
    bio                   TEXT            NULL,
    manifesto             MEDIUMTEXT      NULL,
    photo                 VARCHAR(255)    NULL,
    status                ENUM('pending','approved','rejected','withdrawn') NOT NULL DEFAULT 'pending',
    created_at            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_candidate_position_member (election_position_id, member_id),
    CONSTRAINT fk_cand_position FOREIGN KEY (election_position_id) REFERENCES election_positions (id) ON DELETE CASCADE,
    CONSTRAINT fk_cand_member FOREIGN KEY (member_id) REFERENCES members (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- voting_tokens : QUI a le droit de voter et QUI a déjà voté.
-- Ne contient aucun contenu de bulletin.
-- ---------------------------------------------------------------------
CREATE TABLE voting_tokens (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    election_id  BIGINT UNSIGNED NOT NULL,
    member_id    BIGINT UNSIGNED NOT NULL,
    token_hash   CHAR(64)        NOT NULL,     -- SHA-256 du token, jamais le token en clair
    used_at      DATETIME        NULL,
    expires_at   DATETIME        NOT NULL,
    created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_token_election_member (election_id, member_id),  -- un seul token par membre et par élection
    UNIQUE KEY uq_token_hash (token_hash),
    CONSTRAINT fk_token_election FOREIGN KEY (election_id) REFERENCES elections (id) ON DELETE CASCADE,
    CONSTRAINT fk_token_member FOREIGN KEY (member_id) REFERENCES members (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- votes : CE QUI a été voté. Aucun member_id, aucune référence au token,
-- aucun horodatage précis permettant un recoupement.
-- Le lien entre l'électeur et son bulletin ne doit exister nulle part.
-- ---------------------------------------------------------------------
CREATE TABLE votes (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    election_id   BIGINT UNSIGNED NOT NULL,
    position_id   BIGINT UNSIGNED NOT NULL,
    candidate_id  BIGINT UNSIGNED NULL,        -- NULL = vote blanc
    vote_hash     CHAR(64)        NOT NULL,    -- intégrité du bulletin, non réversible vers un membre
    created_at    DATE            NOT NULL,    -- volontairement une DATE, pas un DATETIME
    PRIMARY KEY (id),
    KEY idx_votes_election (election_id),
    KEY idx_votes_position (position_id),
    KEY idx_votes_candidate (candidate_id),
    CONSTRAINT fk_votes_election FOREIGN KEY (election_id) REFERENCES elections (id) ON DELETE CASCADE,
    CONSTRAINT fk_votes_position FOREIGN KEY (position_id) REFERENCES election_positions (id) ON DELETE CASCADE,
    CONSTRAINT fk_votes_candidate FOREIGN KEY (candidate_id) REFERENCES candidates (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- mentorships : phase 6
-- ---------------------------------------------------------------------
CREATE TABLE mentorships (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    mentor_id   BIGINT UNSIGNED NOT NULL,
    student_id  BIGINT UNSIGNED NOT NULL,
    topic       VARCHAR(150)    NULL,
    message     TEXT            NULL,
    status      ENUM('pending','accepted','declined','completed') NOT NULL DEFAULT 'pending',
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_mentorship_mentor (mentor_id),
    KEY idx_mentorship_student (student_id),
    CONSTRAINT fk_mentorship_mentor FOREIGN KEY (mentor_id) REFERENCES members (id) ON DELETE CASCADE,
    CONSTRAINT fk_mentorship_student FOREIGN KEY (student_id) REFERENCES members (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- activity_logs : traçabilité (section 48)
-- ---------------------------------------------------------------------
CREATE TABLE activity_logs (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     BIGINT UNSIGNED NULL,
    action      VARCHAR(100)    NOT NULL,
    entity      VARCHAR(60)     NULL,
    entity_id   BIGINT UNSIGNED NULL,
    ip_address  VARBINARY(16)   NULL,
    user_agent  VARCHAR(255)    NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_logs_user (user_id),
    KEY idx_logs_action (action),
    CONSTRAINT fk_logs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- RÈGLES NON NÉGOCIABLES
-- 1. Ne jamais ajouter member_id (ni aucune clé vers members) à la table votes.
-- 2. L'écriture d'un bulletin et le marquage du token se font dans deux
--    opérations distinctes, sans identifiant commun.
-- 3. Les tokens sont stockés hashés, jamais en clair.
-- 4. Le droit de vote se déduit de members.status = 'validated'
--    ET members.status_type = 'student'. Un alumni reste membre sans être électeur.
-- =====================================================================
