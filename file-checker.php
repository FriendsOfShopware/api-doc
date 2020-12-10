<?php

$installedVersion = getUsedShopwareVersion();
echo '=> Detected Shopware Version: ' . $installedVersion . PHP_EOL;
$md5Sums = getMd5SumsForVersion($installedVersion);
$baseDir = __DIR__ . '/';
$foundSomething = false;

foreach ($md5Sums as $row) {
    list($expectedMd5Sum, $file) = explode('  ', trim($row));
    $fileAvailable = is_file($baseDir . $file);

    if ($fileAvailable) {
        $md5Sum = md5_file($baseDir . $file);

        // This file differs on update systems. This change is missing in update packages lol!
        // @see: https://github.com/shopware/platform/commit/957e605c96feef67a6c759f00c58e35d2d1ac84f#diff-e49288a50f0d7d8acdabb5ffef2edcd5ac4f4126f764d3153d19913ce98aba1cL10-R80
        // @see: https://issues.shopware.com/issues/NEXT-11618
        if ($file === 'vendor/shopware/core/Checkout/Order/Aggregate/OrderAddress/OrderAddressDefinition.php' && $md5Sum === 'e3da59baff091fd044a12a61cd445385') {
            continue;
        }

        // This file differs on update systems. This change is missing in update packages lol!
        // @see: https://github.com/shopware/platform/commit/bbdcbe254e3239e92eb1f71a7afedfb94b7fb150
        // @see: https://issues.shopware.com/issues/NEXT-11775
        if ($file === 'vendor/shopware/administration/Resources/app/administration/src/app/component/media/sw-media-compact-upload-v2/index.js' && $md5Sum === '74d18e580ffe87559e6501627090efb3') {
            continue;
        }

        if ($md5Sum !== $expectedMd5Sum) {
            echo(sprintf('File "%s" has been modified. Please revert it back to default!' . PHP_EOL, $file));
            $foundSomething = true;
        }
    }
}

if (!$foundSomething) {
    echo 'Everything is okay!' . PHP_EOL;
}

function getUsedShopwareVersion(): string
{
    $lock = json_decode(file_get_contents(__DIR__ . '/composer.lock'), true);
    foreach ($lock['packages'] as $package) {
        if ($package['name'] === 'shopware/core') {
            return $package['version'];
        }
    }

    echo('Cannot find installed Shopware Version in Composer' . PHP_EOL);
    exit(1);
}

function getMd5SumsForVersion(string $version): array
{
    $url = sprintf('https://swagger.docs.fos.gg/version/%s/Files.md5sums', $version);
    echo '=> Downloading meta information from ' . $url . PHP_EOL;

    $data = trim(@file_get_contents($url));

    if (empty($data)) {
        echo(sprintf('Cannot download md5sums from %s. Maybe not generated? Contact Shyim' . PHP_EOL, $url));
        exit(1);
    }

    return explode("\n", $data);
}

