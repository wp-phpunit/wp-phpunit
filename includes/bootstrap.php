<?php

/**
 * Installs WordPress for running the tests and loads WordPress and the test libraries
 */

if (\defined('WP_TESTS_CONFIG_FILE_PATH')) {
    $config_file_path = WP_TESTS_CONFIG_FILE_PATH;
} else {
    $config_file_path = \dirname(__DIR__);
    if (! file_exists($config_file_path . '/wp-tests-config.php')) {
        // Support the config file from the root of the develop repository.
        if (basename($config_file_path) === 'phpunit' && basename(\dirname($config_file_path)) === 'tests') {
            $config_file_path = \dirname($config_file_path, 2);
        }
    }
    $config_file_path .= '/wp-tests-config.php';
}

/*
 * Globalize some WordPress variables, because PHPUnit loads this file inside a function.
 * See: https://github.com/sebastianbergmann/phpunit/issues/325
 */
global $wpdb, $current_site, $current_blog, $wp_rewrite, $shortcode_tags, $wp, $phpmailer, $wp_theme_directories;

if (! is_readable($config_file_path)) {
    echo 'Error: wp-tests-config.php is missing! Please use wp-tests-config-sample.php to create a config file.' . PHP_EOL;
    exit(1);
}

require_once $config_file_path;
require_once __DIR__ . '/functions.php';

if (\defined('WP_RUN_CORE_TESTS') && WP_RUN_CORE_TESTS && ! is_dir(ABSPATH)) {
    if (!str_ends_with(ABSPATH, '/build/')) {
        printf(
            'Error: The ABSPATH constant in the `wp-tests-config.php` file is set to a non-existent path "%s". Please verify.' . PHP_EOL,
            ABSPATH,
        );
        exit(1);
    } else {
        echo 'Error: The PHPUnit tests should be run on the /src/ directory, not the /build/ directory.'
            . ' Please update the ABSPATH constant in your `wp-tests-config.php` file to `dirname( __FILE__ ) . \'/src/\'`'
            . ' or run `npm run build` prior to running PHPUnit.' . PHP_EOL;
        exit(1);
    }
}

$phpunit_version = tests_get_phpunit_version();

if (version_compare($phpunit_version, '13.0.0', '<')) {
    printf(
        "Error: Looks like you're using PHPUnit %s. This harness requires PHPUnit 13 or later." . PHP_EOL,
        $phpunit_version,
    );
    exit(1);
}

// If running core tests, check if all the required PHP extensions are loaded before running the test suite.
if (\defined('WP_RUN_CORE_TESTS') && WP_RUN_CORE_TESTS) {
    $required_extensions = [
        'gd',
    ];
    $missing_extensions  = [];

    foreach ($required_extensions as $extension) {
        if (! \extension_loaded($extension)) {
            $missing_extensions[] = $extension;
        }
    }

    if ($missing_extensions) {
        printf(
            'Error: The following required PHP extensions are missing from the testing environment: %s.' . PHP_EOL,
            implode(', ', $missing_extensions),
        );
        echo 'Please make sure they are installed and enabled.' . PHP_EOL,
        exit(1);
    }
}

$required_constants = [
    'WP_TESTS_DOMAIN',
    'WP_TESTS_EMAIL',
    'WP_TESTS_TITLE',
    'WP_PHP_BINARY',
];
$missing_constants  = [];

foreach ($required_constants as $constant) {
    if (! \defined($constant)) {
        $missing_constants[] = $constant;
    }
}

if ($missing_constants) {
    printf(
        'Error: The following required constants are not defined: %s.' . PHP_EOL,
        implode(', ', $missing_constants),
    );
    echo 'Please check out `wp-tests-config-sample.php` for an example.' . PHP_EOL,
    exit(1);
}

tests_reset__SERVER();

\define('WP_TESTS_TABLE_PREFIX', $table_prefix);

