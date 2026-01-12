#!/usr/bin/env php
<?php
/*
 Shembuj përdorimi
▶️ Playlist normale
php EXTM3U_CLI.php MP3

🔀 Playlist random
php EXTM3U_CLI.php -r MP3

📥 Lexim nga STDIN
find /media/music -type f | php EXTM3U_CLI.php -

💾 Ruajtje në file
php EXTM3U_CLI.php -r MP3 > Playlist.m3u
*/
require_once __DIR__ . '/getid3/getid3.php';

$getID3 = new getID3();

/**
 * Print help
 */
function help(): void
{
fwrite(STDERR, <<<TXT

Usage: php EXTM3U_CLI.php -r <media dir>

-r            Randomize playlist order (uses memory)

<media-dir>   Search directory recursively for audio files
-             Read file paths from STDIN

Generates an Extended .m3u Playlist (#EXTM3U) Printed to STDOUT.
Supported formats: mp3, ogg, flac, wav

Examples:
MP3 is Media FOLDER/DIR/

Normal Playlist:
php EXTM3U_CLI.php MP3

Random Playlist
php EXTM3U_CLI.php -r MP3

Save as M3U
php EXTM3U_CLI.php -r MP3 > Playlist.m3u

TXT
);
}

/* ===================== TITLES BUILDER ===================== */

function build_extinf_titles(string $file, string $artist, string $title): string
{
    if ($artist !== '' && $title !== '') {
        return $artist . ' - ' . $title;
    }

    if ($title !== '') {
        return $title;
    }

    if ($artist !== '') {
        return $artist;
    }

    return pathinfo($file, PATHINFO_FILENAME);
}

/**
 * Print EXTINF + file path
 */
function printFile(string $file, int $sec = 0, string $artist = '', string $title = ''): void
{
    //$base = pathinfo($file, PATHINFO_FILENAME);
    $extinf_title = build_extinf_titles($file, $artist, $title);


    if ($artist !== '' || $title !== '') {
      //  echo "#EXTINF:$sec,$artist - $title\n";
		//echo "#EXTINF:0,$artist - $title\n";
		echo "#EXTINF:0,$extinf_title\n";
    } else {
        //echo "#EXTINF:$sec,$base\n";
		echo "#EXTINF:0,$extinf_title\n";
    }

    echo $file . "\n";
}

/**
 * Read metadata using getID3
 */
function getAudioInfo(string $file): array
{
    global $getID3;

    $info = $getID3->analyze($file);

    $sec = isset($info['playtime_seconds'])
        ? (int) $info['playtime_seconds']
        : 0;

    $artist = '';
    $title  = '';

    if (!empty($info['tags'])) {
        foreach ($info['tags'] as $tagset) {
            if (isset($tagset['artist'][0])) {
                $artist = $tagset['artist'][0];
            }
            if (isset($tagset['title'][0])) {
                $title = $tagset['title'][0];
            }
        }
    }

    return [$sec, $artist, $title];
}

/**
 * Recursive directory reader
 */
function readFiles(string $path, bool $random): array
{
    $allFiles = [];

    if ($path === '-') {
        while (($line = fgets(STDIN)) !== false) {
            $file = trim($line);
            if ($file !== '') {
                $allFiles[] = [$file, getAudioInfo($file)];
            }
        }
        return $allFiles;
    }

    $items = scandir($path);
    if ($items === false) {
        return [];
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $fullPath = $path . '/' . $item;

        if (is_dir($fullPath)) {
            $allFiles = array_merge($allFiles, readFiles($fullPath, $random));
        } else {
            if (preg_match('/\.(mp3|ogg|flac|wav|mp4)$/i', $fullPath)) {
                $info = getAudioInfo($fullPath);

                if ($random) {
                    $allFiles[] = [$fullPath, $info];
                } else {
                    printFile($fullPath, $info[0], $info[1], $info[2]);
                }
            }
        }
    }

    return $allFiles;
}

/**
 * Random Shuffle
 */
function random_Shuffle(array &$array): void
{
    for ($i = count($array) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$array[$i], $array[$j]] = [$array[$j], $array[$i]];
    }
}

/* ===================== MAIN ===================== */

$args = $argv;
array_shift($args);

$random = false;
if (isset($args[0]) && $args[0] === '-r') {
    $random = true;
    array_shift($args);
}

if (count($args) === 0) {
    help();
    exit(1);
}

echo "#EXTM3U\n";

$all = [];

foreach ($args as $root) {
    $root = rtrim($root, '/');
    $all = array_merge($all, readFiles($root, $random));
}

if ($random) {
    random_Shuffle($all);

    foreach ($all as [$file, $info]) {
        printFile($file, $info[0], $info[1], $info[2]);
    }
}
