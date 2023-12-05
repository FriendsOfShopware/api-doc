<?php

include 'functions.php';

$forceGenerate = isset($_SERVER['FORCE_GENERATE']) && $_SERVER['FORCE_GENERATE'] === '1';

$tags = fetch_tags();

$workDir = tempnam(sys_get_temp_dir(), uniqid('composer', true));

$components = ['shopware/core', 'shopware/storefront', 'shopware/administration', 'shopware/elasticsearch'];

$composerFolder = __DIR__ . '/composer';

if (!file_exists($composerFolder)) {
    mkdir($composerFolder);
}

$availableVersions = [];

foreach ($tags as $tag) {
    if (!isset($tag['name'])) {
        throw new \RuntimeException('Invalid tag data');
    }

    $versionRaw = trim(ltrim($tag['name'], 'v'));
    $folderPath = __DIR__ . '/composer/' . $versionRaw . '/';
    $availableVersions[] = $versionRaw;

    if (!$forceGenerate && file_exists($folderPath)) {
        continue;
    }

    $phpVersion = "7.2.0";

    if (version_compare('6.5.0.0', $versionRaw, '>=')) {
        $phpVersion = "8.1.0";
    } else if (version_compare('6.4.0.0', $versionRaw, '>=')) {
        $phpVersion = "7.4.0";
    }

    $stabilityFlags = [];

    if (stripos($versionRaw, 'rc') !== false) {
        $stabilityFlags = [
            'minimum-stability' => 'RC',
            'prefer-stable' => true
        ];
    }

    foreach ($components as $component) {
        exec('rm -rf ' . escapeshellarg($workDir));
        mkdir($workDir);

        $composerJson = [
            'require' => [
                $component => $tag['name']
            ],
            'config' => [
                'platform' => [
                    'php' => $phpVersion,
                ]
            ]
        ] + $stabilityFlags;

        file_put_contents($workDir . '/composer.json', json_encode($composerJson));
        exec('composer install --no-dev --no-scripts --no-plugins --ignore-platform-reqs -d ' . escapeshellarg($workDir));

        if (!file_exists($workDir . '/composer.lock')) {
            throw new RuntimeException('No composer.lock found');
        }

        $locks = [];

        $composerLock = json_decode(file_get_contents($workDir . '/composer.lock'), true);

        foreach ($composerLock['packages'] as $package) {
            $locks[$package['name']] = $package['version'];
        }


        if (!file_exists($folderPath)) {
            mkdir($folderPath);
        }

        file_put_contents($folderPath . str_replace('shopware/', '', $component) . '.json', json_encode($locks, JSON_PRETTY_PRINT));
    }
}

// @todo remove when 6.6 is out
$availableVersions[] = '6.6.0';

file_put_contents($composerFolder . '/versions.json', json_encode($availableVersions, JSON_PRETTY_PRINT));
