<?php
declare(strict_types=1);

/**
 * Routes publiques et membres — section 46 du cahier des charges.
 * Le 4e élément d'une route est la liste de middleware (voir App\Core\Router) :
 * ['auth'] exige une session connectée, quel que soit le statut du membre
 * (pending compris — voir CLAUDE.md, "connecté" ≠ "membre validé").
 * ['validated'] exige en plus members.status = 'validated' (FIUCC-35) :
 * toute route qui expose d'autres membres (annuaire, mentorat...) doit
 * l'utiliser à la place de ['auth'], jamais en plus.
 * Les routes marquées [vote] portent ['auth'] (session) au niveau routeur,
 * mais le contrôle "jeton de vote déjà validé pour CETTE élection" reste
 * dans App\Controllers\VoteController::eligibleContext()/votingAuthorized(),
 * pas dans une classe middleware séparée : App\Core\Router::runMiddleware()
 * n'a accès à aucun paramètre de route ({id} compris), un middleware ne
 * pourrait donc pas savoir quelle élection est concernée (constaté en
 * construisant FIUCC-66 — pas une régression, une contrainte réelle du
 * routeur tel qu'il existe).
 */

return [
    // Vitrine
    ['GET',  '/',                              'HomeController@index'],
    ['GET',  '/community',                     'CommunityController@index'],
    ['GET',  '/community/government',          'GovernmentController@index'],
    ['GET',  '/community/government/archives', 'GovernmentController@archives'],
    ['GET',  '/community/presidents',          'CommunityController@presidents'],
    ['GET',  '/new-at-final',                  'OnboardingController@index'],

    // Contenu
    ['GET',  '/events',                        'EventController@index'],
    ['GET',  '/events/{slug}',                 'EventController@show'],
    ['POST', '/events/{slug}/register',        'EventController@register',        ['validated']],
    ['POST', '/events/{slug}/unregister',      'EventController@unregister',      ['validated']],
    ['GET',  '/news',                          'NewsController@index'],
    ['GET',  '/news/{slug}',                   'NewsController@show'],
    ['GET',  '/gallery',                       'GalleryController@index'],
    ['GET',  '/gallery/{slug}',                'GalleryController@show'],
    ['GET',  '/documents',                     'DocumentController@index'],
    // Pas de middleware fixe : l'autorisation dépend de documents.visibility
    // (par ligne), pas de la route — voir DocumentController::download().
    ['GET',  '/documents/{id}/download',       'DocumentController@download'],
    ['GET',  '/documents/{id}/open',           'DocumentController@open'],
    ['GET',  '/partners',                      'PartnerController@index'],

    // Recensement et authentification
    ['GET',  '/register',                      'RegistrationController@create'],
    ['POST', '/register',                      'RegistrationController@store'],
    ['GET',  '/verify-email',                  'VerificationController@show'],
    ['POST', '/verify-email',                  'VerificationController@verify'],
    ['POST', '/verify-email/resend',           'VerificationController@resend'],
    ['GET',  '/login',                         'AuthController@showLogin'],
    ['POST', '/login',                         'AuthController@login'],
    ['POST', '/logout',                        'AuthController@logout'],
    ['GET',  '/password/forgot',               'PasswordController@forgot'],
    ['POST', '/password/forgot',               'PasswordController@sendLink'],
    ['GET',  '/password/reset',                'PasswordController@showReset'],
    ['POST', '/password/reset',                'PasswordController@reset'],

    // Espace membre
    ['GET',  '/profile',                       'ProfileController@index',         ['auth']],
    ['GET',  '/profile/edit',                  'ProfileController@edit',          ['auth']],
    ['POST', '/profile',                       'ProfileController@update',        ['auth']],
    ['GET',  '/profile/privacy',               'ProfileController@privacy',       ['auth']],
    ['POST', '/profile/privacy',               'ProfileController@updatePrivacy', ['auth']],
    ['GET',  '/profile/password',              'ProfileController@password',       ['auth']],
    ['POST', '/profile/password',              'ProfileController@updatePassword', ['auth']],
    ['GET',  '/profile/mentor',                'ProfileController@mentor',        ['auth']],
    ['POST', '/profile/mentor',                'ProfileController@updateMentor',  ['auth']],
    ['POST', '/profile/mentor/requests/{id}/respond', 'ProfileController@respondToMentorship', ['auth']],

    // Réseau et alumni (phase 6) — exposent d'autres membres : ['validated'],
    // jamais ['auth'] seul (voir commentaire d'en-tête, FIUCC-35).
    ['GET',  '/directory',                     'DirectoryController@index',       ['validated']],
    ['GET',  '/directory/{id}',                'DirectoryController@show',        ['validated']],
    ['GET',  '/alumni',                        'AlumniController@index',          ['validated']],
    ['GET',  '/mentors',                       'MentorController@index',          ['validated']],
    ['POST', '/mentors/{id}/request',          'MentorController@request',        ['validated']],

    // Élections
    ['GET',  '/elections',                     'ElectionController@index'],
    ['GET',  '/elections/{id}',                'ElectionController@show'],
    ['GET',  '/elections/{id}/candidates',     'ElectionController@candidates'],
    // Dépôt de candidature par le membre (lot 3, règlement électoral art.
    // 9-14) — membre validé ; le reste est revérifié en SQL (Candidate).
    ['GET',  '/elections/{id}/candidacy',        'CandidacyController@show',        ['validated']],
    ['POST', '/elections/{id}/candidacy',        'CandidacyController@submit',      ['validated']],
    ['POST', '/elections/{id}/candidacy/cancel', 'CandidacyController@cancel',      ['validated']],
    ['GET',  '/elections/{id}/authenticate',   'VoteController@authenticate',     ['auth']],
    ['POST', '/elections/{id}/authenticate',   'VoteController@sendToken',        ['auth']],
    ['GET',  '/elections/{id}/vote',           'VoteController@ballot',           ['auth']], // [vote] voir ci-dessous
    ['POST', '/elections/{id}/vote',           'VoteController@submit',           ['auth']], // [vote] voir ci-dessous
    ['GET',  '/elections/{id}/results',        'ElectionController@results'],

    // Divers
    ['GET',  '/contact',                       'ContactController@index'],
    ['POST', '/contact',                       'ContactController@send'],
    // Aide et urgences (migration 028) : page publique (numéros d'urgence),
    // demande d'aide réservée à un membre connecté, validé ou non — elle
    // n'expose aucun autre membre (voir HelpController).
    ['GET',  '/help',                          'HelpController@index'],
    ['POST', '/help',                          'HelpController@store',            ['auth']],

    // Langue (FIUCC-30)
    ['GET',  '/lang/{locale}',                 'LocaleController@switch'],
    ['GET',  '/cookies',                       'CookieController@index'],
    ['POST', '/cookies/dismiss',               'CookieController@dismiss'],
];
