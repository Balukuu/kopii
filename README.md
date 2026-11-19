# Kopii — Ugandan Freelancer & Business Showcase Marketplace

**Kopii** (*"Where Customers Meet Businesses"*) is a custom, work-first online marketplace built with **PHP 8+ & MySQL (PDO)** and optimized for **LiteSpeed / cPanel shared hosting** (`kopii.online`), with an automatic zero-config SQLite fallback for local development.

## Key Capabilities
- **Self-Registration for Freelancers & Businesses (`/register`)**: Photographers, studios, designers, tailors, caterers, and local businesses can register in 2 minutes and immediately launch a public showcase profile.
- **Work-First Marketplace Discovery (`/`, `/explore`)**:
  - Browse **Talent & Businesses** cards featuring live 3-photo portfolio strips, verified badges, Ugandan cities, star ratings, and starting **UGX** prices.
  - Switch to the **Portfolio Work Gallery** (`/explore?tab=work`) to browse actual work samples directly and inspect them in a full-screen lightbox.
  - Filter by keyword, category, Ugandan city (*Kampala, Entebbe, Jinja, Mbarara, Gulu, Fort Portal...*), provider type (*Freelancer* vs *Business*), and max UGX budget.
  - **Compare Shortlist**: Save multiple photographers or providers and compare their portfolios and rates side-by-side.
- **Rich Showcase Profiles (`/provider/{slug}`)**:
  - Full portfolio gallery with native `<dialog>` lightbox viewer.
  - Service packages & transparent **UGX** pricing tiers with deliverables.
  - **Direct Quote / Booking Requests** delivered straight to the provider's in-app Kopii Leads Inbox + **1-click WhatsApp (`+256`)** and phone call actions.
  - Public client reviews & star ratings.
- **Provider Studio Dashboard (`/dashboard`)**:
  - Manage incoming **Client Leads & Quote Requests** (`new`, `contacted`, `booked`, `archived`) with 1-click WhatsApp reply.
  - Upload & manage **Portfolio Showcase** photographs (`JPG`, `PNG`, `WebP` validated via `finfo` + `getimagesize`).
  - Create & manage **UGX Service Packages**.
  - Customize profile bio, specialties, Ugandan location, avatar, cover banner, and booking availability.
- **Shop (`/shop`, `/product/{slug}`)**: any provider (a freelancer or a business) can list products with up to five photos, a price in UGX, condition and availability. Buyers browse by category, city and price, open a product, and message the seller; the message lands in the seller's **Client Leads** inbox (and by email) tagged with the product, exactly like a quote request. **There is no checkout or payment yet**; each product page says so. Sellers manage products on the **Shop Products** tab of their dashboard (add, edit, pause, delete). A product is public only while both it and its seller's listing are active.
- **Opportunities board (`/opportunities`)**: a client or business with a *confirmed email* posts a project, job, tender or supply request (budget range, place, closing date; at most 5 posts a day). Providers answer with a proposal (message, price, timeline; one per opportunity). The poster's phone and email are never shown publicly; proposals arrive in `/dashboard/opportunities/{id}` and by email, where the poster can shortlist, decline or choose one, then close it or mark it filled. "Open" is a status *and* a date, so old posts drop off the board without any scheduled job.
- **Admin Console (`/admin`)**, seven sections:
  - **Overview**: what needs a decision (reviews waiting, listings to verify, unconfirmed emails), the last 7 days, and recent admin activity. The *Admin* link in the nav carries a badge with the number of reviews waiting.
  - **Listings** (`/admin/providers`): search and filter by status, Verified, Featured. Each listing has a review page with the evidence for the three Verified checks (phone / WhatsApp, portfolio, pricing). **Verify** needs all three ticked and records who verified and when. Pause (with a reason) and Feature are explicit actions, never "flip whatever it is now".
  - **Products** and **Opportunities**: search and filter, then **Hide** (a reason is required and emailed to the owner) or **Reinstate**. Hiding a provider or suspending an account also hides their products and opportunities.
  - **Reviews**: waiting / published / all, approve or delete (two presses to delete).
  - **Users**: search and filter accounts, resend a confirmation email, **suspend** (reason required, emailed to them) and reinstate.
  - **Activity**: the audit log of every admin action, kept even after the thing it was about is deleted.
  - Providers are emailed when they are verified, paused, reactivated or suspended, and administrators are emailed when a review is waiting. A paused provider sees a banner on their dashboard.

---

