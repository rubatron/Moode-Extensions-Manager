<?php

/**
 * MoodeHelper - Safe wrappers for moOde API, database, and MPD operations
 *
 * This library provides extensions with a clean interface to interact with moOde
 * without causing conflicts (database locks, session issues, etc.)
 *
 * Usage in extensions:
 *   require_once '/var/www/extensions/sys/api/lib/MoodeHelper.php';
 *   $moode = new MoodeHelper();
 *
 *   // Call moOde REST API
 *   $currentSong = $moode->api('get_currentsong');
 *
 *   // Safe database access
 *   $stations = $moode->db()->query("SELECT * FROM cfg_radio WHERE type != 'f'");
 *
 *   // MPD commands
 *   $moode->mpd('play');
 *
 * @package ext-mgr
 * @version 1.0.0
 */

class MoodeHelper
{
  private ?MoodeDb $db = null;
  private string $moodeRoot = '/var/www';
  private string $commandEndpoint = '/command/index.php';
  private string $radioEndpoint = '/command/radio.php';

  /**
   * Get database helper with safe locking/retry mechanism
   */
  public function db(): MoodeDb
  {
    if ($this->db === null) {
      $this->db = new MoodeDb();
    }
    return $this->db;
  }

  /**
   * Call moOde REST API endpoint
   *
   * @param string $cmd Command (e.g., 'get_currentsong', 'get_volume', 'play')
   * @param array $postData Optional POST data
   * @return array|string|null Response data
   */
  public function api(string $cmd, array $postData = []): mixed
  {
    $url = 'http://localhost' . $this->commandEndpoint . '?cmd=' . urlencode($cmd);

    if (!empty($postData)) {
      $options = [
        'http' => [
          'header' => 'Content-type: application/x-www-form-urlencoded',
          'method' => 'POST',
          'content' => http_build_query($postData),
          'timeout' => 10
        ]
      ];
    } else {
      $options = [
        'http' => [
          'method' => 'GET',
          'timeout' => 10
        ]
      ];
    }

    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);

    if ($result === false) {
      return null;
    }

