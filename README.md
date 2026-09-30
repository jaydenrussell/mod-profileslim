# Joomla Profile Slim Display (`mod_profileslim`)

Standalone **Joomla 3 module** that displays the **logged-in user's display name and
avatar** in the site's top header navbar.

It runs entirely on **Joomla's built-in profile system** — the display name, the avatar,
and the profile link are all resolved natively. No third-party profile extension is
required for anything the module does.

**Community Builder is optional.** When CB is installed, the module is *aware* of it and
uses only CB's canonical "View Profile" menu item for the profile link (see
"[Community Builder](#community-builder-optional)" below). Community Builder is a
compatibility layer, never a requirement.

## Why this exists

Joomla's own login module cannot show the name and avatar inline in a header bar. This
module is standalone: it works on every page (articles, calendar, anywhere) without
depending on another profile extension being loaded on the page.

## Install

1. **Extensions → Manage → Install**, upload `mod_profileslim.zip`.
2. Place the module in your header navbar position (e.g. `topbar-2` on Astroid templates,
   such as `tpl_jdseattle`).
3. Clear Joomla cache.

## How the avatar works

- **Joomla profile system (default).** The avatar comes from the `avatar` profile field
  in Joomla's `#__user_profiles` table; several common keys are recognised
  (`avatar`, `profile.avatar`, `user.avatar`, `avatar_url`, `profile_picture`).
- **DB fallback is opt-in.** A module parameter `avatar_db_fallback` (default **No**)
  adds a direct `#__user_profiles` query when the profile API returns nothing.
- The display name uses the user's Joomla `name`, falling back to `username`.
- The default profile link is Joomla's native profile view
  (`index.php?option=com_users&view=profile&id=X`); it can be overridden with the
  `profile_url` module parameter (absolute http(s) or safe site-relative path).
- **Avatar storage requirement (hard constraint).** `sanitizeAvatarUrl()` only accepts a
  **relative path** under your configured avatar base directory — a flat filename
  (`383_abc.jpg`) **or** a subfolder path (`sub/dir/x.png`) — and, when one is returned,
  a same-site absolute URL (the foreign host is stripped to a same-origin relative
  path). It **rejects** foreign/absolute hosts, `javascript:`/`data:` and any other
  scheme, protocol-relative (`//`), backslashes, `..` path traversal, and any unsafe
  character; rejected values render the avatar blank. Do **not** relax the validator —
  it is intentional hardening.

### Community Builder (optional)

Only the profile **link** is CB-aware. When CB's files are present on the site, the
module reuses the canonical "View Profile" menu item resolution (the same
`SccCbMenuResolver` shipped by the `cblogin-modern-blue` template) so the profile link
follows CB's routing — this also covers CB's interception of Joomla profile links.
Name and avatar continue to come from Joomla's profile system. If no accessible CB
"View Profile" menu item exists, the module falls back to the configured or native
Joomla profile link and logs a warning.

## Updates

The module registers a Joomla update server (`update.xml` on GitHub). After installing
once, **Extensions → Update** will offer newer versions, verified by the SHA256 checksum
in the feed.

## Version history

| Version | Notes |
|---------|-------|
| 1.10.1 | Patch release so the updater detects it; avatar layout-shift fix, manifest Joomla 3.1 target, resolver version sync |
| 1.10.0 | Fresh start under the `mod_profileslim` element: the module is explicitly a native Joomla extension with optional Community Builder awareness, and prior `mod_cbprofileslim` releases were removed from the repository alongside the rename |

## License

GNU General Public License v2 or later.