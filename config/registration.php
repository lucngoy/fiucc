<?php
declare(strict_types=1);

use App\Core\Env;

/**
 * Données de référence du recensement (FIUCC-31/32). Listées ici, pas en dur
 * dans la vue, parce qu'elles changeront (nouvelles facultés, etc.).
 */

return [
    // Domaines email autorisés à l'inscription — configurable : le domaine
    // étudiant réel n'est pas confirmé au moment d'écrire ce ticket, une
    // erreur ici empêcherait tout le monde de s'inscrire.
    'allowed_email_domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) Env::get('ALLOWED_EMAIL_DOMAINS', 'final.edu.tr,std.final.edu.tr'))
    ))),

    'faculties' => [
        'engineering'  => 'Faculty of Engineering',
        'economics'    => 'Faculty of Economics & Administrative Sciences',
        'law'          => 'Faculty of Law',
        'architecture' => 'Faculty of Architecture & Fine Arts',
        'education'    => 'Faculty of Educational Sciences',
        'pharmacy'     => 'Faculty of Pharmacy',
    ],

    // Stocké tel quel dans members.year (TINYINT) : 0 = prépa, 5 = master, 6 = doctorat.
    'study_levels' => [
        0 => 'Preparatory English',
        1 => 'Year 1',
        2 => 'Year 2',
        3 => 'Year 3',
        4 => 'Year 4',
        5 => "Master's Candidate",
        6 => 'PhD Scholar',
    ],

    // Fiche étendue (migration 018) : clés seulement, stockées telles quelles
    // en base — les libellés passent par t() ('census.community_role.<clé>',
    // 'census.district.<clé>', 'census.religion.<clé>').
    'community_roles' => ['student', 'pupil', 'worker', 'parent', 'child', 'other'],

    'districts' => ['lefkosa', 'girne', 'gazimagusa', 'guzelyurt', 'iskele', 'lefke'],

    'religions' => [
        'catholic', 'protestant', 'kimbanguist', 'muslim', 'orthodox',
        'revival', 'other', 'none', 'prefer_not_to_say',
    ],

    // Scan du passeport : image seulement (JPG/PNG/WEBP, comme le formulaire
    // de référence), ré-encodée par GD et stockée sous storage/passports/.
    'passport_scan_max_size_mb'   => (int) Env::get('PASSPORT_SCAN_MAX_SIZE_MB', 8),
    'passport_scan_max_dimension' => (int) Env::get('PASSPORT_SCAN_MAX_DIMENSION', 2000),

    // Conservation de la photo du passeport : supprimée N jours après la
    // dernière décision admin (validation, refus, suspension) — elle sert à
    // vérifier l'identité au moment de décider, pas au-delà (minimisation
    // RGPD/KVKK). Décision utilisateur, 2026-10-09. Voir
    // App\Services\PassportScanRetention.
    'passport_scan_retention_days' => (int) Env::get('PASSPORT_SCAN_RETENTION_DAYS', 90),
];