    $decoded = json_decode($result, true);
    return $decoded !== null ? $decoded : $result;
  }

  /**
   * Call moOde radio API endpoint
   *
   * @param string $cmd Command (e.g., 'get_stations', 'new_station')
   * @param array $postData Optional POST data
   * @return array|string|null Response data
   */
  public function radioApi(string $cmd, array $postData = []): mixed
  {
    $url = 'http://localhost' . $this->radioEndpoint . '?cmd=' . urlencode($cmd);

    if (!empty($postData)) {
      $options = [
        'http' => [
          'header' => 'Content-type: application/x-www-form-urlencoded',
          'method' => 'POST',
          'content' => http_build_query($postData),
          'timeout' => 10
        ]
      ];
    } else {
      $options = [
        'http' => [
          'method' => 'GET',
          'timeout' => 10
        ]
      ];
    }

    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);

    if ($result === false) {
      return null;
    }

    $decoded = json_decode($result, true);
    return $decoded !== null ? $decoded : $result;
  }

  /**
   * Send MPD command via moOde API (automatically passed through to MPD)
   *
   * @param string $cmd MPD command (e.g., 'play', 'pause', 'stop', 'clear')
   * @return array|null MPD response
   */
  public function mpd(string $cmd): ?array
  {
    return $this->api($cmd);
  }

  /**
   * Get current playback info
   */
  public function getCurrentSong(): ?array
  {
    return $this->api('get_currentsong');
  }

  /**
   * Get current volume and mute state
   */
  public function getVolume(): ?array
  {
    return $this->api('get_volume');
  }

  /**
   * Set volume (0-100)
   */
  public function setVolume(int $volume): ?array
  {
    return $this->api('set_volume ' . min(100, max(0, $volume)));
  }

  /**
   * Play an item (station, track, album, playlist)
   */
  public function playItem(string $item): ?array
  {
    return $this->api('play_item ' . $item);
  }

  /**
   * Toggle play/pause
   */
  public function togglePlayPause(): ?array
  {
    return $this->api('toggle_play_pause');
  }

  /**
   * Clear the play queue
   */
  public function clearQueue(): ?array
  {
    return $this->api('clear_queue');
  }

  /**
   * Get all radio stations from database
   */
  public function getRadioStations(): ?array
  {
    return $this->radioApi('get_stations');
  }

  /**
   * Get station details by name
   */
  public function getStationContents(string $stationName): ?array
  {
    return $this->radioApi('get_station_contents', ['path' => 'RADIO/' . $stationName . '.pls']);
  }

  /**
   * Create a new radio station using moOde's API
   * This is the SAFE way - uses moOde's own validation and session sync
   *
   * @param array $station Station data with keys: name, url, type, genre, broadcaster, etc.
   * @return string 'OK' on success, error message on failure
   */
  public function addRadioStation(array $station): string
  {
    $path = [
      'name' => $station['name'] ?? '',
      'url' => $station['url'] ?? '',
      'type' => $station['type'] ?? 's',
      'logo' => $station['logo'] ?? 'local',
      'genre' => $station['genre'] ?? '',
      'broadcaster' => $station['broadcaster'] ?? '',
      'language' => $station['language'] ?? '',
      'country' => $station['country'] ?? '',
      'region' => $station['region'] ?? '',
      'bitrate' => $station['bitrate'] ?? '',
      'format' => $station['format'] ?? '',
      'geo_fenced' => $station['geo_fenced'] ?? 'No',
      'home_page' => $station['home_page'] ?? '',
      'monitor' => $station['monitor'] ?? 'No'
    ];

    $result = $this->radioApi('new_station', ['path' => $path]);
    return is_string($result) ? $result : 'Unknown error';
  }

  /**
   * Update existing radio station
   *
   * @param int $id Station ID
   * @param array $station Station data
   * @return string 'OK' on success, error message on failure
   */
  public function updateRadioStation(int $id, array $station): string
  {
    $station['id'] = $id;
    $path = [
      'id' => $id,
      'name' => $station['name'] ?? '',
      'url' => $station['url'] ?? '',
      'type' => $station['type'] ?? 's',
      'logo' => $station['logo'] ?? 'local',
      'genre' => $station['genre'] ?? '',
      'broadcaster' => $station['broadcaster'] ?? '',
      'language' => $station['language'] ?? '',
      'country' => $station['country'] ?? '',
      'region' => $station['region'] ?? '',
      'bitrate' => $station['bitrate'] ?? '',
      'format' => $station['format'] ?? '',
      'geo_fenced' => $station['geo_fenced'] ?? 'No',
      'home_page' => $station['home_page'] ?? '',
      'monitor' => $station['monitor'] ?? 'No'
    ];

    $result = $this->radioApi('upd_station', ['path' => $path]);
    return is_string($result) ? $result : 'Unknown error';
  }

  /**
   * Delete a radio station
   *
   * @param string $stationName Station name (without .pls)
   * @return bool Success
   */
  public function deleteRadioStation(string $stationName): bool
  {
    $result = $this->radioApi('del_station', ['path' => 'RADIO/' . $stationName . '.pls']);
    return $result !== null;
  }

  /**
   * Trigger library update
   */
  public function updateLibrary(): ?array
  {
    return $this->api('upd_library');
  }

  // ════════════════════════════════════════════════════════════════════════════
  // PLAYLIST API (/command/playlist.php)
  // ════════════════════════════════════════════════════════════════════════════

  /**
   * Call moOde playlist API endpoint
   */
  public function playlistApi(string $cmd, array $postData = [], array $getData = []): mixed
  {
    $url = 'http://localhost/command/playlist.php?cmd=' . urlencode($cmd);
    foreach ($getData as $key => $value) {
      $url .= '&' . urlencode($key) . '=' . urlencode($value);
    }

    if (!empty($postData)) {
      $options = [
        'http' => [
          'header' => 'Content-type: application/x-www-form-urlencoded',
          'method' => 'POST',
          'content' => http_build_query($postData),
          'timeout' => 10
        ]
      ];
    } else {
      $options = ['http' => ['method' => 'GET', 'timeout' => 10]];
    }

    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    if ($result === false) return null;
    $decoded = json_decode($result, true);
    return $decoded !== null ? $decoded : $result;
  }

  /**
   * Get all playlists
   */
  public function getPlaylists(): ?array
  {
    return $this->playlistApi('get_playlists');
  }

  /**
   * Get playlist contents
   */
  public function getPlaylistContents(string $name): ?array
  {
    return $this->playlistApi('get_playlist_contents', ['path' => $name]);
  }

  /**
   * Create a new playlist
   */
  public function createPlaylist(string $name, string $genre = '', array $items = []): mixed
  {
    return $this->playlistApi('new_playlist', [
      'path' => ['name' => $name, 'genre' => $genre, 'items' => $items]
    ]);
  }

  /**
   * Add items to a playlist
   */
  public function addToPlaylist(string $playlistName, array $items): mixed
  {
    return $this->playlistApi('add_to_playlist', [
      'path' => ['playlist' => $playlistName, 'items' => $items]
    ]);
  }

  /**
   * Delete a playlist
   */
  public function deletePlaylist(string $name): mixed
  {
    return $this->playlistApi('del_playlist', ['path' => $name]);
  }

  /**
   * Save current queue as a playlist
   */
  public function saveQueueToPlaylist(string $name): mixed
  {
    return $this->playlistApi('save_queue_to_playlist', [], ['name' => $name]);
  }

  // ════════════════════════════════════════════════════════════════════════════
  // FAVORITES API (/command/playlist.php)
  // ════════════════════════════════════════════════════════════════════════════

  /**
   * Get the name of the favorites playlist
   */
  public function getFavoritesName(): ?string
  {
    $result = $this->playlistApi('get_favorites_name');
    return is_string($result) ? $result : null;
  }

  /**
   * Set the favorites playlist name
   */
  public function setFavoritesName(string $name): mixed
  {
    return $this->playlistApi('set_favorites_name', [], ['name' => $name]);
  }

  /**
   * Add an item to favorites
   * For radio stations, this also sets type='f' in cfg_radio
   *
   * @param string $item File path or URL (http://... for stations)
   */
  public function addToFavorites(string $item): mixed
  {
    return $this->playlistApi('add_item_to_favorites', [], ['item' => $item]);
  }

  /**
   * Mark a radio station as favorite (sets type='f' in cfg_radio)
   */
  public function markRadioAsFavorite(string $stationUrl): bool
  {
    $result = $this->db()->execute(
      "UPDATE cfg_radio SET type='f' WHERE station = ?",
      [$stationUrl]
    );
    return $result !== false && $result > 0;
  }

  /**
   * Unmark a radio station as favorite (sets type='s' for shown)
   */
  public function unmarkRadioAsFavorite(string $stationUrl): bool
  {
    $result = $this->db()->execute(
      "UPDATE cfg_radio SET type='s' WHERE station = ?",
      [$stationUrl]
    );
    return $result !== false && $result > 0;
  }

  /**
   * Get all favorite radio stations
   */
  public function getFavoriteRadioStations(): array
  {
    $result = $this->db()->query(
      "SELECT * FROM cfg_radio WHERE type = 'f' AND station NOT IN ('OFFLINE', 'zx reserved 499')"
    );
    return $result ?: [];
  }

  // ════════════════════════════════════════════════════════════════════════════
  // QUEUE API (/command/queue.php)
  // ════════════════════════════════════════════════════════════════════════════

  /**
   * Call moOde queue API endpoint
   */
  public function queueApi(string $cmd, array $postData = [], array $getData = []): mixed
  {
    $url = 'http://localhost/command/queue.php?cmd=' . urlencode($cmd);
    foreach ($getData as $key => $value) {
      $url .= '&' . urlencode($key) . '=' . urlencode($value);
    }

    if (!empty($postData)) {
      $options = [
        'http' => [
          'header' => 'Content-type: application/x-www-form-urlencoded',
          'method' => 'POST',
          'content' => http_build_query($postData),
          'timeout' => 10
        ]
      ];
    } else {
      $options = ['http' => ['method' => 'GET', 'timeout' => 10]];
    }

    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    if ($result === false) return null;
    $decoded = json_decode($result, true);
    return $decoded !== null ? $decoded : $result;
  }

  /**
   * Get the current play queue
   */
  public function getPlayQueue(): ?array
  {
    return $this->queueApi('get_playqueue');
  }

  /**
   * Clear the play queue
   */
  public function clearPlayQueue(): mixed
  {
    return $this->queueApi('clear_playqueue');
  }

  /**
   * Add item to queue
   */
  public function addItemToQueue(string $path): mixed
  {
    return $this->queueApi('add_item', ['path' => $path]);
  }

  /**
   * Add item to queue and play next
   */
  public function addItemToQueueNext(string $path): mixed
  {
    return $this->queueApi('add_item_next', ['path' => $path]);
  }

  /**
   * Clear queue and play item
   */
  public function clearAndPlayItem(string $path): mixed
  {
    return $this->queueApi('clear_play_item', ['path' => $path]);
  }

  /**
   * Delete item from queue by position
   */
  public function deleteQueueItem(string $range): mixed
  {
    return $this->queueApi('delete_playqueue_item', [], ['range' => $range]);
  }

  /**
   * Move item in queue
   */
  public function moveQueueItem(string $range, string $newPos): mixed
  {
    return $this->queueApi('move_playqueue_item', [], ['range' => $range, 'newpos' => $newPos]);
  }

  // ════════════════════════════════════════════════════════════════════════════
  // MUSIC LIBRARY API (/command/music-library.php)
  // ════════════════════════════════════════════════════════════════════════════

  /**
   * Call moOde music library API endpoint
   */
  public function libraryApi(string $cmd, array $postData = [], array $getData = []): mixed
  {
    $url = 'http://localhost/command/music-library.php?cmd=' . urlencode($cmd);
    foreach ($getData as $key => $value) {
      $url .= '&' . urlencode($key) . '=' . urlencode($value);
    }

    if (!empty($postData)) {
      $options = [
        'http' => [
          'header' => 'Content-type: application/x-www-form-urlencoded',
          'method' => 'POST',
          'content' => http_build_query($postData),
          'timeout' => 30  // Library ops can be slow
        ]
      ];
    } else {
      $options = ['http' => ['method' => 'GET', 'timeout' => 30]];
    }

    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    if ($result === false) return null;
    $decoded = json_decode($result, true);
    return $decoded !== null ? $decoded : $result;
  }

  /**
   * List contents of a path (like MPD lsinfo)
   */
  public function lsInfo(string $path = ''): ?array
  {
    return $this->libraryApi('lsinfo', [], ['path' => $path]);
  }

  /**
   * Search MPD database
   * @param string $tagname 'any' for all tags, or 'specific' for MPD filter syntax
   * @param string $query Search query
   */
  public function search(string $tagname, string $query): ?array
  {
    return $this->libraryApi('search', [], ['tagname' => $tagname, 'query' => $query]);
  }

  /**
   * Get library update status
   */
  public function getLibraryUpdateStatus(): mixed
  {
    return $this->libraryApi('get_dbupdate_status');
  }

  // ════════════════════════════════════════════════════════════════════════════
  // CONFIG/SYSTEM API (/command/cfg-table.php, /command/system.php)
  // ════════════════════════════════════════════════════════════════════════════

  /**
   * Call moOde config table API endpoint
   */
  public function configApi(string $cmd, array $postData = [], array $getData = []): mixed
  {
    $url = 'http://localhost/command/cfg-table.php?cmd=' . urlencode($cmd);
    foreach ($getData as $key => $value) {
      $url .= '&' . urlencode($key) . '=' . urlencode($value);
    }

    if (!empty($postData)) {
      $options = [
        'http' => [
          'header' => 'Content-type: application/x-www-form-urlencoded',
          'method' => 'POST',
          'content' => http_build_query($postData),
          'timeout' => 10
        ]
      ];
    } else {
      $options = ['http' => ['method' => 'GET', 'timeout' => 10]];
    }

    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    if ($result === false) return null;
    $decoded = json_decode($result, true);
    return $decoded !== null ? $decoded : $result;
  }

  /**
   * Get all config tables (cfg_system, cfg_theme, cfg_network, cfg_ssid, cfg_radio)
   */
  public function getConfigTables(bool $includeRadio = true): ?array
  {
    $cmd = $includeRadio ? 'get_cfg_tables' : 'get_cfg_tables_no_radio';
    return $this->configApi($cmd);
  }

  /**
   * Get cfg_system table
   */
  public function getSystemConfig(): ?array
  {
    return $this->configApi('get_cfg_system');
  }

  /**
   * Get a specific cfg_system value via API
   */
  public function getSystemConfigValue(string $param): mixed
  {
    return $this->configApi('get_cfg_system_value', [], ['param' => $param]);
  }

  /**
   * Update cfg_system values
   */
  public function updateSystemConfig(array $values): mixed
  {
    return $this->configApi('upd_cfg_system', $values);
  }

  /**
   * System reboot
   */
  public function systemReboot(): mixed
  {
    return $this->systemApi('reboot');
  }

  /**
   * System poweroff
   */
  public function systemPoweroff(): mixed
  {
    return $this->systemApi('poweroff');
  }

  /**
   * Call system API endpoint
   */
  private function systemApi(string $cmd): mixed
  {
    $url = 'http://localhost/command/system.php?cmd=' . urlencode($cmd);
    $options = ['http' => ['method' => 'GET', 'timeout' => 10]];
    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    if ($result === false) return null;
    $decoded = json_decode($result, true);
    return $decoded !== null ? $decoded : $result;
  }

  // ════════════════════════════════════════════════════════════════════════════
  // AUDIO INFO API (/command/audioinfo.php)
  // ════════════════════════════════════════════════════════════════════════════

  /**
   * Get radio station info
   */
  public function getStationInfo(string $stationUrl): ?array
  {
    $url = 'http://localhost/command/audioinfo.php?cmd=station_info&path=' . urlencode($stationUrl);
    $result = @file_get_contents($url);
    if ($result === false) return null;
    return json_decode($result, true);
  }

  /**
   * Get track info/metadata
   */
  public function getTrackInfo(string $path): ?array
  {
    $url = 'http://localhost/command/audioinfo.php?cmd=track_info&path=' . urlencode($path);
    $result = @file_get_contents($url);
    if ($result === false) return null;
    return json_decode($result, true);
  }

  // ════════════════════════════════════════════════════════════════════════════
  // RENDERER CONTROL (/command/index.php)
  // ════════════════════════════════════════════════════════════════════════════

  /**
   * Restart a renderer
   * @param string $renderer One of: bluetooth, airplay, spotify, pleezer, squeezelite, roonbridge
   */
  public function restartRenderer(string $renderer): mixed
  {
    return $this->api('restart_renderer --' . $renderer);
  }

  /**
   * Turn renderer on or off
   * @param string $renderer One of: bluetooth, airplay, spotify, pleezer, squeezelite, roonbridge
   * @param bool $on True to enable, false to disable
   */
  public function setRendererOnOff(string $renderer, bool $on): mixed
  {
    return $this->api('renderer_onoff --' . $renderer . ' ' . ($on ? 'on' : 'off'));
  }

  /**
   * Get multiroom receiver status
   */
  public function getReceiverStatus(): mixed
  {
    return $this->api('get_receiver_status -rx');
  }

  /**
   * Set multiroom receiver on/off
   */
  public function setReceiverOnOff(bool $on): mixed
  {
    return $this->api('set_receiver_onoff ' . ($on ? '-on' : '-off'));
  }

  // ════════════════════════════════════════════════════════════════════════════
  // CAMILLADSP (/command/index.php)
  // ════════════════════════════════════════════════════════════════════════════

  /**
   * Get current CamillaDSP configuration
   */
  public function getCDSPConfig(): mixed
  {
    return $this->api('get_cdsp_config');
  }

  /**
   * Set CamillaDSP configuration
   */
  public function setCDSPConfig(string $configName): mixed
  {
    return $this->api('set_cdsp_config ' . $configName);
  }
}


