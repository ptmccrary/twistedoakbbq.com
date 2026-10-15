# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

WordPress site for Twisted Oak Barbecue (Charlottesville, VA), rebuilt from an earlier Drupal site. WordPress core is vendored at the repo root; all custom code lives in three places:

- `wp-content/themes/twistedoak/`: classic (non-block) PHP theme, **buildless**. Plain CSS/JS in `assets/` is served as-is and cache-busted by file mtime (`inc/assets.php`). There is no npm or composer step.
- `wp-content/plugins/twisted-oak-core/`: content model (menu items, events, taxonomies, meta) plus the menu source abstraction (Local CPT or Square).
- `wp-content/plugins/twisted-oak-catering/`: catering booking system (availability rules, scheduling REST API, lead workflow, emails/ICS, `[twisted_oak_catering]` shortcode/block).

Everything else under `wp-content/` (twentytwenty* themes, akismet, hello.php) is stock. Requires PHP 8.2+, and all custom files use `declare(strict_types=1)`.

## Local environment

- Docker runs on **OrbStack** (`docker context` = `orbstack`). Never open Docker Desktop.
- `docker compose up -d --build` builds the `twistedoakbbq` container (php-fpm 8.5 + nginx under supervisord, Xdebug on port 9003). The repo root is mounted at `/var/www`.
- The container depends on shared external services on the `journey-network` network: `journey-db` (MySQL at `journey-db:3307`, DB `twistedoakbbq.com`, root and no password), `journey-mailpit` (catches outgoing mail), and a Traefik router serving `http://twistedoakbbq.com.test`.
- Quick check from the host: `curl -s -H 'Host: twistedoakbbq.com.test' http://127.0.0.1/`
- `WP_DEBUG`, `WP_DEBUG_LOG` and `WP_DEBUG_DISPLAY` are on. Errors go to `wp-content/debug.log`.
- `twisted-oak-bbq` (note the hyphens) is a **different** container: the old Drupal site, used only as the import source (`drush`).

### WP-CLI

Run WP-CLI inside the container:

```sh
docker exec twistedoakbbq wp --allow-root <command> 2>/dev/null
```

Add `2>/dev/null` because, under PHP 8.5 with Xdebug, WP-CLI prints a long deprecation stack trace on stderr for every command.

Custom commands:

- `wp twisted-oak-menu seed [--keep-existing]`: idempotent catering buffet menu (keyed by `_tobbq_seed_key`). Without the flag it **permanently deletes** menu items and categories it did not create.
- `wp twisted-oak-menu sync-square [--dry-run]`: Square to CPT sync. It does nothing until Square is configured and live requests are enabled.
- `wp twisted-oak-menu dump [--category=<slug>]`: prints the active menu source's DTOs as JSON. Use it to debug what templates receive.
- `wp twisted-oak-catering seed [--settings-only] [--remove]`: placeholder availability settings plus about 7 bookings over the next 6 weeks. It never sends email.
- `wp eval-file wp-content/plugins/twisted-oak-core/tools/import.php`: idempotent import of Drupal products (from `tools/data/drupal-products.json`) and taxonomies.

There are no tests, linters or build commands in this repo.

### Backups

`.backups/` holds DB dumps and theme tarballs taken before destructive changes, for example `pre-redesign-2026-10-06.sql`. Take a new one with `wp db export` before you run seeds that delete content.

## Architecture

### Content model (twisted-oak-core)

- CPTs: `tobbq_menu_item` (rewrite `/menu/`) and `tobbq_event` (`/events/`).
- Taxonomies: `tobbq_menu_category` (`/menu/category/`), `tobbq_dietary` (no rewrite) and `tobbq_event_type` (`/events/type/`). Taxonomies are registered before CPTs so that their rewrite rules take precedence.
- Meta keys use the `_tobbq_` prefix, for example `_tobbq_price`, `_tobbq_unit`, `_tobbq_featured`, `_tobbq_premium`, `_tobbq_sold_out` and `_tobbq_square_id`. The `tobbq_` prefix comes from the Drupal theme name.

### Menu source abstraction

`includes/menu/interface-source.php` defines the `TwistedOak\Core\Menu\Source` contract and documents the **item and category DTO shapes**. Templates only ever see these plain arrays, never `WP_Post` or Square objects.

- `TwistedOak\Core\Menu\source()` picks `LocalSource` (CPT-backed, the default) or `SquareSource`. The choice comes from the `twistedoak_square_settings` option and can be overridden with the `twistedoak_menu_source` filter.
- `SquareSource` is a stub. It makes live HTTP calls only when the `twistedoak_square_live_requests` filter returns true. Otherwise, and whenever a request fails, it falls back to `LocalSource`.
- The intended production path is `SquareSync`, which writes Square-owned fields into the CPT while LocalSource keeps rendering. Field ownership is documented in the header of `class-square-sync.php`.
- `groups()` and `featured()` are the theme's entry points. The theme wraps them in `twistedoak_menu_groups()` and `twistedoak_menu_featured()` (`inc/menu.php`) behind `function_exists`, so it degrades when the plugin is inactive. Keep that guard pattern for any new theme-to-plugin calls.

### Catering (twisted-oak-catering)

- Leads are stored as the `tobbq_catering_lead` CPT. Settings live in the `TWISTEDOAK_CATERING_OPTION` and `TWISTEDOAK_CATERING_SCHEDULE_OPTION` options.
- REST namespace `twisted-oak/v1` has these routes: `/availability`, `/bookings`, `/catering/config` and `/catering`.
- No-JS submission goes to `admin-post.php?action=twistedoak_catering`. The handler validates, stores the lead, sends the emails, and redirects back with `?catering=sent|error` plus a flash token that holds the submitted values. ICS files are served from `action=twistedoak_catering_ics`.
- **The live catering page does not use the plugin's form or wizard.** `themes/twistedoak/inc/catering-form.php` renders the design's own quote form, which posts to the plugin's admin-post endpoint. `inc/assets.php` dequeues the plugin's `tobbq-catering` CSS/JS on that page. Changes to the form markup go in the theme, and validation and storage go in the plugin.
- The site timezone must be a named zone (`America/New_York`), because the scheduling code depends on it.

### Theme

- `functions.php` only `require`s the files in `inc/`, one concern per file: setup, assets, content, customizer, components, navigation, menu, events and catering-form.
- `inc/components.php` contains PHP ports of the design-system components (`window.TwistedOak` in the design). They return strings with the **same markup and class names** as the design, so `assets/css/components.css` styles them unchanged. Reuse these functions instead of hand-writing component markup. Icons are inline Lucide paths via `twistedoak_icon()`.
- CSS layers load in order, each depending on the previous one: `tokens.css` (design tokens), then `components.css` (design-system components), then `theme.css` (page layout). `editor.css` is for the block editor.
- Fonts are self-hosted in `assets/fonts/` (Zilla Slab and Libre Franklin). The two above-the-fold faces are preloaded.
- Block library and global styles CSS are dequeued on the front page and the CPT archives.

## Git

The folder is not currently a git repository. The root `.gitignore` is a whitelist: it ignores everything at the root except `wp-content/` and a few named files (`.gitignore`, `CLAUDE.md`, `README.md`, `CHANGELOG.md`, `composer.json` and similar). Under `wp-content/` it tracks only `themes/twistedoak/` and `plugins/twisted-oak-*/`. Root directories such as `docker/` and `docs/`, and `docker-compose.yml`, are **not** whitelisted. Add them before you initialize a repo if they should be tracked.
