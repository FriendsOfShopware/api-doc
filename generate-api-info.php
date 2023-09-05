<?php

include 'functions.php';

$shopwareDir = $_SERVER['GITHUB_WORKSPACE'] . '/shopware/';
$apiDir = $_SERVER['GITHUB_WORKSPACE'] . '/api-doc/';

$forceGenerate = isset($_SERVER['FORCE_GENERATE']) && $_SERVER['FORCE_GENERATE'] === '1';

echo "Force generate mode: " . var_export($forceGenerate, true);

function updateShopware($updateInfo)
{
    $name = $updateInfo['name'];

    exec('composer require -W --no-audit --no-scripts --no-interaction shopware/core:' . $name .  ' shopware/administration:' . $name . ' shopware/storefront:' . $name . ' shopware/elasticsearch:' . $name);

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
        $version = ltrim($release['name'], 'v');

        if (version_compare($version, '6.4.18.0', '<')) {
            continue;
        }

        if (str_contains(strtolower($version), '-rc')) {
            continue;
        }

        if (is_file(dirname(__DIR__) . '/api-doc/version/' . $version . '/api.json') && !$forceGenerate) {
            continue;
        }

        $release['version'] = $version;

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
                'name' => $version . ' | Management API',
                'version' => $version
            ];
        }

        if (\file_exists($folderPath . '/sales-channel-api.json')) {
            $listing[] = [
                'url' => '/version/' . $version . '/sales-channel-api.json',
                'name' => $version . ' | Sales Channel API',
                'version' => $version
            ];
        }

        if (\file_exists($folderPath . '/store-api.json')) {
            $listing[] = [
                'url' => '/version/' . $version . '/store-api.json',
                'name' => $version . ' | Store API',
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

    dumpApiInfo($missingVersion['version']);
}

updateSwaggerIndex();
