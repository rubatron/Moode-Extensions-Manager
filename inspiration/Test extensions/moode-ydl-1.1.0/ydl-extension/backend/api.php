<?php
/**
 * api.php — moode-ydl v1.1.0
 *
 * Faithful port of Stephanowicz youtubeDL addon + coverart injection support.
 *
 * Original endpoints (youtube.php API preserved 1:1):
 *   GET  ?yturl=<url>&plopts=<add|clear|append|insert>&index=N&length=N
 *   GET  ?ytpl=<playlist_url>
 *   GET  ?loadnplay
 *   GET  ?playlist
 *   GET  ?ytpllist
 *   PUT  ?saveytpl=<url,title>
 *   PUT  ?delytpl=<url,title>
 *   GET  ?thumb=<url>
 *
 * New endpoints (coverart injection):
 *   GET  ?action=nowplaying    → { is_youtube, video_id, title, state }
 *   GET  ?action=status        → yt-dlp version + mpc status
 */

header('Content-Type: text/plain; charset=utf-8');

$ROOT        = dirname(__DIR__);
$YTDLP       = "$ROOT/packages/bin/yt-dlp";
$PYLIB       = "$ROOT/packages/pylib";
$MPD_PLROOT  = '/var/lib/mpd/playlists';
$YOUTUBE_M3U = "$MPD_PLROOT/youtube.m3u";
$YTPLLIST    = "$MPD_PLROOT/ytpllist.txt";
$PLMAP       = "$ROOT/data/playlist-map.json";
$NOWPLAYING  = "$ROOT/data/now-playing.json";

// ── Helpers ───────────────────────────────────────────────────────

function ytdlp(string $args): string {
    global $YTDLP, $PYLIB;
    if (is_executable($YTDLP)) {
        $env = "PYTHONPATH=" . escapeshellarg($PYLIB);
        $cmd = "$env bash " . escapeshellarg($YTDLP) . " $args 2>/dev/null";
    } elseif (is_executable('/usr/local/bin/yt-dlp')) {
        $cmd = "/usr/local/bin/yt-dlp $args 2>/dev/null";
    } elseif (shell_exec('which yt-dlp 2>/dev/null')) {
        $cmd = "yt-dlp $args 2>/dev/null";
    } else {
        return '';
    }
    return shell_exec($cmd) ?? '';
}

function read_json(string $path, $default = []) {
    if (!file_exists($path)) return $default;
    $d = json_decode(file_get_contents($path), true);
    return is_array($d) ? $d : $default;
}

