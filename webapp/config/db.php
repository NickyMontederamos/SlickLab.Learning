<?php

require_once __DIR__ . '/../lib/table_prefix.php';

date_default_timezone_set('UTC');

function csa_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }
    return $config;
}

/**
 * Rewrites every query passed through it via csa_prefix_tables() before
 * running it. When config.php doesn't set table_prefix (production, local
 * dev), the prefix is '' and this is a transparent passthrough — existing
 * deployments are unaffected. Exists so a deployment can share a database
 * with another one (e.g. a staging site on a host that only allows one DB)
 * without any of the ~33 API files needing to know about it.
 */
class CsaPrefixedPdo extends PDO
{
    private string $prefix;

    public function __construct(string $dsn, string $user, string $pass, array $options, string $prefix)
    {
        parent::__construct($dsn, $user, $pass, $options);
        $this->prefix = $prefix;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare(csa_prefix_tables($query, $this->prefix), $options);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        return parent::query(csa_prefix_tables($query, $this->prefix), $fetchMode, ...$fetchModeArgs);
    }

    public function exec(string $statement): int|false
    {
        return parent::exec(csa_prefix_tables($statement, $this->prefix));
    }
}

function csa_db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $db = csa_config()['db'];
        $prefix = csa_config()['table_prefix'] ?? '';
        $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset=utf8mb4";
        $pdo = new CsaPrefixedPdo($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 10,
        ], $prefix);
        // Force UTC so NOW()/CURRENT_TIMESTAMP align with PHP's UTC-based time()/strtotime().
        $pdo->exec("SET time_zone = '+00:00'");
    }
    return $pdo;
}
