<?php

include 'functions.php';

$shopwareDir = $_SERVER['GITHUB_WORKSPACE'] . '/shopware/';
$apiDir = $_SERVER['GITHUB_WORKSPACE'] . '/api-doc/';

chdir($shopwareDir);

$forceGenerate = isset($_SERVER['FORCE_GENERATE']) && $_SERVER['FORCE_GENERATE'] === '1';

echo "Force generate mode: " . var_export($forceGenerate, true);

function updateShopware($updateInfo)
{
    $version = $updateInfo['name'];

    exec('composer require -W --no-audit --no-scripts --no-interaction shopware/core:' . $version .  ' shopware/administration:' . $version . ' shopware/storefront:' . $version . ' shopware/elasticsearch:' . $version);

}

function dumpApiInfo(string $currentVersion)
{
    $outputDir = dirname(__DIR__) . '/api-doc/version/' . $currentVersion . '/';
    if (!file_exists($outputDir)) {
        mkdir($outputDir, 0777, true);
    }

    exec('php bin/console framework:schema -s entity-schema ' . $outputDir . '/entity-schema.json');
    exec('php bin/console framework:schema -s openapi3 ' . $outputDir . '/api.json');
    exec('php bin/console framework:schema -s openapi3 --store-api ' . $outputDir . '/store-api.json');
   
    printf('> Dumped files for version %s' . PHP_EOL, $currentVersion);
}

function getMissingVersions(array $releases): array
{
    global $forceGenerate;
    $missing = [];

    foreach ($releases as $release) {
        if (version_compare(ltrim($release['name'], 'v'), '6.4.18.0', '<')) {
            continue;
        }

        if (is_file(dirname(__DIR__) . '/api-doc/version/' . ltrim($release['name'], 'v') . '/api.json') && !$forceGenerate) {
            continue;
        }

        $missing[] = $release;
    }

    return $missing;
}

function updateSwaggerIndex()
{
    $folders = scandir(__DIR__ . '/version/', SCANDIR_SORT_ASCENDING);
    $listing = [];

    foreach ($folders as $version) {
        if ($version[0] === '.') {
            continue;
        }

        $folderPath = __DIR__ . '/version/' . $version;

        if (\file_exists($folderPath . '/api.json')) {
            $listing[] = [
                'url' => '/version/' . $version . '/api.json',
                'name' => 'Management API (' . $version . ')',
                'version' => $version
            ];
        }

        if (\file_exists($folderPath . '/sales-channel-api.json')) {
            $listing[] = [
                'url' => '/version/' . $version . '/sales-channel-api.json',
                'name' => 'Sales Channel API (' . $version . ')',
                'version' => $version
            ];
        }

        if (\file_exists($folderPath . '/store-api.json')) {
            $listing[] = [
                'url' => '/version/' . $version . '/store-api.json',
                'name' => 'Store API (' . $version . ')',
                'version' => $version
            ];
        }
    }

    usort($listing, static function (array $a, array $b) {
        return strnatcmp($a['version'], $b['version']);
    });

    file_put_contents(__DIR__ . '/data.json', json_encode($listing, JSON_PRETTY_PRINT));
}

$releases = array_reverse(fetch_tags());
$missingVersions = getMissingVersions($releases);

if ($missingVersions === []) {
    echo '> API-Doc is up to date' . PHP_EOL;
    exit(0);
}

foreach ($missingVersions as $i => $missingVersion) {
    updateShopware($missingVersion);

    dumpApiInfo($missingVersion['name']);
}

updateSwaggerIndex();
