# Changelog

## 5.0.0 (2026-08-23)

### Fixed

- `fetchEmails()` now really orders emails newest first. `sortEmails()` used to throw away the result
  of `usort()`, and the comparison predicate compared the `Date` header as text, so `"Fri, 2 Jun"`
  sorted before `"Thu, 21 May"`. The header is now parsed as an RFC 2822 date and emails are compared
  as instants in time.
- The `Date` header is accepted both as a plain string and as a list of values, which is the shape the
  MailHog API returns. An email whose `Date` header is missing, empty or uninterpretable no longer
  makes the order undefined: it is placed after every email with a usable send time, keeping the order
  the server returned.

### BREAKING

- **Order of emails changed.** `$currentInbox` and `$unreadInbox` are now ordered by send time,
  descending. Tests that unknowingly relied on the order the MailHog API returned may now get a
  different email from `openNextUnreadEmail()`. With a single email in the inbox — the vast majority
  of usages — nothing changes.
- **`sortEmails()` changed shape** from `($inbox): void` to `(array $inbox): array`. It is `protected`,
  so this only affects subclasses that override it for a custom order. Such an override has to return
  the sorted inbox:

  ```php
  // before
  protected function sortEmails($inbox): void
  {
      usort($inbox, [$this, 'sortEmailsByCreationDatePredicate']);
  }

  // after
  protected function sortEmails(array $inbox): array
  {
      usort($inbox, [$this, 'sortEmailsByCreationDatePredicate']);

      return $inbox;
  }
  ```

## 4.0.0 (2026-08-08)

### Added

- Support for PHP 8.5 and for `guzzlehttp/guzzle` 8. Keeping `^8.0` in the constraint is what lets
  consumers upgrade `guzzlehttp/psr7` to 3.x.

### Changed

- `ericmartel/codeception-email` is no longer a dependency. The upstream project is abandoned, so its
  traits `Codeception\Email\TestsEmails` and `Codeception\Email\EmailServiceProvider` are bundled in
  `src/Email/` (MIT, see `src/Email/LICENSE`) with their namespace and method signatures unchanged.
  `composer.json` declares `"replace": {"ericmartel/codeception-email": "^1.0"}`, so sibling modules
  that still require the package do not end up with a second, colliding copy of those classes.
  Nothing changes for consumers of `Codeception\Module\MailHog`.
- The `timeout` request option is cast to float, because Guzzle 8 validates option types and the value
  arrives from the suite config as a string.
- Development dependency `phpunit/phpunit` moved from `^9.5` to `^11 | ^12 | ^13`.

### BREAKING

- **Dropped support for PHP 8.1.** The package now requires PHP `^8.2`.
- **Dropped support for `guzzlehttp/guzzle` 6.1.** The constraint is now `^7.0 | ^8.0`.

---

Releases before 4.0.0 are not covered here; see the git history and the tags on
[Packagist](https://packagist.org/packages/zdenekgebauer/codeception-email-mailhog).