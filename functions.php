<?php

function fetch_tags(int $page = 1) {
    $ch = curl_init('https://api.github.com/repos/shopware/shopware/tags?per_page=100&page=' . $page);
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