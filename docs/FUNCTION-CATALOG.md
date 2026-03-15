# ext-mgr Function Catalog

Auto-generated function index for transfer/handover. Each row includes file, line, function name, and role hint.

## API Action Entry Points

| Action | File:Line |
|---|---|
| download_extension_template | ext-mgr-api.php:4683 |
| import_extension_upload | ext-mgr-api.php:4715 |
| list_extension_logs | ext-mgr-api.php:4884 |
| read_extension_log | ext-mgr-api.php:4910 |
| download_extension_log | ext-mgr-api.php:4964 |
| analyze_logs | ext-mgr-api.php:5013 |
| list | ext-mgr-api.php:5029 |
| status | ext-mgr-api.php:5036 |
| registry_sync | ext-mgr-api.php:5043 |
| check_update | ext-mgr-api.php:5057 |
| run_update | ext-mgr-api.php:5111 |
| set_update_advanced | ext-mgr-api.php:5265 |
| system_update_hook | ext-mgr-api.php:5327 |
| repair | ext-mgr-api.php:5350 |
| set_enabled | ext-mgr-api.php:5381 |
| repair_symlink | ext-mgr-api.php:5437 |
| remove_extension | ext-mgr-api.php:5482 |
| clear_extensions_folder | ext-mgr-api.php:5520 |
| system_resources | ext-mgr-api.php:5537 |
| clear_cache | ext-mgr-api.php:5550 |
| create_backup_snapshot | ext-mgr-api.php:5575 |
| set_manager_visibility | ext-mgr-api.php:5601 |
| set_menu_visibility | ext-mgr-api.php:5644 |
| set_settings_card_only | ext-mgr-api.php:5707 |

## Function Index

