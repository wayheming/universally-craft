# Release Notes for Universally

## 0.2.0 - 2026-11-24

### Added

- Connect Universally from Settings → Plugins in the Craft control panel using a project API key and the site owner’s consent.
- Verify that the key matches the Craft site and store it encrypted in the Craft database.
- Revoke consent from the plugin settings to disable translation. Existing environment-based connections remain supported.

## 0.1.0 - 2026-11-23

### Added

- Translate public Twig pages on the server with a connected Universally project.
- Serve translated pages at language-prefixed URLs, such as `/fr/about`.
- Show the Universally language switcher on public pages.
- Require the site owner to authorize content transfer before connecting.
- Serve the source page during translation outages and prevent indexing that fallback at the translated URL.
