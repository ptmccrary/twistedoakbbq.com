# Before opening a PR

- [ ] Run `docker exec twistedoakbbq composer check` and make sure it passes. It runs `php -l`, PHPStan (level 6) and the unit tests in about 2 seconds.
- [ ] Add or update unit tests in `tests/unit/` for any logic you changed (schedule parsing, availability, validation and similar).
- [ ] Don't add new PHPStan errors to `phpstan-baseline.neon`. Fix them instead. Only regenerate the baseline when you are removing entries.
- [ ] Check that `wp-content/debug.log` has no new notices, warnings or deprecations from the pages you touched.
- [ ] Load the pages you changed at `http://twistedoakbbq.com.test`.
- [ ] If you touched catering: submit the quote form on `/catering/` with JavaScript disabled, then check the redirect (`?catering=sent`) and the emails in Mailpit.
- [ ] If you touched the menu: run `docker exec twistedoakbbq wp --allow-root twisted-oak-menu dump 2>/dev/null` and check that the DTOs still match the shape in `includes/menu/interface-source.php`.
- [ ] If you changed theme CSS or JS, there's no build step to run. Hard-refresh the page to confirm the mtime cache-busting picked up the change.
- [ ] Before running any seed or import that deletes content, take a DB backup with `wp db export .backups/<name>.sql`.