/**
 * MoodeDb - Safe SQLite access with proper locking
 *
 * Provides retry logic and busy_timeout to prevent "database table is locked" errors
 * when worker.php or mountmon.php hold connections.
 */
class MoodeDb
{
  private ?PDO $pdo = null;
  private string $dbPath = '/var/local/www/db/moode-sqlite3.db';
  private int $busyTimeout = 5000;  // 5 seconds
  private int $maxRetries = 3;
  private int $retryDelayMs = 100;  // Start with 100ms, exponential backoff

  /**
   * Get PDO connection with WAL mode and busy_timeout
   */
  public function getConnection(): ?PDO
  {
    if ($this->pdo !== null) {
      return $this->pdo;
    }

    try {
      $this->pdo = new PDO('sqlite:' . $this->dbPath);
      $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

      // Enable WAL mode for better concurrent access
      $this->pdo->exec('PRAGMA journal_mode=WAL');
      // Set busy timeout so SQLite waits instead of immediately failing
      $this->pdo->exec('PRAGMA busy_timeout=' . $this->busyTimeout);

      return $this->pdo;
    } catch (PDOException $e) {
      error_log('MoodeDb: Connection failed: ' . $e->getMessage());
      return null;
    }
  }

  /**
   * Execute a read query with retry logic
   *
   * @param string $sql SQL query
   * @param array $params PDO parameters
   * @return array|false Query results or false on failure
   */
  public function query(string $sql, array $params = []): array|false
  {
    return $this->executeWithRetry(function ($pdo) use ($sql, $params) {
      $stmt = $pdo->prepare($sql);
      $stmt->execute($params);
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    });
  }

