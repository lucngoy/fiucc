<?php
declare(strict_types=1);

/** Routes du back office — toutes derrière le middleware admin. */

return [
    ['GET',  '/admin',                          'Admin\DashboardController@index'],

    ['GET',  '/admin/members',                  'Admin\MemberController@index'],
    ['POST', '/admin/members/{id}/validate',    'Admin\MemberController@validate'],
    ['POST', '/admin/members/{id}/reject',      'Admin\MemberController@reject'],
    ['POST', '/admin/members/{id}/suspend',     'Admin\MemberController@suspend'],
    ['GET',  '/admin/members/export',           'Admin\MemberController@export'],

    ['GET',  '/admin/events',                   'Admin\EventController@index'],
    ['GET',  '/admin/events/create',            'Admin\EventController@create'],
    ['POST', '/admin/events',                   'Admin\EventController@store'],
    ['POST', '/admin/events/{id}',              'Admin\EventController@update'],

    ['GET',  '/admin/news',                     'Admin\NewsController@index'],
    ['GET',  '/admin/gallery',                  'Admin\GalleryController@index'],
    ['GET',  '/admin/documents',                'Admin\DocumentController@index'],

    ['GET',  '/admin/elections',                'Admin\ElectionController@index'],
    ['GET',  '/admin/elections/{id}',           'Admin\ElectionController@show'],
    ['POST', '/admin/elections/{id}/positions', 'Admin\ElectionController@storePosition'],
    ['POST', '/admin/elections/{id}/candidates','Admin\ElectionController@storeCandidate'],
    ['POST', '/admin/elections/{id}/open',      'Admin\ElectionController@open'],
    ['POST', '/admin/elections/{id}/close',     'Admin\ElectionController@close'],
    ['POST', '/admin/elections/{id}/publish',   'Admin\ElectionController@publishResults'],

    ['GET',  '/admin/emails',                   'Admin\EmailController@index'],
    ['GET',  '/admin/logs',                     'Admin\LogController@index'],
];
