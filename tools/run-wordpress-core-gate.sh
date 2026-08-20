#!/bin/sh

set -eu

SCRIPT_DIRECTORY=$(CDPATH='' cd -- "$(dirname -- "$0")" && pwd)
readonly SCRIPT_DIRECTORY
REPOSITORY_ROOT=$(CDPATH='' cd -- "${SCRIPT_DIRECTORY}/.." && pwd)
readonly REPOSITORY_ROOT

readonly WORDPRESS_DEVELOP_REPOSITORY="${WORDPRESS_DEVELOP_REPOSITORY:-https://github.com/WordPress/wordpress-develop.git}"
readonly WORDPRESS_DEVELOP_REF="${WORDPRESS_DEVELOP_REF:-98d6d9097862a5cba242bfae097bf60b93bea867}"
readonly WORDPRESS_CORE_WORKSPACE="${WORDPRESS_CORE_WORKSPACE:-${REPOSITORY_ROOT}/.build/wordpress-develop}"
readonly CORE_PATCH="${REPOSITORY_ROOT}/tools/wordpress-core-phpunit13.patch"
readonly DATA_PROVIDER_PATCH="${REPOSITORY_ROOT}/tools/wordpress-core-phpunit13-data-providers.patch"
readonly QUERY_PROVIDER_PATCH="${REPOSITORY_ROOT}/tools/wordpress-core-phpunit13-query-provider-followup.patch"
readonly RUNTIME_FOLLOWUP_PATCH="${REPOSITORY_ROOT}/tools/wordpress-core-phpunit13-runtime-followup.patch"
readonly NATIVE_DEPRECATIONS_PATCH="${REPOSITORY_ROOT}/tools/wordpress-core-phpunit13-native-deprecations.patch"
readonly ASSERTION_METADATA_PATCH="${REPOSITORY_ROOT}/tools/wordpress-core-phpunit13-assertion-metadata.patch"
readonly OUTPUT_EXPECTATIONS_PATCH="${REPOSITORY_ROOT}/tools/wordpress-core-phpunit13-output-expectations.patch"
readonly PARATEST_ISOLATION_PATCH="${REPOSITORY_ROOT}/tools/wordpress-core-phpunit13-paratest-isolation.patch"
readonly MULTISITE_CONFIG_PATCH="${REPOSITORY_ROOT}/tools/wordpress-core-phpunit13-multisite-config.patch"
readonly MULTISITE_SCHEMA_PATCH="${REPOSITORY_ROOT}/tools/wordpress-core-phpunit13-multisite-schema.patch"
readonly BASELINE_FIXES_PATCH="${REPOSITORY_ROOT}/tools/wordpress-core-phpunit13-baseline-fixes.patch"
readonly PARATEST_SERIAL_PATCH="${REPOSITORY_ROOT}/tools/wordpress-core-phpunit13-paratest-serial.patch"
readonly GLOBAL_STYLES_FIXTURES_PATCH="${REPOSITORY_ROOT}/tools/wordpress-core-phpunit13-global-styles-fixtures.patch"
readonly MULTISITE_EXPECTATIONS_PATCH="${REPOSITORY_ROOT}/tools/wordpress-core-phpunit13-multisite-expectations.patch"
readonly STRICT_RUNTIME_PATCH="${REPOSITORY_ROOT}/tools/wordpress-core-phpunit13-strict-runtime.patch"
readonly OEMBED_FIXTURE_PATCH="${REPOSITORY_ROOT}/tools/wordpress-core-phpunit13-oembed-fixture.patch"
readonly NOTICE_CLEANUP_PATCH="${REPOSITORY_ROOT}/tools/wordpress-core-phpunit13-notice-cleanup.patch"
readonly SUBDOMAIN_FIXTURES_PATCH="${REPOSITORY_ROOT}/tools/wordpress-core-phpunit13-subdomain-fixtures.patch"
readonly PROCESS_ISOLATION_FIXTURES_PATCH="${REPOSITORY_ROOT}/tools/wordpress-core-phpunit13-process-isolation-fixtures.patch"
readonly FONT_STYLE_VARIATIONS_FIXTURES_PATCH="${REPOSITORY_ROOT}/tools/wordpress-core-phpunit13-font-style-variations-fixtures.patch"
readonly RESTORED_LEGACY_TESTS_PATCH="${REPOSITORY_ROOT}/tools/wordpress-core-phpunit13-restored-legacy-tests.patch"
readonly PARITY_FIXES_PATCH="${REPOSITORY_ROOT}/tools/wordpress-core-phpunit13-parity-fixes.patch"
readonly RUNTIME_PARITY_FIXES_PATCH="${REPOSITORY_ROOT}/tools/wordpress-core-phpunit13-runtime-parity-fixes.patch"
readonly UPSTREAM_BASELINE_DEFECTS_PATCH="${REPOSITORY_ROOT}/tools/wordpress-core-phpunit13-upstream-baseline-defects.patch"
readonly RUNTIME_DISCOVERY_PARITY_PATCH="${REPOSITORY_ROOT}/tools/wordpress-core-phpunit13-runtime-discovery-parity.patch"
readonly PARATEST_PROCESSES="${PARATEST_PROCESSES:-8}"
readonly SUITE="${1:-all}"

