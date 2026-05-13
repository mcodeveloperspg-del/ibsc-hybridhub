# InfinityFree / cPanel Upload

## Upload Files

Upload the application files into your InfinityFree `htdocs` folder, or upload the prepared zip from `dist/` and extract it there.

Do not upload:

- `.git`
- `.env` from your local machine
- `Dockerfile`
- `docker/`
- old files inside `uploads/resources`, `uploads/slides`, or `uploads/student_photos`

## Configure Environment

Create a new `.env` file in the same folder as `index.php`.

Use your real InfinityFree values:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
APP_MAX_UPLOAD_MB=20

DB_HOST=sqlXXX.infinityfree.com
DB_PORT=3306
DB_DATABASE=if0_XXXXXXXX_hybrid_hub
DB_USERNAME=if0_XXXXXXXX
DB_PASSWORD=your_vpanel_mysql_password
DB_CHARSET=utf8mb4
```

## Import Database

In InfinityFree Control Panel:

1. Create a MySQL database.
2. Open phpMyAdmin for that database.
3. Import `database/schema.sql`.
4. Import `database/seed.sql` only if you want demo users/data.

The schema file no longer creates or selects a database, so it can import into the database name InfinityFree assigns.

## Check Upload Folders

Keep these folders present:

- `uploads/resources`
- `uploads/slides`
- `uploads/student_photos`

Uploaded files are runtime data. Back them up separately from GitHub.

## After Upload

Visit your domain. If you see a database error, check the `.env` database host, database name, username, and password first.
