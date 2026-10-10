# Basik Receivables Management Portal

A web-based receivables tracking and MIS reporting application designed to monitor outstanding invoices, overdue payments, customer collections, and weekly collection performance.

> **Project status:** This README documents the intended portal features and typical local setup. Some modules depend on the version of the project code you are running. Confirm the actual filenames, import schema, and enabled features against your project ZIP before deployment.

## Overview

The **Basik Receivables Management Portal** brings invoice, collection, and customer follow-up data into an interactive dashboard. It supports faster receivables analysis with customer-wise views, aging buckets, date filters, and week-wise MIS reporting.

## Key Features

- **KPI dashboard:** Total Outstanding, Total Overdue, Received, Collection %, BM, Credit Notes (CN Issued), RTV, Write-Off, Other Adjustments, and Total.
- **Interactive cards:** Click a KPI card to filter the underlying detail records.
- **Receivables aging:** Separate Not Due and Overdue amounts; overdue aging buckets of 0–30, 31–60, 61–90, 91–180, and 181–365 days (plus older invoices where applicable).
- **Weekly summary:** Week, TTL OS, TTL O/Due, Received, Collection %, BM, CN Issued, RTV, Write-Off, Other, and Total.
- **Customer analysis:** Top customers and top overdue customers, with a Top 5 preview and extended Top 20 view.
- **Flexible filters:** Invoice/Bill Date, Due Date, week, customer, and CRM.
- **Customer follow-ups:** Party-wise remarks and remark history.
- **Data import:** Excel import with update/duplicate-handling rules based on the configured record key.
- **Google Sheets connectivity:** Google Apps Script web-app API integration for live data retrieval; write-back requires a separately configured and authorized endpoint.
- **Access controls:** Admin/employee roles and assigned data scope, when enabled in the installed version.
- **Reporting:** Dashboard views and export options where implemented.

## Technology Stack

| Layer | Technology |
| --- | --- |
| Backend | PHP 8.x |
| Database | MySQL / MariaDB |
| Frontend | HTML, JavaScript, Tailwind CSS |
| Data sources | Excel files, Google Sheets API |
| Local server | XAMPP (Apache + MySQL) |
| Reporting | KPI cards, interactive tables, charts |

## Local Installation (Windows + XAMPP)

1. Install and open **XAMPP**.
2. Start **Apache** and **MySQL**.
3. Extract the project ZIP into:

   ```text
   C:\xampp\htdocs\receivables_portal\
   ```

4. Open [phpMyAdmin](http://localhost/phpmyadmin/).
5. Create a database named `receivables_portal`.
6. If your project includes a `.sql` schema or backup, import it into that database. **Do not assume the project can run without the required tables.**
7. Update your project's actual configuration file (for example `config.php`, if present) with your local database credentials:

   ```php
   define('DB_HOST', '127.0.0.1');
   define('DB_PORT', '3306');
   define('DB_NAME', 'receivables_portal');
   define('DB_USER', 'root');
   define('DB_PASS', ''); // Local XAMPP default only
   define('APP_NAME', 'Basik Receivables Portal');
   define('APP_TIMEZONE', 'Asia/Kolkata');
   date_default_timezone_set(APP_TIMEZONE);
   ```

8. Open the portal in your browser:

   ```text
   http://localhost/receivables_portal/
   ```

9. Sign in using a user account provisioned in your own database (if login is enabled).

**Security:** An empty MySQL root password is acceptable only as a local XAMPP example. Use a dedicated least-privilege database user and secure credentials for any shared or production deployment.

## Google Sheets Integration

The portal can use a Google Apps Script Web App endpoint to retrieve receivables data.

1. Prepare the Google Sheet with the columns expected by the import/sync mapping.
2. Deploy or configure the authorized Apps Script API.
3. Store the API URL and any authentication secret in **server-side configuration**, not in browser JavaScript or this README.
4. Enable live fetching in the application's configuration if that capability is available.
5. Test the endpoint and verify row counts, dates, amounts, and weekly totals against the source Sheet.
6. For two-way editing, implement and authorize a write-back endpoint and maintain an audit trail.

Example placeholders (replace according to your actual config structure):

```php
define('GOOGLE_SHEET_LIVE', true);
define('GOOGLE_SHEET_API_URL', getenv('GOOGLE_SHEET_API_URL') ?: '');
```

**Never commit real Apps Script secret keys, API URLs containing credentials, database passwords, or customer financial data to GitHub.**

## Important Reporting Rules

- **Outstanding and overdue:** Derive consistently from the source invoice amount, remaining balance, due date, and payment status. Document your project's exact formula.
- **Collection %:** Define numerator, denominator, date scope, and handling of credit notes or adjustments consistently before publishing.
- **Aging:** Compare due dates with the selected reporting/as-of date; keep Not Due separate from overdue aging buckets.
- **Weekly reports:** Sort newest week first, and include all relevant weeks even when a category total is zero.
- **Duplicate handling:** Define a stable invoice/row identifier before deciding whether imports should insert, update, or overwrite rows.
- **PR/PO/GRN (if installed):** Repeated PO rows must not be automatically deduplicated when the report is intended to count source rows.

## Recommended Validation Checklist

- [ ] Database connection and login work.
- [ ] Google Sheets and/or Excel data loads successfully.
- [ ] Dashboard totals match source data.
- [ ] Invoice Date and Due Date filters work independently and together.
- [ ] Not Due and Overdue totals reconcile.
- [ ] Weekly summary does not incorrectly show zero for populated periods.
- [ ] Top 5 preview and Top 20 extended view show correct customers.
- [ ] Clicking dashboard cards filters the correct details.
- [ ] Remarks save correctly and change history is retained.
- [ ] Admin and employee data permissions are enforced on the server.
- [ ] Exports match on-screen filtered results (if enabled).

## Troubleshooting

| Problem | What to check |
| --- | --- |
| Blank dashboard / all zeros | Data-source response, field mapping, PHP errors, and date parsing |
| MySQL connection error | XAMPP service, database name, port, username, and password |
| Weekly summary shows zero | Week grouping, source amounts, date format, and filter conditions |
| Date filter not working | Invoice Date vs Due Date mapping and inclusive date range |
| Google Sheets not loading | Apps Script deployment permissions, server-side URL, API response, and PHP cURL |
| Unauthorized data visible | Server-side role checks and CRM/customer scope filtering |

## Possible Future Enhancements

- Automated collection reminders through authorized email or WhatsApp integrations.
- Scheduled reports and collection follow-up alerts.
- Audit logs and downloadable reconciliation reports.
- Enhanced CRM-wise performance dashboards.
- Controlled two-way synchronization and conflict resolution with Google Sheets.

## Intended Users

MIS Analysts, Finance & Accounts Teams, Collection Teams, CRM Executives, and Authorized Administrators.

## Disclaimer

This is a project README template based on the portal's discussed functionality. It is not a statement that every feature is fully implemented or production-tested in every build. Verify and adjust it to match the source code before publishing.
