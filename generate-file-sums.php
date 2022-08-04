<?php

$forceGenerate = isset($_SERVER['FORCE_GENERATE']) && $_SERVER['FORCE_GENERATE'] === '1';

echo "Force generate mode: " . var_export($forceGenerate);

function getMissingVersions(array $releases): array
{
    global $forceGenerate;

    $missing = [];

    foreach ($releases as $release) {
        $folder = dirname(__DIR__) . '/api-doc/version/' . $release['version'];

        $filename = $folder . '/Files.md5sums';
        if (!$forceGenerate && is_file($filename)) {
            continue;
        }

        if (!file_exists($folder)) {
            if (!mkdir($concurrentDirectory = $folder) && !is_dir($concurrentDirectory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
        }

        $missing[] = $release + ['output' => $filename];
    }

    return $missing;
}

$releases = array_reverse(json_decode(file_get_contents('https://update-api.shopware.com/v1/releases/install?major=6'), true));
$missingVersions = getMissingVersions($releases);

$ignoredFiles = [
    'vendor/shopware/recovery/Common/vendor/autoload.php',
    'vendor/shopware/recovery/Common/vendor/composer/ClassLoader.php',
    'vendor/shopware/recovery/Common/vendor/composer/InstalledVersions.php',
    'vendor/shopware/recovery/Common/vendor/composer/autoload_classmap.php',
    'vendor/shopware/recovery/Common/vendor/composer/autoload_files.php',
    'vendor/shopware/recovery/Common/vendor/composer/autoload_namespaces.php',
    'vendor/shopware/recovery/Common/vendor/composer/autoload_psr4.php',
    'vendor/shopware/recovery/Common/vendor/composer/autoload_real.php',
    'vendor/shopware/recovery/Common/vendor/composer/autoload_static.php',
    'vendor/shopware/recovery/Common/vendor/composer/installed.php',
    'vendor/shopware/recovery/vendor/autoload.php',
    'vendor/shopware/recovery/vendor/composer/ClassLoader.php',
    'vendor/shopware/recovery/vendor/composer/InstalledVersions.php',
    'vendor/shopware/recovery/vendor/composer/autoload_classmap.php',
    'vendor/shopware/recovery/vendor/composer/autoload_namespaces.php',
    'vendor/shopware/recovery/vendor/composer/autoload_psr4.php',
    'vendor/shopware/recovery/vendor/composer/autoload_real.php',
    'vendor/shopware/recovery/vendor/composer/autoload_static.php',
    'vendor/shopware/recovery/vendor/composer/installed.php',
];

$ignoredFilesFilter = [];

foreach ($ignoredFiles as $ignoredFile) {
    $ignoredFilesFilter[] = '-not -iwholename \'' . $ignoredFile . '\'';
}

foreach ($missingVersions as $release) {
    $installFolder = sys_get_temp_dir() . '/' . uniqid('sw', true);
    if (!mkdir($installFolder) && !is_dir($installFolder)) {
        throw new \RuntimeException(sprintf('Directory "%s" was not created', $installFolder));
    }

    chdir($installFolder);

    printf('> Unpacking Shopware with Version: %s in %s' . PHP_EOL, $release['version'], $installFolder);

    exec('wget -O install.zip -qq ' . $release['uri']);
    exec('unzip -q install.zip');

    exec("find vendor/shopware -type f \( " . implode(' ', $ignoredFilesFilter) . " -iname '*.php' -o -iname '*.twig' -o -iname '*.js' -o -iname '*.scss' -o -iname '*.xml' \) -print0 | xargs -0 md5sum | sort -k 2 -d > " . $release['output']);

    exec('rm -rf ' . escapeshellarg($installFolder));
}
