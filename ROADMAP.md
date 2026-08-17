# Roadmap

[Русская версия](ROADMAP.ru.md)

The roadmap is directional. Priorities may change based on real Laravel
applications and community feedback.

## 0.1 — Safe capture foundation

- [x] opt-in failed HTTP request capture
- [x] recursive redaction and encrypted local storage
- [x] bounded retention and atomic writes
- [x] Artisan inspect, replay, generate-test, and delete commands
- [x] Laravel 12–13 compatibility matrix

## 0.2 — More deterministic HTTP replay

- [ ] fake captured outbound Laravel HTTP client calls
- [ ] richer mismatch reporting between captured and replayed responses
- [ ] pluggable snapshot storage
- [ ] user-defined metadata collectors

## 0.3 — Application state adapters

- [ ] explicit, opt-in database fixture adapters
- [ ] feature flag and cache-state adapters
- [ ] queue job and event capture
- [ ] snapshot schema migrations and compatibility tooling

## Before 1.0

- [ ] external security review
- [ ] concurrency and performance benchmarks
- [ ] documented snapshot compatibility policy
- [ ] production feedback from multiple real-world applications

Have a use case that should change the order? Start a GitHub Discussion.
