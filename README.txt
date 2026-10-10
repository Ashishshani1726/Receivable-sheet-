BASIK RECEIVABLES PORTAL - PHP + MySQL + LIVE GOOGLE SHEET
===========================================================

WHAT THIS PROJECT DOES
- Secure Admin + Employee login
- Central user accounts in MySQL
- Admin can create/disable employees and reset passwords
- Per-user menu permissions: Overview, Ageing, Customers, Bills, Data Checks, Overdue, Party Remarks, PR, PO, POD, GRN
- Coordinator-level data access: All coordinators or selected coordinators only
- Party-wise remarks save centrally to MySQL
- Unified Invoice/Due date filter, Due/Not Due separation, TDS support, ageing buckets
- LIVE Google Sheet source: Data sheet -> Apps Script Web App -> PHP Portal
- Server-side Google Sheet fetch keeps the API key out of employee browsers
- 60-second server cache reduces Apps Script calls
- Admin gets a Sync Google Sheet button for immediate refresh
- If Google is temporarily unavailable, the last cached copy is used automatically

LIVE GOOGLE SHEET CONFIG
The current API URL is already configured in config.php.
Google Sheet mode is ON by default:
  GOOGLE_SHEET_LIVE = true

The current Web App endpoint is:
  https://script.google.com/macros/s/AKfycbyk9_2W8J4mC8UC5Ujj4wk3P9GKWwCztEIJKP0_XLVfA4mfBfP9NXW3-Rt1sRl6FcWg/exec?key=BASIK2026

If you make a new Apps Script deployment later, edit only GOOGLE_SHEET_API_URL in config.php.
For security, rotate BASIK2026 to a new private key later if this project is put on a public internet server.

IMPORTANT DATA MAPPING
Your live sheet currently uses:
- Customer Name -> dashboard Party
- Sales Rep -> dashboard BDM
- Sales Coordinator -> Sales Coordinator
- Due Date / Bill Date are converted to ISO dates by PHP
- Pending Amount, Receipt Amount, OLD Pending Amount and other receivable fields are mapped automatically

PR / PO DASHBOARD SOURCE
- PR Register, PO Tracker and GRN Tracker are now connected to the uploaded / live PO-FMS workbook.
- Source file is stored separately at data/procurement_pr_po.json.
- Source sheet used: Sheet55.
- Counts are row-wise, not unique PO counts. If one PO appears in 5 item rows, all 5 rows are shown.
- This PR/PO/GRN data does NOT overwrite or mix with the live receivables Google Sheet Data source.
- Sync Google Sheet affects only receivables data; PR/PO/GRN dashboard remains separate.
- GRN Tracker uses GRNNo, GRN Date, Received Qty, Accepted Qty, Rejected Qty, Invoice and QC Approval columns from Sheet55.
- POD tracker screen can be connected later if a separate source is supplied.

INSTALL / UPDATE ON XAMPP
1) If this is a NEW install:
   Extract this package directly inside:
     C:\xampp\htdocs\receivables_portal

2) If you ALREADY installed the previous portal:
   Back up your current receivables_portal folder first.
   Replace the project PHP/assets/private files with this package.
   Do NOT delete your MySQL database. Existing users, passwords, permissions and remarks remain in MySQL.

3) Start Apache and MySQL in XAMPP.

4) New install only:
   Open:
     http://localhost/receivables_portal/setup.php
   Create the first Admin account.

5) Login:
     http://localhost/receivables_portal/

6) Admin can click:
     Sync Google Sheet
   to force an immediate refresh.
   Normal page loads use a short 60-second cache.

7) Same office network employee link:
   Your server PC IPv4 was previously 192.168.1.113, so while that IP remains the same:
     http://192.168.1.113/receivables_portal/
   If the IP changes, run ipconfig again.

SECURITY NOTES
- Do NOT expose a default XAMPP installation directly to the public internet.
- For outside-office access, use HTTPS on a properly configured server or a VPN.
- Passwords are stored with password_hash(), not plain text.
- Employee coordinator filtering happens on the PHP server before data is sent to the browser.
- The Google API key is stored in config.php and is not exposed in the employee page source.
- If this API URL/key has been shared widely, rotate the key in Apps Script before public deployment.

TROUBLESHOOTING
- Google Sheet live pill says cached/fallback:
  1) Open the Apps Script /exec?key=... URL in Chrome and confirm success:true.
  2) Confirm PHP has internet access.
  3) Confirm cURL/OpenSSL is enabled in XAMPP PHP.
  4) Click Sync Google Sheet as Admin and read the error toast.
- 'Access denied for user': update DB_USER / DB_PASS in config.php.
- Apache cannot be reached from another PC: confirm same Wi-Fi/LAN and allow Apache through Windows Firewall (Private network).
- If setup cannot create the database, create receivables_portal manually in phpMyAdmin and reopen setup.php.


WEEK-WISE DUE REPORT
- New Week-wise due menu groups receivables by Due Date, Monday-Sunday.
- Due amount = positive OLD Pending Amount. Remaining = positive Pending Amount.
- Receipt, Debit Note and Overdue baki are shown separately.
- Admin can grant/revoke the Week-wise due permission per employee.

