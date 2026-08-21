# Changelog

[Русская версия](CHANGELOG.ru.md)

## Unreleased

### Fixed

- Fixed failed requests never being captured while the application exception handler was active. `Illuminate\Routing\Pipeline` renders throwables into responses before they reach global middleware, so the middleware `catch` block was unreachable outside of `withoutExceptionHandling()`.
- Fixed `laratimecode.replay.restore_auth_user` being ignored: `timecode:replay` always passed an explicit `false` when `--auth` was absent.
- Fixed truncated strings being reported with a literal `\n` instead of a newline.
- Fixed multibyte strings being cut mid-character on truncation, which produced invalid UTF-8 and made the whole snapshot fail to encode.
- Fixed `timecode:list` failing outright when a single snapshot was unreadable, for example after an `APP_KEY` rotation. Unreadable snapshots are now skipped and reported to the log.
- Fixed snapshots being lost when request data contained invalid UTF-8 bytes.
- Fixed the service provider throwing when no HTTP kernel is bound.

### Changed

- `ignore_exceptions` now also lists `AuthorizationException`, `RecordsNotFoundException`, and `TokenMismatchException`. Capture sees the original throwable rather than the one Laravel maps it to, so 403, 404, and 419 responses were being recorded despite the intent of the shipped defaults.
- Added `ext-mbstring`, `illuminate/auth`, `illuminate/session`, `psr/log`, `symfony/http-foundation`, and `symfony/http-kernel` as explicit dependencies.

## 0.1.0-alpha.1 - 2026-08-17

- Added PHP 8.2–8.5 and Laravel 12–13 support.
- Added encrypted filesystem snapshots with atomic writes and retention limits.
- Added failed request capture with recursive secret redaction.
- Added SQL and outbound Laravel HTTP client context.
- Added `timecode:list`, `timecode:show`, `timecode:replay`, and `timecode:delete` commands.
- Added Pest regression test generation via `timecode:make-test`.
