# moOde Integration Guide for Extensions

This guide explains how extensions should interact with moOde to avoid common issues like database locks and session conflicts.

## The Problem

When extensions write directly to moOde's SQLite database, they can encounter:

- **"database table is locked"** errors when `worker.php` or `mountmon.php` hold connections
- **Session sync issues** when radio stations aren't properly reflected in `$_SESSION`
- **MPD playlist desync** when changes aren't notified to MPD

## The Solution: MoodeHelper

ext-mgr provides a `MoodeHelper` class that wraps moOde's APIs and adds:

- Safe database access with retry logic and `busy_timeout`
- Proper session synchronization via moOde's REST API
- MPD command passthrough

### Basic Usage

```php
<?php
// In your extension's API or PHP file
require_once '/var/www/extensions/sys/api/lib/MoodeHelper.php';

$moode = new MoodeHelper();
```

## API Methods

### Playback Control

```php
// Get current song info
$song = $moode->getCurrentSong();
// Returns: ['file' => '...', 'Title' => '...', 'Artist' => '...']

// Get volume
$vol = $moode->getVolume();
// Returns: ['volume' => '50', 'muted' => 'no']

// Set volume (0-100)
$moode->setVolume(75);

// Play/pause toggle
$moode->togglePlayPause();

// Clear queue
$moode->clearQueue();

// Play a specific item
$moode->playItem('RADIO/BBC Radio 1.pls');
$moode->playItem('http://stream.example.com/live');
$moode->playItem('NAS/Music/album-folder');
```

### Direct MPD Commands

Any MPD command is passed through via the moOde API:

```php
// Basic commands
$moode->mpd('play');
$moode->mpd('pause');
$moode->mpd('stop');
$moode->mpd('next');
$moode->mpd('previous');

// With arguments
$moode->mpd('seek 0 30');    // Seek to 30 seconds
$moode->mpd('random 1');     // Enable shuffle
```

### Radio Stations (Recommended)

Use the safe API approach which handles session sync:

```php
// Get all stations
$stations = $moode->getRadioStations();

// Add a new station (safe - uses moOde's API)
$result = $moode->addRadioStation([
    'name' => 'My Station',
    'url' => 'http://stream.example.com/live',
    'type' => 's',              // 's' = shown, 'f' = favorite, 'h' = hidden
    'genre' => 'Rock, Indie',
    'broadcaster' => 'Example Radio',
    'country' => 'NL',
    'bitrate' => '128',
    'format' => 'MP3'
]);

if ($result === 'OK') {
    echo "Station added!";
} else {
    echo "Error: $result";
}

// Update a station
$result = $moode->updateRadioStation(501, [
    'name' => 'Updated Name',
    'url' => 'http://new-stream.example.com/live',
    // ... other fields
]);

// Delete a station
$moode->deleteRadioStation('My Station');
```

## Direct Database Access

When you need direct database access (not recommended for radio stations), use `MoodeDb`:

```php
$db = $moode->db();

// Read queries with automatic retry
$rows = $db->query("SELECT * FROM cfg_system WHERE param = ?", ['timezone']);

// Single value
$timezone = $db->getValue("SELECT value FROM cfg_system WHERE param = ?", ['timezone']);

// Single row
$station = $db->getRow("SELECT * FROM cfg_radio WHERE id = ?", [501]);

// Write operations (with retry on lock)
$affected = $db->execute("UPDATE cfg_system SET value = ? WHERE param = ?", ['value', 'key']);

// Insert and get ID
$id = $db->insert("INSERT INTO cfg_radio (station, name, type) VALUES (?, ?, ?)",
    ['http://...', 'My Station', 's']);
```

### Why MoodeDb is Safer

1. **WAL Mode**: Enables Write-Ahead Logging for better concurrent reads
2. **Busy Timeout**: Waits up to 5 seconds instead of immediately failing
3. **Retry Logic**: Automatically retries with exponential backoff (100ms → 200ms → 400ms)
4. **Proper Error Logging**: Logs failures to PHP error log

