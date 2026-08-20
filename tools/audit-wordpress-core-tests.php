<?php

declare(strict_types=1);

const DEFAULT_WORDPRESS_REF = '98d6d9097862a5cba242bfae097bf60b93bea867';

if ($argc < 2 || $argc > 3) {
    fwrite(STDERR, "Usage: php tools/audit-wordpress-core-tests.php <wordpress-develop-workspace> [allowlist.json]\n");
    exit(2);
}

$workspace = realpath($argv[1]);
if ($workspace === false || !is_dir($workspace . '/.git')) {
    fwrite(STDERR, "WordPress workspace is not a Git checkout: {$argv[1]}\n");
    exit(2);
}

$allowlistPath = $argv[2] ?? __DIR__ . '/wordpress-core-test-audit-allowlist.json';
$allowlist = loadAllowlist($allowlistPath);
$ref = getenv('WORDPRESS_DEVELOP_REF') ?: DEFAULT_WORDPRESS_REF;
$currentRef = trim(runGit($workspace, ['rev-parse', 'HEAD']));
if ($currentRef !== $ref) {
    fwrite(STDERR, "Canonical ref mismatch: expected {$ref}, got {$currentRef}.\n");
    exit(2);
}

$canonicalFiles = array_values(array_filter(
    preg_split('/\R/', trim(runGit($workspace, ['ls-tree', '-r', '--name-only', $ref, '--', 'tests/phpunit/tests']))) ?: [],
    static fn(string $path): bool => str_ends_with($path, '.php'),
));

$canonical = [];
foreach ($canonicalFiles as $path) {
    $source = runGit($workspace, ['show', $ref . ':' . $path]);
    foreach (scanTests($source, $path) as $id => $test) {
        $canonical[$id] = $test;
    }
}

$current = [];
$currentRoot = $workspace . '/tests/phpunit/tests';
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($currentRoot, FilesystemIterator::SKIP_DOTS),
);
foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $currentPath = $file->getPathname();
    $relativePath = substr($currentPath, strlen($workspace) + 1);
    $currentSource = file_get_contents($currentPath);
    if ($currentSource === false) {
        throw new RuntimeException("Unable to read {$currentPath}");
    }
    foreach (scanTests($currentSource, $relativePath) as $id => $test) {
        $current[$id] = $test;
    }
}

$legacyPathsPresent = array_fill_keys(array_map(
    static fn(array $test): string => $test['path'],
    $current,
), true);
$missingLegacyPaths = array_values(array_filter(
    $canonicalFiles,
    static fn(string $path): bool => !isset($legacyPathsPresent[$path]) && !is_file($workspace . '/' . $path),
));

$findings = [];

foreach ($canonical as $id => $legacyTest) {
    if (!isset($current[$id])) {
        addFinding($findings, $allowlist, 'missing-test', $id, "Legacy test method is missing from the migrated tree ({$legacyTest['path']}).");
        continue;
    }

    $migratedTest = $current[$id];
    foreach ($legacyTest['checks'] as $check => $count) {
        $migratedCount = semanticallyEquivalentCheckCount($check, $migratedTest['checks']);
        if ($migratedCount < $count) {
            addFinding(
                $findings,
                $allowlist,
                'reduced-check',
                $id . '|' . $check,
                sprintf('%s reduced from %d to %d in %s.', $check, $count, $migratedCount, $migratedTest['path']),
            );
        }
    }

    if (!$legacyTest['skips'] && $migratedTest['skips']) {
        addFinding($findings, $allowlist, 'added-skip', $id, "Migrated test adds skip/incomplete behavior ({$migratedTest['path']}).");
    }

    if ($legacyTest['providers'] !== [] && $migratedTest['providers'] === []) {
        addFinding($findings, $allowlist, 'removed-provider', $id, "Legacy data provider metadata disappeared ({$migratedTest['path']}).");
    }
}

$canonicalIds = array_keys($canonical);
$currentIds = array_keys($current);
$missingIds = array_values(array_diff($canonicalIds, $currentIds));
$newIds = array_values(array_diff($currentIds, $canonicalIds));

printf("Canonical WordPress test audit\n");
printf("  ref: %s\n", $ref);
printf("  legacy PHP test files: %d\n", count($canonicalFiles));
printf("  legacy test methods: %d\n", count($canonical));
printf("  migrated legacy test methods found: %d\n", count($canonical) - count($missingIds));
printf("  additional migrated test methods: %d\n", count($newIds));
printf("  legacy paths moved/removed: %d\n", count($missingLegacyPaths));
printf("  unapproved anti-weakening findings: %d\n", count($findings));

if ($findings !== []) {
    foreach (array_slice($findings, 0, 200) as $finding) {
        printf("  FAIL [%s] %s: %s\n", $finding['rule'], $finding['key'], $finding['message']);
    }
    if (count($findings) > 200) {
        printf("  ... %d additional findings omitted.\n", count($findings) - 200);
    }
    fwrite(STDERR, "Canonical legacy test contract weakened or not yet reviewed.\n");
    exit(1);
}

