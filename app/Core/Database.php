<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        return self::$connection ??= self::connect();
    }

    private static function connect(): PDO
    {
        $host     = (string) Config::get('database.host');
        $port     = (int) Config::get('database.port', 3306);
        $database = (string) Config::get('database.database');
        $charset  = (string) Config::get('database.charset', 'utf8mb4');

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

        try {
            $pdo = new PDO(
                $dsn,
                (string) Config::get('database.username'),
                (string) Config::get('database.password'),
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );

            // NOW() à l'heure de l'application, pas à celle du serveur MySQL :
            // les dates sont écrites par PHP (app.timezone) et comparées à
            // NOW() en SQL (ouverture/fermeture du vote, dates limites,
            // expiration des codes). Un MySQL réglé ailleurs — UTC chez un
            // hébergeur, par exemple — décalerait chaque échéance de
            // plusieurs heures. Décalage numérique (+03:00) : n'exige pas
            // les tables de fuseaux nommés côté MySQL.
            // Jamais bloquant : un refus ne doit pas rendre tout le site
            // inaccessible — il se voit dans les logs (et dans
            // tests/Database/TimezoneAlignmentTest).
            try {
                $pdo->prepare('SET time_zone = :offset')->execute(['offset' => date('P')]);
            } catch (PDOException $e) {
                Logger::error('database', 'Fuseau horaire MySQL non aligné', ['offset' => date('P'), 'error' => $e->getMessage()]);
            }

            return $pdo;
        } catch (PDOException) {
            // Message volontairement générique : jamais le message natif du driver
            // (il peut mentionner l'utilisateur de connexion).
            throw new RuntimeException(
                sprintf('Connexion à la base de données impossible (%s:%d/%s).', $host, $port, $database)
            );
        }
    }
}
