<?php
declare(strict_types=1);

/** Routes du back office — toutes derrière le middleware admin. */

return [
    ['GET',  '/admin',                          'Admin\DashboardController@index'],

    ['GET',  '/admin/account/password',         'Admin\AccountController@password'],
    ['POST', '/admin/account/password',         'Admin\AccountController@updatePassword'],

    ['GET',  '/admin/members',                  'Admin\MemberController@index'],
    ['POST', '/admin/members/{id}/validate',    'Admin\MemberController@validate'],
    ['POST', '/admin/members/{id}/reject',      'Admin\MemberController@reject'],
    ['POST', '/admin/members/{id}/suspend',     'Admin\MemberController@suspend'],
    ['POST', '/admin/members/{id}/mark-alumni', 'Admin\MemberController@markAlumni'],
    ['POST', '/admin/members/bulk-validate',    'Admin\MemberController@bulkValidate'],
    ['POST', '/admin/members/bulk-reject',      'Admin\MemberController@bulkReject'],
    ['POST', '/admin/members/bulk-suspend',     'Admin\MemberController@bulkSuspend'],
    ['GET',  '/admin/members/export',           'Admin\MemberController@export'],
    // Après /export : {id} accepte n'importe quel segment, la première
    // route qui correspond gagne (App\Core\Router).
    ['GET',  '/admin/members/{id}',             'Admin\MemberController@show'],
    ['GET',  '/admin/members/{id}/passport-scan', 'Admin\MemberController@passportScan'],

    ['GET',  '/admin/events',                   'Admin\EventController@index'],
    ['GET',  '/admin/events/create',            'Admin\EventController@create'],
    ['POST', '/admin/events',                   'Admin\EventController@store'],
    ['GET',  '/admin/events/{id}/edit',         'Admin\EventController@edit'],
    ['POST', '/admin/events/{id}',              'Admin\EventController@update'],
    ['POST', '/admin/events/{id}/archive',      'Admin\EventController@archive'],
    ['POST', '/admin/events/{id}/unarchive',    'Admin\EventController@unarchive'],
    ['POST', '/admin/events/{id}/delete',       'Admin\EventController@delete'],

    ['GET',  '/admin/news',                     'Admin\NewsController@index'],
    ['GET',  '/admin/news/create',              'Admin\NewsController@create'],
    ['POST', '/admin/news',                     'Admin\NewsController@store'],
    ['GET',  '/admin/news/{id}/edit',           'Admin\NewsController@edit'],
    ['POST', '/admin/news/{id}',                'Admin\NewsController@update'],
    ['POST', '/admin/news/{id}/delete',         'Admin\NewsController@delete'],

    ['GET',  '/admin/gallery',                  'Admin\GalleryController@index'],
    ['GET',  '/admin/gallery/create',           'Admin\GalleryController@create'],
    ['POST', '/admin/gallery',                  'Admin\GalleryController@store'],
    ['GET',  '/admin/gallery/{id}/edit',        'Admin\GalleryController@edit'],
    ['POST', '/admin/gallery/{id}',             'Admin\GalleryController@update'],
    ['POST', '/admin/gallery/{id}/photos',      'Admin\GalleryController@uploadPhotos'],
    ['POST', '/admin/gallery/{id}/photos/manage', 'Admin\GalleryController@managePhotos'],
    ['POST', '/admin/gallery/{id}/delete',      'Admin\GalleryController@delete'],

    ['GET',  '/admin/documents',                'Admin\DocumentController@index'],
    ['GET',  '/admin/documents/create',         'Admin\DocumentController@create'],
    ['POST', '/admin/documents',                'Admin\DocumentController@store'],
    ['GET',  '/admin/documents/{id}/edit',      'Admin\DocumentController@edit'],
    ['POST', '/admin/documents/{id}',           'Admin\DocumentController@update'],
    ['POST', '/admin/documents/{id}/delete',    'Admin\DocumentController@delete'],

    ['GET',  '/admin/partners',                 'Admin\PartnerController@index'],
    ['GET',  '/admin/partners/create',          'Admin\PartnerController@create'],
    ['POST', '/admin/partners',                 'Admin\PartnerController@store'],
    ['GET',  '/admin/partners/{id}/edit',       'Admin\PartnerController@edit'],
    ['POST', '/admin/partners/{id}',            'Admin\PartnerController@update'],
    ['POST', '/admin/partners/{id}/delete',     'Admin\PartnerController@delete'],

    ['GET',  '/admin/elections',                'Admin\ElectionController@index'],
    ['GET',  '/admin/elections/create',         'Admin\ElectionController@create'],
    ['POST', '/admin/elections',                'Admin\ElectionController@store'],
    ['GET',  '/admin/elections/{id}',           'Admin\ElectionController@show'],
    ['GET',  '/admin/elections/{id}/edit',      'Admin\ElectionController@edit'],
    ['POST', '/admin/elections/{id}',           'Admin\ElectionController@update'],
    ['POST', '/admin/elections/{id}/delete',    'Admin\ElectionController@delete'],
    ['POST', '/admin/elections/{id}/extend-deadline', 'Admin\ElectionController@extendDeadline'],
    ['POST', '/admin/elections/{id}/positions', 'Admin\ElectionController@storePosition'],
    ['POST', '/admin/elections/{id}/positions/{position_id}',        'Admin\ElectionController@updatePosition'],
    ['POST', '/admin/elections/{id}/positions/{position_id}/delete', 'Admin\ElectionController@deletePosition'],
    ['GET',  '/admin/elections/{id}/candidates',        'Admin\CandidateController@index'],
    ['GET',  '/admin/elections/{id}/candidates/create', 'Admin\CandidateController@create'],
    ['POST', '/admin/elections/{id}/candidates',        'Admin\CandidateController@store'],
    ['GET',  '/admin/elections/{id}/candidates/{candidate_id}/edit',     'Admin\CandidateController@edit'],
    ['POST', '/admin/elections/{id}/candidates/{candidate_id}',          'Admin\CandidateController@update'],
    ['POST', '/admin/elections/{id}/candidates/{candidate_id}/approve',  'Admin\CandidateController@approve'],
    ['POST', '/admin/elections/{id}/candidates/{candidate_id}/reject',   'Admin\CandidateController@reject'],
    ['POST', '/admin/elections/{id}/candidates/{candidate_id}/withdraw','Admin\CandidateController@withdraw'],
    ['GET',  '/admin/elections/{id}/tokens',              'Admin\VotingTokenController@index'],
    ['POST', '/admin/elections/{id}/tokens/send',          'Admin\VotingTokenController@send'],
    ['POST', '/admin/elections/{id}/tokens/{member_id}/regenerate', 'Admin\VotingTokenController@regenerate'],
    ['POST', '/admin/elections/{id}/reminder',        'Admin\VotingTokenController@sendReminder'],
    ['POST', '/admin/elections/{id}/closure-notice',  'Admin\VotingTokenController@sendClosureNotice'],
    ['POST', '/admin/elections/{id}/start-candidacies', 'Admin\ElectionController@startCandidacies'],
    ['POST', '/admin/elections/{id}/open',      'Admin\ElectionController@open'],
    ['POST', '/admin/elections/{id}/close',     'Admin\ElectionController@close'],
    ['POST', '/admin/elections/{id}/publish',   'Admin\ElectionController@publishResults'],
    ['GET',  '/admin/elections/{id}/results',   'Admin\ElectionController@results'],
    ['GET',  '/admin/elections/{id}/report',    'Admin\ElectionController@report'],

    // Coordonnées de la communauté + messages reçus (page /contact, §39).
    ['GET',  '/admin/contact',                  'Admin\ContactController@index'],
    ['POST', '/admin/contact',                  'Admin\ContactController@update'],

    ['GET',  '/admin/backups',                  'Admin\BackupController@index'],
    ['POST', '/admin/backups',                  'Admin\BackupController@create'],
    ['GET',  '/admin/backups/{filename}',       'Admin\BackupController@download'],
    ['POST', '/admin/backups/{filename}/delete','Admin\BackupController@delete'],

    ['GET',  '/admin/emails',                   'Admin\EmailController@index'],
    ['GET',  '/admin/logs',                     'Admin\LogController@index'],
];
