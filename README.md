# eWards PMS Basic

Project management system built on the following stack:

| Layer | Stack |
| --- | --- |
| Backend Framework | Laravel 13 |
| Frontend Framework | Vue.js 3 + Inertia.js |
| UI Library | Tailwind CSS 4 |
| Database | MySQL 8 |
| Build Tooling | Vite |

## Local setup

1. Copy `.env.example` to `.env`.
2. Create a MySQL 8 database named `ewards_pms`.
3. Update the MySQL credentials in `.env` if your local values differ from the example.
4. Install dependencies:

```bash
composer install
npm install
```

5. Generate the app key and run migrations:

```bash
php artisan key:generate
php artisan migrate --seed
```

6. Start the development servers:

```bash
composer run dev
```

## Testing

The test configuration is set to MySQL as well. Create a MySQL 8 test database named `ewards_pms_test`, or override the database environment variables before running:

```bash
php artisan test
```

## Deployment

The Docker runtime installs the MySQL PDO extension, and `render.yaml` is configured for a web service that expects MySQL 8 connection variables to be supplied through the deployment environment.
