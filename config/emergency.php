<?php
declare(strict_types=1);

/**
 * Numéros d'urgence de Chypre du Nord (TRNC / KKTC), page /help.
 *
 * Source : liste officielle du bureau de presse du ministère des Affaires
 * étrangères TRNC — https://pio.mfa.gov.ct.tr/en/general-information/
 * (vérifiée le 2026-10-24). Ne pas confondre avec les numéros de Turquie
 * (110 pompiers, 156 gendarmerie) qui ne s'appliquent pas ici.
 *
 * Libellés dans resources/lang (help.emergency.<clé>), jamais ici.
 */
return [
    'numbers' => [
        'police'        => ['number' => '155', 'icon' => 'user-shield'],
        'ambulance'     => ['number' => '112', 'icon' => 'truck-medical'],
        'fire'          => ['number' => '199', 'icon' => 'fire'],
        'forest_fire'   => ['number' => '177', 'icon' => 'tree'],
        'civil_defence' => ['number' => '101', 'icon' => 'shield-halved'],
    ],
    'source_url' => 'https://pio.mfa.gov.ct.tr/en/general-information/',
];