case "${SUITE}" in
    all|audit|single-site|multisite|multisite-subdomain|external-object-cache|html5lib|ajax|ms-files)
        ;;
    *)
        printf 'Unknown suite: %s\n' "${SUITE}" >&2
        printf '%s\n' 'Expected: all, audit, single-site, multisite, multisite-subdomain, external-object-cache, html5lib, ajax, or ms-files.' >&2
        exit 2
        ;;
esac

if [ "$#" -gt 0 ]; then
    shift
fi

if [ "$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')" != '8.5' ]; then
    printf '%s\n' 'The WordPress Core gate requires PHP 8.5.' >&2
    exit 1
fi

if [ ! -f "${CORE_PATCH}" ]; then
    printf 'Missing migration patch: %s\n' "${CORE_PATCH}" >&2
    exit 1
fi

if [ ! -f "${DATA_PROVIDER_PATCH}" ]; then
    printf 'Missing data provider migration patch: %s\n' "${DATA_PROVIDER_PATCH}" >&2
    exit 1
fi

if [ ! -f "${QUERY_PROVIDER_PATCH}" ]; then
    printf 'Missing query provider migration patch: %s\n' "${QUERY_PROVIDER_PATCH}" >&2
    exit 1
fi

if [ ! -f "${RUNTIME_FOLLOWUP_PATCH}" ]; then
    printf 'Missing runtime migration patch: %s\n' "${RUNTIME_FOLLOWUP_PATCH}" >&2
    exit 1
fi

if [ ! -f "${NATIVE_DEPRECATIONS_PATCH}" ]; then
    printf 'Missing native deprecations migration patch: %s\n' "${NATIVE_DEPRECATIONS_PATCH}" >&2
    exit 1
fi

if [ ! -f "${ASSERTION_METADATA_PATCH}" ]; then
    printf 'Missing assertion metadata migration patch: %s\n' "${ASSERTION_METADATA_PATCH}" >&2
    exit 1
fi

if [ ! -f "${OUTPUT_EXPECTATIONS_PATCH}" ]; then
    printf 'Missing output expectations migration patch: %s\n' "${OUTPUT_EXPECTATIONS_PATCH}" >&2
    exit 1
fi

if [ ! -f "${PARATEST_ISOLATION_PATCH}" ]; then
    printf 'Missing ParaTest isolation patch: %s\n' "${PARATEST_ISOLATION_PATCH}" >&2
    exit 1
fi

if [ ! -f "${MULTISITE_CONFIG_PATCH}" ]; then
    printf 'Missing multisite configuration migration patch: %s\n' "${MULTISITE_CONFIG_PATCH}" >&2
    exit 1
fi

if [ ! -f "${MULTISITE_SCHEMA_PATCH}" ]; then
    printf 'Missing multisite PHPUnit 13 schema patch: %s\n' "${MULTISITE_SCHEMA_PATCH}" >&2
    exit 1
fi

if [ ! -f "${BASELINE_FIXES_PATCH}" ]; then
    printf 'Missing baseline fixes patch: %s\n' "${BASELINE_FIXES_PATCH}" >&2
    exit 1
fi

if [ ! -f "${PARATEST_SERIAL_PATCH}" ]; then
    printf 'Missing ParaTest serial-isolation patch: %s\n' "${PARATEST_SERIAL_PATCH}" >&2
    exit 1
fi

