# Contributing

Thanks for helping improve LaraTimeCode.

## Before opening an issue

- Search existing issues and discussions.
- Use the bug report form for reproducible defects.
- Never attach a real `.repro` snapshot unless you have inspected and sanitized it.
- Use GitHub's private vulnerability reporting flow for security issues.

## Local setup

```bash
git clone https://github.com/leonidtimo17/LaraTimeCode.git
cd LaraTimeCode
composer install
composer check
```

## Pull requests

1. Keep changes focused and explain the user-facing problem.
2. Add or update tests for behavior changes.
3. Run `composer check`.
4. Update `CHANGELOG.md` under `Unreleased` when behavior changes.
5. Avoid breaking the supported PHP and Laravel versions.

By participating, you agree to follow the [Code of Conduct](CODE_OF_CONDUCT.md).