## Email, Password Reset & Photos

- **Lead alerts**: every quote request (single provider or multi-provider hire brief) emails the provider's account address, with the client as `Reply-To`. The in-app Leads Inbox remains the source of truth.
- **Password reset**: `/forgot-password` → emailed single-use link (valid 60 minutes, stored only as a hash, rate-limited per account and per IP) → `/reset-password`. The account owner also gets a "your password was changed" email.
- **How email is sent**: PHP `mail()` on cPanel. Set the `mail` block in `app/config/env.php` (the installer writes it; see `env.example.php`). For inbox delivery, enable **SPF and DKIM** in cPanel → *Email Deliverability* and keep `from_email` on your own domain. In local mode (`app_env` = `local`) nothing is sent; messages are appended to `storage/mail.log`, which is where you'll find reset links while developing.
- **Photos**: uploads are re-encoded with GD to web size (max 1600 px portfolio / 1920 px cover / 640 px avatar), rotated upright, and stripped of all EXIF metadata (including GPS). A thumbnail is stored in a `thumbs/` folder beside each photo and used in cards and grids. Replacing a photo or deleting a portfolio item also deletes the old files. Needs the PHP **GD** extension (standard on cPanel); without it the original file is stored unchanged. Photos uploaded before this change keep working and simply have no thumbnail.

## Design system

All styling lives in `assets/css/app.css`; the rules are written at the top of the file.

- **One accent.** Brand blue (`--accent`) is the colour of every primary action, link and focus ring, and there is one primary button per screen. Green means status only (verified, available, success).
- **Scales, not one-off values.** Spacing `--space-1…9` (4px base), type `--text-xs…3xl`, radii `--radius-control` (6px) and `--radius-card` (12px), icons 16 / 20 / 24px. `svg_icon()` snaps any size to that scale and draws every icon with the same 1.5px line.
- **Nested corners:** inner radius = outer radius − padding (see `.hero-search`, `.mode-tabs`). Use `max(2px, calc(var(--r-outer) - var(--inset)))`.
- **Elevation is for floating layers only** (menus, the shortlist tray, dialogs). Static cards use a 1px border, not a shadow.
- **Text colour:** `--ink-3` is the lightest allowed text colour (5.8:1 on white). Don't use `--line*` or lighter greys for text.
- **Numbers** that should line up (prices, counts, ratings) take the `.tnum` class. Headings wrap `balance`, body copy `pretty`.
- **Loading:** submitting a form puts a spinner on its button and disables it (so a slow connection can't send a quote request twice). That behaviour is in `app.js`; the styles are `.btn.is-loading`.
- The older names (`--blue-*`, `--gray-*`, `--slate-*`) are aliases kept for existing rules. New CSS should use the semantic tokens.

## What "Verified" means

Anyone can list for free and goes live immediately; the **Verified** badge is a manual toggle in `/admin`. The site copy promises that the Kopii team has checked three things before toggling it, so please actually do them:

1. **Reachable** — the Ugandan phone / WhatsApp number works.
2. **Original work** — the portfolio looks like the provider's own work (no stock images or other people's photos).
3. **Clear pricing** — a starting rate or itemized UGX packages.

If you change the process, update the wording in `app/views/pages/home.php` (the "Verified Badge" section), `app/views/pages/about.php`, and `app/views/auth/register.php`. Testimonials on the home page come from `app/config/testimonials.php` (empty until you add real, permissioned quotes).

---

## Deploying to `kopii.online` (LiteSpeed / cPanel + MySQL)

1. **Upload to Root Domain (`public_html/`)**:
   - Zip the contents of this folder and extract them directly into `public_html/` on your LiteSpeed / cPanel server (so `https://kopii.online/` loads directly instead of `/wp/`).
   - Ensure hidden `.htaccess` files (`.htaccess`, `app/.htaccess`, `uploads/.htaccess`, `storage/.htaccess`, `database/.htaccess`) are included, and ensure `uploads/`, `storage/`, and `app/config/` are writable (`755` or `775`).
2. **Create a MySQL Database in cPanel**:
   - Open **cPanel → MySQL® Database Wizard**, create a database and user, and grant **ALL PRIVILEGES**.
