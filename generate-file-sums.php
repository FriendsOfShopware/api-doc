<?php

$forceGenerate = isset($_SERVER['FORCE_GENERATE']) && $_SERVER['FORCE_GENERATE'] === '1';

echo "Force generate mode: " . var_export($forceGenerate, true) . PHP_EOL;

function getMissingVersions(array $releases): array
{
    global $forceGenerate;

    $missing = [];

    foreach ($releases as $release) {
        $folder = dirname(__DIR__) . '/api-doc/version/' . ltrim($release['name'], 'v');

        $filename = $folder . '/Files.md5sums';
        if (!$forceGenerate && is_file($filename)) {
            continue;
        }

        if (!file_exists($folder)) {
            if (!mkdir($concurrentDirectory = $folder) && !is_dir($concurrentDirectory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
        }

        $missing[] = $release + ['output' => $folder];
    }

    return $missing;
}

$tags = fetch_tags();
$missingVersions = getMissingVersions($tags);

$ignoredFiles = [
    'Recovery/Common/vendor/autoload.php',
    'Recovery/Common/vendor/composer/ClassLoader.php',
    'Recovery/Common/vendor/composer/InstalledVersions.php',
    'Recovery/Common/vendor/composer/autoload_classmap.php',
    'Recovery/Common/vendor/composer/autoload_files.php',
    'Recovery/Common/vendor/composer/autoload_namespaces.php',
    'Recovery/Common/vendor/composer/autoload_psr4.php',
    'Recovery/Common/vendor/composer/autoload_real.php',
    'Recovery/Common/vendor/composer/autoload_static.php',
    'Recovery/Common/vendor/composer/installed.php',
    'Recovery/vendor/autoload.php',
    'Recovery/vendor/composer/ClassLoader.php',
    'Recovery/vendor/composer/InstalledVersions.php',
    'Recovery/vendor/composer/autoload_classmap.php',
    'Recovery/vendor/composer/autoload_namespaces.php',
    'Recovery/vendor/composer/autoload_psr4.php',
    'Recovery/vendor/composer/autoload_real.php',
    'Recovery/vendor/composer/autoload_static.php',
    'Recovery/vendor/composer/installed.php',
    'Recovery/vendor/composer/autoload_files.php',
];

$ignoredFilesFilter = [];

foreach ($ignoredFiles as $ignoredFile) {
    $ignoredFilesFilter[] = '-not -iwholename \'' . $ignoredFile . '\'';
}

/**
 * @param $output
 * @return void
 */
function fixPaths($output)
{
    $filePath = $output;
    $tmpFilePath = $filePath . '.tmp';

    $reading = fopen($filePath, 'r');
    $writing = fopen($tmpFilePath, 'w');

    while (!feof($reading)) {
        $line = fgets($reading);

        $newLine = preg_replace_callback('/shopware-platform-\w+\/src\/(\w+)/', function ($matches) {
            return 'vendor/shopware/' . strtolower($matches[1]);
        }, $line);

        fputs($writing, $newLine);
    }

    fclose($reading);
    fclose($writing);

    rename($tmpFilePath, $filePath);
}

foreach ($missingVersions as $release) {
    $installFolder = sys_get_temp_dir() . '/' . uniqid('sw', true);
    if (!mkdir($installFolder) && !is_dir($installFolder)) {
        throw new \RuntimeException(sprintf('Directory "%s" was not created', $installFolder));
    }

    chdir($installFolder);

    printf('> Unpacking Shopware with Version: %s in %s' . PHP_EOL, $release['name'], $installFolder);

    exec('wget -O install.zip -qq ' . $release['zipball_url']);
    exec('unzip -q install.zip');

    $outputFolder = $release['output'];

    exec("find shopware-platform-*/src -type f \( " . implode(' ', $ignoredFilesFilter) . " -iname '*.php' -o -iname '*.twig' -o -iname '*.js' -o -iname '*.scss' -o -iname '*.xml' \) -print0 | xargs -0 md5sum | sort -k 2 -d > " . $outputFolder . '/Files.md5sums');
    exec("find shopware-platform-*/src -type f \( " . implode(' ', $ignoredFilesFilter) . " -iname '*.php' -o -iname '*.twig' -o -iname '*.js' -o -iname '*.scss' -o -iname '*.xml' \) -print0 | xargs -0 xxhsum | sort -k 2 -d > " . $outputFolder . '/Files.xxhsums');

    exec('rm -rf ' . escapeshellarg($installFolder));

    fixPaths($outputFolder . '/Files.md5sums');
    fixPaths($outputFolder . '/Files.xxhsums');
}

function fetch_tags(int $page = 1) {
    $ch = curl_init('https://api.github.com/repos/shopware/platform/tags?per_page=100&page=' . $page);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: Composer Dumper'
    ]);
    $tags = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (count($tags) === 100) {
        $tags = array_merge($tags, fetch_tags($page + 1));
    }

    return $tags;
}