if [ ! -f "${GLOBAL_STYLES_FIXTURES_PATCH}" ]; then
    printf 'Missing Global Styles fixture patch: %s\n' "${GLOBAL_STYLES_FIXTURES_PATCH}" >&2
    exit 1
fi

if [ ! -f "${MULTISITE_EXPECTATIONS_PATCH}" ]; then
    printf 'Missing multisite expectations patch: %s\n' "${MULTISITE_EXPECTATIONS_PATCH}" >&2
    exit 1
fi

if [ ! -f "${STRICT_RUNTIME_PATCH}" ]; then
    printf 'Missing strict runtime migration patch: %s\n' "${STRICT_RUNTIME_PATCH}" >&2
    exit 1
fi

if [ ! -f "${OEMBED_FIXTURE_PATCH}" ]; then
    printf 'Missing oEmbed fixture migration patch: %s\n' "${OEMBED_FIXTURE_PATCH}" >&2
    exit 1
fi

if [ ! -f "${NOTICE_CLEANUP_PATCH}" ]; then
    printf 'Missing notice cleanup migration patch: %s\n' "${NOTICE_CLEANUP_PATCH}" >&2
    exit 1
fi

if [ ! -f "${SUBDOMAIN_FIXTURES_PATCH}" ]; then
    printf 'Missing subdomain fixture migration patch: %s\n' "${SUBDOMAIN_FIXTURES_PATCH}" >&2
    exit 1
fi

if [ ! -f "${PROCESS_ISOLATION_FIXTURES_PATCH}" ]; then
    printf 'Missing process-isolation fixture migration patch: %s\n' "${PROCESS_ISOLATION_FIXTURES_PATCH}" >&2
    exit 1
fi

if [ ! -f "${FONT_STYLE_VARIATIONS_FIXTURES_PATCH}" ]; then
    printf 'Missing font style-variations fixture migration patch: %s\n' "${FONT_STYLE_VARIATIONS_FIXTURES_PATCH}" >&2
    exit 1
fi

if [ ! -f "${RESTORED_LEGACY_TESTS_PATCH}" ]; then
    printf 'Missing restored canonical legacy tests patch: %s\n' "${RESTORED_LEGACY_TESTS_PATCH}" >&2
    exit 1
fi

if [ ! -f "${PARITY_FIXES_PATCH}" ]; then
    printf 'Missing canonical parity fixes patch: %s\n' "${PARITY_FIXES_PATCH}" >&2
    exit 1
fi

if [ ! -f "${RUNTIME_PARITY_FIXES_PATCH}" ]; then
    printf 'Missing runtime parity fixes patch: %s\n' "${RUNTIME_PARITY_FIXES_PATCH}" >&2
    exit 1
fi

if [ ! -f "${UPSTREAM_BASELINE_DEFECTS_PATCH}" ]; then
    printf 'Missing verified upstream baseline defects patch: %s\n' "${UPSTREAM_BASELINE_DEFECTS_PATCH}" >&2
    exit 1
fi

if [ ! -f "${RUNTIME_DISCOVERY_PARITY_PATCH}" ]; then
    printf 'Missing runtime discovery parity patch: %s\n' "${RUNTIME_DISCOVERY_PARITY_PATCH}" >&2
    exit 1
fi

if [ -e "${WORDPRESS_CORE_WORKSPACE}" ] && [ ! -d "${WORDPRESS_CORE_WORKSPACE}/.git" ]; then
    printf 'Workspace exists and is not a Git checkout: %s\n' "${WORDPRESS_CORE_WORKSPACE}" >&2
    exit 1
fi

if [ ! -d "${WORDPRESS_CORE_WORKSPACE}/.git" ]; then
    mkdir -p "$(dirname -- "${WORDPRESS_CORE_WORKSPACE}")"
    git clone --filter=blob:none --no-checkout "${WORDPRESS_DEVELOP_REPOSITORY}" "${WORDPRESS_CORE_WORKSPACE}"
fi

origin_url=$(git -C "${WORDPRESS_CORE_WORKSPACE}" remote get-url origin)
if [ "${origin_url}" != "${WORDPRESS_DEVELOP_REPOSITORY}" ]; then
    printf 'Workspace origin mismatch: expected %s, got %s.\n' "${WORDPRESS_DEVELOP_REPOSITORY}" "${origin_url}" >&2
    exit 1
fi