$test_data_directory = __DIR__ . '/../data';
$paratest_token      = getenv('TEST_TOKEN');
if (false !== $paratest_token && '' !== $paratest_token) {
    $worker_token        = preg_replace('/[^0-9A-Za-z_]/', '_', $paratest_token);
    $test_data_directory = \dirname(__DIR__) . '/data-paratest-' . $worker_token;

    if (! is_dir($test_data_directory)) {
        wp_tests_copy_directory(__DIR__ . '/../data', $test_data_directory);
    }
}

\define('DIR_TESTDATA', $test_data_directory);
\define('DIR_TESTROOT', realpath(\dirname(__DIR__)));

\define('WP_LANG_DIR', realpath(DIR_TESTDATA . '/languages'));

if (\defined('WP_RUN_CORE_TESTS') && WP_RUN_CORE_TESTS) {
    \define('WP_PLUGIN_DIR', realpath(DIR_TESTDATA . '/plugins'));
}

if (! \defined('WP_TESTS_FORCE_KNOWN_BUGS')) {
    \define('WP_TESTS_FORCE_KNOWN_BUGS', false);
}

/*
 * Cron tries to make an HTTP request to the site, which always fails,
 * because tests are run in CLI mode only.
 */
\define('DISABLE_WP_CRON', true);

\define('WP_MEMORY_LIMIT', -1);
\define('WP_MAX_MEMORY_LIMIT', -1);

\define('REST_TESTS_IMPOSSIBLY_HIGH_NUMBER', 99999999);

