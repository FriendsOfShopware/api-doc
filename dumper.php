<?php

$url = $_SERVER['argv'][1];
$listing = [];

function fetch($path) {
    global $url;

    $ch = curl_init($url . $path);
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
            'url' => 'http://localhost'
        ]
    ];

    return $api;
}

function update(string $currentVersion)
{
    global $listing;

    $updateInfo = json_decode(file_get_contents('https://update-api.shopware.com/v1/release/update?shopware_version=' . $currentVersion . '&channel=stable&major=6'), true);

    if ($updateInfo['version'] === $currentVersion) {
        file_put_contents(dirname(__DIR__) . '/api-doc/data.json', json_encode($listing, JSON_PRETTY_PRINT));
        return;
    }

    $zipName = basename($updateInfo['uri']);

    echo '=> Downloading ' . $updateInfo['version'] . PHP_EOL;
    exec('wget ' . $updateInfo['uri']);

    echo '=> Unzip ' . $updateInfo['version'] . PHP_EOL;
    exec('unzip -o ' . $zipName);
    exec('rm -rf vendor/shopware/recovery/vendor/phpunit/php-code-coverage/tests/_files/Crash.php');

    echo '=> Updating ' . $updateInfo['version'] . PHP_EOL;
    exec('php public/recovery/update/index.php -n');
    exec('rm -rf update-assets');

    dump($updateInfo['version']);
}

function dump(string $currentVersion) {
    global $listing;
    $apiVersion = substr($currentVersion, 2, 1);

    $api = fetch('/api/v' . $apiVersion . '/_info/openapi3.json');
    $scApi = fetch('/sales-channel-api/v' . $apiVersion . '/_info/openapi3.json');
    $stApi = fetch('/store-api/v' . $apiVersion . '/_info/openapi3.json');

    $outputDir = dirname(__DIR__) . '/api-doc/version/' . $currentVersion . '/';
    if (!file_exists($outputDir)) {
        mkdir($outputDir, 0777, true);
    }

    if ($api) {
        file_put_contents($outputDir . '/api.json', json_encode($api));
        $listing[] = [
            'url' => '/version/' . $currentVersion . '/api.json',
            'name' => 'Management API (' . $currentVersion . ')'
        ];
    }

    if ($scApi) {
        file_put_contents($outputDir . '/sales-channel-api.json', json_encode($scApi));
        $listing[] = [
            'url' => '/version/' . $currentVersion . '/sales-channel-api.json',
            'name' => 'Sales Channel API (' . $currentVersion . ')'
        ];
    }

    if ($stApi) {
        file_put_contents($outputDir . '/store-api.json', json_encode($stApi));
        $listing[] = [
            'url' => '/version/' . $currentVersion . '/store-api.json',
            'name' => 'Store API (' . $currentVersion . ')'
        ];
    }

    update($currentVersion);
}

dump('6.1.0');
