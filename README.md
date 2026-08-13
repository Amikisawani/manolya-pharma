# Manolya Pharma

Gestion d’officine claire et traçable. Stack Laravel 13 + Inertia Vue TypeScript + PostgreSQL.

## Prérequis

- PHP 8.4+
- Composer 2
- Node.js 22+
- PostgreSQL 16
- Redis (recommandé en prod ; optionnel en local)

## Installation rapide (local)

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configurez `.env` (exemple PostgreSQL) :

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=manolya
DB_USERNAME=postgres
DB_PASSWORD=
QUEUE_CONNECTION=sync
SCOUT_DRIVER=collection
FX_USD_CDF=2350
FX_EUR_CDF=2702.5
```

Puis :

```bash
php artisan migrate --seed
npm install
npm run dev
php artisan serve
```

- App : http://127.0.0.1:8000 (redirige vers `/login`)
- Compte démo : `owner@manolya.test` / `password`

## Docker / Coolify

Voir [`docs/ops/deploy.md`](docs/ops/deploy.md). Repo : https://github.com/Amikisawani/manolya-pharma (`main`).

```bash
docker compose up -d --build
docker compose exec app php artisan migrate --seed
```

## Modules livrés (pilote)

- Dashboard, POS + sessions de caisse, retours
- Catalogue (CSV/Excel), stock FEFO, achats, inventaires
- Finance, PDF journalier/mensuel, documents OCR/FTS
- Audit, alertes, SMS/MoMo adapters, Sentry (DSN optionnel)
- Ticket imprimable (cadre facture + identité caissier)

État détaillé : [`docs/ETAT-AVANCEMENT.md`](docs/ETAT-AVANCEMENT.md)

## Tests

```bash
php artisan test
```
