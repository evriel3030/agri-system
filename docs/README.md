# Agriculture Information System (PHP + Supabase PostgreSQL)

CTU AP3 Midterm Project. A web-based system for managing **Farmers, Farms, Crops, Planting Records and Harvest Records**, with a dashboard, reports, login and two user roles.

- **Backend:** PHP 8 + PDO (`pdo_pgsql`)
- **Database:** Supabase PostgreSQL
- **Frontend:** HTML, Bootstrap 5, Bootstrap Icons, Chart.js (all bundled in `assets/vendor/`, so no internet is needed for the design)

Full documentation (ERD, normalization UNF → 3NF, data dictionary, tests, user guide): **`docs/DOCUMENTATION.md`**

---

## Folder structure

```
agri-system/
├── .env.example          Template for your database settings (copy to .env)
├── .env                  YOUR secrets (never share / never upload to GitHub)
├── index.php             Sends you to the login page or dashboard
├── login.php / logout.php
├── dashboard.php         Main page after login (cards, charts, recent records)
├── router.php            Only for PHP's built-in server (blocks secret files)
├── config/database.php   Reads .env and creates the Supabase connection (PDO)
├── includes/
│   ├── bootstrap.php     Loaded by every page: settings, session, error handler
│   ├── helpers.php       Escaping, flash messages, CSRF, search/sort/pagination
│   ├── auth.php          Login, logout, roles (Administrator / Staff)
│   ├── validator.php     Server-side input validation
│   ├── forms.php         Form fields and table buttons (consistent design)
│   └── layout.php        Navigation bar, page footer, delete-confirmation dialog
├── modules/
│   ├── farmers.php  farms.php  crops.php  plantings.php  harvests.php
│   │                     (each file = list, create, edit, view, delete)
│   ├── reports.php       6 reports + filters + print + CSV export
│   └── users.php         User accounts (Administrator only)
├── sql/
│   ├── 01_schema.sql     Tables, keys, constraints, triggers, security
│   └── 02_seed.sql       Lookup values, sample data, 2 login accounts
├── assets/               css/, js/, vendor/ (Bootstrap, icons, Chart.js)
└── docs/                 Documentation, ERD, screenshots
```

---

## Setup (step by step)

### Step 1 - Create the Supabase database
1. Go to <https://supabase.com>, sign in, click **New project**. Choose a name, set a **database password** (write it down!) and pick the region closest to you. Wait until the project is ready.
2. In the left menu open **SQL Editor** → **New query**.
3. Open `sql/01_schema.sql` from this project, copy **everything**, paste it into the editor and click **Run**. (If Supabase warns about "destructive operations", confirm - the database is new and empty.)
4. Open a new query, paste all of `sql/02_seed.sql` and click **Run**.
5. Check **Table Editor**: you should see 15 tables: 6 main (`farmer`, `farm`, `crop_type`, `crop`, `planting`, `harvest`), 7 lookup, and 2 login (`role`, `app_user`) tables. Seed examples include farmer 15 rows, farm 15, crop 10, planting 30, and harvest 20.

`crop_type` stores `crop_name` and `category_id` (the category reference); `crop` stores `crop_type_id`, `variety`, `growing_period_days`, and `season_id`.

### Step 2 - Get the connection details
1. In your Supabase project click the **Connect** button (top of the page).
2. Choose **Session pooler** (this one works on normal home/school networks; the "Direct connection" needs IPv6 and often fails).
3. Note the **Host** (like `aws-0-ap-southeast-1.pooler.supabase.com`), **Port** (`5432`), **Database** (`postgres`) and **User** (`postgres.<your-project-ref>`).

### Step 3 - Install PHP
**XAMPP (Windows) - easiest:**
1. Install XAMPP (PHP 8.1 or newer).
2. Open `C:\xampp\php\php.ini` and make sure these two lines have **no `;` at the start**:
   ```
   extension=pdo_pgsql
   extension=pgsql
   ```
3. Restart Apache in the XAMPP control panel.
4. Copy the whole `agri-system` folder to `C:\xampp\htdocs\`.

**Or use PHP's built-in server** (any OS with PHP 8.1+ and the `pdo_pgsql` extension): see Step 5.

### Step 4 - Configure the database settings
1. In the project folder, copy `.env.example` and name the copy `.env`.
2. Edit `.env` and fill in your Supabase values:
   ```
   DB_HOST=aws-0-ap-southeast-1.pooler.supabase.com
   DB_PORT=5432
   DB_NAME=postgres
   DB_USER=postgres.abcdefghijklmnop
   DB_PASSWORD=your-database-password
   ```
   (If your password has special characters, keep it exactly as is; you can wrap it in quotes.)

### Step 5 - Run it
- **XAMPP:** open <http://localhost/agri-system/>
- **Built-in server:** open a terminal in the project folder and run
  ```
  php -S localhost:8000 router.php
  ```
  then open <http://localhost:8000/>

### Step 6 - Log in
| Username | Password   | Role          |
|----------|------------|---------------|
| `admin`  | `Admin@123` | Administrator |
| `staff`  | `Staff@123` | Staff         |

**Change these passwords** (Users page, as admin) before your demo or deployment.

| Role | Can do |
|------|--------|
| Administrator | Everything: add, view, edit, **delete**, reports, manage users |
| Staff | Add, view, edit, search, reports. **Cannot delete** or manage users |

---

## Troubleshooting

| Problem | Fix |
|---------|-----|
| "The system cannot reach the database right now" on login | Check `.env` (host, user, password). Use the **Session pooler** host. Make sure `pdo_pgsql` is enabled and Apache was restarted. |
| "could not find driver" in the PHP error log | `extension=pdo_pgsql` is still commented out in `php.ini`. |
| Page says "Something went wrong" | Set `APP_DEBUG=1` in `.env` to see the technical message, then set it back to `0`. Also check the PHP error log. |
| Blank/unstyled page | Make sure the `assets/vendor/` folder was copied too. |
| Wrong URL / 404 in XAMPP | The folder must be `htdocs/agri-system/` and you open `http://localhost/agri-system/`. |

## Deployment notes
- Put the database settings in the host's **environment variables** (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`) instead of uploading `.env`. The app reads both.
- Use HTTPS. Keep `APP_DEBUG=0`.
- Never commit `.env` (it is already listed in `.gitignore`).