| File | Line | Function | Role |
|---|---:|---|---|
| assets/js/ext-mgr-hover-menu.js | 19 | esc | General helper function |
| assets/js/ext-mgr-hover-menu.js | 28 | normalizePath | Normalizes incoming values into canonical form |
| assets/js/ext-mgr-hover-menu.js | 36 | sortPinnedFirst | General helper function |
| assets/js/ext-mgr-hover-menu.js | 52 | normalizeIconClass | Normalizes incoming values into canonical form |
| assets/js/ext-mgr-hover-menu.js | 60 | extensionIcon | General helper function |
| assets/js/ext-mgr-hover-menu.js | 66 | toBool | General helper function |
| assets/js/ext-mgr-hover-menu.js | 73 | fetchApiListWithFallback | Fetches local/remote content |
| assets/js/ext-mgr-hover-menu.js | 77 | next | General helper function |
| assets/js/ext-mgr-hover-menu.js | 113 | fetchState | Fetches local/remote content |
| assets/js/ext-mgr-hover-menu.js | 127 | renderList | Renders UI state or menu output |
| assets/js/ext-mgr-hover-menu.js | 155 | applyManagerVisibility | Applies computed state to UI/system |
| assets/js/ext-mgr-hover-menu.js | 179 | renderHeaderManagerButton | Renders UI state or menu output |
| assets/js/ext-mgr-hover-menu.js | 222 | findLibraryMenuContainer | General helper function |
| assets/js/ext-mgr-hover-menu.js | 226 | removeExistingLibraryInjected | Removes files, links, or injected UI |
| assets/js/ext-mgr-hover-menu.js | 239 | isManagerEntry | Validation/predicate helper |
| assets/js/ext-mgr-hover-menu.js | 247 | hasExistingManagerLink | Validation/predicate helper |
| assets/js/ext-mgr-hover-menu.js | 256 | applyLibraryManagerLinkVisibility | Applies computed state to UI/system |
| assets/js/ext-mgr-hover-menu.js | 270 | renderLibraryMenu | Renders UI state or menu output |
| assets/js/ext-mgr-hover-menu.js | 352 | removeExistingMMenuInjected | Removes files, links, or injected UI |
| assets/js/ext-mgr-hover-menu.js | 362 | findMMenuContainer | General helper function |
| assets/js/ext-mgr-hover-menu.js | 380 | appendMMenuEntry | Appends data to log or output stream |
| assets/js/ext-mgr-hover-menu.js | 411 | renderMMenu | Renders UI state or menu output |
| assets/js/ext-mgr-hover-menu.js | 496 | removeExistingSystemMenuInjected | Removes files, links, or injected UI |
| assets/js/ext-mgr-hover-menu.js | 506 | findSystemMenuContainer | General helper function |
| assets/js/ext-mgr-hover-menu.js | 512 | renderSystemMenu | Renders UI state or menu output |
| assets/js/ext-mgr-hover-menu.js | 520 | findConfigureTileList | General helper function |
| assets/js/ext-mgr-hover-menu.js | 524 | removeExistingConfigureTile | Removes files, links, or injected UI |
| assets/js/ext-mgr-hover-menu.js | 537 | appendConfigureEntry | Appends data to log or output stream |
| assets/js/ext-mgr-hover-menu.js | 556 | renderConfigureTile | Renders UI state or menu output |
| assets/js/ext-mgr-hover-menu.js | 598 | ensureHostElements | Ensures required precondition, path, or layout |
| assets/js/ext-mgr-hover-menu.js | 617 | loadExtensions | Loads state/content and prepares it for use |
| assets/js/ext-mgr-hover-menu.js | 629 | observeMMenu | General helper function |
| assets/js/ext-mgr-logs.js | 15 | uniqUrls | General helper function |
| assets/js/ext-mgr-logs.js | 30 | setStatus | Sets state, preference, or mode |
| assets/js/ext-mgr-logs.js | 36 | buildBody | Builds payload, response, or computed structure |
| assets/js/ext-mgr-logs.js | 44 | postApi | General helper function |
| assets/js/ext-mgr-logs.js | 49 | tryAt | General helper function |
| assets/js/ext-mgr-logs.js | 83 | bestApiBase | General helper function |
| assets/js/ext-mgr-logs.js | 88 | buildDownloadUrl | Builds payload, response, or computed structure |
| assets/js/ext-mgr-logs.js | 98 | ensureModal | Ensures required precondition, path, or layout |
| assets/js/ext-mgr-logs.js | 137 | closeModal | General helper function |
| assets/js/ext-mgr-logs.js | 145 | openModal | General helper function |
| assets/js/ext-mgr-logs.js | 151 | byId | General helper function |
| assets/js/ext-mgr-logs.js | 155 | currentLog | General helper function |
| assets/js/ext-mgr-logs.js | 165 | renderMeta | Renders UI state or menu output |
| assets/js/ext-mgr-logs.js | 180 | renderAnalysisText | Renders UI state or menu output |
| assets/js/ext-mgr-logs.js | 188 | formatRows | General helper function |
| assets/js/ext-mgr-logs.js | 207 | loadAnalysis | Loads state/content and prepares it for use |
| assets/js/ext-mgr-logs.js | 245 | loadLogContent | Loads state/content and prepares it for use |
| assets/js/ext-mgr-logs.js | 270 | renderPicker | Renders UI state or menu output |
| assets/js/ext-mgr-logs.js | 305 | wireModalActions | General helper function |
| assets/js/ext-mgr-logs.js | 344 | loadLogList | Loads state/content and prepares it for use |
| assets/js/ext-mgr-logs.js | 373 | makeButton | General helper function |
| assets/js/ext-mgr-logs.js | 381 | attachExtensionButton | General helper function |
| assets/js/ext-mgr-logs.js | 397 | bindManagerButton | General helper function |
| assets/js/ext-mgr-logs.js | 410 | bindManagerDownloadButton | General helper function |
| assets/js/ext-mgr-logs.js | 423 | init | General helper function |
| assets/js/ext-mgr.js | 121 | setStatus | Sets state, preference, or mode |
| assets/js/ext-mgr.js | 140 | api | General helper function |
| assets/js/ext-mgr.js | 156 | tryAt | General helper function |
| assets/js/ext-mgr.js | 195 | tip | General helper function |
| assets/js/ext-mgr.js | 202 | applyTip | Applies computed state to UI/system |
| assets/js/ext-mgr.js | 214 | loadTooltipSnippets | Loads state/content and prepares it for use |
| assets/js/ext-mgr.js | 229 | parseTooltipMarkdown | Parses text/log/config input |
| assets/js/ext-mgr.js | 254 | tryParseTooltipBody | General helper function |
| assets/js/ext-mgr.js | 278 | next | General helper function |
| assets/js/ext-mgr.js | 308 | applyStaticTooltips | Applies computed state to UI/system |
| assets/js/ext-mgr.js | 323 | bindIfPresent | General helper function |
| assets/js/ext-mgr.js | 330 | ensureActionModal | Ensures required precondition, path, or layout |
| assets/js/ext-mgr.js | 369 | closeActionModal | General helper function |
| assets/js/ext-mgr.js | 393 | openActionModal | General helper function |
| assets/js/ext-mgr.js | 416 | syncConfirmState | Synchronizes derived state with source-of-truth |
| assets/js/ext-mgr.js | 446 | setImportWizardNote | Sets state, preference, or mode |
| assets/js/ext-mgr.js | 457 | firstSentence | General helper function |
| assets/js/ext-mgr.js | 469 | apiUpload | General helper function |
| assets/js/ext-mgr.js | 488 | tryAt | General helper function |
| assets/js/ext-mgr.js | 523 | readPref | Reads state/data from file, process, or runtime |
| assets/js/ext-mgr.js | 532 | writePref | Writes or persists data/content |
| assets/js/ext-mgr.js | 541 | setText | Sets state, preference, or mode |
| assets/js/ext-mgr.js | 548 | asMiB | General helper function |
| assets/js/ext-mgr.js | 555 | asPercent | General helper function |
| assets/js/ext-mgr.js | 562 | asBytes | General helper function |
| assets/js/ext-mgr.js | 576 | managerVisibilityAreaName | General helper function |
| assets/js/ext-mgr.js | 583 | managerVisibilityLabel | General helper function |
| assets/js/ext-mgr.js | 587 | applyMoodeToggleState | Applies computed state to UI/system |
| assets/js/ext-mgr.js | 600 | createMoodeToggle | Creates filesystem resources or snapshots |
| assets/js/ext-mgr.js | 656 | applyManagerVisibilityButtonState | Applies computed state to UI/system |
| assets/js/ext-mgr.js | 665 | renderManagerVisibility | Renders UI state or menu output |
| assets/js/ext-mgr.js | 683 | renderMaintenanceStatus | Renders UI state or menu output |
| assets/js/ext-mgr.js | 695 | renderSystemResources | Renders UI state or menu output |
| assets/js/ext-mgr.js | 740 | renderMeta | Renders UI state or menu output |
| assets/js/ext-mgr.js | 760 | renderHealth | Renders UI state or menu output |
| assets/js/ext-mgr.js | 783 | providerStatusFromPolicy | General helper function |
| assets/js/ext-mgr.js | 799 | parseRepository | Parses text/log/config input |
| assets/js/ext-mgr.js | 808 | buildResolveSourceUrl | Builds payload, response, or computed structure |
| assets/js/ext-mgr.js | 836 | buildRawManagedBaseUrl | Builds payload, response, or computed structure |
| assets/js/ext-mgr.js | 863 | renderAdvancedSource | Renders UI state or menu output |
| assets/js/ext-mgr.js | 887 | fallbackCopyText | General helper function |
| assets/js/ext-mgr.js | 907 | buildIntegrityText | Builds payload, response, or computed structure |
| assets/js/ext-mgr.js | 922 | renderUpdateStatus | Renders UI state or menu output |
| assets/js/ext-mgr.js | 950 | getAdvancedModeFromStatus | General helper function |
| assets/js/ext-mgr.js | 964 | renderAdvancedModeButtons | Renders UI state or menu output |
| assets/js/ext-mgr.js | 980 | mergeWithCurrentProviderStatus | General helper function |
| assets/js/ext-mgr.js | 995 | renderAdvancedUpdateControls | Renders UI state or menu output |
| assets/js/ext-mgr.js | 1063 | setRunUpdateButtonState | Sets state, preference, or mode |
| assets/js/ext-mgr.js | 1070 | escapeHtml | General helper function |
| assets/js/ext-mgr.js | 1079 | markdownToHtml | General helper function |
| assets/js/ext-mgr.js | 1084 | closeList | General helper function |
| assets/js/ext-mgr.js | 1127 | renderGuidanceDocs | Renders UI state or menu output |
| assets/js/ext-mgr.js | 1143 | getVisibility | General helper function |
| assets/js/ext-mgr.js | 1154 | setVisibility | Sets state, preference, or mode |
| assets/js/ext-mgr.js | 1161 | visibilityLabel | General helper function |
| assets/js/ext-mgr.js | 1166 | settingsCardLabel | Sets state, preference, or mode |
| assets/js/ext-mgr.js | 1170 | createInlineSwitchControl | Creates filesystem resources or snapshots |
| assets/js/ext-mgr.js | 1203 | getSettingsCardOnly | General helper function |
| assets/js/ext-mgr.js | 1207 | extensionInfoSummary | General helper function |
| assets/js/ext-mgr.js | 1228 | extensionDescription | General helper function |
| assets/js/ext-mgr.js | 1233 | extensionSettingsPage | General helper function |
| assets/js/ext-mgr.js | 1238 | importReviewSummary | General helper function |
| assets/js/ext-mgr.js | 1257 | applyListControls | Applies computed state to UI/system |
| assets/js/ext-mgr.js | 1302 | renderSummary | Renders UI state or menu output |
| assets/js/ext-mgr.js | 1309 | renderItems | Renders UI state or menu output |
| assets/js/ext-mgr.js | 1531 | applyExtensionActionState | Applies computed state to UI/system |
| assets/js/ext-mgr.js | 1584 | clearStatus | General helper function |
| assets/js/ext-mgr.js | 1592 | loadStatusAndList | Loads state/content and prepares it for use |
| assets/js/ext-mgr.js | 1615 | runRefresh | Runs an operation, workflow, or command |
| assets/js/ext-mgr.js | 1634 | reloadPageSoon | General helper function |
| assets/js/ext-mgr.js | 1640 | runSystemResources | Runs an operation, workflow, or command |
| assets/js/ext-mgr.js | 1660 | runCreateBackup | Runs an operation, workflow, or command |
| assets/js/ext-mgr.js | 1690 | runClearCache | Runs an operation, workflow, or command |
| assets/js/ext-mgr.js | 1725 | runClearExtensionsFolder | Runs an operation, workflow, or command |
| assets/js/ext-mgr.js | 1771 | setManagerVisibility | Sets state, preference, or mode |
| assets/js/ext-mgr.js | 1804 | runCheckUpdate | Runs an operation, workflow, or command |
| assets/js/ext-mgr.js | 1835 | runUpdate | Runs an operation, workflow, or command |
| assets/js/ext-mgr.js | 1860 | runRepair | Runs an operation, workflow, or command |
| assets/js/ext-mgr.js | 1875 | runRegistrySync | Runs an operation, workflow, or command |
| ext-mgr-api.php | 23 | defaultMeta | General helper function |
| ext-mgr-api.php | 55 | defaultReleasePolicy | General helper function |
| ext-mgr-api.php | 95 | isSafeManagedPath | Validation/predicate helper |
| ext-mgr-api.php | 116 | normalizeReleasePolicy | Normalizes incoming values into canonical form |
| ext-mgr-api.php | 202 | normalizeVersion | Normalizes incoming values into canonical form |
| ext-mgr-api.php | 214 | safeVersionCompare | General helper function |
| ext-mgr-api.php | 224 | safeHasUpdate | General helper function |
| ext-mgr-api.php | 229 | readJsonFile | Reads state/data from file, process, or runtime |
| ext-mgr-api.php | 245 | readTextFile | Reads state/data from file, process, or runtime |
| ext-mgr-api.php | 257 | readGuidanceDocs | Reads state/data from file, process, or runtime |
| ext-mgr-api.php | 283 | writeJsonFile | Writes or persists data/content |
| ext-mgr-api.php | 304 | canWriteJsonPath | Validation/predicate helper |
| ext-mgr-api.php | 312 | formatWriteFailure | General helper function |
| ext-mgr-api.php | 325 | readSystemTotalMemMiB | Reads state/data from file, process, or runtime |
| ext-mgr-api.php | 349 | buildRuntimeMemoryHealth | Builds payload, response, or computed structure |
| ext-mgr-api.php | 365 | readExtMgrServiceHealth | Reads state/data from file, process, or runtime |
| ext-mgr-api.php | 403 | readExtMgrWatchdogHealth | Reads state/data from file, process, or runtime |
| ext-mgr-api.php | 441 | logTypes | Logging/diagnostic helper |
| ext-mgr-api.php | 446 | ensureExtMgrLogLayout | Ensures required precondition, path, or layout |
| ext-mgr-api.php | 462 | ensureExtensionLogLayout | Ensures required precondition, path, or layout |
| ext-mgr-api.php | 492 | appendLogLine | Appends data to log or output stream |
| ext-mgr-api.php | 505 | appendExtMgrLog | Appends data to log or output stream |
| ext-mgr-api.php | 518 | appendExtensionLog | Appends data to log or output stream |
| ext-mgr-api.php | 542 | buildLogRow | Builds payload, response, or computed structure |
| ext-mgr-api.php | 559 | buildVirtualLogRow | Builds payload, response, or computed structure |
| ext-mgr-api.php | 572 | extMgrSystemLogCandidates | General helper function |
| ext-mgr-api.php | 583 | buildCombinedLogContent | Builds payload, response, or computed structure |
| ext-mgr-api.php | 631 | availableLogsForTarget | General helper function |
| ext-mgr-api.php | 669 | resolveLogPathForRead | Resolves a path, target, or candidate |
| ext-mgr-api.php | 697 | tailFileContent | General helper function |
| ext-mgr-api.php | 721 | readLogLines | Reads state/data from file, process, or runtime |
| ext-mgr-api.php | 745 | parseLogLineEvent | Parses text/log/config input |
| ext-mgr-api.php | 766 | normalizeErrorSignature | Normalizes incoming values into canonical form |
| ext-mgr-api.php | 778 | lineLooksError | General helper function |
| ext-mgr-api.php | 789 | lineLooksRestartEvent | General helper function |
| ext-mgr-api.php | 799 | summarizeLogsForTarget | General helper function |
| ext-mgr-api.php | 885 | discoverKnownExtensionIds | General helper function |
| ext-mgr-api.php | 915 | buildLogAnalysisPayload | Builds payload, response, or computed structure |
| ext-mgr-api.php | 995 | ensureDirectory | Ensures required precondition, path, or layout |
| ext-mgr-api.php | 1006 | removePathRecursiveWithStats | Removes files, links, or injected UI |
| ext-mgr-api.php | 1040 | clearDirectoryContents | General helper function |
| ext-mgr-api.php | 1067 | computeDirectorySizeBytes | General helper function |
| ext-mgr-api.php | 1091 | computeDirectoryEntryCount | General helper function |
| ext-mgr-api.php | 1108 | copyPathRecursive | General helper function |
| ext-mgr-api.php | 1150 | createExtMgrBackupSnapshot | Creates filesystem resources or snapshots |
| ext-mgr-api.php | 1195 | readCpuUsageSamplePct | Reads state/data from file, process, or runtime |
| ext-mgr-api.php | 1239 | readMemoryOverview | Reads state/data from file, process, or runtime |
| ext-mgr-api.php | 1273 | diskUsageForPath | General helper function |
| ext-mgr-api.php | 1307 | readProcessRssMiBFromProc | Reads state/data from file, process, or runtime |
| ext-mgr-api.php | 1323 | estimateExtensionRuntimeMemory | General helper function |
| ext-mgr-api.php | 1421 | buildExtensionsStorageSummary | Builds payload, response, or computed structure |
| ext-mgr-api.php | 1444 | readBackupSnapshotInfo | Reads state/data from file, process, or runtime |
| ext-mgr-api.php | 1482 | buildMaintenanceStatus | Builds payload, response, or computed structure |
| ext-mgr-api.php | 1497 | buildSystemResourceSnapshot | Builds payload, response, or computed structure |
| ext-mgr-api.php | 1529 | readMeta | Reads state/data from file, process, or runtime |
| ext-mgr-api.php | 1538 | readVersionValue | Reads state/data from file, process, or runtime |
| ext-mgr-api.php | 1551 | writeVersionValue | Writes or persists data/content |
| ext-mgr-api.php | 1559 | writeTextFileAtomic | Writes or persists data/content |
| ext-mgr-api.php | 1568 | sanitizeExtensionId | General helper function |
| ext-mgr-api.php | 1577 | isPlaceholderExtensionId | Validation/predicate helper |
| ext-mgr-api.php | 1595 | generateUuidV4 | General helper function |
| ext-mgr-api.php | 1614 | extensionIdExists | General helper function |
| ext-mgr-api.php | 1639 | generateManagedExtensionId | General helper function |
| ext-mgr-api.php | 1651 | updateImportedManifestWithManagedId | General helper function |
| ext-mgr-api.php | 1700 | buildTemplatePackageFiles | Builds payload, response, or computed structure |
| ext-mgr-api.php | 1988 | writeTemplateFilesToDirectory | Writes or persists data/content |
| ext-mgr-api.php | 2014 | writeTemplateZipViaCommand | Writes or persists data/content |
| ext-mgr-api.php | 2080 | writeTemplateZipArchive | Writes or persists data/content |
| ext-mgr-api.php | 2118 | isSafeArchiveEntryPath | Validation/predicate helper |
| ext-mgr-api.php | 2144 | listZipEntriesViaUnzip | General helper function |
| ext-mgr-api.php | 2181 | extractZipArchiveSafely | General helper function |
| ext-mgr-api.php | 2248 | removePathRecursive | Removes files, links, or injected UI |
| ext-mgr-api.php | 2273 | detectImportSourceDir | Detects runtime/environment/property values |
| ext-mgr-api.php | 2301 | runImportWizard | Runs an operation, workflow, or command |
| ext-mgr-api.php | 2336 | isPhpFunctionEnabled | Validation/predicate helper |
| ext-mgr-api.php | 2349 | httpGetViaWget | Fetches local/remote content |
| ext-mgr-api.php | 2404 | httpGet | Fetches local/remote content |
| ext-mgr-api.php | 2470 | githubApiUrl | General helper function |
| ext-mgr-api.php | 2479 | githubRawFileUrl | General helper function |
| ext-mgr-api.php | 2490 | normalizeCustomBaseUrl | Normalizes incoming values into canonical form |
| ext-mgr-api.php | 2502 | buildCustomFileUrl | Builds payload, response, or computed structure |
| ext-mgr-api.php | 2513 | chooseGithubReleaseByChannel | General helper function |
| ext-mgr-api.php | 2551 | githubReleaseApiPathForChannel | General helper function |
| ext-mgr-api.php | 2559 | resolveRemoteBranchCandidate | Resolves a path, target, or candidate |
| ext-mgr-api.php | 2620 | resolveAvailableRemoteBranches | Resolves a path, target, or candidate |
| ext-mgr-api.php | 2669 | hasUpdateForCandidate | Validation/predicate helper |
| ext-mgr-api.php | 2691 | chooseGithubTagByChannel | General helper function |
| ext-mgr-api.php | 2739 | resolveRemoteTagCandidate | Resolves a path, target, or candidate |
| ext-mgr-api.php | 2794 | resolveCustomBaseCandidate | Resolves a path, target, or candidate |
| ext-mgr-api.php | 2839 | resolveRemoteReleaseCandidate | Resolves a path, target, or candidate |
| ext-mgr-api.php | 2926 | fetchManagedFilesFromRelease | Fetches local/remote content |
| ext-mgr-api.php | 2977 | normalizeDigestValue | Normalizes incoming values into canonical form |
| ext-mgr-api.php | 2986 | fetchIntegrityManifestFromRelease | Fetches local/remote content |
| ext-mgr-api.php | 3070 | verifyPayloadsAgainstManifest | General helper function |
| ext-mgr-api.php | 3125 | applyManagedFiles | Applies computed state to UI/system |
| ext-mgr-api.php | 3242 | updateReleasePolicyFromCandidate | General helper function |
| ext-mgr-api.php | 3256 | markMetaMaintenance | General helper function |
| ext-mgr-api.php | 3270 | readReleasePolicy | Reads state/data from file, process, or runtime |
| ext-mgr-api.php | 3276 | buildMeta | Builds payload, response, or computed structure |
| ext-mgr-api.php | 3307 | readRegistry | Reads state/data from file, process, or runtime |
| ext-mgr-api.php | 3316 | normalizeUiPathOrUrl | Normalizes incoming values into canonical form |
| ext-mgr-api.php | 3331 | normalizeIconClass | Normalizes incoming values into canonical form |
| ext-mgr-api.php | 3346 | normalizeScalarStringList | Normalizes incoming values into canonical form |
| ext-mgr-api.php | 3367 | buildImportPackageReview | Builds payload, response, or computed structure |
| ext-mgr-api.php | 3429 | readExtensionInstallMetadata | Reads state/data from file, process, or runtime |
| ext-mgr-api.php | 3478 | loadExtensionInfo | Loads state/content and prepares it for use |
| ext-mgr-api.php | 3522 | normalizeRegistry | Normalizes incoming values into canonical form |
| ext-mgr-api.php | 3605 | sanitizeRegistryForPersist | General helper function |
| ext-mgr-api.php | 3625 | applyImportedExtensionDefaults | Applies computed state to UI/system |
| ext-mgr-api.php | 3660 | responseData | General helper function |
| ext-mgr-api.php | 3725 | syncRegistryWithFilesystem | Synchronizes derived state with source-of-truth |
| ext-mgr-api.php | 3833 | isValidExtensionId | Validation/predicate helper |
| ext-mgr-api.php | 3838 | isSafeRelativeSubPath | Validation/predicate helper |
| ext-mgr-api.php | 3850 | resolveExtensionEntryFile | Resolves a path, target, or candidate |
| ext-mgr-api.php | 3900 | repairExtensionSymlink | General helper function |
| ext-mgr-api.php | 3943 | runPrivilegedSymlinkRepair | Runs an operation, workflow, or command |
| ext-mgr-api.php | 3997 | runShellCommands | Runs an operation, workflow, or command |
| ext-mgr-api.php | 4017 | removePathWithFallback | Removes files, links, or injected UI |
| ext-mgr-api.php | 4073 | getInstalledMetadataByExtensionId | General helper function |
| ext-mgr-api.php | 4095 | collectSharedPackageRefs | Collects related entities for processing |
| ext-mgr-api.php | 4111 | collectBundledDebPackageNames | Collects related entities for processing |
| ext-mgr-api.php | 4154 | removeExtensionRuntimeLinks | Removes files, links, or injected UI |
| ext-mgr-api.php | 4191 | clearExtensionAcl | General helper function |
| ext-mgr-api.php | 4252 | removeExtensionServiceUnits | Removes files, links, or injected UI |
| ext-mgr-api.php | 4309 | runExtensionUninstallScript | Runs an operation, workflow, or command |
| ext-mgr-api.php | 4343 | uninstallExtensionPackagesGracefully | General helper function |
| ext-mgr-api.php | 4409 | removeExtensionById | Removes files, links, or injected UI |
| ext-mgr-api.php | 4579 | clearExtensionsFolderGracefully | General helper function |
| install.sh | 146 | set_source_root | Sets state, preference, or mode |
| install.sh | 176 | detect_primary_user | Detects runtime/environment/property values |
| install.sh | 188 | sync_security_user_groups | Synchronizes derived state with source-of-truth |
| install.sh | 202 | ensure_extmgr_structure_permissions | Ensures required precondition, path, or layout |
| install.sh | 273 | require_file | General helper function |
| install.sh | 281 | print_usage | General helper function |
| install.sh | 299 | confirm_destructive_action | General helper function |
| install.sh | 318 | show_interactive_menu | General helper function |
| install.sh | 382 | run_restore_oobe | Runs an operation, workflow, or command |
| install.sh | 414 | ensure_install_sources | Ensures required precondition, path, or layout |
| install.sh | 428 | graceful_finalize_services | General helper function |
| install.sh | 458 | read_version_value | Reads state/data from file, process, or runtime |
| install.sh | 467 | version_compare | General helper function |
| install.sh | 486 | print_version_warning | General helper function |
| install.sh | 511 | fetch_from_main_branch | Fetches local/remote content |
| install.sh | 578 | cleanup_tmp_dir | General helper function |
| install.sh | 586 | cleanup_shell_bridge_includes | General helper function |
| install.sh | 616 | restore_latest_backup_file | General helper function |
| install.sh | 629 | restore_core_web_files_from_backups | General helper function |
| install.sh | 640 | run_uninstall | Runs an operation, workflow, or command |
| install.sh | 728 | patch_index_template_menu | General helper function |
| install.sh | 756 | patch_header_and_footer_menu | General helper function |
| install.sh | 1007 | is_safe_rel | Validation/predicate helper |
| scripts/ext-mgr-import-wizard.sh | 30 | log | Logging/diagnostic helper |
| scripts/ext-mgr-import-wizard.sh | 31 | err | Logging/diagnostic helper |
| scripts/ext-mgr-import-wizard.sh | 33 | manager_install_log | General helper function |
| scripts/ext-mgr-import-wizard.sh | 40 | extension_install_log | General helper function |
| scripts/ext-mgr-import-wizard.sh | 54 | ensure_log_roots | Ensures required precondition, path, or layout |
| scripts/ext-mgr-import-wizard.sh | 62 | require_root | General helper function |
| scripts/ext-mgr-import-wizard.sh | 69 | require_command | General helper function |
| scripts/ext-mgr-import-wizard.sh | 77 | group_exists | General helper function |
| scripts/ext-mgr-import-wizard.sh | 78 | user_exists | General helper function |
| scripts/ext-mgr-import-wizard.sh | 80 | add_user_to_group_safe | General helper function |
| scripts/ext-mgr-import-wizard.sh | 88 | detect_default_ssh_user | Detects runtime/environment/property values |
| scripts/ext-mgr-import-wizard.sh | 98 | setup_security_principal | Sets state, preference, or mode |
| scripts/ext-mgr-import-wizard.sh | 119 | grant_database_access | General helper function |
| scripts/ext-mgr-import-wizard.sh | 137 | ensure_extmgr_service | Ensures required precondition, path, or layout |
| scripts/ext-mgr-import-wizard.sh | 148 | run_install_helper_if_present | Runs an operation, workflow, or command |
| scripts/ext-mgr-import-wizard.sh | 168 | json_get | General helper function |
| scripts/ext-mgr-import-wizard.sh | 173 | ensure_registry | Ensures required precondition, path, or layout |
| scripts/ext-mgr-import-wizard.sh | 188 | update_registry_entry | General helper function |
| scripts/ext-mgr-import-wizard.sh | 246 | detect_extension_version | Detects runtime/environment/property values |
| scripts/ext-mgr-import-wizard.sh | 277 | set_extension_permissions | Sets state, preference, or mode |
| scripts/ext-mgr-import-wizard.sh | 299 | toggle_extension_state | General helper function |
| scripts/ext-mgr-import-wizard.sh | 336 | run_import | Runs an operation, workflow, or command |
| scripts/ext-mgr-import-wizard.sh | 417 | run_mode_toggle | Runs an operation, workflow, or command |
| scripts/ext-mgr-import-wizard.sh | 436 | parse_args | Parses text/log/config input |
| scripts/ext-mgr-import-wizard.sh | 480 | main | Script entrypoint/argument parser |
