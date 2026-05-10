# WP Starter Kit

A modular WordPress developer toolkit with 8 plugins that can run as a single suite or individually.

## 📦 Downloads — What's What

| File | What is it? | Who is it for? |
|---|---|---|
| `wp-starter-kit-source.zip` | **Full source code** (monorepo) | Developers — push to GitHub, modify code, run `build.sh` |
| `wp-starter-kit-1.1.0.zip` | **Installable suite plugin** | End users — upload to WordPress, enable modules from dashboard |
| `wpsk-*.zip` | **Individual standalone plugins** | End users who only need 1-2 features |

### Which one do I install in WordPress?

**For all features:** Install `wp-starter-kit-1.1.0.zip` via Plugins → Add New → Upload Plugin.

**For one feature only:** Install just the `wpsk-*.zip` you need (e.g. `wpsk-turnstile-1.1.0.zip`).

**Do NOT install `wp-starter-kit-source.zip`** in WordPress — that's the development monorepo with build scripts, standalone wrappers, and source files. It's for GitHub, not for WordPress.

## 🔧 Modules

| Module | Description |
|---|---|
| Cloudflare Turnstile | CAPTCHA protection for login, registration, comments, WooCommerce |
| Custom Login URL | Hide wp-login.php behind a secret URL |
| Media Organizer | Folder-based media library with drag-and-drop |
| Media Replace | Replace media files keeping the same URL |
| Brevo Mailer | Route emails through Brevo (Sendinblue) API |
| Accessibility Toolbar | Frontend a11y widget: font zoom, contrast, greyscale, keyboard focus |
| Security Hardening | HTTP security headers, email obfuscation, author enumeration blocking |
| Performance Optimizer | Remove emoji/embed scripts, throttle heartbeat, clean wp_head |

## 🔀 Suite + Standalone Coexistence

- If the suite is active, standalone plugins auto-detect it and show a notice to deactivate
- Settings are shared (same option keys), so switching from standalone to suite preserves all configuration
- Multiple standalone plugins can run simultaneously without conflicts

## 🏗️ For Developers

```bash
# Clone the repo
git clone https://github.com/YOUR_USERNAME/wp-starter-kit.git
cd wp-starter-kit

# Build everything (suite + all 8 standalones)
chmod +x build.sh
./build.sh

# Build just the suite
./build.sh suite

# Build one standalone
./build.sh turnstile
```

## Requirements

- WordPress 5.9+
- PHP 7.4+