## Migration Guide for Radio Browser

If your extension currently does direct SQL like:

```php
// ❌ OLD WAY - May cause locks
$dbh = new PDO('sqlite:/var/local/www/db/moode-sqlite3.db');
$dbh->query("INSERT INTO cfg_radio VALUES (...)");
```

Change to:

```php
// ✅ NEW WAY - Uses moOde API with proper session sync
require_once '/var/www/extensions/sys/api/lib/MoodeHelper.php';

$moode = new MoodeHelper();
$result = $moode->addRadioStation([
    'name' => $stationName,
    'url' => $stationUrl,
    'type' => 's',
    // ...
]);
```

Or if you must use direct DB access:

```php
// ✅ SAFER - With retry and busy_timeout
$db = $moode->db();
$db->execute("INSERT INTO cfg_radio (...) VALUES (...)", $params);
```

## Available moOde API Endpoints

The MoodeHelper wraps these moOde endpoints:

### `/command/index.php` (General + MPD)

| Command | Description | MoodeHelper Method |
|---------|-------------|-------------------|
| `get_currentsong` | Current playing track info | `getCurrentSong()` |
| `get_volume` | Volume and mute state | `getVolume()` |
| `set_volume N` | Set volume (0-100) | `setVolume(N)` |
| `set_volume -mute` | Toggle mute | `mpd('set_volume -mute')` |
| `play_item <path>` | Play item immediately | `playItem($path)` |
| `toggle_play_pause` | Toggle play/pause | `togglePlayPause()` |
| `clear_queue` | Clear playback queue | `clearQueue()` |
| `upd_library` | Trigger library update | `updateLibrary()` |
| `get_cdsp_config` | Get CamillaDSP config | `getCDSPConfig()` |
| `set_cdsp_config` | Set CamillaDSP config | `setCDSPConfig($name)` |
| `restart_renderer` | Restart renderer | `restartRenderer($type)` |
| `renderer_onoff` | Enable/disable renderer | `setRendererOnOff($type, $on)` |
| `get_receiver_status` | Multiroom receiver status | `getReceiverStatus()` |
| `set_receiver_onoff` | Multiroom on/off | `setReceiverOnOff($on)` |
| Any MPD command | Passed through to MPD | `mpd($cmd)` |

### `/command/radio.php` (Radio Stations)

| Command | Description | MoodeHelper Method |
|---------|-------------|-------------------|
| `get_stations` | List all radio stations | `getRadioStations()` |
| `get_station_contents` | Get station details | `getStationContents($name)` |
| `new_station` | Create station (POST) | `addRadioStation($data)` |
| `upd_station` | Update station (POST) | `updateRadioStation($id, $data)` |
| `del_station` | Delete station (POST) | `deleteRadioStation($name)` |

### `/command/playlist.php` (Playlists + Favorites)

| Command | Description | MoodeHelper Method |
|---------|-------------|-------------------|
| `get_playlists` | List all playlists | `getPlaylists()` |
| `get_playlist_contents` | Get playlist items | `getPlaylistContents($name)` |
| `new_playlist` | Create playlist | `createPlaylist($name, $genre, $items)` |
| `add_to_playlist` | Add items to playlist | `addToPlaylist($name, $items)` |
| `del_playlist` | Delete playlist | `deletePlaylist($name)` |
| `save_queue_to_playlist` | Save queue as playlist | `saveQueueToPlaylist($name)` |
| `get_favorites_name` | Get favorites playlist name | `getFavoritesName()` |
| `set_favorites_name` | Set favorites playlist | `setFavoritesName($name)` |
| `add_item_to_favorites` | Add to favorites | `addToFavorites($item)` |

### `/command/queue.php` (Queue Management)

