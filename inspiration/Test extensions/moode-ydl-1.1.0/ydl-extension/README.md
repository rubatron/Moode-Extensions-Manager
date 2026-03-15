# YouTube Audioplayback — moOde Extension
## v1.1.0

Stream audio from YouTube, SoundCloud and 1000+ sites directly into MPD.

Ported from: Stephanowicz/moOde-audioplayer-addons

---

## What's new in v1.1.0

- **Coverart video injection** — when a YouTube stream plays, a **Video** toggle
  button appears in moOde's coverart area. Tap to switch between the YouTube
  video player and the album art. The video auto-activates on playback start.
- **Now-playing tracking** — `api.php?action=nowplaying` returns the current
  video ID, title, and playback state so the JS can keep in sync with MPD.
- **playlist-map.json** — every added YouTube URL is stored with its video ID
  and stream host for reliable now-playing detection.

---

## How coverart injection works

1. `youtube-dl.js` is loaded into moOde's main page via the Stephanowicz
   addon mechanism (inject into `header.php` using `AddRemoveAddons.sh`).
2. It polls `/extensions/installed/moode-ydl/backend/api.php?action=nowplaying`
   every 2.5 seconds.
3. When a YouTube stream is detected in MPD: replaces `#coverart-url` with a
   1:1 YouTube iframe. Uses moOde design tokens (`--accentxts`, `--btnshade2`,
   `--img-border-radius`) so the toggle adapts to any moOde theme.
4. Toggle button switches between **Video** and **Cover** modes.
5. When MPD stops or plays non-YouTube audio: iframe is removed, original
   album art restored automatically.

**File:** `assets/js/youtubeDL.js` — this replaces the original Stephanowicz
`youtubeDL.js` with the original modal code preserved + coverart module added.

---

## Installation

1. Import via ext-mgr wizard (zip upload)
2. ext-mgr runs `install.sh` as `moode-extmgrusr`:
   - installs yt-dlp via pip into `packages/pylib/`
   - creates `packages/bin/yt-dlp` wrapper script
3. To activate coverart injection:
   - Copy `assets/js/youtubeDL.js` to `/var/www/addons/Stephanowicz/youtubeDL/`
   - Run `utils/AddRemoveAddons.sh` in the Stephanowicz addon to inject the
     script reference into `header.php`

---

## Sandbox layout

```
packages/bin/yt-dlp           wrapper → no /usr/local/bin symlink needed
packages/pylib/               pip install --target (yt-dlp Python package)
assets/js/youtubeDL.js        injected into moOde main page (coverart module)
assets/js/coverart-inject.js  standalone coverart module (used by extension page)
backend/api.php               all endpoints incl. ?action=nowplaying
data/playlist-map.json        video ID tracking (runtime, not in zip)
data/now-playing.json         current video (runtime, not in zip)
```

## Build importable zip

```bash
bash scripts/build-zip.sh
# → moode-ydl-1.1.0.zip
```
