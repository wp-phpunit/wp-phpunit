<?php

declare(strict_types=1);

if ($argc === 3 && $argv[1] === '--reset-prefix') {
    $prefix = $argv[2];
    if ($prefix === '' || preg_match('/^[0-9A-Za-z_]+$/', $prefix) !== 1) {
        fwrite(STDERR, "Invalid test table prefix.\n");
        exit(2);
    }

    $database = getenv('WP_TESTS_DB_NAME') ?: 'wordpress_test';
    $user = getenv('WP_TESTS_DB_USER') ?: 'root';
    $password = getenv('WP_TESTS_DB_PASSWORD') ?: 'root';
    $hostConfiguration = getenv('WP_TESTS_DB_HOST') ?: '127.0.0.1';
    $host = $hostConfiguration;
    $port = 3306;

    if (preg_match('/^(.+):(\\d+)$/', $hostConfiguration, $matches) === 1) {
        $host = $matches[1];
        $port = (int) $matches[2];
    }

    $mysqli = new mysqli($host, $user, $password, $database, $port);
    $mysqli->set_charset('utf8mb4');

    $statement = $mysqli->prepare(
        'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME LIKE ?',
    );
    $like = $prefix . '%';
    $statement->bind_param('ss', $database, $like);
    $statement->execute();
    $result = $statement->get_result();
    $tables = [];
    while ($row = $result->fetch_assoc()) {
        $tables[] = $row['TABLE_NAME'];
    }
    $statement->close();

    $mysqli->query('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($tables as $table) {
        $identifier = '`' . str_replace('`', '``', $table) . '`';
        $mysqli->query("DROP TABLE IF EXISTS {$identifier}");
    }
    $mysqli->query('SET FOREIGN_KEY_CHECKS = 1');
    $mysqli->close();

    exit(0);
}

if ($argc !== 2 || !is_file($argv[1])) {
    fwrite(
        STDERR,
        "Usage: php tools/configure-core-test-database.php <wp-tests-config.php>\n"
        . "   or: php tools/configure-core-test-database.php --reset-prefix <table-prefix>\n",
    );
    exit(2);
}

$path = $argv[1];
$contents = file_get_contents($path);
if ($contents === false) {
    throw new RuntimeException("Cannot read {$path}.");
}

$values = [
    'DB_NAME' => getenv('WP_TESTS_DB_NAME') ?: 'wordpress_test',
    'DB_USER' => getenv('WP_TESTS_DB_USER') ?: 'root',
    'DB_PASSWORD' => getenv('WP_TESTS_DB_PASSWORD') ?: 'root',
    'DB_HOST' => getenv('WP_TESTS_DB_HOST') ?: '127.0.0.1',
];

foreach ($values as $constant => $value) {
    $quotedValue = var_export($value, true);
    $pattern = "/define\\( '{$constant}', '[^']*' \\);/";
    $replacement = "define( '{$constant}', {$quotedValue} );";
    $updated = preg_replace($pattern, $replacement, $contents, 1, $count);

    if ($updated === null || $count !== 1) {
        throw new RuntimeException("Cannot configure {$constant} in {$path}.");
    }

    $contents = $updated;
}

$tablePrefixConfiguration = <<<'PHP'
$paratestToken = getenv( 'TEST_TOKEN' );
$table_prefix = false === $paratestToken || '' === $paratestToken
    ? 'wptests_'
    : 'wptests_' . preg_replace( '/[^0-9A-Za-z_]/', '_', $paratestToken ) . '_';
PHP;
$contents = preg_replace(
    '/\$table_prefix = \'wptests_\';\s*\/\/ Only numbers, letters, and underscores please!/',
    $tablePrefixConfiguration,
    $contents,
    1,
    $tablePrefixConfigurationCount,
);

if ($contents === null || $tablePrefixConfigurationCount !== 1) {
    throw new RuntimeException("Cannot configure per-worker table prefix in {$path}.");
}

$debugConfiguration = "define( 'WP_DEBUG', true );\n"
    . "define( 'WP_DEBUG_DISPLAY', true );\n"
    . "define( 'WP_DEBUG_LOG', true );";
$contents = preg_replace(
    "/define\\( 'WP_DEBUG', true \\);/",
    $debugConfiguration,
    $contents,
    1,
    $debugConfigurationCount,
);

if ($contents === null || $debugConfigurationCount !== 1) {
    throw new RuntimeException("Cannot configure WordPress debug constants in {$path}.");
}

$memcachedHost = getenv('WP_TESTS_MEMCACHED_HOST') ?: '127.0.0.1';
$memcachedPort = (int) (getenv('WP_TESTS_MEMCACHED_PORT') ?: 11211);
$memcachedConfiguration = sprintf(
    "\n\n\$GLOBALS['memcached_servers'] = [ [ %s, %d ] ];",
    var_export($memcachedHost, true),
    $memcachedPort,
);
$contents .= $memcachedConfiguration;

if (file_put_contents($path, $contents) === false) {
    throw new RuntimeException("Cannot write {$path}.");
}