| Command | Description | MoodeHelper Method |
|---------|-------------|-------------------|
| `get_playqueue` | Get current queue | `getPlayQueue()` |
| `clear_playqueue` | Clear queue | `clearPlayQueue()` |
| `add_item` | Add item to queue | `addItemToQueue($path)` |
| `add_item_next` | Add item to play next | `addItemToQueueNext($path)` |
| `clear_play_item` | Clear and play item | `clearAndPlayItem($path)` |
| `delete_playqueue_item` | Delete from queue | `deleteQueueItem($range)` |
| `move_playqueue_item` | Move item in queue | `moveQueueItem($range, $newPos)` |

### `/command/music-library.php` (Library)

| Command | Description | MoodeHelper Method |
|---------|-------------|-------------------|
| `update_library` | Trigger MPD database update | `updateLibrary()` |
| `lsinfo` | List directory contents | `lsInfo($path)` |
| `search` | Search MPD database | `search($tagname, $query)` |
| `get_dbupdate_status` | Library update status | `getLibraryUpdateStatus()` |

### `/command/cfg-table.php` (System Configuration)

| Command | Description | MoodeHelper Method |
|---------|-------------|-------------------|
| `get_cfg_tables` | Get all config tables | `getConfigTables()` |
| `get_cfg_system` | Get system config | `getSystemConfig()` |
| `get_cfg_system_value` | Get single config value | `getSystemConfigValue($param)` |
| `upd_cfg_system` | Update system config | `updateSystemConfig($values)` |

### `/command/audioinfo.php` (Audio Info)

| Command | Description | MoodeHelper Method |
|---------|-------------|-------------------|
| `station_info` | Get radio station metadata | `getStationInfo($url)` |
| `track_info` | Get track metadata | `getTrackInfo($path)` |

### `/command/system.php` (System Control)

| Command | Description | MoodeHelper Method |
|---------|-------------|-------------------|
| `reboot` | Reboot system | `systemReboot()` |
| `poweroff` | Power off system | `systemPoweroff()` |

## Favorites System

moOde's favorites are **playlists** with a special name stored in `cfg_system.favorites_name`.

### How Favorites Work

1. **Playlist-based**: Favorites are stored as a regular `.m3u` playlist
2. **Name configurable**: Default is "Favorites" but can be changed
3. **Radio station flag**: Radio stations also get `type='f'` in `cfg_radio`

### Working with Favorites

```php
$moode = new MoodeHelper();

// Get current favorites playlist name
$favName = $moode->getFavoritesName();  // e.g., "Favorites"

// Set a different favorites playlist
$moode->setFavoritesName('My Favorites');

// Add item to favorites (works for tracks and stations)
$moode->addToFavorites('RADIO/BBC Radio 1.pls');
$moode->addToFavorites('NAS/Music/song.mp3');
$moode->addToFavorites('http://stream.example.com/live');

// Mark/unmark radio station as favorite (database flag only)
$moode->markRadioAsFavorite('http://stream.example.com/live');
$moode->unmarkRadioAsFavorite('http://stream.example.com/live');

// Get all favorite radio stations
$favoriteStations = $moode->getFavoriteRadioStations();
```

### Radio Station Types

In `cfg_radio.type`:

| Type | Meaning |
|------|---------|
| `s` | Shown (regular) |
| `f` | Favorite |
| `h` | Hidden |
| `r` | Regular (legacy) |

## Best Practices

1. **Use the API** for radio station CRUD - it handles session sync and MPD updates
2. **Use MoodeDb** only for read operations or custom tables
3. **Never hold long-running database connections** - open, query, close
4. **Log errors** to your extension's log file, not moOde's
5. **Test with concurrent access** - run your extension while moOde is playing
6. **Use `addToFavorites()`** instead of direct DB writes for favorites

## Troubleshooting

### "database table is locked"

Using MoodeHelper/MoodeDb should prevent this. If you still see it:

- Check if you're holding a connection open too long
- Reduce write frequency
- Consider batching multiple writes

### Radio stations not appearing

If stations are in the database but not showing:

- The session cache might be stale
- Use `MoodeHelper::addRadioStation()` which syncs the session
- As fallback, trigger a page reload in moOde
