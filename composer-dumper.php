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

foreach ($tags as $tag) {
    foreach ($components as $component) {
        exec('rm -rf ' . escapeshellarg($workDir));
        mkdir($workDir);

        $versionRaw = trim(ltrim($tag['name'], 'v'));

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

        $folderPath = __DIR__ . '/composer/' . $versionRaw . '/';

        if (!file_exists($folderPath)) {
            mkdir($folderPath);
        }

        file_put_contents($folderPath . str_replace('shopware/', '', $component) . '.json', json_encode($locks, JSON_PRETTY_PRINT));
    }
}
