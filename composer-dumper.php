<?php

$ch = curl_init('https://api.github.com/repos/shopware/platform/tags');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'User-Agent: Composer Dumper'
]);
$tags = json_decode(curl_exec($ch), true);
curl_close($ch);

$workDir = tempnam(sys_get_temp_dir(), uniqid('composer', true));

$components = ['shopware/core', 'shopware/storefront', 'shopware/administration', 'shopware/elasticsearch'];

$composerFolder = __DIR__ . '/composer';

if (!file_exists($composerFolder)) {
    mkdir($composerFolder);
}

$availableVersions = [];

foreach ($tags as $tag) {
    $versionRaw = trim(ltrim($tag['name'], 'v'));
    $folderPath = __DIR__ . '/composer/' . $versionRaw . '/';
    $availableVersions[] = $versionRaw;

    if (file_exists($folderPath)) {
        continue;
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
                    'php' => version_compare('6.4.0.0', $versionRaw, '>=') ? '7.4.2' : '7.2.0'
                ]
            ]
        ];

        file_put_contents($workDir . '/composer.json', json_encode($composerJson));
        exec('composer install --no-dev --no-scripts --no-plugins --ignore-platform-reqs -d ' . escapeshellarg($workDir));

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

file_put_contents($composerFolder . '/versions.json', json_encode($availableVersions, JSON_PRETTY_PRINT));
