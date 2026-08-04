# Importing a local dump on InfinityFree

InfinityFree free hosting uses MariaDB with limits on SQL import size, packet size, and privileges. Use the converter script before uploading your dump to phpMyAdmin.

## Ready-to-import dump

A converted dump of `ecommerce_deploy` is already in this folder:

- `ecommerce_deploy_infinityfree.sql` — import this in phpMyAdmin (select your InfinityFree DB first)
- `ecommerce_deploy_mariadb.sql` — original local MariaDB export (for re-conversion)

## 1. Export locally (optional / newer data)

From your development machine, export the `ecommerce_deploy` database with `mysqldump` or MariaDB tools. A typical command:

```bash
mysqldump -u root -p ecommerce_deploy > database/dumps/ecommerce_deploy_mariadb.sql
```

Keep the dump as plain SQL (no compression) for the conversion step.

## 2. Convert for InfinityFree

Run the converter from the project root:

```bash
python3 scripts/convert_sql_for_infinityfree.py \
  database/dumps/ecommerce_deploy_mariadb.sql \
  -o database/dumps/ecommerce_deploy_infinityfree.sql
```

Optional flags:

- `--split-rows 25` — maximum rows per `INSERT` (default: 25). Lower this if phpMyAdmin reports packet or timeout errors.
- `--keep-sessions` — preserve `sessions` table data (not recommended after migration; session payloads are host-specific).

The script:

- Removes `CREATE DATABASE`, `USE`, and `DEFINER` statements
- Replaces MariaDB JSON `CHECK (json_valid(...))` columns with plain `LONGTEXT`
- Normalizes MySQL 8 collations to `utf8mb4_unicode_ci`
- Splits large multi-row `INSERT` batches
- Clears `sessions` rows by default

## 3. Create the database on InfinityFree

1. Log in to the [InfinityFree control panel](https://dash.infinityfree.com/).
2. Open **MySQL Databases** and create a new empty database.
3. Note the database name, username, password, and host (often `sqlXXX.infinityfree.com`).

Do not rely on `CREATE DATABASE` inside the SQL file; InfinityFree does not allow it via import.

## 4. Import via phpMyAdmin

1. Open **phpMyAdmin** from the control panel.
2. Select your new database in the left sidebar.
3. Go to **Import**, choose `ecommerce_deploy_infinityfree.sql`, and start the import.

If the file is large or the import times out:

- Reduce `--split-rows` and re-run the converter.
- Use [BigDump](https://www.ozerov.de/bigdump/) or import in smaller chunks.

## 5. Point Laravel at InfinityFree

Update `.env` on the hosted site:

```env
DB_CONNECTION=mysql
DB_HOST=sqlXXX.infinityfree.com
DB_PORT=3306
DB_DATABASE=your_infinityfree_db_name
DB_USERNAME=your_infinityfree_db_user
DB_PASSWORD=your_infinityfree_db_password
```

Run migrations only if you did not import a full schema+data dump. After a full import, clear config cache if needed:

```bash
php artisan config:clear
```

## Verify the conversion locally

```bash
python3 scripts/test_convert_sql_for_infinityfree.py
```
