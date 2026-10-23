-- =====================================================================
-- FIUCC Portal — diagnostic des clés (2026-10-12). Lecture seule.
-- Facultatif : la page admin « Mises à jour de la base » fait le même
-- contrôle et répare (App\Services\SchemaKeys). Ce fichier reste utile
-- dans l'onglet SQL de phpMyAdmin si l'admin est inaccessible. Sur une
-- base saine :
--   tables_sans_cle_primaire = 0, id_sans_auto_increment = 0,
--   cles_etrangeres = 35.
-- =====================================================================

SELECT
    (SELECT COUNT(*) FROM information_schema.TABLES t
      WHERE t.TABLE_SCHEMA = DATABASE() AND t.TABLE_TYPE = 'BASE TABLE'
        AND NOT EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS c
                        WHERE c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = t.TABLE_NAME
                          AND c.CONSTRAINT_TYPE = 'PRIMARY KEY'))            AS tables_sans_cle_primaire,
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'id'
        AND EXTRA NOT LIKE '%auto_increment%')                                AS id_sans_auto_increment,
    (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA = DATABASE())                                   AS cles_etrangeres,
    (SELECT GROUP_CONCAT(t.TABLE_NAME ORDER BY t.TABLE_NAME) FROM information_schema.TABLES t
      WHERE t.TABLE_SCHEMA = DATABASE() AND t.TABLE_TYPE = 'BASE TABLE'
        AND NOT EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS c
                        WHERE c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = t.TABLE_NAME
                          AND c.CONSTRAINT_TYPE = 'PRIMARY KEY'))            AS liste_sans_cle_primaire;
