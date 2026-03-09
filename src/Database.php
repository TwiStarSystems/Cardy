<?php
declare(strict_types=1);

namespace Cardy;

class Database
{
    private static ?\PDO $instance = null;

    public static function getInstance(): \PDO
    {
        if (self::$instance === null) {
            $host = Config::get('db.host', 'localhost');
            $port = Config::get('db.port', 3306);
            $name = Config::get('db.name', 'cardy');
            $user = Config::get('db.user');
            $pass = Config::get('db.pass');

            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

            self::$instance = new \PDO($dsn, $user, $pass, [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }

        return self::$instance;
    }

    /** Allow injection of an existing PDO (useful for the SabreDAV backends). */
    public static function setInstance(\PDO $pdo): void
    {
        self::$instance = $pdo;
    }
}
