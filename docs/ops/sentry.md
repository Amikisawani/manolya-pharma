# Sentry (Phase 4)

Set these when Sentry DSN is available. Package `sentry/sentry-laravel` can be added then.

```
SENTRY_LARAVEL_DSN=
SENTRY_TRACES_SAMPLE_RATE=0.1
```

Frontend: initialize `@sentry/vue` in `resources/js/app.ts` when DSN is present.
