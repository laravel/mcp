<?php

declare(strict_types=1);

$suite = $argv[1] ?? null;

if (! in_array($suite, ['server', 'client'], true)) {
    fwrite(STDERR, "Usage: php score.php <server|client>\n");

    exit(1);
}

$resultsDir = __DIR__.'/results/'.$suite;

if (! is_dir($resultsDir)) {
    fwrite(STDERR, sprintf("No results in [%s]. Run the suite with --output-dir first.\n", $resultsDir));

    exit(1);
}

$directory = new RecursiveDirectoryIterator($resultsDir, FilesystemIterator::SKIP_DOTS);

$passed = 0;
$total = 0;

foreach (new RecursiveIteratorIterator($directory) as $file) {
    if ($file->getFilename() !== 'checks.json') {
        continue;
    }

    $checks = json_decode((string) file_get_contents($file->getPathname()), true);

    if (! is_array($checks)) {
        continue;
    }

    foreach ($checks as $check) {
        $status = $check['status'] ?? null;

        if ($status === 'SUCCESS') {
            $passed++;
            $total++;
        } elseif ($status === 'FAILURE') {
            $total++;
        }
    }
}

$percentage = $total === 0 ? 0 : (int) round($passed / $total * 100);

$color = match (true) {
    $percentage >= 90 => 'brightgreen',
    $percentage >= 70 => 'yellow',
    default => 'orange',
};

file_put_contents(__DIR__.'/'.$suite.'-conformance.json', json_encode([
    'schemaVersion' => 1,
    'label' => $suite.' conformance',
    'message' => sprintf('%d/%d (%d%%)', $passed, $total, $percentage),
    'color' => $color,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

printf("%s conformance: %d/%d (%d%%)\n", $suite, $passed, $total, $percentage);
