# Module 3 Report: Extensions Manager

## Goal
Introduce a flexible extension management baseline using module 2 menu integration as the starting point.

## Implemented Concept
- Library menu gets an `Extensions` entry
- `Extensions` opens a dedicated `Extensions Manager` page
- Manager page shows installed plugins from a generated registry file
- Manager page includes a `Refresh` action to rescan installed extensions
- `Extensions` now also supports an inline hover panel in `index.php` that dynamically lists installed extensions
- Pinning is managed in `Extensions Manager` per extension (saved in `registry.json`)

## Why This Is the Right Next Step
This creates a stable foundation for dynamic extension menus without forcing immediate database schema changes.

## Current UX
From `index.php` menu:
- Extensions
- Radio
- Tag
- Album
- Artist

Hovering `Extensions` shows discovered entries from `registry.json`.
Each entry shows an icon. Pinned extensions are shown first and keep the extension panel visible when the Library dropdown opens.
Clicking `Extensions` opens the manager page.

From `Extensions Manager`:
- Radio Browser
- Extension 2 (future)
- Extension 3 (future)

## Future Upgrades
- Nested submenu rendering directly in index menu
- Enable/disable toggles
- Version metadata and health checks
- Optional migration to SQLite when required