  /**
   * Execute a write query with retry logic
   *
   * @param string $sql SQL statement
   * @param array $params PDO parameters
   * @return int|false Number of affected rows or false on failure
   */
  public function execute(string $sql, array $params = []): int|false
  {
    return $this->executeWithRetry(function ($pdo) use ($sql, $params) {
      $stmt = $pdo->prepare($sql);
      $stmt->execute($params);
      return $stmt->rowCount();
    });
  }

  /**
   * Insert a record and return last insert ID
   *
   * @param string $sql INSERT statement
   * @param array $params PDO parameters
   * @return int|false Last insert ID or false on failure
   */
  public function insert(string $sql, array $params = []): int|false
  {
    return $this->executeWithRetry(function ($pdo) use ($sql, $params) {
      $stmt = $pdo->prepare($sql);
      $stmt->execute($params);
      return (int)$pdo->lastInsertId();
    });
  }

  /**
   * Get a single value
   *
   * @param string $sql SQL query
   * @param array $params PDO parameters
   * @return mixed Value or null
   */
  public function getValue(string $sql, array $params = []): mixed
  {
    $result = $this->query($sql, $params);
    if ($result && count($result) > 0) {
      return array_values($result[0])[0] ?? null;
    }
    return null;
  }

  /**
   * Get a single row
   *
   * @param string $sql SQL query
   * @param array $params PDO parameters
   * @return array|null Row or null
   */
  public function getRow(string $sql, array $params = []): ?array
  {
    $result = $this->query($sql, $params);
    if ($result && count($result) > 0) {
      return $result[0];
    }
    return null;
  }

