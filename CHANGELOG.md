# Changelog

All notable changes to `mod_profileslim` are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.10.1] - 2026-09-30

### Fixed
- **Version bump so Extensions → Update detects the release**: post-v1.10.0 fixes were rebuilt under the same version number, which Joomla's updater silently ignores (it only offers strictly newer versions). v1.10.1 carries everything since the original v1.10.0 build: redundant CSS preload removal, avatar width/height to prevent layout shift, manifest Joomla target corrected to 3.1, CB include `@` suppression removed.
- **Version-skew guard kept in sync**: `SccCbMenuResolver::VERSION` and the entry-point expectation bumped to `1.10.1` together; CI now asserts manifest, resolver, and guard versions always match.

## [1.10.0] - 2026-09-07

Fresh start under the `mod_profileslim` element. The module is now explicitly a native
Joomla extension with optional Community Builder awareness, prior `mod_cbprofileslim`
releases were removed from the repository, and only this version is distributed going
forward.

### Changed
- **Element renamed `mod_cbprofileslim` → `mod_profileslim`** (repository → `mod-profileslim`). The "CB" prefix no longer implies Community Builder is the primary function: the module is now explicitly a native Joomla extension that optionally uses CB for profile-link resolution only. Renamed files (`mod_profileslim.php`, `mod_profileslim.xml`, `en-GB.mod_profileslim.ini`/`.sys.ini`), language keys (`MOD_PROFILESLIM_*`) and the log category (`mod_profileslim`). Manifest, update feed, language descriptions and docs repositioned Joomla-first, with Community Builder referenced only as an optional post-native compatibility layer.
- **Version-skew expected version** bumped to `1.10.0` (`SccCbMenuResolver::VERSION` in `cbmenu.php`).
- **Update feed simplified**: a single `update.xml` serves the `mod_profileslim` element going forward. All prior `mod_cbprofileslim` versions, tags, releases and transition machinery were removed from the repository.

### Fixed
- **Stale PHPUnit suites aligned with current behavior**: `SanitizerTest` class references updated to `ModProfileSlimHelper` and base-path expectations restored to the `/images/` default; `ModuleEntryPointTest` references the renamed element/class.