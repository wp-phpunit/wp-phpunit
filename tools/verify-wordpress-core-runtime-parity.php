<?php

declare(strict_types=1);

if ($argc !== 4) {
    fwrite(STDERR, "Usage: php tools/verify-wordpress-core-runtime-parity.php <single-site|multisite> <list-tests.txt> <serial-list-tests.txt>\n");
    exit(2);
}

$profile = $argv[1];
$manifestPath = $argv[2];
$serialManifestPath = $argv[3];

$expectations = [
    'single-site' => [
        'canonical_total' => 29140,
        'migrated_total' => 29147,
        'serial_total' => 95,
    ],
    'multisite' => [
        'canonical_total' => 29932,
        'migrated_total' => 29939,
        'serial_total' => 97,
    ],
];

if (!isset($expectations[$profile])) {
    fwrite(STDERR, "Unknown runtime parity profile: {$profile}\n");
    exit(2);
}

$knownDatasetGains = [
    'Tests_Functions_WpParseSlugList::test_wp_parse_slug_list' => [12, 14],
    'Tests_DB::test_process_fields_value_too_long_for_field' => [2, 4],
    'Tests_Functions_wpListUtil::test_wp_list_util_sort' => [39, 40],
    'Tests_Fonts_WpPrintFontFaces::test_should_print_given_fonts' => [4, 5],
    'Tests_Fonts_WPFontFace_GenerateAndPrint::test_should_generate_and_print_given_fonts' => [4, 5],
];

$tests = readManifest($manifestPath);
$serialTests = readManifest($serialManifestPath);
$expected = $expectations[$profile];

if (count($tests) !== $expected['migrated_total']) {
    fail(sprintf(
        '%s discovery count changed: expected %d migrated cases (canonical %d + 7 reviewed datasets), got %d.',
        $profile,
        $expected['migrated_total'],
        $expected['canonical_total'],
        count($tests),
    ));
}

if (count($serialTests) !== $expected['serial_total']) {
    fail(sprintf(
        '%s serial inventory changed: expected %d cases, got %d.',
        $profile,
        $expected['serial_total'],
        count($serialTests),
    ));
}

$cardinality = [];
foreach ($tests as $test) {
    $base = normalizeTestIdentity($test);
    $cardinality[$base] = ($cardinality[$base] ?? 0) + 1;
}

$gain = 0;
foreach ($knownDatasetGains as $method => [$canonicalCount, $migratedCount]) {
    $actualCount = $cardinality[$method] ?? 0;
    if ($actualCount !== $migratedCount) {
        fail(sprintf(
            '%s dataset cardinality changed for %s: canonical %d, expected migrated %d, got %d.',
            $profile,
            $method,
            $canonicalCount,
            $migratedCount,
            $actualCount,
        ));
    }
    $gain += $migratedCount - $canonicalCount;
}

if ($gain !== $expected['migrated_total'] - $expected['canonical_total']) {
    fail(sprintf(
        '%s reviewed dataset gain mismatch: known gains total %d but profile delta is %d.',
        $profile,
        $gain,
        $expected['migrated_total'] - $expected['canonical_total'],
    ));
}

printf("Runtime parity %s: GREEN\n", $profile);
printf("  canonical discovered cases: %d\n", $expected['canonical_total']);
printf("  migrated discovered cases: %d\n", count($tests));
printf("  reviewed additional datasets: +%d\n", $gain);
printf("  paratest-serial cases accounted: %d\n", count($serialTests));

/** @return list<string> */
function readManifest(string $path): array
{
    if (!is_file($path)) {
        fail("Runtime parity manifest does not exist: {$path}");
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        fail("Unable to read runtime parity manifest: {$path}");
    }

    $tests = [];
    foreach ($lines as $line) {
        if (str_starts_with($line, ' - ')) {
            $tests[] = trim(substr($line, 3));
        }
    }

    return $tests;
}

function normalizeTestIdentity(string $test): string
{
    $test = preg_replace('/ with data set(?: #[0-9]+| ".*")(?: \(.*\))?$/', '', $test) ?? $test;
    $test = preg_replace('/(?<=\\w)(?:#[0-9]+|"[^"]*")$/', '', $test) ?? $test;
    return $test;
}

function fail(string $message): never
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}