git -C "${WORDPRESS_CORE_WORKSPACE}" fetch --depth=1 origin "${WORDPRESS_DEVELOP_REF}"
git -C "${WORDPRESS_CORE_WORKSPACE}" checkout --detach --force FETCH_HEAD
git -C "${WORDPRESS_CORE_WORKSPACE}" clean -ffdqx

actual_ref=$(git -C "${WORDPRESS_CORE_WORKSPACE}" rev-parse HEAD)
if [ "${actual_ref}" != "${WORDPRESS_DEVELOP_REF}" ]; then
    printf 'Expected WordPress Core ref %s, got %s.\n' "${WORDPRESS_DEVELOP_REF}" "${actual_ref}" >&2
    exit 1
fi

# The patch is an exported Core migration corpus. Only maintained Core test
# paths are applied; editor caches and migration-development artifacts are
# deliberately outside the public gate.
git -C "${WORDPRESS_CORE_WORKSPACE}" apply --check \
    --include='phpunit.xml.dist' \
    --include='tests/phpunit/**' \
    "${CORE_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply \
    --include='phpunit.xml.dist' \
    --include='tests/phpunit/**' \
    "${CORE_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply --check "${DATA_PROVIDER_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply "${DATA_PROVIDER_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply --check "${QUERY_PROVIDER_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply "${QUERY_PROVIDER_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply --check "${RUNTIME_FOLLOWUP_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply "${RUNTIME_FOLLOWUP_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply --check "${NATIVE_DEPRECATIONS_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply "${NATIVE_DEPRECATIONS_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply --check "${ASSERTION_METADATA_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply "${ASSERTION_METADATA_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply --check "${OUTPUT_EXPECTATIONS_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply "${OUTPUT_EXPECTATIONS_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply --check "${PARATEST_ISOLATION_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply "${PARATEST_ISOLATION_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply --check "${MULTISITE_CONFIG_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply "${MULTISITE_CONFIG_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply --check "${MULTISITE_SCHEMA_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply "${MULTISITE_SCHEMA_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply --check "${BASELINE_FIXES_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply "${BASELINE_FIXES_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply --check "${PARATEST_SERIAL_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply "${PARATEST_SERIAL_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply --check "${GLOBAL_STYLES_FIXTURES_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply "${GLOBAL_STYLES_FIXTURES_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply --check "${MULTISITE_EXPECTATIONS_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply "${MULTISITE_EXPECTATIONS_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply --check "${STRICT_RUNTIME_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply "${STRICT_RUNTIME_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply --check "${OEMBED_FIXTURE_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply "${OEMBED_FIXTURE_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply --check "${NOTICE_CLEANUP_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply "${NOTICE_CLEANUP_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply --check "${SUBDOMAIN_FIXTURES_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply "${SUBDOMAIN_FIXTURES_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply --check "${PROCESS_ISOLATION_FIXTURES_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply "${PROCESS_ISOLATION_FIXTURES_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply --check "${FONT_STYLE_VARIATIONS_FIXTURES_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply "${FONT_STYLE_VARIATIONS_FIXTURES_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply --check "${RESTORED_LEGACY_TESTS_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply "${RESTORED_LEGACY_TESTS_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply --check "${PARITY_FIXES_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply "${PARITY_FIXES_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply --check "${RUNTIME_PARITY_FIXES_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply "${RUNTIME_PARITY_FIXES_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply --check "${UPSTREAM_BASELINE_DEFECTS_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply "${UPSTREAM_BASELINE_DEFECTS_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply --check "${RUNTIME_DISCOVERY_PARITY_PATCH}"
git -C "${WORDPRESS_CORE_WORKSPACE}" apply "${RUNTIME_DISCOVERY_PARITY_PATCH}"

printf '\n==> Auditing migrated Core tests against canonical legacy ref\n'
env WORDPRESS_DEVELOP_REF="${WORDPRESS_DEVELOP_REF}" php \
    "${REPOSITORY_ROOT}/tools/audit-wordpress-core-tests.php" \
    "${WORDPRESS_CORE_WORKSPACE}" \
    "${REPOSITORY_ROOT}/tools/wordpress-core-test-audit-allowlist.json"

if [ "${SUITE}" = 'audit' ]; then
    exit 0
fi

# Exercise the harness from this checkout, including every CI candidate.
rsync -a --delete "${REPOSITORY_ROOT}/includes/" "${WORDPRESS_CORE_WORKSPACE}/tests/phpunit/includes/"
rsync -a --delete "${REPOSITORY_ROOT}/data/" "${WORDPRESS_CORE_WORKSPACE}/tests/phpunit/data/"
cp "${REPOSITORY_ROOT}/tools/core/bootstrap-phpunit13.php" \
    "${WORDPRESS_CORE_WORKSPACE}/tests/phpunit/includes/bootstrap-phpunit13.php"

composer --working-dir="${WORDPRESS_CORE_WORKSPACE}" remove --dev --no-update yoast/phpunit-polyfills
composer --working-dir="${WORDPRESS_CORE_WORKSPACE}" require --dev --no-update phpunit/phpunit:13.3.1 brianium/paratest:7.24.0
composer --working-dir="${WORDPRESS_CORE_WORKSPACE}" update --with-all-dependencies --no-interaction --prefer-dist
php "${REPOSITORY_ROOT}/tools/patch-paratest-phpunit13.php" \
    "${WORDPRESS_CORE_WORKSPACE}/vendor/brianium/paratest/src/WrapperRunner/WrapperWorker.php"

cp "${WORDPRESS_CORE_WORKSPACE}/wp-tests-config-sample.php" \
    "${WORDPRESS_CORE_WORKSPACE}/wp-tests-config.php"
# PHPUnit 13 builds a source map before spawning process-isolated tests and
# expects configured WordPress content directories to exist even in a pristine
# Core checkout. Create the empty canonical directories deterministically.
mkdir -p \
    "${WORDPRESS_CORE_WORKSPACE}/src/wp-content/mu-plugins" \
    "${WORDPRESS_CORE_WORKSPACE}/src/wp-content/plugins"
php "${REPOSITORY_ROOT}/tools/configure-core-test-database.php" \
    "${WORDPRESS_CORE_WORKSPACE}/wp-tests-config.php"

prepare_paratest_databases() {
    suite_name=$1

    case "${suite_name}" in
        multisite|multisite-subdomain|ms-files|external-object-cache)
            ms_tests=run_ms_tests
            ;;
        *)
            ms_tests=no_ms_tests
            ;;
    esac

    printf '==> Preparing ParaTest master database (%s)\n' "${suite_name}"
    php "${REPOSITORY_ROOT}/tools/configure-core-test-database.php" --reset-prefix wptests_
    (
        cd "${WORDPRESS_CORE_WORKSPACE}"
        env -u TEST_TOKEN XDEBUG_MODE=off php tests/phpunit/includes/install.php \
            wp-tests-config.php "${ms_tests}" run_core_tests
    )

    worker=1
    while [ "${worker}" -le "${PARATEST_PROCESSES}" ]; do
        printf '==> Preparing ParaTest worker database: %s/%s (%s)\n' "${worker}" "${PARATEST_PROCESSES}" "${suite_name}"
        php "${REPOSITORY_ROOT}/tools/configure-core-test-database.php" --reset-prefix "wptests_${worker}_"
        (
            cd "${WORDPRESS_CORE_WORKSPACE}"
            TEST_TOKEN="${worker}" XDEBUG_MODE=off php tests/phpunit/includes/install.php \
                wp-tests-config.php "${ms_tests}" run_core_tests
        )
        worker=$((worker + 1))
    done
}

verify_runtime_discovery_parity() {
    profile=$1
    configuration=$2
    manifest="${WORDPRESS_CORE_WORKSPACE}/.runtime-parity-${profile}.txt"
    serial_manifest="${WORDPRESS_CORE_WORKSPACE}/.runtime-parity-${profile}-serial.txt"

    printf '\n==> Runtime discovery parity: %s\n' "${profile}"
    (
        cd "${WORDPRESS_CORE_WORKSPACE}"
        WP_TESTS_SKIP_INSTALL=1 XDEBUG_MODE=off vendor/bin/phpunit \
            --configuration "${configuration}" \
            --list-tests
    ) >"${manifest}"
    (
        cd "${WORDPRESS_CORE_WORKSPACE}"
        WP_TESTS_SKIP_INSTALL=1 XDEBUG_MODE=off vendor/bin/phpunit \
            --configuration "${configuration}" \
            --list-tests \
            --group paratest-serial
    ) >"${serial_manifest}"

    php "${REPOSITORY_ROOT}/tools/verify-wordpress-core-runtime-parity.php" \
        "${profile}" "${manifest}" "${serial_manifest}"
    rm -f "${manifest}" "${serial_manifest}"
}

run_serial_suite() {
    suite_name=$1
    configuration=$2
    shift 2
    printf '\n==> WordPress Core serial suite: %s\n' "${suite_name}"
    php "${REPOSITORY_ROOT}/tools/configure-core-test-database.php" --reset-prefix wptests_
    (
        cd "${WORDPRESS_CORE_WORKSPACE}"
        env -u TEST_TOKEN XDEBUG_MODE=off php tests/phpunit/includes/install.php \
            wp-tests-config.php run_ms_tests run_core_tests
        WP_TESTS_SKIP_INSTALL=1 XDEBUG_MODE=off vendor/bin/phpunit \
            --configuration "${configuration}" \
            --fail-on-warning \
            --fail-on-risky \
            --fail-on-deprecation \
            --fail-on-phpunit-deprecation \
            --fail-on-notice \
            --fail-on-phpunit-notice \
            --display-notices \
            --display-phpunit-notices \
            "$@"
    )
}

run_suite() {
    suite_name=$1
    shift
    printf '\n==> WordPress Core suite: %s\n' "${suite_name}"
    prepare_paratest_databases "${suite_name}"
    case "${suite_name}" in
        single-site)
            verify_runtime_discovery_parity single-site phpunit.xml.dist
            ;;
        multisite|multisite-subdomain)
            verify_runtime_discovery_parity multisite tests/phpunit/multisite.xml
            ;;
    esac
    rm -rf "${WORDPRESS_CORE_WORKSPACE}"/tests/phpunit/data-paratest-*
    quiet_bootstrap=0
    if [ "${suite_name}" = 'ajax' ]; then
        quiet_bootstrap=1
    fi
    (
        cd "${WORDPRESS_CORE_WORKSPACE}"
        WP_TESTS_SKIP_INSTALL=1 WP_TESTS_QUIET_BOOTSTRAP="${quiet_bootstrap}" XDEBUG_MODE=off vendor/bin/paratest \
            --processes="${PARATEST_PROCESSES}" \
            --fail-on-warning \
            --fail-on-risky \
            --fail-on-deprecation \
            --fail-on-phpunit-deprecation \
            --fail-on-notice \
            --fail-on-phpunit-notice \
            --display-notices \
            --display-phpunit-notices \
            "$@"
    )
}

run_isolated_test() {
    suite_name=$1
    configuration=$2
    test_filter=$3
    printf '\n==> WordPress Core isolated PHPUnit 13 test: %s :: %s\n' "${suite_name}" "${test_filter}"
    (
        cd "${WORDPRESS_CORE_WORKSPACE}"
        XDEBUG_MODE=off vendor/bin/phpunit \
            --configuration "${configuration}" \
            --filter "${test_filter}" \
            --fail-on-warning \
            --fail-on-risky \
            --fail-on-deprecation \
            --fail-on-phpunit-deprecation \
            --fail-on-notice \
            --fail-on-phpunit-notice \
            --display-notices \
            --display-phpunit-notices
    )
}

run_verified_upstream_failure() {
    suite_name=$1
    configuration=$2
    test_filter=$3
    expected_pattern=$4
    printf '\n==> Verifying canonical upstream baseline defect: %s :: %s\n' "${suite_name}" "${test_filter}"
    output_file="${WORDPRESS_CORE_WORKSPACE}/.phpunit-upstream-baseline-defect.log"
    rm -f "${output_file}"
    set +e
    (
        cd "${WORDPRESS_CORE_WORKSPACE}"
        WP_TESTS_SUBDOMAIN_INSTALL=1 XDEBUG_MODE=off vendor/bin/phpunit \
            --configuration "${configuration}" \
            --filter "${test_filter}" \
            --fail-on-warning \
            --fail-on-risky \
            --fail-on-deprecation \
            --fail-on-phpunit-deprecation \
            --fail-on-notice \
            --fail-on-phpunit-notice
    ) >"${output_file}" 2>&1
    exit_code=$?
    set -e
    cat "${output_file}"
    if [ "${exit_code}" -eq 0 ]; then
        printf 'Expected canonical upstream defect unexpectedly passed: %s\n' "${test_filter}" >&2
        exit 1
    fi
    if ! grep -F "${expected_pattern}" "${output_file}" >/dev/null; then
        printf 'Canonical upstream defect changed unexpectedly: %s\n' "${test_filter}" >&2
        exit 1
    fi
    if ! grep -F 'There was 1 failure:' "${output_file}" >/dev/null; then
        printf 'Canonical upstream defect did not fail in exactly the expected way: %s\n' "${test_filter}" >&2
        exit 1
    fi
    rm -f "${output_file}"
}

run_single_site_isolated_tests() {
    run_isolated_test single-site phpunit.xml.dist 'Tests_Media::test_wp_filter_content_tags_does_not_lazy_load_first_featured_image_in_block_theme'
    run_isolated_test single-site phpunit.xml.dist 'Tests_Block_Supports_Layout::test_layout_support_flag_uses_variation_block_gap_value'
    run_isolated_test single-site phpunit.xml.dist 'Tests_L10n_LoadScriptTextdomain'
    run_isolated_test single-site phpunit.xml.dist 'Tests_Blocks_Render::test_do_block_output'
    run_isolated_test single-site phpunit.xml.dist 'Tests_Query_ThePost::test_wp_query_with_custom_fields_value_populates_the_global_post'
    run_isolated_test single-site phpunit.xml.dist 'Tests_Query_Results::test_query_orderby_post_parent__in$'
    run_isolated_test single-site phpunit.xml.dist 'Tests_Query_Results::test_query_orderby_post_parent__in_with_order_desc'
    run_isolated_test single-site phpunit.xml.dist 'Tests_Widgets_wpWidgetMedia::test_constructor'
    run_isolated_test single-site phpunit.xml.dist 'WP_REST_Global_Styles_Revisions_Controller_Test::test_get_item_preserves_block_style_variations'
    run_isolated_test single-site phpunit.xml.dist 'WP_REST_Global_Styles_Revisions_Controller_Test::test_multiple_block_variations_are_preserved'
    run_isolated_test single-site phpunit.xml.dist 'WP_REST_Global_Styles_Revisions_Controller_Test::test_theme_variations_are_registered_for_revisions'
    run_isolated_test single-site phpunit.xml.dist 'WP_REST_Global_Styles_Revisions_Controller_Test::test_get_items_preserves_block_style_variations'
}

run_multisite_isolated_tests() {
    run_isolated_test multisite tests/phpunit/multisite.xml 'Tests_Media::test_wp_filter_content_tags_does_not_lazy_load_first_featured_image_in_block_theme'
    run_isolated_test multisite tests/phpunit/multisite.xml 'Tests_Block_Supports_Layout::test_layout_support_flag_uses_variation_block_gap_value'
    run_isolated_test multisite tests/phpunit/multisite.xml 'Tests_L10n_LoadScriptTextdomain'
    run_isolated_test multisite tests/phpunit/multisite.xml 'Tests_Blocks_Render::test_do_block_output'
    run_isolated_test multisite tests/phpunit/multisite.xml 'Tests_Fonts_WpFontDir::test_fonts_dir_for_multisite'
    run_isolated_test multisite tests/phpunit/multisite.xml 'Tests_Multisite_Site::test_switch_upload_dir'
    run_isolated_test multisite tests/phpunit/multisite.xml 'Tests_Query_ThePost::test_wp_query_with_custom_fields_value_populates_the_global_post'
    run_isolated_test multisite tests/phpunit/multisite.xml 'Tests_Query_Results::test_query_orderby_post_parent__in$'
    run_isolated_test multisite tests/phpunit/multisite.xml 'Tests_Query_Results::test_query_orderby_post_parent__in_with_order_desc'
    run_isolated_test multisite tests/phpunit/multisite.xml 'Tests_Widgets_wpWidgetMedia::test_constructor'
    run_isolated_test multisite tests/phpunit/multisite.xml 'WP_REST_Global_Styles_Revisions_Controller_Test::test_get_item_preserves_block_style_variations'
    run_isolated_test multisite tests/phpunit/multisite.xml 'WP_REST_Global_Styles_Revisions_Controller_Test::test_multiple_block_variations_are_preserved'
    run_isolated_test multisite tests/phpunit/multisite.xml 'WP_REST_Global_Styles_Revisions_Controller_Test::test_theme_variations_are_registered_for_revisions'
    run_isolated_test multisite tests/phpunit/multisite.xml 'WP_REST_Global_Styles_Revisions_Controller_Test::test_get_items_preserves_block_style_variations'
}

run_subdomain_suite() {
    export WP_TESTS_SUBDOMAIN_INSTALL=1
    run_suite "$@"
}

run_external_object_cache_suite() {
    object_cache_dropin="${WORDPRESS_CORE_WORKSPACE}/src/wp-content/object-cache.php"
    cp "${WORDPRESS_CORE_WORKSPACE}/tests/phpunit/includes/object-cache.php" "${object_cache_dropin}"
    trap 'rm -f "${object_cache_dropin}"' EXIT HUP INT TERM
    run_suite "$@"
    rm -f "${object_cache_dropin}"
    trap - EXIT HUP INT TERM
}

run_selected_suite() {
    selected_suite=$1
    shift
    case "${selected_suite}" in
        single-site)
            run_suite single-site --configuration phpunit.xml.dist \
                --exclude-group ajax \
                --exclude-group ms-files \
                --exclude-group ms-required \
                --exclude-group external-http \
                --exclude-group html-api-html5lib-tests \
                --exclude-group paratest-serial \
                "$@"
            run_single_site_isolated_tests
            ;;
        multisite)
            run_suite multisite --configuration tests/phpunit/multisite.xml \
                --exclude-group ajax \
                --exclude-group ms-files \
                --exclude-group ms-excluded \
                --exclude-group external-http \
                --exclude-group html-api-html5lib-tests \
                --exclude-group paratest-serial \
                "$@"
            run_multisite_isolated_tests
            ;;
        multisite-subdomain)
            run_subdomain_suite multisite-subdomain --configuration tests/phpunit/multisite.xml \
                --exclude-group ajax \
                --exclude-group ms-files \
                --exclude-group ms-excluded \
                --exclude-group ms-subdirectory-only \
                --exclude-group upstream-baseline-defect \
                --exclude-group external-http \
                --exclude-group html-api-html5lib-tests \
                --exclude-group paratest-serial \
                "$@"
            run_multisite_isolated_tests
            run_verified_upstream_failure multisite-subdomain tests/phpunit/multisite.xml \
                'Tests_Multisite_GetBlogDetails::test_get_blog_details_with_only_domain_in_fields_subdomain' \
                'Failed asserting that'
            ;;
        external-object-cache)
            # Canonical WordPress enables the object-cache.php drop-in and runs the
            # normal test profile; there is no external-object-cache PHPUnit group.
            # Keep this profile serial: a shared persistent cache cannot be safely
            # flushed by independent ParaTest workers without cross-worker races.
            object_cache_dropin="${WORDPRESS_CORE_WORKSPACE}/src/wp-content/object-cache.php"
            cp "${WORDPRESS_CORE_WORKSPACE}/tests/phpunit/includes/object-cache.php" "${object_cache_dropin}"
            trap 'rm -f "${object_cache_dropin}"' EXIT HUP INT TERM
            run_serial_suite external-object-cache tests/phpunit/multisite.xml \
                --exclude-group ajax \
                --exclude-group ms-files \
                --exclude-group ms-excluded \
                --exclude-group external-http \
                --exclude-group html-api-html5lib-tests \
                --exclude-group paratest-serial \
                "$@"
            run_multisite_isolated_tests
            rm -f "${object_cache_dropin}"
            trap - EXIT HUP INT TERM
            ;;
        html5lib)
            run_suite html5lib --configuration phpunit.xml.dist --group html-api-html5lib-tests --exclude-group ajax,ms-files,ms-required,external-http,external-object-cache "$@"
            ;;
        ajax)
            run_suite ajax --configuration phpunit.xml.dist --group ajax "$@"
            ;;
        ms-files)
            run_suite ms-files --configuration tests/phpunit/multisite.xml --group ms-files "$@"
            ;;
    esac
}

if [ "${SUITE}" = 'all' ]; then
    for core_suite in single-site multisite multisite-subdomain ajax ms-files external-object-cache html5lib; do
        run_selected_suite "${core_suite}" "$@"
    done
else
    run_selected_suite "${SUITE}" "$@"
fi