WEEK-WISE SUMMARY - AUTOMATIC FORMULAS
- Management weeks are: day 1-7, 8-14, 15-21, and 22 through month-end.
- Total Outstanding = reconstructed opening outstanding at the start of that management week.
- TTL O/Due = opening outstanding that was already due at the start of that week.
- Received = Receipt Amount whose Receipt Date falls inside the week.
- Collection % = Received / TTL O/Due x 100; if TTL O/Due is zero, Received / Total Outstanding is used.
- BM defaults to 80% when the live Google Sheet does not contain a BM/Benchmark column.
- Credit Note reads Credit Note Amount / Credit Note / CN Issued / CN Issue. Debit Note is NOT used as a Credit Note fallback.
- RTV, Write Off and Other read their optional numeric amount columns when present.
- If adjustment date columns exist (Credit Note Date/CN Date, RTV Date, Write Off Date, Other Date), those dates are used; otherwise Receipt Date is the fallback week allocation date.
- Total = Received + Credit Note + RTV + Write Off + Other.
- The table includes an automatic TOTAL footer and CSV export uses the same formulas.

MODERN PHP + TAILWIND GUI UPGRADE
- Dashboard shell redesigned with a navy gradient sidebar, blue active navigation, sticky glass topbar and cleaner filters.
- Tailwind Browser CDN is used with a tw- prefix and preflight disabled, so it does not disturb the existing dashboard CSS.
- Existing CSS remains as a visual fallback, so the portal is still usable if Tailwind CDN is temporarily unavailable.
- Week-wise Summary now has 5 KPI cards: Total Outstanding, TTL O/Due, Total Received, Collection %, Total Adjustments.
- Added a pure-SVG 8-week trend chart: Total Outstanding vs Received vs TTL O/Due.
- Added a current-week collection donut for Received, Adjustments and Balance.
- Collection % is highlighted green/amber/red against the BM benchmark.
- Added an Export Weekly CSV button directly on the weekly panel.
- No MySQL schema change is required for this GUI update. Do NOT import schema.sql again on an existing installation.

IMPORT EXCEL (ADMIN)
--------------------
The dashboard now supports both live Google Sheet data and manual Excel imports.
1. Admin clicks Import Excel and selects the receivables .xlsx file (same Data-sheet layout).
2. The imported dataset is stored on the server and becomes the active source for all users.
3. The header shows "Imported Excel active" while manual data is active.
4. Click "Use Google Sheet" / "Sync Google Sheet" to switch back to live Google Sheet data.
5. Import does not change or upload anything into Google Sheets.


MERGE + DUPLICATE IMPORT UPDATE
-------------------------------
When Admin uploads the receivables Excel again, the portal preserves existing rows,
merges new rows, refreshes matching invoices, and removes duplicates. Import header
matching also accepts Party / Customer Name / Customer / Party Name and common pending
amount header variants.

Recommended use: upload the latest full receivables workbook. Existing history remains,
matching invoices are refreshed, and only genuinely new invoices are added.

DATE FILTER -> WEEKLY DASHBOARD UPDATE
--------------------------------------
- Quick Date Range and From/To filters now update Week-wise KPI cards, trend and collection donut immediately.
- Customer, coordinator, BDM, ageing and balance-status filters also apply to the weekly dashboard visuals.
- The Week-wise Summary table still keeps the complete historical week list so older weeks are not hidden by a current date filter.
- Write Off terminology is used in the weekly dashboard.


MASTER DATA VIEW
- Sidebar me Master Data menu add hai.
- Current imported/merged Excel source ke saare Data sheet rows aur columns dikhenge.
- Excel re-upload ke baad merged/deduplicated saved result Master Data me automatically visible hoga.
- Admin ko Master Data permission automatically milti hai; employee ko Admin > Users me permission di ja sakti hai.

MASTER DATA CRUD UPDATE
=======================
- Master Data page now supports Excel-like CRUD.
- Edit any cell directly in the table.
- Add Row, Duplicate Row, Delete selected Row(s).
- Add Column, Rename Column, Delete selected Column.
- Save Master Data writes the updated source back to server using existing API.
- Dashboard, Ageing, Weekly Summary and Reports refresh from the saved master source after save.
- Admin login is required for server save. Employees can view according to permissions.

UPDATE - PARTY REMARK MANUAL SAVE + DASHBOARD EXPORT TOOLBAR
- Party wise remark auto-save is disabled.
- Each remark row now has Edit, Save and Delete actions.
- Save Visible button saves unsaved remarks visible on the current page.
- Dashboard header now includes CSV, Excel, PDF, Print, Import Excel and Template buttons.
- PDF opens the browser print dialog; choose Save as PDF.

REMARK HISTORY UPDATE
- Party wise remark now keeps history for every create, update and clear action.
- History button shows old remark, new remark, changed by and time.
- Clear only removes the party remark/comment; it does not delete invoice/customer/master data.
- A party_remark_history table is created automatically by the portal. No manual schema import is required for existing installs.

