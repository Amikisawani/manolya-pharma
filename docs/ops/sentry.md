# Sentry (Laravel + Vue)

Monitoring des erreurs backend et frontend. **Sans DSN, Sentry est un no-op** (local / CI).

## Variables

```env
# Backend (PHP) — projet Sentry Laravel
SENTRY_LARAVEL_DSN=https://xxxx@o0.ingest.sentry.io/0
SENTRY_TRACES_SAMPLE_RATE=0.1

# Frontend (Vite / Vue) — DSN public navigateur (souvent le même projet ou un projet JS dédié)
VITE_SENTRY_DSN=https://xxxx@o0.ingest.sentry.io/0

# Optionnel
# SENTRY_ENVIRONMENT=production
# SENTRY_RELEASE=manolya@1.0.0
```

Sur Coolify / Forge : injecter ces variables, puis **rebuild** les assets (`npm run build` ou image Docker) pour que `VITE_SENTRY_DSN` soit embarqué.

## Ce qui est branché

| Couche | Comportement |
|--------|----------------|
| Laravel | `sentry/sentry-laravel` + `Integration::handles()` dans `bootstrap/app.php` |
| Contexte | `EnsureTenant` pose `user` + tag `tenant_id` sur le scope Sentry |
| Vue | `@sentry/vue` initialisé dans `resources/js/app.ts` si `VITE_SENTRY_DSN` est non vide |
| Inertia | props `sentry.environment` / `sentry.release` (pas de DSN secret partagé) |

## Vérification

1. Laisser les DSN vides → l’app tourne normalement, aucun envoi.
2. Renseigner les DSN en staging → provoquer une exception test (`php artisan sentry:test` si disponible, ou une route temporaire) et confirmer l’événement dans Sentry.
3. Frontend : erreur Vue non catchée → événement navigateur dans le projet JS.

## Docs liées

- Déploiement : [`deploy.md`](./deploy.md)
- État produit : [`../ETAT-AVANCEMENT.md`](../ETAT-AVANCEMENT.md)