$PHP_SELF            = '/index.php';
$GLOBALS['PHP_SELF'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

// Should we run in multisite mode?
$multisite = ('1' === getenv('WP_MULTISITE'));
$multisite = $multisite || (\defined('WP_TESTS_MULTISITE') && WP_TESTS_MULTISITE);
$multisite = $multisite || (\defined('MULTISITE') && MULTISITE);

if (! \defined('WP_DEFAULT_THEME')) {
    \define('WP_DEFAULT_THEME', 'default');
}
$wp_theme_directories = [];

if (file_exists(DIR_TESTDATA . '/themedir1')) {
    $wp_theme_directories[] = DIR_TESTDATA . '/themedir1';
}

if ('1' !== getenv('WP_TESTS_SKIP_INSTALL')) {
    $core_tests = (\defined('WP_RUN_CORE_TESTS') && WP_RUN_CORE_TESTS) ? 'run_core_tests' : 'no_core_tests';
    $ms_tests   = $multisite ? 'run_ms_tests' : 'no_ms_tests';

    system(WP_PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/install.php') . ' ' . escapeshellarg($config_file_path) . ' ' . $ms_tests . ' ' . $core_tests, $retval);
    if (0 !== $retval) {
        exit($retval);
    }
}

if ($multisite) {
    if ('1' !== getenv('WP_TESTS_QUIET_BOOTSTRAP')) {
        echo 'Running as multisite...' . PHP_EOL;
    }
    \defined('MULTISITE') or \define('MULTISITE', true);
    \defined('SUBDOMAIN_INSTALL') or \define('SUBDOMAIN_INSTALL', '1' === getenv('WP_TESTS_SUBDOMAIN_INSTALL'));
    $GLOBALS['base'] = '/';
} elseif ('1' !== getenv('WP_TESTS_QUIET_BOOTSTRAP')) {
    echo 'Running as single site... To run multisite, use -c tests/phpunit/multisite.xml' . PHP_EOL;
}
unset($multisite);

$GLOBALS['_wp_die_disabled'] = false;
// Allow tests to override wp_die().
tests_add_filter('wp_die_handler', '_wp_die_handler_filter');
// Use the Spy REST Server instead of default.
tests_add_filter('wp_rest_server_class', '_wp_rest_server_class_filter');
// Prevent updating translations asynchronously.
tests_add_filter('async_update_translation', '__return_false');
// Disable background updates.
tests_add_filter('automatic_updater_disabled', '__return_true');

if (false !== getenv('TEST_TOKEN')) {
    tests_add_filter('upload_dir', '_wp_tests_paratest_upload_dir');
}

// Preset WordPress options defined in bootstrap file.
// Used to activate themes, plugins, as well as other settings.
if (isset($GLOBALS['wp_tests_options'])) {
    function wp_tests_options($value)
    {
        $key = substr(current_filter(), \strlen('pre_option_'));
        return $GLOBALS['wp_tests_options'][ $key ];
    }

    foreach (array_keys($GLOBALS['wp_tests_options']) as $key) {
        tests_add_filter('pre_option_' . $key, 'wp_tests_options');
    }
}

// Load WordPress.
require_once ABSPATH . 'wp-settings.php';

// Override the PHPMailer.
require_once __DIR__ . '/mock-mailer.php';

$phpmailer = new MockPHPMailer(true);

// Delete any default posts & related data.
_delete_all_posts();

require __DIR__ . '/phpunit-adapter-testcase.php';
require __DIR__ . '/abstract-testcase.php';
require __DIR__ . '/testcase.php';
require __DIR__ . '/testcase-rest-api.php';
require __DIR__ . '/testcase-rest-controller.php';
require __DIR__ . '/testcase-rest-post-type-controller.php';
require __DIR__ . '/testcase-xmlrpc.php';
require __DIR__ . '/testcase-ajax.php';
require __DIR__ . '/testcase-canonical.php';
require __DIR__ . '/testcase-xml.php';
require __DIR__ . '/exceptions.php';
require __DIR__ . '/utils.php';
require __DIR__ . '/spy-rest-server.php';
require __DIR__ . '/class-wp-rest-test-search-handler.php';
require __DIR__ . '/class-wp-rest-test-configurable-controller.php';
require __DIR__ . '/class-wp-fake-block-type.php';
require __DIR__ . '/class-wp-fake-hasher.php';
require __DIR__ . '/class-wp-sitemaps-test-provider.php';
require __DIR__ . '/class-wp-sitemaps-empty-test-provider.php';
require __DIR__ . '/class-wp-sitemaps-large-test-provider.php';

/**
 * A class to handle additional command line arguments passed to the script.
 *
 * If it is determined that phpunit was called with a --group that corresponds
 * to an @ticket annotation (such as `phpunit --group 12345` for bugs marked
 * as #WP12345), then it is assumed that known bugs should not be skipped.
 *
 * If WP_TESTS_FORCE_KNOWN_BUGS is already set in wp-tests-config.php, then
 * how you call phpunit has no effect.
 */
class WP_PHPUnit_Util_Getopt
{
    public function __construct($argv)
    {
        $skipped_groups = [
            'ajax'          => true,
            'ms-files'      => true,
            'external-http' => true,
        ];

        while (current($argv)) {
            $option = current($argv);
            $value  = next($argv);

            switch ($option) {
                case '--exclude-group':
                    foreach ($skipped_groups as $group_name => $skipped) {
                        $skipped_groups[ $group_name ] = false;
                    }
                    continue 2;
                case '--group':
                    $groups = explode(',', $value);
                    foreach ($groups as $group) {
                        if (is_numeric($group) || preg_match('/^(UT|Plugin)\d+$/', $group)) {
                            WP_UnitTestCase::forceTicket($group);
                        }
                    }

                    foreach ($skipped_groups as $group_name => $skipped) {
                        if (\in_array($group_name, $groups, true)) {
                            $skipped_groups[ $group_name ] = false;
                        }
                    }
                    continue 2;
            }
        }

        $skipped_groups = array_filter($skipped_groups);
        if ('1' !== getenv('WP_TESTS_QUIET_BOOTSTRAP')) {
            foreach ($skipped_groups as $group_name => $skipped) {
                echo \sprintf('Not running %1$s tests. To execute these, use --group %1$s.', $group_name) . PHP_EOL;
            }

            if (! isset($skipped_groups['external-http'])) {
                echo PHP_EOL;
                echo 'External HTTP skipped tests can be caused by timeouts.' . PHP_EOL;
                echo 'If this changeset includes changes to HTTP, make sure there are no timeouts.' . PHP_EOL;
                echo PHP_EOL;
            }
        }
    }
}
new WP_PHPUnit_Util_Getopt($_SERVER['argv']);
