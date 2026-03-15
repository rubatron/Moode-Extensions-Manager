# ext-mgr Function Catalog V2

This V2 catalog extends the original index with parameter signatures, expected return behavior,
and side-effect classification to accelerate onboarding and maintenance handovers.

## API Action Map

| Action | File | Line | Handler shape |
|---|---|---:|---|
| download_extension_template | ext-mgr-api.php | 4683 | inline action branch in ext-mgr-api.php |
| import_extension_upload | ext-mgr-api.php | 4715 | inline action branch in ext-mgr-api.php |
| list_extension_logs | ext-mgr-api.php | 4884 | inline action branch in ext-mgr-api.php |
| read_extension_log | ext-mgr-api.php | 4910 | inline action branch in ext-mgr-api.php |
| download_extension_log | ext-mgr-api.php | 4964 | inline action branch in ext-mgr-api.php |
| analyze_logs | ext-mgr-api.php | 5013 | inline action branch in ext-mgr-api.php |
| list | ext-mgr-api.php | 5029 | inline action branch in ext-mgr-api.php |
| status | ext-mgr-api.php | 5036 | inline action branch in ext-mgr-api.php |
| registry_sync | ext-mgr-api.php | 5043 | inline action branch in ext-mgr-api.php |
| check_update | ext-mgr-api.php | 5057 | inline action branch in ext-mgr-api.php |
| run_update | ext-mgr-api.php | 5111 | inline action branch in ext-mgr-api.php |
| set_update_advanced | ext-mgr-api.php | 5265 | inline action branch in ext-mgr-api.php |
| system_update_hook | ext-mgr-api.php | 5327 | inline action branch in ext-mgr-api.php |
| repair | ext-mgr-api.php | 5350 | inline action branch in ext-mgr-api.php |
| set_enabled | ext-mgr-api.php | 5381 | inline action branch in ext-mgr-api.php |
| repair_symlink | ext-mgr-api.php | 5437 | inline action branch in ext-mgr-api.php |
| remove_extension | ext-mgr-api.php | 5482 | inline action branch in ext-mgr-api.php |
| clear_extensions_folder | ext-mgr-api.php | 5520 | inline action branch in ext-mgr-api.php |
| system_resources | ext-mgr-api.php | 5537 | inline action branch in ext-mgr-api.php |
| clear_cache | ext-mgr-api.php | 5550 | inline action branch in ext-mgr-api.php |
| create_backup_snapshot | ext-mgr-api.php | 5575 | inline action branch in ext-mgr-api.php |
| set_manager_visibility | ext-mgr-api.php | 5601 | inline action branch in ext-mgr-api.php |
| set_menu_visibility | ext-mgr-api.php | 5644 | inline action branch in ext-mgr-api.php |
| set_settings_card_only | ext-mgr-api.php | 5707 | inline action branch in ext-mgr-api.php |

## Function Contracts

