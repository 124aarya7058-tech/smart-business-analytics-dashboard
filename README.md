# Smart Business Analytics Dashboard

A capstone project for Advanced Database Management Systems (ADBMS) and Web Technology.

## Stack

- HTML5
- CSS3
- Bootstrap 5
- JavaScript
- Chart.js
- PHP 8+
- MySQL
- XAMPP / Apache / phpMyAdmin

## XAMPP installation

1. Install XAMPP.
2. Start Apache and MySQL.
3. Copy this folder into `C:\xampp\htdocs\`.
4. Open `http://localhost/phpmyadmin`.
5. Open the SQL tab and run these files in order (seed data only once):
   - `database/schema.sql`
   - `database/indexes.sql`
   - `database/views.sql`
   - `database/procedures.sql`
   - `database/triggers.sql`
   - `database/sample_data.sql`
     The project uses its own `smart_business_analytics` database so it will not overwrite an older `smart_business` database.
6. Open `config/database.php` and change the password if your XAMPP MySQL root account has a password.
7. Open `http://localhost/smart-business-analytics/`. Make sure Apache's document root is `C:\xampp\htdocs` and use the Apache instance bundled with the same XAMPP installation as MySQL.

## Deploy to Vercel

This project uses the community `vercel-php` runtime configured in `vercel.json`. Vercel cannot connect to a MySQL server running on your computer, so first create a **publicly reachable managed MySQL/MariaDB database** with a provider that permits connections from Vercel Functions. Do not use `127.0.0.1`, `localhost`, or the XAMPP database for a hosted deployment.

1. Push this project to a GitHub repository and import that repository in the Vercel dashboard. Keep the project root set to this repository's root; Vercel reads `vercel.json` automatically.
2. Create a database and a restricted database user with permissions only on that database. In its SQL console, run `database/schema.sql`, `database/indexes.sql`, `database/views.sql`, `database/procedures.sql`, and `database/triggers.sql`, in that order. If you created this database before the `app_sessions` table was added, run this migration once: `CREATE TABLE IF NOT EXISTS app_sessions (session_id VARCHAR(128) NOT NULL PRIMARY KEY, session_data MEDIUMBLOB NOT NULL, expires_at BIGINT UNSIGNED NOT NULL, INDEX idx_app_sessions_expiry (expires_at)) ENGINE=InnoDB;`.
3. In Vercel **Project Settings → Environment Variables**, add `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` using the values from the database provider. Add `DB_SSL_CA` only if your provider requires a CA certificate file and you include that file in the deployment. Do not commit database credentials.
4. Create a production admin account with a unique password. Do **not** import `database/sample_data.sql` into a public deployment: it contains the published demo credentials shown below. To generate a password hash locally, run `php -r "echo password_hash(readline('New admin password: '), PASSWORD_DEFAULT), PHP_EOL;"`, then insert a user into `users` using that hash and the `ADMIN` role. Do not put the plaintext password or generated hash in Git.
5. Redeploy from Vercel after setting the variables, then test login, customer/product changes, sales, and reports. The `app_sessions` table created by `schema.sql` stores PHP sessions in MySQL so logins survive serverless requests. Importing `database/sample_data.sql` on your private local XAMPP database remains optional.

The Vercel PHP runtime is community-maintained, not one of Vercel's official runtimes. Verify compatibility with your Vercel plan and runtime version before relying on it for production. SQL, configuration, and Markdown paths are blocked from HTTP access by the deployment router.

## Demo login

Admin:

- Email: admin@example.com
- Password: admin123

Manager:

- Email: manager@example.com
- Password: manager123

## Dataset

A suitable public dataset is the Kaggle "Superstore_Dataset" / Sample Superstore-style retail dataset. A currently accessible Kaggle page describes 9,994 records with order, customer, product, category, sales, quantity and profit fields:
https://www.kaggle.com/datasets/divyjn28/superstore-dataset

The project does not depend on that dataset to run. The included `sample_data.sql` provides demonstration data.

If you import the public dataset later:

1. Keep the original CSV/XLSX as raw data.
2. Clean duplicate/blank records.
3. Map customer fields into `customers`.
4. Map product fields into `products`.
5. Map order fields into `sales`.
6. Map line-item fields into `sale_details`.
7. Inventory values are not reliably present in many Superstore datasets, so the project's inventory stock/reorder values are clearly synthetic demonstration values.

## ADBMS concepts demonstrated

- ER model
- 1NF, 2NF and 3NF
- Primary/foreign keys
- Constraints
- SQL joins and aggregates
- GROUP BY / HAVING
- Subqueries
- CASE WHEN
- Views
- Stored procedures
- Trigger
- Indexes
- Transactions
- ACID concepts

## Main relationships

customers 1:M sales
sales 1:M sale_details
products 1:M sale_details
products 1:M inventory_log

## Important design note

The web sales form records one product line per sale to keep the one-month capstone simple. The database still uses a normalized `sales` header and `sale_details` line table, so the structure can be extended to multiple products per invoice later.

## Normalization

1NF: Each column contains atomic values and there are no repeating groups.
2NF: Non-key attributes in `sale_details` depend on the complete row identifier; sale and product information is separated into their own tables.
3NF: Non-key attributes do not depend on other non-key attributes. Customer, product and sale information are stored separately.

## Security

- Passwords use password_hash()/password_verify().
- Database operations use PDO prepared statements.
- PHP sessions are used for authentication.
- Output is escaped using htmlspecialchars().
- Admin-only deletion is checked server-side.
