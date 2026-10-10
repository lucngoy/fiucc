-- =====================================================================
-- FIUCC Portal — migration 021 : page contact (§39 du cahier des
-- charges) et coordonnées modifiables depuis le back office.
-- Ne modifie pas 001_initial_schema.sql (CLAUDE.md : "ne pas refondre").
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- site_settings : réglages éditables par un admin, en clé/valeur. Première
-- utilisation : les coordonnées de la communauté (email, téléphone,
-- WhatsApp, Instagram, adresse, horaires, urgence — liste fermée dans
-- App\Services\ContactSettings::FIELDS, jamais une clé libre). Clé/valeur
-- plutôt qu'une table à colonnes : ces informations changent de nature
-- d'un bureau à l'autre, ajouter un réglage ne doit pas exiger une
-- migration. updated_by : dernier admin à avoir modifié la valeur.
-- ---------------------------------------------------------------------
CREATE TABLE site_settings (
    setting_key   VARCHAR(64)     NOT NULL,
    setting_value TEXT            NULL,
    updated_by    BIGINT UNSIGNED NULL,
    updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key),
    CONSTRAINT fk_site_settings_user FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- contact_messages : messages reçus par le formulaire /contact. Le cahier
-- demande un envoi par email (PHPMailer) ; la copie en base sert à deux
-- choses : la limitation de débit par IP (§39, "rate limiting") et la
-- garantie qu'aucun message n'est perdu si l'envoi SMTP échoue (constaté
-- en local) — mail_sent l'indique, l'admin les retrouve dans le back office.
-- ---------------------------------------------------------------------
CREATE TABLE contact_messages (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(120)    NOT NULL,
    email       VARCHAR(190)    NOT NULL,
    subject     VARCHAR(150)    NOT NULL,
    message     TEXT            NOT NULL,
    ip_address  VARCHAR(45)     NOT NULL,
    mail_sent   TINYINT(1)      NOT NULL DEFAULT 0,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_contact_messages_ip_created (ip_address, created_at),
    KEY idx_contact_messages_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
