# Twisted Oak Barbecue

WordPress site for [Twisted Oak Barbecue](https://twistedoakbbq.com) in Charlottesville, VA. It replaces an earlier Drupal site.

The repo tracks only the custom code. WordPress core, `wp-config.php` and uploads are not committed:

| Path | What it is |
|---|---|
| `wp-content/themes/twistedoak/` | Classic PHP theme, **buildless**. Plain CSS and JS in `assets/`, cache-busted by file mtime. |
| `wp-content/plugins/twisted-oak-core/` | Content model (menu items, events, taxonomies, meta) and the menu source abstraction (local CPT or Square). |
| `wp-content/plugins/twisted-oak-catering/` | Catering quote and booking system: availability rules, REST API, lead workflow, emails and ICS. |
| `docker/`, `docker-compose.yml` | Local container (php-fpm 8.5, nginx, supervisord, Xdebug). |
| `tests/`, `phpunit.xml.dist`, `phpstan.neon.dist` | Unit tests and static analysis. |
| `docs/` | Architecture map (`onboarding.md`) and the pre-PR checklist (`testing.md`). |

## Requirements

- PHP 8.5+ (provided by the container). All custom files use `declare(strict_types=1)`.
- WordPress 7.1.x (currently 7.1.3).
- Docker via **OrbStack**.
- The shared services on the external `journey-network` Docker network:
  - `journey-db`: MySQL on port 3307 (user `root`, no password)
  - `journey-mailpit`: catches all outgoing mail
  - Traefik, which routes `http://twistedoakbbq.com.test` to the container

Runtime code has no Composer or npm dependencies. Composer is used only for dev tooling.

## Getting started

### Fresh clone

```sh
git clone git@github.com:ptmccrary/twistedoakbbq.com.git
cd twistedoakbbq.com
docker compose up -d --build

# Shorthand for the commands below
wp() { docker exec twistedoakbbq wp --allow-root "$@" 2>/dev/null; }

# WordPress core goes at the repo root, next to the tracked wp-content/
wp core download --version=7.1.3 --skip-content

wp config create --dbname=twistedoakbbq.com --dbuser=root --dbpass='' \
  --dbhost=journey-db:3307 --skip-check
wp config set WP_HOME http://twistedoakbbq.com.test
wp config set WP_SITEURL http://twistedoakbbq.com.test
wp config set WP_DEBUG true --raw
wp config set WP_DEBUG_LOG true --raw
wp config set WP_DEBUG_DISPLAY true --raw

wp db create

# Dev tooling (vendor/ is git-ignored)
docker exec twistedoakbbq composer install
```

Then load data in one of two ways.

**From a dump** (recommended, if someone can share one):

```sh
wp db import .backups/<dump>.sql
```

**From scratch:**

```sh
wp core install --url=http://twistedoakbbq.com.test --title="Twisted Oak Barbecue" \
  --admin_user=admin --admin_email=you@example.com --prompt=admin_password
wp theme activate twistedoak
wp plugin activate twisted-oak-core twisted-oak-catering
wp option update timezone_string America/New_York   # must be a named zone
wp rewrite structure '/%postname%/' --hard

wp eval-file wp-content/plugins/twisted-oak-core/tools/import.php   # Drupal products and taxonomies
wp twisted-oak-menu seed --keep-existing                           # catering buffet menu
wp twisted-oak-catering seed                                       # placeholder availability and bookings
```

Visit <http://twistedoakbbq.com.test>. To check from the host without DNS:

```sh
curl -s -H 'Host: twistedoakbbq.com.test' http://127.0.0.1/
```

### Day to day

- `docker compose up -d` starts the site. The repo root is mounted at `/var/www`, so edits show up immediately.
- PHP errors go to `wp-content/debug.log`.
- Outgoing email (catering confirmations and notifications) lands in Mailpit.
- Xdebug listens on port 9003. Map `/var/www` to the repo root in your IDE.

## WP-CLI

Run WP-CLI inside the container:

```sh
docker exec twistedoakbbq wp --allow-root <command> 2>/dev/null
```

Under PHP 8.5 the WP-CLI phar prints a deprecation notice on every command. `docker/php-cli.ini` sends it to stderr, so `2>/dev/null` keeps stdout clean. Drop the redirect when you want to see PHP warnings, and add `-e XDEBUG_MODE=off` to `docker exec` to skip the debugger.

Custom commands:

| Command | What it does |
|---|---|
| `wp twisted-oak-menu seed [--keep-existing]` | Idempotent catering buffet menu. **Without the flag it permanently deletes** menu items and categories it didn't create. |
| `wp twisted-oak-menu sync-square [--dry-run]` | Square to CPT sync. Does nothing until Square is configured and live requests are enabled. |
| `wp twisted-oak-menu dump [--category=<slug>]` | Prints the active menu source's DTOs as JSON. |
| `wp twisted-oak-catering seed [--settings-only] [--remove]` | Placeholder availability settings and about 7 bookings over the next 6 weeks. Never sends email. |
| `wp eval-file wp-content/plugins/twisted-oak-core/tools/import.php` | Idempotent import of the Drupal products and taxonomies. |

Take a backup before running anything that deletes content:

```sh
docker exec twistedoakbbq wp --allow-root db export .backups/pre-<change>-$(date +%F).sql
```

`.backups/` and `*.sql` are git-ignored.

## Tests and static analysis

```sh
docker exec twistedoakbbq composer check   # php -l + PHPStan (level 6) + unit tests
docker exec twistedoakbbq composer lint    # php -l + PHPStan only
docker exec twistedoakbbq composer test    # unit tests only
```

- Unit tests live in `tests/unit/` and use PHPUnit and Brain Monkey. They need no WordPress or database. "Now" is frozen at Thursday 2026-10-15, America/New_York.
- Don't add new entries to `phpstan-baseline.neon`. Fix the finding instead.
- Work through [`docs/testing.md`](docs/testing.md) before opening a PR.

## How it fits together

- **Menu.** Templates never touch `WP_Post` or Square objects. They get plain arrays (DTOs) from `TwistedOak\Core\Menu\source()`, whose shapes are documented in `includes/menu/interface-source.php`. The default source is the local `tobbq_menu_item` CPT. `SquareSource` is a stub that falls back to the local source, and live Square calls stay off until the `twistedoak_square_live_requests` filter returns true.
- **Catering.** The live `/catering/` page uses the **theme's** quote form (`inc/catering-form.php`), not the plugin's form or wizard. It posts to `admin-post.php?action=twistedoak_catering`, where the plugin validates, stores a `tobbq_catering_lead`, and sends the emails. The same pipeline is exposed over REST at `twisted-oak/v1` (`/availability`, `/bookings`, `/catering`, `/catering/config`). Change form markup in the theme, and validation or storage in the plugin.
- **Theme to plugin.** Every theme call into a plugin is wrapped in `function_exists`, so the site degrades instead of fataling when a plugin is off. Keep that pattern.
- **Design system.** `inc/components.php` ports the design-system components to PHP with identical markup and class names. CSS loads in layers: `tokens.css`, then `components.css`, then `theme.css`. Fonts (Zilla Slab and Libre Franklin) are self-hosted.

For a file-by-file map with a dependency diagram, see [`docs/onboarding.md`](docs/onboarding.md). For conventions and gotchas, see [`CLAUDE.md`](CLAUDE.md).

## Content model

| Type | Slug | URL |
|---|---|---|
| Menu item (CPT) | `tobbq_menu_item` | `/menu/` |
| Event (CPT) | `tobbq_event` | `/events/` |
| Catering lead (CPT, admin only) | `tobbq_catering_lead` | none |
| Menu category | `tobbq_menu_category` | `/menu/category/` |
| Event type | `tobbq_event_type` | `/events/type/` |
| Dietary tag | `tobbq_dietary` | none |

Custom meta uses the `_tobbq_` prefix (for example `_tobbq_price`, `_tobbq_featured`, `_tobbq_sold_out`, `_tobbq_square_id`).

## Git

`.gitignore` is a whitelist: everything is ignored unless listed. At the root it tracks the docs, Docker and tooling files. Under `wp-content/` it tracks only `themes/twistedoak/` and `plugins/twisted-oak-*/`. If you add a new top-level file or a new plugin outside that pattern, whitelist it in `.gitignore` or it won't be committed.

Work happens on branches and lands on `main` through pull requests.