3. **Run the 1-Click Web Installer**:
   - Visit **`https://kopii.online/install.php`** in your browser.
   - Enter your MySQL database name, username, password, and desired Admin email/password, then click **Install & Launch Kopii**.
   - The installer creates all `utf8mb4` InnoDB tables (`database/schema.mysql.sql`), seeds Ugandan categories (and, only if you tick the box, made-up sample providers), writes `app/config/env.php`, and automatically locks itself via `storage/installed.lock`.
   - It checks that `app/config` and `storage` are writable **before** touching the database, and only reports success once `env.php` really exists.
4. **Delete `install.php` from the server** once it says Kopii is live. Until the installer has run, whoever opens it first controls the installation, so run it right after uploading.

---

## Security notes

- **No silent fallback on a live site.** If `app/config/env.php` is missing, Kopii shows a "not set up yet" page (HTTP 503) instead of creating a demo database. The throw-away SQLite database with documented demo passwords is only used by `php -S` or from `127.0.0.1` / `::1`.
- **Demo shortcuts are local-only.** The one-click demo sign-in on `/login` only appears when `app_env` is `local`. Sample providers created by the web installer get a random password nobody knows.
- **Errors are never shown to visitors.** Anything unexpected is logged (cPanel → Errors, or your PHP error log) and visitors get a plain apology page. Only `app_env = local` shows details on screen.
- **Rate limiting.** Sign-in (per account and per connection), registration, quote requests, hire briefs, reviews, and password resets are throttled. Behind Cloudflare or another proxy, set `'trusted_ip_header'` in `app/config/env.php` (for Cloudflare, `'HTTP_CF_CONNECTING_IP'`); otherwise every visitor looks like the proxy and shares one allowance.
- **Email addresses must be confirmed** before they are used to find a client's earlier quote requests. New accounts get a confirmation link by email; opening a password-reset link also confirms the address. Without this, anyone could register somebody else's address and read their requests.
- **Review moderation.** A review from a signed-in person who really asked that provider for a quote through Kopii is published immediately. Every other review waits as *pending* in `/admin/reviews` and does not count toward ratings or ranking until you approve it (you also get an email). Providers cannot review themselves, and each account can review a provider once.
- **Suspending an account takes effect immediately.** The signed-in account is re-checked against the database on every request (`refresh_session_user()`), so a suspended person is signed out on their next click, cannot sign in again, and their listing is hidden. Reinstating brings the listing back *paused*; you make it public on purpose. Administrator accounts cannot be suspended from the console.
- **Every admin action is logged** in the `admin_log` table (who, what, when, and the note), shown on *Activity* and on each listing's page. Deleted reviews keep their text in the log.
- **Admin forms can only return to `/admin` pages.** The hidden `return` field is checked against a strict pattern, so it cannot be used to bounce an admin to another site.
- **Set `'support_email'`** in `app/config/env.php` so the emails about a paused listing or suspended account tell the provider where to write. Without it they say "get in touch with the Kopii team".
- **Files that must never be downloaded** (`.md`, `.sql`, `.sqlite`, `.log`, `.lock`, backups) are blocked by `.htaccess`, in addition to the blocked `app/`, `database/` and `storage/` folders.
- **Upgrading an existing site:** new columns and tables are added automatically the first time the site runs (`app/core/Migrator.php`; progress is remembered in `storage/schema.version`, currently version 3: the shop and opportunities tables, and `product_id` / `product_name` on `inquiries`; version 2 was account suspension, who-verified-when and the admin log). The database user needs the `ALTER` and `CREATE` privileges, which cPanel's "All Privileges" includes. The MySQL side of the migration has not been run against a real MySQL server yet, so after uploading check `/admin/activity` loads, and look at your PHP error log once.

## Running Locally

With native PHP 8+ installed:
```bash
php -S localhost:8080 index.php
```

Or without native PHP installed (via Node.js & `@php-wasm/cli`):
```bash
npx -y @php-wasm/cli -S localhost:8080 index.php
```
When `app/config/env.php` is not present, Kopii automatically initializes and seeds a local SQLite database at `storage/kopii.sqlite`.

### Demo Accounts (local development only)
These exist only in the local SQLite demo database. They are never created on a live install.

- **Photographer Studio:** `pearl@kopii.online` / `KopiiDemo2026!`
- **Client (posts opportunities):** `client@kopii.online` / `KopiiDemo2026!`
- **Platform Admin:** `admin@kopii.online` / `KopiiAdmin2026!`

The local demo database also holds **placeholder** products and opportunities (created by `Seeder::seedCommerceDemo()`, only when `app_env` is `local` on SQLite with the demo providers present, and only while the shop and board are still empty). The installer adds them only if you tick the sample-providers box.
