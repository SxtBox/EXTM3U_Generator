<?php
/*************************************************
 * EXTM3U WEB STANDALONE
 * Formats: mp3, ogg, flac, wav, mp4
 *************************************************/

require_once __DIR__ . '/getid3/getid3.php';

$getID3 = new getID3();

/* ===================== AUDIO INFO ===================== */
function getAudioInfo(string $file): array
{
    global $getID3;

    $info = $getID3->analyze($file);

    $seconds = isset($info['playtime_seconds'])
        ? (int) $info['playtime_seconds']
        : 0;

    $artist = '';
    $title  = '';

    if (!empty($info['tags'])) {
        foreach ($info['tags'] as $tagset) {
            if (isset($tagset['artist'][0])) {
                $artist = trim($tagset['artist'][0]);
            }
            if (isset($tagset['title'][0])) {
                $title = trim($tagset['title'][0]);
            }
        }
    }

    return [$seconds, $artist, $title];
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

/* ===================== DIRECTORY SCAN ===================== */
function scanDirRecursive(string $dir, array &$list): void
{
    $items = scandir($dir);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir . '/' . $item;

        if (is_dir($path)) {
            scanDirRecursive($path, $list);
        } else {
            if (preg_match('/\.(mp3|ogg|flac|wav|mp4)$/i', $path)) {
                $list[] = $path;
            }
        }
    }
}

/* ===================== SHUFFLE ===================== */
function random_Shuffle(array &$array): void
{
    for ($i = count($array) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$array[$i], $array[$j]] = [$array[$j], $array[$i]];
    }
}

/* ===================== FILE TO FULL URL ===================== */
function file_to_url(string $file): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        ? 'https'
        : 'http';

    $host = $_SERVER['HTTP_HOST'];

    // Folder ku ndodhet script-i (p.sh /3/DEV/m3u)
    $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

    // Path relativ ndaj atij folderi
    $relative = ltrim($file, '/');
    if (str_starts_with($relative, ltrim($basePath, '/'))) {
        $relative = substr($relative, strlen(ltrim($basePath, '/')));
    }

    $relative = '/' . ltrim($relative, '/');

    // Encode çdo segment (jo slash-et)
    $encoded = implode(
        '/',
        array_map('rawurlencode', explode('/', $relative))
    );

    return $scheme . '://' . $host . $basePath . $encoded;
}

/* ===================== OUTPUT M3U ===================== */
function EXTM3U_Builder(array $files, bool $random): void
{
    if ($random) {
        random_Shuffle($files);
    }

    header('Content-Type: audio/x-mpegurl; charset=UTF-8');
    header('Content-Disposition: inline; filename="Playlist.m3u"');

    echo "#EXTM3U\n";

    foreach ($files as $file) {

        [$sec, $artist, $title] = getAudioInfo($file);

        $label = build_extinf_titles($file, $artist, $title);
        $url   = file_to_url($file);

        //echo "#EXTINF:$sec,$label\n";
		echo "#EXTINF:0,$label\n";
        echo $url . "\n";
    }
}

/* ===================== MAIN ===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $root   = rtrim($_POST['path'] ?? 'MP3', '/');
    $random = isset($_POST['random']);

    if (!is_dir($root)) {
        http_response_code(400);
        die('Invalid Directory');
    }

    $files = [];
    scanDirRecursive($root, $files);

    EXTM3U_Builder($files, $random);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>EXTM3U Generator</title>
<link rel="shortcut icon" href="https://kodi.al/favicon.ico"/>
<style>
body {
    background: #0b0b0b;
    color: #eee;
    font-family: Arial, sans-serif;
}
.box {
    max-width: 600px;
    margin: 40px auto;
    background: #151515;
    padding: 25px;
    border-radius: 10px;
}
input[type=text] {
    width: 100%;
    padding: 10px;
    background: #000;
    color: #fff;
    border: 1px solid #333;
}
button {
    margin-top: 15px;
    background: #00ff88;
    color: #000;
    padding: 10px 20px;
    border: none;
    font-weight: bold;
    cursor: pointer;
}
label {
    display: block;
    margin-top: 10px;
}
</style>
</head>
<body>

<div class="box">
    <h2>🎵 EXTM3U Web Generator</h2>

    <form method="post">
        <label>Music Directory (Relative or Absolute)</label>
        <input type="text" name="path" value="MP3" required>

        <label>
            <input type="checkbox" name="random">
            Randomize Playlist
        </label>

        <button type="submit">Generate M3U</button>
    </form>
</div>

</body>
</html>
