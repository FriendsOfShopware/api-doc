<?php

$forceGenerate = isset($_SERVER['FORCE_GENERATE']) ? $_SERVER['FORCE_GENERATE'] === '1' : false;

echo "Force generate mode: " . var_export($forceGenerate);

function getMissingVersions(array $releases): array
{
    global $forceGenerate;

    $missing = [];

    foreach ($releases as $release) {
        $filename = dirname(__DIR__) . '/api-doc/version/' . $release['version'] . '/Files.md5sums';
        if (is_file($filename) && !$forceGenerate) {
            continue;
        }

        $folder = dirname(__DIR__) . '/api-doc/version/' . $release['version'];

        if (!file_exists(dirname(__DIR__) . '/api-doc/version/' . $release['version'])) {
            if (!mkdir($concurrentDirectory = dirname(__DIR__) . '/api-doc/version/' . $release['version']) && !is_dir($concurrentDirectory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
        }

        $missing[] = $release + ['output' => $filename];
    }

    return $missing;
}

$releases = array_reverse(json_decode(file_get_contents('https://update-api.shopware.com/v1/releases/install?major=6'), true));
$missingVersions = getMissingVersions($releases);

foreach ($missingVersions as $release) {
    $installFolder = sys_get_temp_dir() . '/' . uniqid('sw', true);
    if (!mkdir($installFolder) && !is_dir($installFolder)) {
        throw new \RuntimeException(sprintf('Directory "%s" was not created', $installFolder));
    }

    chdir($installFolder);

    printf('> Unpacking Shopware with Version: %s in %s' . PHP_EOL, $release['version'], $installFolder);

    exec('wget -O install.zip -qq ' . $release['uri']);
    exec('unzip -q install.zip');

    exec("find vendor/shopware -type f \( -iname '*.php' -o -iname '*.twig' -o -iname '*.js' -o -iname '*.scss' -o -iname '*.xml' \) -print0 | xargs -0 md5sum | sort -k 2 -d > " . $release['output']);

    exec('rm -rf ' . escapeshellarg($installFolder));
}