| File | Line | Function | Parameters | Returns | Side-effects |
|---|---:|---|---|---|---|
| assets/js/ext-mgr-hover-menu.js | 19 | esc | value | value or void | General helper behavior |
| assets/js/ext-mgr-hover-menu.js | 28 | normalizePath | url | value or void | Computes/reads data (low mutation) |
| assets/js/ext-mgr-hover-menu.js | 36 | sortPinnedFirst | items | value or void | General helper behavior |
| assets/js/ext-mgr-hover-menu.js | 52 | normalizeIconClass | value, fallback | value or void | Computes/reads data (low mutation) |
| assets/js/ext-mgr-hover-menu.js | 60 | extensionIcon | item, fallback | value or void | General helper behavior |
| assets/js/ext-mgr-hover-menu.js | 66 | toBool | value, fallback | value or void | General helper behavior |
| assets/js/ext-mgr-hover-menu.js | 73 | fetchApiListWithFallback | (none) | value or void | Performs network/API I/O |
| assets/js/ext-mgr-hover-menu.js | 77 | next | (none) | value or void | General helper behavior |
| assets/js/ext-mgr-hover-menu.js | 113 | fetchState | (none) | value or void | Performs network/API I/O |
| assets/js/ext-mgr-hover-menu.js | 127 | renderList | host, items | void (UI side-effects) | Mutates DOM/UI state |
| assets/js/ext-mgr-hover-menu.js | 155 | applyManagerVisibility | meta, refs | void (UI side-effects) | Mutates filesystem/runtime state |
| assets/js/ext-mgr-hover-menu.js | 179 | renderHeaderManagerButton | meta | void (UI side-effects) | Mutates DOM/UI state |
| assets/js/ext-mgr-hover-menu.js | 222 | findLibraryMenuContainer | (none) | value or void | General helper behavior |
| assets/js/ext-mgr-hover-menu.js | 226 | removeExistingLibraryInjected | container | value or void | Mutates filesystem/runtime state |
| assets/js/ext-mgr-hover-menu.js | 239 | isManagerEntry | item | boolean | Computes/reads data (low mutation) |
| assets/js/ext-mgr-hover-menu.js | 247 | hasExistingManagerLink | container | boolean | Computes/reads data (low mutation) |
| assets/js/ext-mgr-hover-menu.js | 256 | applyLibraryManagerLinkVisibility | container, visible | void (UI side-effects) | Mutates filesystem/runtime state |
| assets/js/ext-mgr-hover-menu.js | 270 | renderLibraryMenu | items, meta | void (UI side-effects) | Mutates DOM/UI state |
| assets/js/ext-mgr-hover-menu.js | 352 | removeExistingMMenuInjected | container | value or void | Mutates filesystem/runtime state |
| assets/js/ext-mgr-hover-menu.js | 362 | findMMenuContainer | (none) | value or void | General helper behavior |
| assets/js/ext-mgr-hover-menu.js | 380 | appendMMenuEntry | container, entryHref, label, iconClass, useListItem | value or void | Mutates filesystem/runtime state |
| assets/js/ext-mgr-hover-menu.js | 411 | renderMMenu | items, meta | void (UI side-effects) | Mutates DOM/UI state |
| assets/js/ext-mgr-hover-menu.js | 496 | removeExistingSystemMenuInjected | container | value or void | Mutates filesystem/runtime state |
| assets/js/ext-mgr-hover-menu.js | 506 | findSystemMenuContainer | (none) | value or void | General helper behavior |
| assets/js/ext-mgr-hover-menu.js | 512 | renderSystemMenu | (none) | void (UI side-effects) | Mutates DOM/UI state |
| assets/js/ext-mgr-hover-menu.js | 520 | findConfigureTileList | (none) | value or void | Computes/reads data (low mutation) |
| assets/js/ext-mgr-hover-menu.js | 524 | removeExistingConfigureTile | list | value or void | Mutates filesystem/runtime state |
| assets/js/ext-mgr-hover-menu.js | 537 | appendConfigureEntry | list, entry | value or void | Mutates filesystem/runtime state |
| assets/js/ext-mgr-hover-menu.js | 556 | renderConfigureTile | items, meta | void (UI side-effects) | Mutates DOM/UI state |
| assets/js/ext-mgr-hover-menu.js | 598 | ensureHostElements | (none) | value or void | Mutates filesystem/runtime state |
| assets/js/ext-mgr-hover-menu.js | 617 | loadExtensions | host | Promise or async workflow result | General helper behavior |
| assets/js/ext-mgr-hover-menu.js | 629 | observeMMenu | (none) | value or void | Mutates DOM/UI state |
| assets/js/ext-mgr-logs.js | 15 | uniqUrls | urls | value or void | General helper behavior |
| assets/js/ext-mgr-logs.js | 30 | setStatus | text, kind | value or void | Mutates filesystem/runtime state |
| assets/js/ext-mgr-logs.js | 36 | buildBody | params | value or void | Computes/reads data (low mutation) |
| assets/js/ext-mgr-logs.js | 44 | postApi | params | Promise or async workflow result | Performs network/API I/O |
| assets/js/ext-mgr-logs.js | 49 | tryAt | index | value or void | General helper behavior |
| assets/js/ext-mgr-logs.js | 83 | bestApiBase | (none) | value or void | Performs network/API I/O |
| assets/js/ext-mgr-logs.js | 88 | buildDownloadUrl | extensionId, key | value or void | Computes/reads data (low mutation) |
| assets/js/ext-mgr-logs.js | 98 | ensureModal | (none) | value or void | Mutates filesystem/runtime state |
| assets/js/ext-mgr-logs.js | 137 | closeModal | (none) | void (UI side-effects) | Mutates DOM/UI state |
| assets/js/ext-mgr-logs.js | 145 | openModal | (none) | void (UI side-effects) | Mutates DOM/UI state |
| assets/js/ext-mgr-logs.js | 151 | byId | id | value or void | General helper behavior |
| assets/js/ext-mgr-logs.js | 155 | currentLog | (none) | value or void | General helper behavior |
| assets/js/ext-mgr-logs.js | 165 | renderMeta | logRow | void (UI side-effects) | Mutates DOM/UI state |
| assets/js/ext-mgr-logs.js | 180 | renderAnalysisText | text | void (UI side-effects) | Mutates DOM/UI state |
| assets/js/ext-mgr-logs.js | 188 | formatRows | prefix, rows, limit | value or void | General helper behavior |
| assets/js/ext-mgr-logs.js | 207 | loadAnalysis | (none) | Promise or async workflow result | Computes/reads data (low mutation) |
| assets/js/ext-mgr-logs.js | 245 | loadLogContent | (none) | Promise or async workflow result | General helper behavior |
| assets/js/ext-mgr-logs.js | 270 | renderPicker | (none) | void (UI side-effects) | Mutates DOM/UI state |
| assets/js/ext-mgr-logs.js | 305 | wireModalActions | (none) | value or void | General helper behavior |
| assets/js/ext-mgr-logs.js | 344 | loadLogList | extensionId, label | Promise or async workflow result | Computes/reads data (low mutation) |
| assets/js/ext-mgr-logs.js | 373 | makeButton | text, className | value or void | General helper behavior |
| assets/js/ext-mgr-logs.js | 381 | attachExtensionButton | item, container | void (UI side-effects) | Mutates DOM/UI state |
| assets/js/ext-mgr-logs.js | 397 | bindManagerButton | buttonId | void (UI side-effects) | Mutates DOM/UI state |
| assets/js/ext-mgr-logs.js | 410 | bindManagerDownloadButton | buttonId | void (UI side-effects) | Mutates DOM/UI state |
| assets/js/ext-mgr-logs.js | 423 | init | options | value or void | General helper behavior |
| assets/js/ext-mgr.js | 121 | setStatus | text, kind | value or void | Mutates filesystem/runtime state |
| assets/js/ext-mgr.js | 140 | api | params | Promise or async workflow result | Performs network/API I/O |
| assets/js/ext-mgr.js | 156 | tryAt | idx | value or void | General helper behavior |
| assets/js/ext-mgr.js | 195 | tip | key, fallback | value or void | General helper behavior |
| assets/js/ext-mgr.js | 202 | applyTip | el, key, fallback | void (UI side-effects) | Mutates filesystem/runtime state |
| assets/js/ext-mgr.js | 214 | loadTooltipSnippets | (none) | Promise or async workflow result | General helper behavior |
| assets/js/ext-mgr.js | 229 | parseTooltipMarkdown | text | value or void | Mutates filesystem/runtime state |
| assets/js/ext-mgr.js | 254 | tryParseTooltipBody | url, bodyText | value or void | Mutates filesystem/runtime state |
| assets/js/ext-mgr.js | 278 | next | (none) | value or void | General helper behavior |
| assets/js/ext-mgr.js | 308 | applyStaticTooltips | (none) | void (UI side-effects) | Mutates filesystem/runtime state |
| assets/js/ext-mgr.js | 323 | bindIfPresent | el, eventName, handler | void (UI side-effects) | Mutates DOM/UI state |
| assets/js/ext-mgr.js | 330 | ensureActionModal | (none) | value or void | Mutates filesystem/runtime state |
| assets/js/ext-mgr.js | 369 | closeActionModal | confirmed | void (UI side-effects) | Mutates DOM/UI state |
| assets/js/ext-mgr.js | 393 | openActionModal | options | void (UI side-effects) | Mutates DOM/UI state |
| assets/js/ext-mgr.js | 416 | syncConfirmState | (none) | value or void | Mutates filesystem/runtime state |
| assets/js/ext-mgr.js | 446 | setImportWizardNote | text, kind | value or void | Mutates filesystem/runtime state |
| assets/js/ext-mgr.js | 457 | firstSentence | text | value or void | General helper behavior |
| assets/js/ext-mgr.js | 469 | apiUpload | file, dryRun | Promise or async workflow result | Performs network/API I/O |
| assets/js/ext-mgr.js | 488 | tryAt | idx | value or void | General helper behavior |
| assets/js/ext-mgr.js | 523 | readPref | key, fallback | value or void | Computes/reads data (low mutation) |
| assets/js/ext-mgr.js | 532 | writePref | key, value | value or void | Mutates filesystem/runtime state |
| assets/js/ext-mgr.js | 541 | setText | el, value | value or void | Mutates filesystem/runtime state |
| assets/js/ext-mgr.js | 548 | asMiB | value | value or void | General helper behavior |
| assets/js/ext-mgr.js | 555 | asPercent | value | value or void | General helper behavior |
| assets/js/ext-mgr.js | 562 | asBytes | value | value or void | General helper behavior |
| assets/js/ext-mgr.js | 576 | managerVisibilityAreaName | area | value or void | Computes/reads data (low mutation) |
| assets/js/ext-mgr.js | 583 | managerVisibilityLabel | area, visible | value or void | Computes/reads data (low mutation) |
| assets/js/ext-mgr.js | 587 | applyMoodeToggleState | toggleEl, visible | void (UI side-effects) | Mutates filesystem/runtime state |
| assets/js/ext-mgr.js | 600 | createMoodeToggle | id, initialVisible, onChange | value or void | Mutates filesystem/runtime state |
| assets/js/ext-mgr.js | 656 | applyManagerVisibilityButtonState | toggleEl, stateEl, area, visible | void (UI side-effects) | Mutates filesystem/runtime state |
| assets/js/ext-mgr.js | 665 | renderManagerVisibility | visibility | void (UI side-effects) | Mutates DOM/UI state |
| assets/js/ext-mgr.js | 683 | renderMaintenanceStatus | maintenance | void (UI side-effects) | Mutates DOM/UI state |
| assets/js/ext-mgr.js | 695 | renderSystemResources | resources | void (UI side-effects) | Mutates DOM/UI state |
| assets/js/ext-mgr.js | 740 | renderMeta | meta | void (UI side-effects) | Mutates DOM/UI state |
| assets/js/ext-mgr.js | 760 | renderHealth | health | void (UI side-effects) | Mutates DOM/UI state |
| assets/js/ext-mgr.js | 783 | providerStatusFromPolicy | policy | value or void | General helper behavior |
| assets/js/ext-mgr.js | 799 | parseRepository | repository | value or void | Computes/reads data (low mutation) |
| assets/js/ext-mgr.js | 808 | buildResolveSourceUrl | providerStatus | value or void | Computes/reads data (low mutation) |
| assets/js/ext-mgr.js | 836 | buildRawManagedBaseUrl | providerStatus, candidate | value or void | Computes/reads data (low mutation) |
| assets/js/ext-mgr.js | 863 | renderAdvancedSource | providerStatus, candidate | void (UI side-effects) | Mutates DOM/UI state |
| assets/js/ext-mgr.js | 887 | fallbackCopyText | text | value or void | General helper behavior |
| assets/js/ext-mgr.js | 907 | buildIntegrityText | integrity, providerStatus | value or void | Computes/reads data (low mutation) |
| assets/js/ext-mgr.js | 922 | renderUpdateStatus | meta, hasUpdate, candidate, warning, providerStatus, integrity | void (UI side-effects) | Mutates filesystem/runtime state |
| assets/js/ext-mgr.js | 950 | getAdvancedModeFromStatus | providerStatus | value or void | General helper behavior |
| assets/js/ext-mgr.js | 964 | renderAdvancedModeButtons | (none) | void (UI side-effects) | Mutates DOM/UI state |
| assets/js/ext-mgr.js | 980 | mergeWithCurrentProviderStatus | overrides | value or void | General helper behavior |
| assets/js/ext-mgr.js | 995 | renderAdvancedUpdateControls | providerStatus, payloadWarning, candidate | void (UI side-effects) | Mutates filesystem/runtime state |
| assets/js/ext-mgr.js | 1063 | setRunUpdateButtonState | (none) | value or void | Mutates filesystem/runtime state |
| assets/js/ext-mgr.js | 1070 | escapeHtml | value | value or void | General helper behavior |
| assets/js/ext-mgr.js | 1079 | markdownToHtml | markdown | value or void | General helper behavior |
| assets/js/ext-mgr.js | 1084 | closeList | (none) | void (UI side-effects) | Mutates DOM/UI state |
| assets/js/ext-mgr.js | 1127 | renderGuidanceDocs | guidance | void (UI side-effects) | Mutates DOM/UI state |
| assets/js/ext-mgr.js | 1143 | getVisibility | item, key | value or void | Computes/reads data (low mutation) |
| assets/js/ext-mgr.js | 1154 | setVisibility | item, key, value | value or void | Mutates filesystem/runtime state |
| assets/js/ext-mgr.js | 1161 | visibilityLabel | target, visible | value or void | Computes/reads data (low mutation) |
| assets/js/ext-mgr.js | 1166 | settingsCardLabel | enabled | value or void | Mutates filesystem/runtime state |
| assets/js/ext-mgr.js | 1170 | createInlineSwitchControl | labelText, toggleEl | value or void | Mutates filesystem/runtime state |
| assets/js/ext-mgr.js | 1203 | getSettingsCardOnly | item | value or void | Mutates filesystem/runtime state |
| assets/js/ext-mgr.js | 1207 | extensionInfoSummary | item | value or void | General helper behavior |
| assets/js/ext-mgr.js | 1228 | extensionDescription | item | value or void | General helper behavior |
| assets/js/ext-mgr.js | 1233 | extensionSettingsPage | item | value or void | Mutates filesystem/runtime state |
| assets/js/ext-mgr.js | 1238 | importReviewSummary | review | value or void | General helper behavior |
| assets/js/ext-mgr.js | 1257 | applyListControls | items | void (UI side-effects) | Mutates filesystem/runtime state |
| assets/js/ext-mgr.js | 1302 | renderSummary | visibleCount, totalCount | void (UI side-effects) | Mutates DOM/UI state |
| assets/js/ext-mgr.js | 1309 | renderItems | items | void (UI side-effects) | Mutates DOM/UI state |
| assets/js/ext-mgr.js | 1531 | applyExtensionActionState | enabled | void (UI side-effects) | Mutates filesystem/runtime state |
| assets/js/ext-mgr.js | 1584 | clearStatus | (none) | value or void | Mutates filesystem/runtime state |
| assets/js/ext-mgr.js | 1592 | loadStatusAndList | silent | Promise or async workflow result | Mutates DOM/UI state |
| assets/js/ext-mgr.js | 1615 | runRefresh | (none) | Promise or async workflow result | Mutates filesystem/runtime state |
| assets/js/ext-mgr.js | 1634 | reloadPageSoon | (none) | value or void | General helper behavior |
| assets/js/ext-mgr.js | 1640 | runSystemResources | silent | Promise or async workflow result | Mutates filesystem/runtime state |
| assets/js/ext-mgr.js | 1660 | runCreateBackup | (none) | Promise or async workflow result | Mutates filesystem/runtime state |
| assets/js/ext-mgr.js | 1690 | runClearCache | (none) | Promise or async workflow result | Mutates filesystem/runtime state |
| assets/js/ext-mgr.js | 1725 | runClearExtensionsFolder | (none) | Promise or async workflow result | Mutates filesystem/runtime state |
| assets/js/ext-mgr.js | 1771 | setManagerVisibility | area, visible, button | value or void | Mutates filesystem/runtime state |
| assets/js/ext-mgr.js | 1804 | runCheckUpdate | (none) | Promise or async workflow result | Mutates filesystem/runtime state |
| assets/js/ext-mgr.js | 1835 | runUpdate | (none) | Promise or async workflow result | Mutates filesystem/runtime state |
| assets/js/ext-mgr.js | 1860 | runRepair | (none) | Promise or async workflow result | Mutates filesystem/runtime state |
| assets/js/ext-mgr.js | 1875 | runRegistrySync | triggerLabel | Promise or async workflow result | Mutates filesystem/runtime state |
| ext-mgr-api.php | 23 | defaultMeta | (none) | mixed | General helper behavior |
| ext-mgr-api.php | 55 | defaultReleasePolicy | (none) | mixed | General helper behavior |
| ext-mgr-api.php | 95 | isSafeManagedPath | $filePath | bool | Computes/reads data (low mutation) |
| ext-mgr-api.php | 116 | normalizeReleasePolicy | $policy | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 202 | normalizeVersion | $value | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 214 | safeVersionCompare | $left, $right, $operator | mixed | General helper behavior |
| ext-mgr-api.php | 224 | safeHasUpdate | $latestVersion, $currentVersion | mixed | Mutates filesystem/runtime state |
| ext-mgr-api.php | 229 | readJsonFile | $path, $fallback | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 245 | readTextFile | $path, $fallback | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 257 | readGuidanceDocs | $baseDir | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 283 | writeJsonFile | $path, $data | bool/array status + error-by-ref | Mutates filesystem/runtime state |
| ext-mgr-api.php | 304 | canWriteJsonPath | $path | bool | Mutates filesystem/runtime state |
| ext-mgr-api.php | 312 | formatWriteFailure | $path, $label | mixed | Mutates filesystem/runtime state |
| ext-mgr-api.php | 325 | readSystemTotalMemMiB | (none) | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 349 | buildRuntimeMemoryHealth | (none) | array/string/scalar payload | Mutates filesystem/runtime state |
| ext-mgr-api.php | 365 | readExtMgrServiceHealth | (none) | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 403 | readExtMgrWatchdogHealth | (none) | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 441 | logTypes | (none) | mixed | General helper behavior |
| ext-mgr-api.php | 446 | ensureExtMgrLogLayout | (none) | mixed | Mutates filesystem/runtime state |
| ext-mgr-api.php | 462 | ensureExtensionLogLayout | $extensionId | mixed | Mutates filesystem/runtime state |
| ext-mgr-api.php | 492 | appendLogLine | $path, $message | mixed | Mutates filesystem/runtime state |
| ext-mgr-api.php | 505 | appendExtMgrLog | $type, $message | mixed | Mutates filesystem/runtime state |
| ext-mgr-api.php | 518 | appendExtensionLog | $extensionId, $type, $message | mixed | Mutates filesystem/runtime state |
| ext-mgr-api.php | 542 | buildLogRow | $key, $label, $path, $source | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 559 | buildVirtualLogRow | $key, $label, $source | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 572 | extMgrSystemLogCandidates | (none) | mixed | Computes/reads data (low mutation) |
| ext-mgr-api.php | 583 | buildCombinedLogContent | $targetId, $lineLimit = 120 | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 631 | availableLogsForTarget | $targetId | mixed | General helper behavior |
| ext-mgr-api.php | 669 | resolveLogPathForRead | $targetId, $key, &$error | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 697 | tailFileContent | $path, $lineLimit = 120 | mixed | General helper behavior |
| ext-mgr-api.php | 721 | readLogLines | $path, $lineLimit = 1200 | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 745 | parseLogLineEvent | $line | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 766 | normalizeErrorSignature | $message | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 778 | lineLooksError | $line | mixed | General helper behavior |
| ext-mgr-api.php | 789 | lineLooksRestartEvent | $line | mixed | General helper behavior |
| ext-mgr-api.php | 799 | summarizeLogsForTarget | $targetId, $lineLimit = 1200 | mixed | Computes/reads data (low mutation) |
| ext-mgr-api.php | 885 | discoverKnownExtensionIds | $registryPath, $extensionsLogsPath | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 915 | buildLogAnalysisPayload | $registryPath, $extensionsLogsPath, $targetId = '' | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 995 | ensureDirectory | $path, $mode = 0775 | mixed | Mutates filesystem/runtime state |
| ext-mgr-api.php | 1006 | removePathRecursiveWithStats | $path, &$removedEntries, &$freedBytes | bool/array status + error-by-ref | Mutates filesystem/runtime state |
| ext-mgr-api.php | 1040 | clearDirectoryContents | $dir, &$removedEntries, &$freedBytes, &$error | mixed | Mutates filesystem/runtime state |
| ext-mgr-api.php | 1067 | computeDirectorySizeBytes | $path | mixed | General helper behavior |
| ext-mgr-api.php | 1091 | computeDirectoryEntryCount | $path | mixed | General helper behavior |
| ext-mgr-api.php | 1108 | copyPathRecursive | $sourcePath, $targetPath, &$copiedItems, &$error | mixed | General helper behavior |
| ext-mgr-api.php | 1150 | createExtMgrBackupSnapshot | $baseDir, $backupRoot, &$snapshotPath, &$copiedItems, &$error | mixed | Mutates filesystem/runtime state |
| ext-mgr-api.php | 1195 | readCpuUsageSamplePct | (none) | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 1239 | readMemoryOverview | (none) | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 1273 | diskUsageForPath | $path | mixed | Computes/reads data (low mutation) |
| ext-mgr-api.php | 1307 | readProcessRssMiBFromProc | $pid | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 1323 | estimateExtensionRuntimeMemory | $extensions | mixed | Mutates filesystem/runtime state |
| ext-mgr-api.php | 1421 | buildExtensionsStorageSummary | $extensionsInstalledPath, $registryExtensions | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 1444 | readBackupSnapshotInfo | $backupRoot | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 1482 | buildMaintenanceStatus | $cacheDir, $backupRoot | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 1497 | buildSystemResourceSnapshot | $registryExtensions, $extensionsInstalledPath | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 1529 | readMeta | $path | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 1538 | readVersionValue | $path | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 1551 | writeVersionValue | $path, $version | bool/array status + error-by-ref | Mutates filesystem/runtime state |
| ext-mgr-api.php | 1559 | writeTextFileAtomic | $path, $content | bool/array status + error-by-ref | Mutates filesystem/runtime state |
| ext-mgr-api.php | 1568 | sanitizeExtensionId | $value | mixed | General helper behavior |
| ext-mgr-api.php | 1577 | isPlaceholderExtensionId | $value | bool | Computes/reads data (low mutation) |
| ext-mgr-api.php | 1595 | generateUuidV4 | (none) | mixed | General helper behavior |
| ext-mgr-api.php | 1614 | extensionIdExists | $extId, $registryPath | mixed | Computes/reads data (low mutation) |
| ext-mgr-api.php | 1639 | generateManagedExtensionId | $registryPath | mixed | General helper behavior |
| ext-mgr-api.php | 1651 | updateImportedManifestWithManagedId | $sourceDir, $manifestData, $newId, &$error | bool/array status + error-by-ref | Mutates filesystem/runtime state |
| ext-mgr-api.php | 1700 | buildTemplatePackageFiles | $extensionId | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 1988 | writeTemplateFilesToDirectory | $rootDir, $files, &$error | bool/array status + error-by-ref | Mutates filesystem/runtime state |
| ext-mgr-api.php | 2014 | writeTemplateZipViaCommand | $zipPath, $extensionId, $files, &$error | bool/array status + error-by-ref | Mutates filesystem/runtime state |
| ext-mgr-api.php | 2080 | writeTemplateZipArchive | $zipPath, $extensionId, &$error | bool/array status + error-by-ref | Mutates filesystem/runtime state |
| ext-mgr-api.php | 2118 | isSafeArchiveEntryPath | $entryPath | bool | Computes/reads data (low mutation) |
| ext-mgr-api.php | 2144 | listZipEntriesViaUnzip | $zipPath, &$error | mixed | Computes/reads data (low mutation) |
| ext-mgr-api.php | 2181 | extractZipArchiveSafely | $zipPath, $extractDir, &$error | bool/array status + error-by-ref | Mutates filesystem/runtime state |
| ext-mgr-api.php | 2248 | removePathRecursive | $path | bool/array status + error-by-ref | Mutates filesystem/runtime state |
| ext-mgr-api.php | 2273 | detectImportSourceDir | $extractRoot | mixed | Computes/reads data (low mutation) |
| ext-mgr-api.php | 2301 | runImportWizard | $wizardPath, $sourceDir, $dryRun, &$error, &$outputText | bool/array status + error-by-ref | Mutates filesystem/runtime state |
| ext-mgr-api.php | 2336 | isPhpFunctionEnabled | $name | bool | Computes/reads data (low mutation) |
| ext-mgr-api.php | 2349 | httpGetViaWget | $url, &$error | mixed | Performs network/API I/O |
| ext-mgr-api.php | 2404 | httpGet | $url, &$error | mixed | Performs network/API I/O |
| ext-mgr-api.php | 2470 | githubApiUrl | $repository, $path | mixed | Performs network/API I/O |
| ext-mgr-api.php | 2479 | githubRawFileUrl | $repository, $ref, $filePath | mixed | General helper behavior |
| ext-mgr-api.php | 2490 | normalizeCustomBaseUrl | $url | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 2502 | buildCustomFileUrl | $baseUrl, $filePath | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 2513 | chooseGithubReleaseByChannel | $releases, $channel | mixed | General helper behavior |
| ext-mgr-api.php | 2551 | githubReleaseApiPathForChannel | $channel | mixed | Performs network/API I/O |
| ext-mgr-api.php | 2559 | resolveRemoteBranchCandidate | $repository, $branch, &$error | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 2620 | resolveAvailableRemoteBranches | $repository, &$error | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 2669 | hasUpdateForCandidate | $candidate, $currentVersion, $policy | bool | Mutates filesystem/runtime state |
| ext-mgr-api.php | 2691 | chooseGithubTagByChannel | $tags, $channel | mixed | General helper behavior |
| ext-mgr-api.php | 2739 | resolveRemoteTagCandidate | $repository, $channel, &$error | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 2794 | resolveCustomBaseCandidate | $policy, &$error | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 2839 | resolveRemoteReleaseCandidate | $policy, &$error | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 2926 | fetchManagedFilesFromRelease | $policy, $candidate, &$error | mixed | Performs network/API I/O |
| ext-mgr-api.php | 2977 | normalizeDigestValue | $value | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 2986 | fetchIntegrityManifestFromRelease | $policy, $candidate, &$error | mixed | Performs network/API I/O |
| ext-mgr-api.php | 3070 | verifyPayloadsAgainstManifest | $payloads, $managedFiles, $manifest, &$error, &$details | mixed | General helper behavior |
| ext-mgr-api.php | 3125 | applyManagedFiles | $baseDir, $payloads, &$error | bool/array status + error-by-ref | Mutates filesystem/runtime state |
| ext-mgr-api.php | 3242 | updateReleasePolicyFromCandidate | $policyPath, $policy, $candidate | bool/array status + error-by-ref | Mutates filesystem/runtime state |
| ext-mgr-api.php | 3256 | markMetaMaintenance | $meta, $actionName, $result | mixed | General helper behavior |
| ext-mgr-api.php | 3270 | readReleasePolicy | $path | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 3276 | buildMeta | $metaPath, $versionPath, $releasePath | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 3307 | readRegistry | $path | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 3316 | normalizeUiPathOrUrl | $value | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 3331 | normalizeIconClass | $value, $fallback = 'fa-solid fa-sharp fa-puzzle-piece' | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 3346 | normalizeScalarStringList | $values | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 3367 | buildImportPackageReview | $sourceDir | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 3429 | readExtensionInstallMetadata | $extId | array/string/scalar payload | Mutates filesystem/runtime state |
| ext-mgr-api.php | 3478 | loadExtensionInfo | $extId, $entryPath, $fallbackName, $fallbackVersion | array/string/scalar payload | General helper behavior |
| ext-mgr-api.php | 3522 | normalizeRegistry | $registry | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 3605 | sanitizeRegistryForPersist | $registry | mixed | Computes/reads data (low mutation) |
| ext-mgr-api.php | 3625 | applyImportedExtensionDefaults | $registryPath, $extId | bool/array status + error-by-ref | Mutates filesystem/runtime state |
| ext-mgr-api.php | 3660 | responseData | $registryPath, $metaPath, $versionPath, $releasePath | mixed | General helper behavior |
| ext-mgr-api.php | 3725 | syncRegistryWithFilesystem | $registryPath, $pruneMissing = false | bool/array status + error-by-ref | Mutates filesystem/runtime state |
| ext-mgr-api.php | 3833 | isValidExtensionId | $id | bool | Computes/reads data (low mutation) |
| ext-mgr-api.php | 3838 | isSafeRelativeSubPath | $path | bool | Computes/reads data (low mutation) |
| ext-mgr-api.php | 3850 | resolveExtensionEntryFile | $extId, $entryPath, &$error | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 3900 | repairExtensionSymlink | $extId, $entryPath, &$error | bool/array status + error-by-ref | Mutates filesystem/runtime state |
| ext-mgr-api.php | 3943 | runPrivilegedSymlinkRepair | $extId, $entryPath, &$error | bool/array status + error-by-ref | Mutates filesystem/runtime state |
| ext-mgr-api.php | 3997 | runShellCommands | $commands, &$outputText | bool/array status + error-by-ref | Mutates filesystem/runtime state |
| ext-mgr-api.php | 4017 | removePathWithFallback | $path, &$outputNote | bool/array status + error-by-ref | Mutates filesystem/runtime state |
| ext-mgr-api.php | 4073 | getInstalledMetadataByExtensionId | $excludeExtId = '' | mixed | Mutates filesystem/runtime state |
| ext-mgr-api.php | 4095 | collectSharedPackageRefs | $excludeExtId | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 4111 | collectBundledDebPackageNames | $installedDir, $metadata | array/string/scalar payload | Computes/reads data (low mutation) |
| ext-mgr-api.php | 4154 | removeExtensionRuntimeLinks | $extId, $metadata, &$warnings | bool/array status + error-by-ref | Mutates filesystem/runtime state |
| ext-mgr-api.php | 4191 | clearExtensionAcl | $installedDir, $metadata, &$warnings | mixed | Mutates filesystem/runtime state |
| ext-mgr-api.php | 4252 | removeExtensionServiceUnits | $extId, $metadata, &$warnings | bool/array status + error-by-ref | Mutates filesystem/runtime state |
| ext-mgr-api.php | 4309 | runExtensionUninstallScript | $extId, $installedDir, $metadata, &$warnings | bool/array status + error-by-ref | Mutates filesystem/runtime state |
| ext-mgr-api.php | 4343 | uninstallExtensionPackagesGracefully | $extId, $installedDir, $metadata, &$warnings | bool/array status + error-by-ref | Mutates filesystem/runtime state |
| ext-mgr-api.php | 4409 | removeExtensionById | $extId, $registryPath, $backupRoot, &$error | bool/array status + error-by-ref | Mutates filesystem/runtime state |
| ext-mgr-api.php | 4579 | clearExtensionsFolderGracefully | $registryPath, $backupRoot, $extensionsInstalledPath, &$error | mixed | Mutates filesystem/runtime state |
| install.sh | 146 | set_source_root | (none) | shell status code / stdout | Mutates filesystem/runtime state |
| install.sh | 176 | detect_primary_user | (none) | shell status code / stdout | Computes/reads data (low mutation) |
| install.sh | 188 | sync_security_user_groups | (none) | shell status code / stdout | Mutates filesystem/runtime state |
| install.sh | 202 | ensure_extmgr_structure_permissions | (none) | shell status code / stdout | Mutates filesystem/runtime state |
| install.sh | 273 | require_file | (none) | shell status code / stdout | General helper behavior |
| install.sh | 281 | print_usage | (none) | shell status code / stdout | General helper behavior |
| install.sh | 299 | confirm_destructive_action | (none) | shell status code / stdout | General helper behavior |
| install.sh | 318 | show_interactive_menu | (none) | shell status code / stdout | General helper behavior |
| install.sh | 382 | run_restore_oobe | (none) | shell status code / stdout | Mutates filesystem/runtime state |
| install.sh | 414 | ensure_install_sources | (none) | shell status code / stdout | Mutates filesystem/runtime state |
| install.sh | 428 | graceful_finalize_services | (none) | shell status code / stdout | General helper behavior |
| install.sh | 458 | read_version_value | (none) | shell status code / stdout | Computes/reads data (low mutation) |
| install.sh | 467 | version_compare | (none) | shell status code / stdout | General helper behavior |
| install.sh | 486 | print_version_warning | (none) | shell status code / stdout | General helper behavior |
| install.sh | 511 | fetch_from_main_branch | (none) | shell status code / stdout | Performs network/API I/O |
| install.sh | 578 | cleanup_tmp_dir | (none) | shell status code / stdout | General helper behavior |
| install.sh | 586 | cleanup_shell_bridge_includes | (none) | shell status code / stdout | General helper behavior |
| install.sh | 616 | restore_latest_backup_file | (none) | shell status code / stdout | General helper behavior |
| install.sh | 629 | restore_core_web_files_from_backups | (none) | shell status code / stdout | General helper behavior |
| install.sh | 640 | run_uninstall | (none) | shell status code / stdout | Mutates filesystem/runtime state |
| install.sh | 728 | patch_index_template_menu | (none) | shell status code / stdout | Mutates filesystem/runtime state |
| install.sh | 756 | patch_header_and_footer_menu | (none) | shell status code / stdout | Mutates filesystem/runtime state |
| install.sh | 1007 | is_safe_rel | (none) | shell status code / stdout | Computes/reads data (low mutation) |
| scripts/ext-mgr-import-wizard.sh | 30 | log | (none) | shell status code / stdout | General helper behavior |
| scripts/ext-mgr-import-wizard.sh | 31 | err | (none) | shell status code / stdout | General helper behavior |
| scripts/ext-mgr-import-wizard.sh | 33 | manager_install_log | (none) | shell status code / stdout | Mutates filesystem/runtime state |
| scripts/ext-mgr-import-wizard.sh | 40 | extension_install_log | (none) | shell status code / stdout | Mutates filesystem/runtime state |
| scripts/ext-mgr-import-wizard.sh | 54 | ensure_log_roots | (none) | shell status code / stdout | Mutates filesystem/runtime state |
| scripts/ext-mgr-import-wizard.sh | 62 | require_root | (none) | shell status code / stdout | General helper behavior |
| scripts/ext-mgr-import-wizard.sh | 69 | require_command | (none) | shell status code / stdout | General helper behavior |
| scripts/ext-mgr-import-wizard.sh | 77 | group_exists | (none) | shell status code / stdout | Computes/reads data (low mutation) |
| scripts/ext-mgr-import-wizard.sh | 78 | user_exists | (none) | shell status code / stdout | Computes/reads data (low mutation) |
| scripts/ext-mgr-import-wizard.sh | 80 | add_user_to_group_safe | (none) | shell status code / stdout | General helper behavior |
| scripts/ext-mgr-import-wizard.sh | 88 | detect_default_ssh_user | (none) | shell status code / stdout | Computes/reads data (low mutation) |
| scripts/ext-mgr-import-wizard.sh | 98 | setup_security_principal | (none) | shell status code / stdout | Mutates filesystem/runtime state |
| scripts/ext-mgr-import-wizard.sh | 119 | grant_database_access | (none) | shell status code / stdout | Mutates filesystem/runtime state |
| scripts/ext-mgr-import-wizard.sh | 137 | ensure_extmgr_service | (none) | shell status code / stdout | Mutates filesystem/runtime state |
| scripts/ext-mgr-import-wizard.sh | 148 | run_install_helper_if_present | (none) | shell status code / stdout | Mutates filesystem/runtime state |
| scripts/ext-mgr-import-wizard.sh | 168 | json_get | (none) | shell status code / stdout | General helper behavior |
| scripts/ext-mgr-import-wizard.sh | 173 | ensure_registry | (none) | shell status code / stdout | Mutates filesystem/runtime state |
| scripts/ext-mgr-import-wizard.sh | 188 | update_registry_entry | (none) | shell status code / stdout | Mutates filesystem/runtime state |
| scripts/ext-mgr-import-wizard.sh | 246 | detect_extension_version | (none) | shell status code / stdout | Computes/reads data (low mutation) |
| scripts/ext-mgr-import-wizard.sh | 277 | set_extension_permissions | (none) | shell status code / stdout | Mutates filesystem/runtime state |
| scripts/ext-mgr-import-wizard.sh | 299 | toggle_extension_state | (none) | shell status code / stdout | General helper behavior |
| scripts/ext-mgr-import-wizard.sh | 336 | run_import | (none) | shell status code / stdout | Mutates filesystem/runtime state |
| scripts/ext-mgr-import-wizard.sh | 417 | run_mode_toggle | (none) | shell status code / stdout | Mutates filesystem/runtime state |
| scripts/ext-mgr-import-wizard.sh | 436 | parse_args | (none) | shell status code / stdout | Computes/reads data (low mutation) |
| scripts/ext-mgr-import-wizard.sh | 480 | main | (none) | shell status code / stdout | General helper behavior |

## Critical Workflow Anchors

- Import pipeline start: ext-mgr-api.php:4715 (`import_extension_upload`)
- Template package generation: ext-mgr-api.php:1700 (`buildTemplatePackageFiles`)
- Safe extraction gate: ext-mgr-api.php:2181 (`extractZipArchiveSafely`)
- Privileged import bridge: ext-mgr-api.php:2301 (`runImportWizard`)
- Extension uninstall orchestrator: ext-mgr-api.php:4409 (`removeExtensionById`)
- Registry/filesystem reconciliation: ext-mgr-api.php:3725 (`syncRegistryWithFilesystem`)
- Header manager button injection: assets/js/ext-mgr-hover-menu.js:179 (`renderHeaderManagerButton`)
- Library menu injection: assets/js/ext-mgr-hover-menu.js:270 (`renderLibraryMenu`)
- M-menu injection: assets/js/ext-mgr-hover-menu.js:411 (`renderMMenu`)
- ACL/bootstrap hardening: scripts/ext-mgr-import-wizard.sh:98 (`setup_security_principal`)
- Permission enforcement: install.sh:202 (`ensure_extmgr_structure_permissions`)
