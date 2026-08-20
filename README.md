# WordPress PHPUnit 13 Harness

A modern WordPress integration-test harness for PHP 8.5 and PHPUnit 13.

This fork tracks the WordPress core test library while removing its historical
PHPUnit compatibility layers. It targets one supported runtime:

- PHP 8.5
- PHPUnit 13
- the current WordPress release

## Installation

Add this repository to the consuming project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/kolotov/wp-phpunit"
        }
    ],
    "require-dev": {
        "wp-phpunit/wp-phpunit": "dev-phpunit-13"
    }
}
```

Use a tagged release in reproducible builds once the PHPUnit 13 port is
validated and tagged.

## Scope

The repository provides the WordPress bootstrap, fixtures, factories, and base
test cases needed by plugin and theme integration tests. Compatibility shims
for historical PHPUnit releases are outside its scope.

## Support Policy

This project intentionally supports a single modern toolchain. The following
legacy compatibility mechanisms are unsupported and will not be accepted:

- PHPUnit 12 and earlier
- PHP versions earlier than 8.5
- `yoast/phpunit-polyfills`
- `PHPUnit_Framework_*` and `PHPUnit_Util_*` aliases
- DocBlock-based PHPUnit metadata
- compatibility adapters spanning multiple PHPUnit generations

PHPUnit extensions and consuming tests must use PHPUnit 13 public APIs, native
lifecycle methods, and PHP attributes. The harness retains WordPress test hooks
such as `set_up()` internally to run the official WordPress Core suite.

## Upstream

WordPress test-library changes originate in
[`wordpress-develop`](https://github.com/WordPress/wordpress-develop). Relevant
changes are periodically incorporated into this fork and adapted to its PHP 8.5
and PHPUnit 13 baseline.

## Status

The PHPUnit 13 port is under active development on the `phpunit-13` branch.

## WordPress Core compatibility gate

The compatibility gate tests this checkout against pinned `wordpress-develop`
commit `98d6d9097862a5cba242bfae097bf60b93bea867`. It clones Core into `.build/`,
applies the maintained PHPUnit 13 migration corpus, audits the resulting Core
tests against the untouched tests at that canonical commit, overlays this
harness, and installs PHPUnit 13 for the Core checkout.

The canonical audit is deny-by-default for deleted legacy tests, reduced
assertion/expectation calls, newly introduced skips/incomplete paths, and lost
data-provider metadata. Any intentional semantic delta must be documented in
`tools/wordpress-core-test-audit-allowlist.json` with a non-empty rationale.
Run it independently with:

```shell
composer core:audit
```

The complete gate runs all supported database-backed profiles:

```shell
composer core:test
```

Each profile is independently runnable and independently enforced in CI:

```shell
composer core:test:single-site
composer core:test:multisite
composer core:test:ajax
composer core:test:ms-files
```

Database connection settings can be supplied with `WP_TESTS_DB_NAME`,
`WP_TESTS_DB_USER`, `WP_TESTS_DB_PASSWORD`, and `WP_TESTS_DB_HOST`. The default
values match the MariaDB service declared in the GitHub Actions workflow.