BILL REGISTER FOLLOW-UP EDIT + GOOGLE SHEET WRITE-BACK
------------------------------------------------------
Added in this build:
- Bill-wise outstanding register now has editable Action / follow-up field.
- Use Edit, Save and Clear buttons on each bill row.
- Save updates the portal source data and attempts to update Google Sheet row Data!<row>, column Action Point.
- If Google Apps Script does not yet support write-back, the change is saved in the portal/manual source and a warning is shown.

To enable Google Sheet live write-back:
1. Open your Google Sheet Apps Script project.
2. Keep your existing doGet(e) that returns live sheet data.
3. Paste the contents of google_sync/apps_script_two_way_sync.gs into the same Apps Script project.
4. Deploy > Manage deployments > Edit > Deploy again.
5. Web app settings: Execute as Me, Who has access Anyone.
6. Keep GOOGLE_SHEET_API_URL in config.php using the same /exec?key=BASIK2026 URL.

Security note: only logged-in users with Bill Register permission can call the follow-up API. Admin is recommended for live sheet write-back.

UPDATE - Send buttons position/visibility fix
- Party wise remark table me Send column ko Action ke baad move kiya gaya.
- WhatsApp/Mail buttons ko visible labels + icons ke sath dikhaya gaya.
- Agar customer master me contact/email missing hai to No WhatsApp / No Mail clearly dikhega.

UPDATE: Contact edit option
- Party wise remark Contact column me Edit Contact button add hai.
- Existing WhatsApp/Mobile, Email aur City ko dobara change/update kar sakte hain.
- Save api/save_party_contact.php se hoga; dashboard refresh ke baad WhatsApp/Mail buttons updated contact use karenge.

CRM WISE REPORT UPDATE
- Added CRM Wise menu below Master Data in the sidebar and mobile navigation.
- CRM Wise report shows CRM filter, bill count, open bills, customers, receipts, total outstanding, overdue, not due, TDS and adjustments.
- Clicking a CRM name filters the bill details for that CRM.
- CRM CSV export added.
- Existing Party wise remark, Master Data CRUD, contact edit, WhatsApp/Mail options and bill follow-up edit remain unchanged.

DASHBOARD UI UPDATE - LOGISTICS STYLE
------------------------------------
- Dashboard overview redesigned with dark navy sidebar style and colourful KPI cards.
- KPI cards use blue, red, green, yellow, cyan and purple colours similar to the sample UI.
- Performance section added with Top 10 Customers, Top Coordinators, Ageing Bucket, Customer Type and Receipts Trend tabs.
- Alerts section added for overdue, pending follow-up, missing contacts, credit note review and bills due this week.
- Week-wise chart and ageing distribution remain available in the dashboard layout.
- Existing features preserved: Google Sheet sync, CRM Wise menu, Master Data, Party Wise Remark, contact edit, WhatsApp/Mail actions and Bill Register follow-up edit.

SIDEBAR UI UPDATE
- Sidebar redesigned in BASIK logistics style: dark navy gradient, large BASIK title, admin profile, MAIN MENU and MANAGEMENT sections.
- Dashboard UI, CRM Wise, Master Data, bill follow-up edit, WhatsApp/Mail/contact features remain unchanged.
- No database/schema change required.

FIX NOTE - Sidebar/Dashboard UI
- Fixed JavaScript break caused by export Excel helper.
- Data/cards now render normally with logistics-style dashboard and sidebar UI.
- Export Excel still works with clean table formatting.

LOGO + ADMIN PROFILE LINK UPDATE
- Sidebar BASIK text replaced with uploaded Basik Homeware logo at assets/img/basik-logo.png.
- Sidebar admin profile block now opens admin/users.php when clicked.


SECOND LIVE PR / PO GOOGLE SHEET
--------------------------------
Your PR/PO Google Sheet link:
https://docs.google.com/spreadsheets/d/1_4aVIV9GrJzdVNtBWH0hEuQrPi216NensynFFBH9Mpk/edit?usp=sharing

Important: the normal Google Sheet /edit link is not used directly in PHP.
You must deploy the Apps Script Web App first.

Steps:
1. Open your PR/PO Google Sheet.
2. Go to Extensions > Apps Script.
3. Paste the code from google_sync/pr_po_live_apps_script.gs.
4. Deploy > New deployment > Web app.
5. Execute as: Me.
6. Who has access: Anyone with the link.
7. Copy the Web App URL.
8. Open config.php and paste it here:
   define('PR_PO_SHEET_API_URL', 'https://script.google.com/macros/s/AKfycbwglEv4_MnNXyq0Ca-C5bZT3WwILCXr6StorUZvGmGTYcTgd7i-csIEutKIirE19D1aQQ/exec?key=BASIK2026&sheet=Sheet55');

After that login as admin and click PR Register, PO Tracker or GRN Tracker.
Use the "Sync PR/PO Sheet" button. Receivables live sheet will remain separate.