function write_json(string $path, $data): void {
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function extract_video_id(string $url): string {
    if (preg_match('/(?:youtube\.com.*[?&]v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $m)) {
        return $m[1];
    }
    return '';
}

function extract_stream_host(string $stream_url): string {
    $parts = parse_url($stream_url);
    return $parts['host'] ?? '';
}

// ── Route ─────────────────────────────────────────────────────────

// New action-based endpoints
if (isset($_GET['action'])) {
    switch ($_GET['action']) {

        case 'nowplaying':
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(getNowPlaying());
            break;

        case 'status':
            header('Content-Type: application/json; charset=utf-8');
            $ver = trim(ytdlp('--version'));
            $mpc = trim(shell_exec('mpc status 2>/dev/null') ?? '');
            echo json_encode(['ok' => true, 'ytdlp_version' => $ver ?: 'not found', 'mpc' => $mpc]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    }
    exit;
}

// Original Stephanowicz youtube.php endpoints (preserved 1:1)
if (isset($_REQUEST['yturl'])) {
    createPLentry(
        $_REQUEST['yturl'],
        $_REQUEST['plopts']  ?? 'add',
        $_REQUEST['index']   ?? 1,
        $_REQUEST['length']  ?? 1
    );
} elseif (isset($_REQUEST['ytpl'])) {
    getYTPlaylist($_REQUEST['ytpl']);
} elseif (isset($_REQUEST['loadnplay'])) {
    shell_exec('mpc clear');
    sleep(1);
    shell_exec('mpc load youtube');
    sleep(1);
    shell_exec('mpc play');
    echo 'playlist loaded ... playback started';
} elseif (isset($_REQUEST['playlist'])) {
    showPlaylist();
} elseif (isset($_REQUEST['thumb'])) {
    getThumb($_REQUEST['thumb']);
} elseif (isset($_REQUEST['ytpllist'])) {
    ytpllist();
} elseif (isset($_REQUEST['saveytpl'])) {
    saveytpl($_REQUEST['saveytpl']);
} elseif (isset($_REQUEST['delytpl'])) {
    delytpl($_REQUEST['delytpl']);
} else {
    echo 'moode-ydl api v1.1.0 ready';
}

// ── Now playing detection ─────────────────────────────────────────

function getNowPlaying(): array {
    global $PLMAP, $NOWPLAYING;

    // Ask mpc for current status
    $mpcStatus  = shell_exec('mpc status 2>/dev/null') ?? '';
    $mpcCurrent = shell_exec('mpc current 2>/dev/null') ?? '';

    // Detect state
    $state = 'stop';
    if (strpos($mpcStatus, '[playing]') !== false) $state = 'play';
    elseif (strpos($mpcStatus, '[paused]') !== false)  $state = 'pause';

    // Get the currently playing file URL from mpc status
    // mpc -f %file% current gives the raw file path/URL
    $currentFile = trim(shell_exec('mpc -f %file% current 2>/dev/null') ?? '');

    // Detect if it's a YouTube stream:
    // yt-dlp stream URLs contain 'googlevideo.com' or have 'expire=' param
    $isYoutube = false;
    $videoId   = '';
    $title     = trim($mpcCurrent);

    if ($state !== 'stop' && !empty($currentFile)) {
        if (preg_match('/googlevideo\.com|youtube\.com|youtu\.be/i', $currentFile)) {
            $isYoutube = true;
        }
        // Also check if file is an http stream that matches our playlist map
        if (!$isYoutube && substr($currentFile, 0, 4) === 'http') {
            $plmap = read_json($PLMAP);
            foreach ($plmap as $entry) {
                $streamHost = $entry['stream_host'] ?? '';
                if (!empty($streamHost) && strpos($currentFile, $streamHost) !== false) {
                    $isYoutube = true;
                    $videoId   = $entry['video_id'] ?? '';
                    $title     = $entry['title'] ?? $title;
                    break;
                }
            }
        }
    }

    // If detected as youtube but no video ID yet, try playlist map by title
    if ($isYoutube && empty($videoId)) {
        $plmap = read_json($PLMAP);
        foreach ($plmap as $entry) {
            if (!empty($entry['title']) && stripos($title, substr($entry['title'], 0, 20)) !== false) {
                $videoId = $entry['video_id'] ?? '';
                break;
            }
        }
    }

    // Final fallback: read from now-playing.json (set when ?yturl= was called)
    if ($isYoutube && empty($videoId)) {
        $np = read_json($NOWPLAYING, []);
        $videoId = $np['video_id'] ?? '';
        if (empty($title) && !empty($np['title'])) $title = $np['title'];
    }

    return [
        'state'      => $state,
        'is_youtube' => $isYoutube,
        'video_id'   => $videoId,
        'title'      => $title,
        'file'       => $currentFile,
    ];
}

// ── createPLentry — ported from youtube.php, extended with video ID tracking ──

function createPLentry(string $yturl, string $plopts, $cnt, $length): void {
    global $YOUTUBE_M3U, $PLMAP, $NOWPLAYING;

    $yt = ytdlp(
        '--no-warnings --no-check-certificate --no-playlist ' .
        '--get-duration --get-thumbnail -e -f bestaudio -g ' .
        escapeshellarg($yturl)
    );

    if (empty(trim($yt))) {
        echo "yt-dlp returned empty for: $yturl ($plopts)";
        return;
    }

    $yt_arr        = explode("\n", trim($yt));
    $title         = $yt_arr[0] ?? '';
    $stream_url    = $yt_arr[1] ?? '';
    $thumbnail_url = $yt_arr[2] ?? '';
    $duration_str  = $yt_arr[3] ?? '0:00';

    // Extract video ID from original YouTube URL
    $video_id   = extract_video_id($yturl);
    $streamHost = extract_stream_host($stream_url);

    // Duration to seconds
    $time_seconds = 0;
    $d_str = preg_replace("/^([\d]{1,2})\:([\d]{2})$/", "00:$1:$2", $duration_str);
    if (sscanf($d_str, "%d:%d:%d", $h, $m, $s) === 3) {
        $time_seconds = $h * 3600 + $m * 60 + $s;
    }

    echo "url created...";

    if ($plopts === 'clear') {
        echo "playlist cleared...";
        file_put_contents($YOUTUBE_M3U, "#EXTM3U\n");
        // Clear playlist map too
        write_json($PLMAP, []);
    }

    if (in_array($plopts, ['clear', 'add'])) {
        echo "adding pl entry...";
        $entry  = "#EXTINF:$time_seconds, $title\n";
        $entry .= "$stream_url\n";
        $entry .= "#EXTIMG:$thumbnail_url\n";
        file_put_contents($YOUTUBE_M3U, $entry, FILE_APPEND);

        if (!empty($thumbnail_url)) {
            $img = @file_get_contents($thumbnail_url);
            if ($img) file_put_contents("/var/tmp/$cnt.jpg", $img);
        }

        // Update playlist map with video ID tracking
        $plmap = read_json($PLMAP);
        if ($plopts === 'clear') $plmap = [];
        $plmap[] = [
            'position'    => (int)$cnt,
            'video_id'    => $video_id,
            'title'       => $title,
            'youtube_url' => $yturl,
            'stream_host' => $streamHost,
            'thumbnail'   => $thumbnail_url,
            'added_at'    => date('c'),
        ];
        write_json($PLMAP, $plmap);

        // Update now-playing.json — first item or clear mode becomes current candidate
        if ((int)$cnt === 1 || $plopts === 'clear') {
            write_json($NOWPLAYING, [
                'video_id'    => $video_id,
                'title'       => $title,
                'youtube_url' => $yturl,
                'stream_host' => $streamHost,
                'thumbnail'   => $thumbnail_url,
                'added_at'    => date('c'),
            ]);
        }

    } elseif ($plopts === 'append') {
        shell_exec("mpc add " . escapeshellarg($stream_url));
        $plmap   = read_json($PLMAP);
        $plmap[] = [
            'position'    => count($plmap) + 1,
            'video_id'    => $video_id,
            'title'       => $title,
            'youtube_url' => $yturl,
            'stream_host' => $streamHost,
            'thumbnail'   => $thumbnail_url,
            'added_at'    => date('c'),
        ];
        write_json($PLMAP, $plmap);

    } elseif ($plopts === 'insert') {
        shell_exec("mpc insert " . escapeshellarg($stream_url));
    }

    echo "playlist entry created for: $title ($plopts) $cnt/$length";
}

// ── Remaining original functions (unchanged) ──────────────────────

function getYTPlaylist(string $ytpl): void {
    $ytpl     = str_ireplace('&', '\&', $ytpl);
    $json_raw = ytdlp('--no-warnings -j --flat-playlist ' . escapeshellarg($ytpl));
    $lines    = array_filter(explode("\n", trim($json_raw)));
    $playlist = [];
    foreach ($lines as $line) {
        $item = json_decode($line, true);
        if (!empty($item['id'])) {
            $url   = 'https://youtu.be/' . $item['id'];
            $title = $item['title'] ?? $item['id'];
            $playlist[$title] = $url;
        }
    }
    echo json_encode($playlist);
}

function showPlaylist(): void {
    global $YOUTUBE_M3U;
    if (!file_exists($YOUTUBE_M3U)) { echo 'empty'; return; }
    $plfile = shell_exec("grep '#EXTINF:' " . escapeshellarg($YOUTUBE_M3U) . " | awk -F':' '{print $2}'");
    if (empty($plfile)) { echo 'empty'; return; }
    $html = '';
    $cnt  = 1;
    foreach (explode("\n", $plfile) as $line) {
        $sec   = substr($line, 0, strpos($line, ','));
        $title = substr($line, strpos($line, ',') + 1);
        if (!empty($sec)) {
            $sec = sprintf('%02d:%02d:%02d', intdiv($sec, 3600), intdiv($sec % 3600, 60), $sec % 60);
        }
        if (!empty(trim($title))) {
            $html .= sprintf('%02d', $cnt) . '. ' . htmlspecialchars(trim($title)) . "  ($sec)<br />";
            $cnt++;
        }
    }
    echo $html ?: 'empty';
}

function getThumb(string $fileUrl): void {
    $res = ytdlp('--no-warnings --list-thumbnails ' . escapeshellarg($fileUrl) . ' | grep mqdefault.jpg');
    if (!empty($res)) {
        $thumbnails = explode("\n", $res);
        $thumbnail  = substr($thumbnails[0], strpos($thumbnails[0], 'http'));
        echo $thumbnail;
    }
}

function ytpllist(): void {
    global $YTPLLIST;
    if (!file_exists($YTPLLIST)) { echo json_encode([]); return; }
    $plfile = file_get_contents($YTPLLIST);
    if (empty($plfile)) { echo json_encode([]); return; }
    $pltitles = explode("\n", trim($plfile));
    $csv = array_map('str_getcsv', array_filter($pltitles));
    echo json_encode(array_values($csv));
}

function saveytpl(string $values): void {
    global $YTPLLIST;
    $dir = dirname($YTPLLIST);
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    file_put_contents($YTPLLIST, $values . "\r\n", FILE_APPEND);
}

function delytpl(string $values): void {
    global $YTPLLIST;
    if (!file_exists($YTPLLIST)) return;
    $plfile   = file_get_contents($YTPLLIST);
    $pltitles = array_map('trim', explode("\n", $plfile));
    $key      = array_search($values, $pltitles);
    if ($key !== false) {
        unset($pltitles[$key]);
        file_put_contents($YTPLLIST, implode("\r\n", $pltitles));
    }
}
