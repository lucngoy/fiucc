<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;

final class RegistrationController extends Controller
{
    public function create(): void
    {
        $this->view('registration.create', [
            'title'             => 'Join the Community',
            'faculties'         => Config::get('registration.faculties', []),
            'studyLevels'       => Config::get('registration.study_levels', []),
            'passwordMinLength' => Config::get('security.password_min_length', 10),
            'old'               => [],
            'errors'            => [],
        ]);
    }
}
