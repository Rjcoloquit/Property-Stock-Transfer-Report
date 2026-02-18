# Property Stock Report – Login / Logout (XAMPP + PHP)

Simple login and logout using PHP sessions and MySQL on XAMPP.

## Setup

1. **Start XAMPP**  
   Start **Apache** and **MySQL** in the XAMPP Control Panel.

2. **Create database and table**  
   - Open **phpMyAdmin**: http://localhost/phpmyadmin  
   - Click **Import** and choose `database/setup.sql`, or run its SQL manually.  
   - This creates the database `property_stock` and the `users` table.

3. **Default login**  
   - **Username:** `admin`  
   - **Password:** `password`  
   Change this after first login (e.g. add a “change password” feature or run `create_user.php` to generate a new hash).

4. **Run the project**  
   - Put this folder under XAMPP’s `htdocs` (e.g. `C:\xampp\htdocs\PROPERTY STOCK REPORT`).  
   - In the browser go to:  
     **http://localhost/PROPERTY%20STOCK%20REPORT/**  
   - Or if you use a subfolder:  
     **http://localhost/your-folder/login.php**

## Files

| File | Purpose |
|------|--------|
| `config/database.php` | MySQL connection (host, db name, user, password). |
| `database/setup.sql` | Creates DB and `users` table + default admin user. |
| `login.php` | Login form and login logic (sessions). |
| `logout.php` | Logout (destroys session, redirects to login). |
| `index.php` | Protected dashboard; redirects to login if not logged in. |
| `create_user.php` | Optional: outputs SQL to add a user with a hashed password. |

## Config

Edit `config/database.php` if your MySQL is different:

- `DB_HOST` – usually `localhost`
- `DB_NAME` – database name (default `property_stock`)
- `DB_USER` – MySQL user (XAMPP default `root`)
- `DB_PASS` – MySQL password (XAMPP default empty)

## Security notes

- Passwords are stored with `password_hash()` and checked with `password_verify()`.
- Session is started on login and destroyed on logout.
- Protect or remove `create_user.php` in production.
