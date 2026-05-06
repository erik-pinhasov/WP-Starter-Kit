# WP Starter Kit

A modular WordPress developer toolkit. Eight focused features — use them all from one plugin, or install only the ones you need.

## Modules

| Module | Standalone Plugin | Description |
|--------|------------------|-------------|
| **Turnstile** | `wpsk-turnstile` | Cloudflare Turnstile CAPTCHA for WP & WooCommerce forms |
| **Login URL** | `wpsk-login-url` | Custom admin login URL (hide `wp-login.php`) |
| **Media Organizer** | `wpsk-media-organizer` | Folder-based media library with drag & drop |
| **Accessibility** | `wpsk-accessibility` | Frontend accessibility toolbar (IS 5568 / WCAG) |
| **Brevo Mailer** | `wpsk-brevo-mailer` | Route all `wp_mail()` through Brevo's transactional API |
| **Media Replace** | `wpsk-media-replace` | Swap media files in-place (keep URL & ID) |
| **Security Headers** | `wpsk-security-headers` | CSP, Permissions-Policy, and WordPress hardening |
| **Performance** | `wpsk-performance` | Remove emoji, XML-RPC, oEmbed, Google Fonts, and more |

## Installation

### Option A: Suite (all modules)

1. Download `wp-starter-kit-x.x.x.zip` from [Releases](../../releases).
2. Upload via **Plugins → Add New → Upload Plugin**.
3. Activate and go to **WP Starter Kit** in the admin sidebar.
4. Enable the modules you want and configure each one.

### Option B: Standalone (single module)

1. Download the specific plugin zip (e.g. `wpsk-turnstile-x.x.x.zip`) from [Releases](../../releases).
2. Upload via **Plugins → Add New → Upload Plugin**.
3. Activate and configure under **Settings → [Module Name]**.

> **Conflict guard:** If you activate both the suite and a standalone plugin for the same module, the standalone plugin automatically defers to the suite and shows an admin notice. No double-loading, no conflicts.

## Languages

English (default) and Hebrew (עברית) are included. The plugin follows your WordPress site language setting (**Settings → General → Site Language**).

## Requirements

- WordPress 5.9+
- PHP 7.4+
- WooCommerce (optional — required only for WooCommerce-specific form protection)

## Building from Source

```bash
git clone https://github.com/erik-pinhasov/wp-starter-kit.git
cd wp-starter-kit
chmod +x build.sh
./build.sh          # Build suite + all standalone plugins
./build.sh suite    # Build suite only
./build.sh turnstile # Build a single standalone plugin
```

Output zips go to `dist/`. Requires `msgfmt` (from gettext) for translation compilation.

## Project Structure

```
wp-starter-kit/
├── wp-starter-kit.php          ← Suite plugin (loads core + all modules)
├── core/                       ← Shared framework
│   ├── class-wpsk-core.php     ← Singleton bootstrap + module registry
│   ├── class-wpsk-module.php   ← Abstract base class for modules
│   ├── class-wpsk-i18n.php     ← Translation loader
│   └── class-wpsk-settings.php ← (reserved for future shared settings UI)
├── modules/                    ← Feature modules
│   ├── turnstile/
│   │   ├── class-wpsk-turnstile.php
│   │   └── languages/
│   ├── login-url/
│   ├── ...
├── standalone/                 ← Standalone plugin wrappers
│   ├── wpsk-turnstile/
│   │   └── wpsk-turnstile.php  ← Loads core + turnstile only
│   ├── ...
├── languages/                  ← Suite-level translations
├── build.sh                    ← Packaging script
└── .gitignore
```

## Contributing

Each module is self-contained. To add a new module:

1. Create `modules/your-module/class-wpsk-your-module.php` extending `WPSK_Module`.
2. Implement `get_id()`, `get_name()`, `get_description()`, `get_settings_fields()`, and `init()`.
3. Add translation files in `modules/your-module/languages/`.
4. Register it in `wp-starter-kit.php`.
5. Create a standalone wrapper in `standalone/wpsk-your-module/`.
6. Add the module slug to `build.sh`.

## License

GPL-2.0-or-later — see [LICENSE](LICENSE) for details.
