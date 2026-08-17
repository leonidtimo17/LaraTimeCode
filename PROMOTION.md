# LaraTimeCode launch kit

This file contains ready-to-use copy and a release checklist. Update version
numbers and links immediately before publishing.

## Repository metadata

**Description**

Turn failed Laravel requests into encrypted, replayable snapshots and Pest
regression tests.

**Topics**

`laravel`, `php`, `debugging`, `testing`, `pest`, `error-tracking`,
`observability`, `developer-tools`, `open-source`

## Short launch post — English

I built LaraTimeCode, an open-source Laravel package that captures a sanitized,
encrypted snapshot when an HTTP request fails. You can inspect it locally, replay
the request, and generate a Pest regression test from it.

The first alpha supports Laravel 12–13 and is deliberately opt-in. I would love
feedback on the capture format, privacy defaults, and the next replay adapters.

GitHub: https://github.com/leonidtimo17/LaraTimeCode

## Short launch post — Russian

Сделал LaraTimeCode — open-source пакет для Laravel. При падении HTTP-запроса он
сохраняет очищенный от секретов и зашифрованный снимок контекста. Затем запрос
можно изучить, повторить локально и превратить в заготовку Pest-теста.

Первая alpha-версия поддерживает Laravel 12–13 и включается только явно. Буду рад
обратной связи по формату снимков, безопасным настройкам и следующим адаптерам
воспроизведения.

GitHub: https://github.com/leonidtimo17/LaraTimeCode

## Longer announcement

Production logs often describe a failure without preserving enough safe context to
reproduce it. LaraTimeCode explores a small workflow for that gap:

1. A Laravel HTTP request throws an exception.
2. Sensitive fields are recursively redacted.
3. The remaining context is encrypted into a local `.repro` snapshot.
4. An Artisan command replays the request in the developer environment.
5. Another command creates a Pest regression-test starting point.

The alpha intentionally does not copy production database rows. That limitation is
important: deterministic state adapters should be explicit, reviewable, and
opt-in.

Try it, inspect the defaults, and share the hardest-to-reproduce Laravel failure
you would want this workflow to handle:
https://github.com/leonidtimo17/LaraTimeCode

## Suggested article outline

1. Why stack traces are not always reproducible
2. The `.repro` snapshot lifecycle
3. Redaction, encryption, retention, and threat boundaries
4. Replaying a failure locally
5. Generating and completing a Pest regression test
6. Why database state is not captured yet
7. Roadmap and request for early adopters

## Release checklist

- [ ] Push the `main` branch and make the repository public
- [ ] Enable Issues, Discussions, and private vulnerability reporting
- [ ] Add repository description, topics, and `art/social-preview.png`
- [ ] Confirm all GitHub Actions jobs pass
- [ ] Create signed tag `v0.1.0-alpha.1`
- [ ] Publish the GitHub prerelease with generated release notes
- [ ] Submit `https://github.com/leonidtimo17/LaraTimeCode` to Packagist
- [ ] Verify `composer require laratimecode/laratimecode`
- [ ] Publish the English and Russian launch posts
- [ ] Submit the package to Laravel News Links
- [ ] Ask early users to open Discussions rather than sending snapshots

## First-month signals

- successful clean installs across supported Laravel versions;
- actionable issues from real applications;
- snapshot capture failures or privacy-default concerns;
- replay-to-test conversions that produce a useful regression test;
- stars and downloads as secondary, not primary, signals.
