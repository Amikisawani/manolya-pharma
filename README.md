# Manolya Pharma

Plateforme de gestion pharmaceutique multi-tenant (Laravel 13 + Breeze Inertia Vue TypeScript) destinée aux officines en République du Congo.

## Prérequis

- PHP 8.3+
- Composer 2
- Node.js 20+
- PostgreSQL 16 (ou SQLite pour les tests)
- Redis (optionnel en local)
- Docker + Docker Compose (optionnel)

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
DB_USERNAME=manolya
DB_PASSWORD=secret
QUEUE_CONNECTION=database
SCOUT_DRIVER=collection
```

Puis :

```bash
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Compte démo :

- Email : `owner@manolya.test`
- Mot de passe : `password`

## Docker Compose

```bash
docker compose up -d --build
docker compose exec app php artisan migrate --seed
```

Services inclus :

| Service     | Port par défaut |
|-------------|-----------------|
| App         | 8080            |
| PostgreSQL  | 5432            |
| Redis       | 6379            |
| Meilisearch | 7700            |
| Mailpit UI  | 8025            |

## Modules

- Dashboard exécutif
- POS / ventes
- Catalogue produits
- Stock & lots (FEFO)
- Achats & réceptions
- Inventaires
- Finance & dépenses
- Documents (OCR stub)
- Audit & alertes
- 2FA (Google Authenticator)

## Planification

Les jobs sont déclarés dans `routes/console.php` :

- `ScanBatchExpiriesJob` — quotidien 01:00
- `DetectStockThresholdsJob` — horaire
- `GenerateDailyReportJob` — quotidien 06:00
- `GenerateMonthlyReportJob` — mensuel

Lancez le scheduler :

```bash
php artisan schedule:work
```

## Tests

```bash
php artisan test
```

## Frontend

```bash
npm run dev
```

Stack UI : Vue 3 + Inertia + Tailwind, labels en français, identité visuelle Manolya (teal).
