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
            return new PDO(
                $dsn,
                (string) Config::get('database.username'),
                (string) Config::get('database.password'),
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (PDOException) {
            // Message volontairement générique : jamais le message natif du driver
            // (il peut mentionner l'utilisateur de connexion).
            throw new RuntimeException(
                sprintf('Connexion à la base de données impossible (%s:%d/%s).', $host, $port, $database)
            );
        }
    }
}
