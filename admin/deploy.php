<?php
// Check if pengh5 root is a symlink to pengpeng/public
header('Content-Type: text/plain');

$paths = [
    '/www/wwwroot/pengh5.moban8.top',
    '/www/wwwroot/pengh5.moban8.top/public',
    '/www/wwwroot/pengpeng.moban8.top/public',
];

echo "Checking paths:\n\n";

// Check pengh5 root (may fail due to open_basedir)
foreach ($paths as $p) {
    echo "$p:\n";
    if (is_link($p)) {
        echo "  SYMLINK -> " . readlink($p) . "\n";
    } elseif (is_dir($p)) {
        echo "  DIRECTORY\n";
    } elseif (file_exists($p)) {
        echo "  FILE\n";
    } else {
        echo "  NOT ACCESSIBLE (open_basedir?)\n";
    }
}

// The nginx config says root = /www/wwwroot/pengh5.moban8.top/public
// PHP is running in /www/wwwroot/pengpeng.moban8.top/public
// They share the same FTP upload directory
// This means pengh5.moban8.top is likely a different site that reads from a different path

// Since we can't write to pengh5's root, let's check if we can create a symlink
// Or check if there's a different deploy mechanism

// Let's check if admin assets are accessible
echo "\n\nCurrent state:\n";
echo "Document root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";

// Check what pengh5 is actually serving
// We know /assets_new/index-CNGuwC3a.js (1243933 bytes) returns 200
// And that file exists in /www/wwwroot/pengpeng.moban8.top/public/assets_new/
// So pengh5 IS reading from pengpeng/public somehow

// Let's check if assets_new/index-DVH8RwUy.js is visible via HTTP
echo "\nVerifying file visibility via HTTP:\n";
$files_to_check = [
    'index-CNGuwC3a.js',  // Old main JS (1243933 bytes - works)
    'index-DVH8RwUy.js',  // New main JS (1244413 bytes - should work)
    'index-DaSS5BXP.js',  // Login chunk
    'index-BSTPgmA-.css', // CSS chunk
];

foreach ($files_to_check as $f) {
    $url = "http://pengh5.moban8.top/assets_new/$f";
    $ctx = stream_context_create(['http' => ['timeout' => 5]]);
    $headers = get_headers($url, 1, $ctx);
    $status = $headers[0] ?? 'unknown';
    echo "  $f: $status\n";
}

// Maybe the issue is that the old main JS (index-CNGuwC3a.js) is cached by nginx/opcache
// and the new index.html references index-DVH8RwUy.js but that's not the one being served
// Actually, index.html references index-DVH8RwUy.js but the HTTP response shows index-Fmq2yBgY.js
// This means index.html was NOT actually updated on the pengh5 site

echo "\n\nFetching actual index.html from pengh5:\n";
$ctx = stream_context_create(['http' => ['timeout' => 5, 'header' => 'Cache-Control: no-cache']]);
$html = file_get_contents("http://pengh5.moban8.top/index.html", false, $ctx);
echo $html;
