<?php

include 'functions.php';

$forceGenerate = isset($_SERVER['FORCE_GENERATE']) && $_SERVER['FORCE_GENERATE'] === '1';

echo "Force generate mode: " . var_export($forceGenerate, true);

function fetch($path)
{
    $ch = curl_init('http://localhost:8000' . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $json = curl_exec($ch);

    if ($json === false) {
        var_dump(curl_getinfo($ch));
    }

    curl_close($ch);

    if (empty($json)) {
        return null;
    }

    $api = json_decode($json, true);

    if (isset($api['errors'])) {
        var_dump($api);
        return null;
    }

    if (!isset($api['openapi'])) {
        return null;
    }

    $api['servers'] = [
        [
            'url' => 'http://localhost',
        ],
    ];

    return $api;
}

function updateShopware($updateInfo): void
{
    echo '=> Downloading ' . $updateInfo['name'] . PHP_EOL;
    exec('wget -O install.zip -qq ' . $updateInfo['zipball_url']);

    echo '=> Unzip ' . $updateInfo['name'] . PHP_EOL;
    exec('unzip -qq -o  install.zip');
    exec('rm -rf vendor/shopware/recovery/vendor/phpunit/php-code-coverage/tests/_files/Crash.php');

    echo '=> Updating ' . $updateInfo['name'] . PHP_EOL;
    exec('php public/recovery/update/index.php -n');
    exec('rm -rf update-assets');
}

function dumpApiInfo(string $currentVersion): void
{
    $currentVersion = ltrim($currentVersion, 'v');

    $apiVersion = $currentVersion[2];
    $apiPath = '/v' . $apiVersion;

    if ($apiVersion >= 4) {
        $apiPath = '';
    }

    $api = fetch('/api' . $apiPath . '/_info/openapi3.json');
    $scApi = fetch('/sales-channel-api' . $apiPath . '/_info/openapi3.json');
    $stApi = fetch('/store-api' . $apiPath . '/_info/openapi3.json');
    $entitySchema = fetch('/api' . $apiPath . '/_info/entity-schema.json');

    $outputDir = dirname(__DIR__) . '/api-doc/version/' . $currentVersion . '/';

    if (!file_exists($outputDir)) {
        mkdir($outputDir, 0777, true);
    }

    if ($api) {
        file_put_contents($outputDir . '/api.json', json_encode($api, JSON_UNESCAPED_SLASHES));
    }

    if ($scApi) {
        file_put_contents($outputDir . '/sales-channel-api.json', json_encode($scApi, JSON_UNESCAPED_SLASHES));
    }

    if ($stApi) {
        file_put_contents($outputDir . '/store-api.json', json_encode($stApi, JSON_UNESCAPED_SLASHES));
    }

    if ($entitySchema) {
        file_put_contents($outputDir . '/entity-schema.json', json_encode($entitySchema, JSON_UNESCAPED_SLASHES));
    }

    printf('> Dumped files for version %s' . PHP_EOL, $currentVersion);
}

function getMissingApiDocVersions(array $releases): array
{
    global $forceGenerate;
    $missing = [];

    foreach ($releases as $release) {
        if (is_file(dirname(__DIR__) . '/api-doc/version/' . ltrim($release['name'], 'v') . '/api.json') && !$forceGenerate) {
            continue;
        }

        $missing[] = $release;
    }

    return $missing;
}

function setUpShopware(array $release): void
{
    $installFolder = sys_get_temp_dir() . '/' . uniqid('sw', true);
    if (!mkdir($installFolder) && !is_dir($installFolder)) {
        throw new \RuntimeException(sprintf('Directory "%s" was not created', $installFolder));
    }

    chdir($installFolder);

    printf('> Installing Shopware with Version: %s in %s' . PHP_EOL, $release['name'], $installFolder);

    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', '/w'],
    ];

    exec('wget -O install.zip -qq ' . $release['zipball_url']);
    exec('unzip -q install.zip');
    exec('mv shopware-platform-*/* .');
    exec('composer install -q');

    $webServer = proc_open('php -S localhost:8000 -t public public/index.php', $descriptorSpec, $pipes);
    register_shutdown_function(static function () use($webServer, $installFolder) {
        proc_terminate($webServer, 9);
        proc_close($webServer);
        exec('rm -rf ' . escapeshellarg($installFolder));
    });

    //TODO: fix installation
    exec('php public/recovery/install/index.php --shop-host localhost --db-host 127.0.0.1  --db-user root --db-password shopware --db-name shopware --shop-locale en-GB --shop-currency EUR --admin-username demo  --admin-password demo --admin-email demo@foo.com --admin-locale en-GB --admin-firstname demo --admin-lastname demo -n');
    exec('echo \'APP_ENV=dev\' >> .env');
}

function updateSwaggerIndex(): void
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

$tags = array_reverse(fetch_tags());
$missingVersions = getMissingApiDocVersions($tags);

if ($missingVersions === []) {
    echo '> API-Doc is up to date' . PHP_EOL;
    exit(0);
}

foreach ($missingVersions as $i => $missingVersion) {
    if ($i === 0) {
        setUpShopware($missingVersion);
    } else {
        updateShopware($missingVersion);
    }

    dumpApiInfo($missingVersion['name']);
}

updateSwaggerIndex();
