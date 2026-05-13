# Deployment Checklist

## 1. Configure the host

Copy `.env.example` to `.env` on the server and set the production values:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://your-real-domain`
- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`

Do not commit `.env` to GitHub.

## 2. Prepare the database

Create the database and import the base schema:

```bash
mysql -u YOUR_USER -p YOUR_DATABASE < database/schema.sql
```

If you want demo data on a non-production environment only:

```bash
mysql -u YOUR_USER -p YOUR_DATABASE < database/seed.sql
```

Run migrations after every deployment:

```bash
php database/migrate.php
```

The migration runner stores completed migrations in `schema_migrations`, so repeat runs are safe.

## 3. Confirm writable uploads

The app writes to:

- `uploads/slides`
- `uploads/resources`
- `uploads/student_photos`

Make those directories writable by the PHP/web-server user. Uploaded runtime files are ignored by Git and should be backed up separately from the repository.

## 4. Public web root

Point the hosting document root at this project only if the host respects `.htaccess`. The repository includes `.htaccess` blocks for `config`, `database`, and executable files inside `uploads`.

For stronger isolation on a VPS, keep `config`, `database`, and uploads outside the public document root and set the `UPLOAD_*_PATH` variables to absolute private paths.

## 5. Render deployment

Render does not run plain PHP as a native runtime. Create the service as a Docker web service so Render builds from the included `Dockerfile`.

Set these Render environment variables:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://your-render-service.onrender.com`
- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`

Use an external MySQL/MariaDB database, because Render does not provide managed MySQL. Attach a persistent disk or external object storage if uploaded files must survive redeploys.
