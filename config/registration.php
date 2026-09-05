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
];