fwrite(STDOUT, "Canonical legacy test contract preserved.\n");

/** @return array<string, mixed> */
function loadAllowlist(string $path): array
{
    if (!is_file($path)) {
        return [];
    }
    $json = file_get_contents($path);
    if ($json === false) {
        throw new RuntimeException("Unable to read allowlist {$path}");
    }
    $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException("Invalid allowlist {$path}");
    }
    return $decoded;
}

/** @param list<string> $args */
function runGit(string $workspace, array $args): string
{
    $command = array_merge(['git', '-C', $workspace], $args);
    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($command, $descriptors, $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start git.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    if ($exit !== 0) {
        throw new RuntimeException("git failed: {$stderr}");
    }
    return $stdout === false ? '' : $stdout;
}

/** @return array<string, array{path:string, checks:array<string,int>, skips:bool, providers:list<string>}> */
function scanTests(string $source, string $path): array
{
    $tokens = token_get_all($source);
    $tests = [];
    $class = null;
    $pendingDoc = '';
    $pendingAttributes = '';
    $count = count($tokens);

    for ($i = 0; $i < $count; ++$i) {
        $token = $tokens[$i];
        if (is_array($token) && $token[0] === T_DOC_COMMENT) {
            $pendingDoc = $token[1];
            continue;
        }
        if (is_array($token) && defined('T_ATTRIBUTE') && $token[0] === T_ATTRIBUTE) {
            $pendingAttributes .= collectAttribute($tokens, $i);
            continue;
        }
        if (is_array($token) && $token[0] === T_CLASS) {
            $namedClass = nextIdentifier($tokens, $i + 1);
            if ($namedClass !== null) {
                $class = $namedClass;
            }
            $pendingDoc = '';
            $pendingAttributes = '';
            continue;
        }
        if (!is_array($token) || $token[0] !== T_FUNCTION || $class === null) {
            if (!isIgnorable($token)) {
                if (!is_array($token) || !in_array($token[0], [T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_FINAL, T_ABSTRACT], true)) {
                    $pendingDoc = '';
                    $pendingAttributes = '';
                }
            }
            continue;
        }

        $name = nextIdentifier($tokens, $i + 1);
        if ($name === null) {
            continue;
        }
        $isTest = str_starts_with(strtolower($name), 'test')
            || stripos($pendingDoc, '@test') !== false
            || preg_match('/(?:PHPUnit\\\\Framework\\\\Attributes\\\\)?Test\b/', $pendingAttributes) === 1;
        if (!$isTest) {
            $pendingDoc = '';
            $pendingAttributes = '';
            continue;
        }

        [$body, $end] = collectFunctionBody($tokens, $i);
        $metadata = $pendingDoc . "\n" . $pendingAttributes;
        $checks = [];
        if (preg_match_all('/->\s*((?:assert|expect)[A-Z][A-Za-z0-9_]*|expects|with|withConsecutive|method|willReturn|willReturnCallback|willReturnOnConsecutiveCalls|willThrowException)\s*\(/', $body, $matches)) {
            foreach ($matches[1] as $check) {
                $checks[$check] = ($checks[$check] ?? 0) + 1;
            }
        }

        // PHPUnit's any() matcher does not require an invocation. Do not treat
        // its syntactic expects() wrapper as a coverage contract.
        $nonBindingExpects = preg_match_all('/->\s*expects\s*\(\s*\$this->any\s*\(\s*\)\s*\)/', $body);
        if ($nonBindingExpects > 0 && isset($checks['expects'])) {
            $checks['expects'] = max(0, $checks['expects'] - $nonBindingExpects);
        }

        // PHPUnit 10+ removed withConsecutive(). The migrated equivalent uses
        // an exact invocation matcher plus a callback that checks arguments by
        // numberOfInvocations(). Record that only when the callback contains
        // an explicit assertion, so a plain willReturnCallback() is not enough.
        if (
            isset($checks['willReturnCallback'])
            && str_contains($body, 'numberOfInvocations()')
            && preg_match('/->\s*assert[A-Z][A-Za-z0-9_]*\s*\(/', $body) === 1
        ) {
            $checks['assertInvocationSequence'] = $checks['willReturnCallback'];
        }

        $outputCaptureCount = substr_count($body, 'ob_get_clean(');
        if ($outputCaptureCount > 0 && isset($checks['assertSame'])) {
            $checks['assertCapturedOutputSame'] = min($outputCaptureCount, $checks['assertSame']);
        }
        if (
            str_contains($body, 'ob_start(')
            && (isset($checks['assertEqualHTML']) || isset($checks['assertEqualHTMLScriptTagById']))
        ) {
            $checks['assertCapturedOutputRegex'] = 1;
        }
        if (
            str_contains($body, 'set_error_handler(')
            && str_contains($body, 'E_DEPRECATED')
            && str_contains($body, '$deprecations')
            && isset($checks['assertContains'])
        ) {
            $checks['assertCapturedPhpDeprecation'] = 1;
        }

        $skips = preg_match('/->\s*markTest(?:Skipped|Incomplete)\s*\(/', $body) === 1;
        $providers = [];
        if (preg_match_all('/@dataProvider\s+([^\s*]+)/', $metadata, $providerMatches)) {
            $providers = array_merge($providers, $providerMatches[1]);
        }
        if (preg_match_all('/DataProvider(?:External)?\s*\(([^)]*)\)/', $metadata, $providerMatches)) {
            $providers = array_merge($providers, $providerMatches[1]);
        }

        $tests[$class . '::' . $name] = [
            'path' => $path,
            'checks' => $checks,
            'skips' => $skips,
            'providers' => array_values(array_unique($providers)),
        ];
        $i = max($i, $end);
        $pendingDoc = '';
        $pendingAttributes = '';
    }

    return $tests;
}

/** @param array<int, array|string> $tokens */
function collectAttribute(array $tokens, int &$index): string
{
    $text = '';
    $depth = 0;
    $count = count($tokens);
    for (; $index < $count; ++$index) {
        $token = $tokens[$index];
        $piece = is_array($token) ? $token[1] : $token;
        $text .= $piece;
        $depth += substr_count($piece, '[');
        $depth -= substr_count($piece, ']');
        if ($depth <= 0 && str_contains($text, ']')) {
            break;
        }
    }
    return $text;
}

/** @param array<int, array|string> $tokens */
function nextIdentifier(array $tokens, int $index): ?string
{
    for ($count = count($tokens); $index < $count; ++$index) {
        $token = $tokens[$index];
        if (is_array($token) && $token[0] === T_STRING) {
            return $token[1];
        }
        if ($token === '(' || $token === '{' || $token === ';') {
            return null;
        }
    }
    return null;
}

/** @param array<int, array|string> $tokens @return array{string,int} */
function collectFunctionBody(array $tokens, int $index): array
{
    $body = '';
    $depth = 0;
    $started = false;
    $count = count($tokens);
    for (; $index < $count; ++$index) {
        $token = $tokens[$index];
        $piece = is_array($token) ? $token[1] : $token;
        if ($piece === '{') {
            ++$depth;
            $started = true;
        } elseif ($piece === '}') {
            --$depth;
        }
        if ($started) {
            $body .= $piece;
        }
        if ($started && $depth === 0) {
            break;
        }
    }
    return [$body, $index];
}

function isIgnorable(array|string $token): bool
{
    return is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
}

/** @param array<string,int> $migratedChecks */
function semanticallyEquivalentCheckCount(string $legacyCheck, array $migratedChecks): int
{
    $count = $migratedChecks[$legacyCheck] ?? 0;

    // Identity equality is strictly stronger than PHPUnit's value equality.
    if ($legacyCheck === 'assertEquals') {
        $count += $migratedChecks['assertSame'] ?? 0;
    }

    // The harness helper captures only E_USER_DEPRECATED, requires at least one
    // deprecation, and asserts that its combined message contains the expected
    // legacy identifier. One helper call therefore preserves both the legacy
    // deprecation-type and deprecation-message expectations.
    if (in_array($legacyCheck, ['expectDeprecation', 'expectDeprecationMessage', 'expectDeprecationMessageMatches'], true)) {
        $count += $migratedChecks['assertExpectedUserDeprecation'] ?? 0;
        $count += $migratedChecks['assertExpectedPhpDeprecations'] ?? 0;
        $count += $migratedChecks['expectPhpDeprecationMessage'] ?? 0;
        $count += $migratedChecks['assertCapturedPhpDeprecation'] ?? 0;
    }

    if ($legacyCheck === 'expectOutputString') {
        $count += $migratedChecks['assertCapturedOutputSame'] ?? 0;
    }

    if ($legacyCheck === 'expectOutputRegex') {
        $count += $migratedChecks['assertCapturedOutputRegex'] ?? 0;
    }

    // withConsecutive() was removed from modern PHPUnit. The migration uses
    // willReturnCallback() with the exact invocation matcher and explicit
    // per-invocation argument assertions instead.
    if ($legacyCheck === 'withConsecutive') {
        $count += $migratedChecks['assertInvocationSequence'] ?? 0;
    }

    // A callback used for the above sequence replacement also supplies the
    // return value formerly provided by willReturn().
    if ($legacyCheck === 'willReturn') {
        $count += $migratedChecks['assertInvocationSequence'] ?? 0;
    }

    return $count;
}

/** @param list<array{rule:string,key:string,message:string}> $findings @param array<string,mixed> $allowlist */
function addFinding(array &$findings, array $allowlist, string $rule, string $key, string $message): void
{
    $allowed = $allowlist[$rule] ?? [];
    if (is_array($allowed) && array_key_exists($key, $allowed)) {
        $reason = $allowed[$key];
        if (!is_string($reason) || trim($reason) === '') {
            throw new RuntimeException("Allowlist entry {$rule}:{$key} must contain a non-empty rationale.");
        }
        return;
    }
    $findings[] = ['rule' => $rule, 'key' => $key, 'message' => $message];
}
