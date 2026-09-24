<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

/**
 * Returns the one PDO connection used by the application during this request.
 *
 * @throws RuntimeException when local configuration is missing or MySQL is unavailable.
 */
function db(): PDO
{
    static $connection = null;

    if ($connection instanceof PDO) {
        return $connection;
    }

    $configFile = __DIR__ . '/database.local.php';

    if (!is_file($configFile)) {
        throw new RuntimeException(
            'Database configuration is missing. Copy config/database.local.example.php '
            . 'to config/database.local.php and set the local values.'
        );
    }

    /** @var array{host: string, port: int, database: string, username: string, password: string} $settings */
    $settings = require $configFile;

    foreach (['host', 'port', 'database', 'username', 'password'] as $requiredKey) {
        if (!array_key_exists($requiredKey, $settings)) {
            throw new RuntimeException('Database configuration is incomplete.');
        }
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $settings['host'],
        $settings['port'],
        $settings['database']
    );

    try {
        $connection = new PDO(
            $dsn,
            $settings['username'],
            $settings['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (PDOException $exception) {
        error_log('College Sports Management System database connection failed: '
            . $exception->getMessage());

        throw new RuntimeException('The service is temporarily unavailable.');
    }

    return $connection;
}