  /**
   * Execute with retry logic and exponential backoff
   */
  private function executeWithRetry(callable $operation): mixed
  {
    $pdo = $this->getConnection();
    if ($pdo === null) {
      return false;
    }

    $lastException = null;
    $delay = $this->retryDelayMs;

    for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
      try {
        return $operation($pdo);
      } catch (PDOException $e) {
        $lastException = $e;
        $errorCode = $e->getCode();

        // SQLITE_BUSY (5) or SQLITE_LOCKED (6)
        if (
          $errorCode == 5 || $errorCode == 6 ||
          stripos($e->getMessage(), 'locked') !== false ||
          stripos($e->getMessage(), 'busy') !== false
        ) {

          if ($attempt < $this->maxRetries) {
            usleep($delay * 1000);  // Convert to microseconds
            $delay *= 2;  // Exponential backoff
            continue;
          }
        }

        // Non-lock error or max retries reached
        break;
      }
    }

    error_log('MoodeDb: Query failed after ' . $this->maxRetries . ' attempts: ' .
      ($lastException ? $lastException->getMessage() : 'unknown error'));
    return false;
  }

  /**
   * Read radio stations directly from database
   * NOTE: Prefer MoodeHelper::getRadioStations() which uses moOde's API
   */
  public function getRadioStations(): array
  {
    $result = $this->query(
      "SELECT * FROM cfg_radio WHERE station NOT IN ('OFFLINE', 'zx reserved 499') AND type != 'f'"
    );
    return $result ?: [];
  }

  /**
   * Read a cfg_system value
   */
  public function getSystemValue(string $param): ?string
  {
    return $this->getValue(
      "SELECT value FROM cfg_system WHERE param = ?",
      [$param]
    );
  }

  /**
   * Escape string for SQLite
   */
  public static function escape(string $value): string
  {
    return SQLite3::escapeString($value);
  }

  /**
   * Close connection
   */
  public function close(): void
  {
    $this->pdo = null;
  }
}
