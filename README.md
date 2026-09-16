# FactoryFlow — Enterprise Manufacturing ERP

A high-performance, modular Manufacturing Resource Planning (ERP) platform built from the ground up to orchestrate end-to-end industrial workflows. FactoryFlow replaces fragmented spreadsheets and bulky off-the-shelf CMS solutions with a bespoke, zero-bloat architecture—handling everything from raw material intake and multi-level Bills of Materials (BOM) to automated stock deduction ledgers and transactional auditing.

---

## 🚀 Key Features

* **Role-Based Access Control (RBAC):** Granular session security enforcing permission tiers across Admins, Production Managers, and Warehouse Operators.
* **Master Data Management:** Centralized registry for Finished Products, Categories, Measurement Units, Suppliers, and Customers with automated SKU/Code generation.
* **Dynamic Bill of Materials (BOM) Engine:**
  - Multi-component recipe builder with dynamic scrap/wastage factor calculation.
  - Automated cost-per-unit rollups based on dynamic raw material spot costs.
  - Strict revision control: activating a new BOM version automatically archives preceding revisions.
* **Dual-Track Inventory & Stock Movement Ledger:**
  - Comprehensive tracking for both Raw Materials and Finished Goods.
  - Granular transaction types: `IN` (Inward receipts), `OUT` (Dispatches), `SCRAP` (Waste write-offs), and `ADJUSTMENT` (Cycle counts).
  - Hard guards against negative inventory anomalies.
* **Dynamic, Asynchronous Client:** Instant DOM updates via Vanilla JS (ES6+ Fetch API), debounced live search filters, modal-driven forms, and zero full-page reloads.
* **Enterprise Security & Reliability:** Atomic MySQL operations via PDO transactions (`BEGIN/COMMIT/ROLLBACK`), CSRF token enforcement, and strict output buffering to prevent response corruption.

---

## 🛠 Tech Stack

| Layer | Technologies |
| :--- | :--- |
| **Backend** | PHP 8.2 (Strict typing, Native PDO, Clean Output Buffering) |
| **Database** | MySQL 8.0+ (InnoDB, Foreign Key Constraints, Indexed Lookups) |
| **Frontend** | Vanilla JavaScript (ES6+), HTML5, CSS3 |
| **UI Framework** | Bootstrap 5.3 |
| **Libraries** | Chart.js (Analytics), SweetAlert2 (Notifications & Dialogs), Flatpickr |
| **Environment** | Apache / XAMPP on Windows/Linux |

---

## 📐 Architecture & Design Principles

* **Bespoke Core (No CMS/Framework Bloat):** Engineered natively without heavy third-party framework overhead, optimizing database access and transaction speeds.
* **Standardized JSON API Contracts:** Every RESTful endpoint returns a consistent response envelope:
  ```json
  {
    "success": true,
    "data": { ... },
    "error": null,
    "meta": { ... }
  }

*Dependency Protection: Foreign key cascades and API validation prevent orphan records (e.g., blocking deletion of materials tied to active BOMs).

Here is the cleanly formatted Markdown version with proper line breaks, indentation, and fenced code blocks so it renders correctly on GitHub:

### Step-by-Step Installation

1. **Clone the Repository:**
   ```bash
   git clone (https://github.com/senurijameena/factoryflow.git)



Place the project directory into your web root (e.g., `C:/xampp/htdocs/factoryflow` or `/var/www/html/factoryflow`).

2. **Configure the Database:**
* Start your Apache and MySQL services.
* Open phpMyAdmin (or your MySQL CLI) and create a database:
```
CREATE DATABASE factoryflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

```


* Import the database schema:
```
mysql -u root -p factoryflow < sql/schema.sql

```




3. **Configure Environment Settings:**
* Copy or rename `config/database.example.php` to `config/database.php` (if applicable) and configure your database credentials:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'factoryflow');
define('DB_USER', 'root');
define('DB_PASS', '');

```




4. **Run the Application:**
* Navigate to `http://localhost/factoryflow` in your browser.
* Log in using default credentials (configured in seed scripts):
* **Username:** `admin`
* **Password:** `admin123`



```

```
