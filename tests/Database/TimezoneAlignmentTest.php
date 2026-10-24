<?php
declare(strict_types=1);

namespace Tests\Database;

use App\Core\Database;
use PHPUnit\Framework\TestCase;

/**
 * NOW() côté MySQL à l'heure de l'application (App\Core\Database) : toutes
 * les échéances — ouverture et fermeture du vote, dates limites,
 * expiration des codes — comparent une date écrite par PHP à NOW(). Un
 * MySQL réglé sur un autre fuseau (UTC chez un hébergeur) les décalerait
 * toutes. Vérifié sur la session elle-même, pas sur l'horloge : en local,
 * le fuseau système de MySQL coïncide parfois avec celui de l'app, et un
 * test d'horloge passerait alors sans rien prouver.
 */
final class TimezoneAlignmentTest extends TestCase
{
    public function test_the_mysql_session_uses_the_application_offset(): void
    {
        $sessionZone = Database::connection()->query('SELECT @@session.time_zone')->fetchColumn();

        self::assertSame(date('P'), $sessionZone);
    }

    public function test_now_in_sql_matches_php_time(): void
    {
        $now = strtotime((string) Database::connection()->query('SELECT NOW()')->fetchColumn());

        self::assertLessThanOrEqual(5, abs($now - time()));
    }
}
