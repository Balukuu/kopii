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
  - **Edit** (not just add and delete) portfolio items and service packages, including swapping a work photo.
  - Customize profile bio, specialties, Ugandan location, avatar, cover banner, and booking availability.
- **Built for how Ugandans actually hire** (market-fit pass, schema v5):
  - **Area, not just city**: Explore has an *Area or Neighbourhood* box (suggestions for Kampala's divisions and common places) that matches where a provider is based *and* the places they list under **Other places you serve** (new `providers.service_areas`, asked at sign-up and in the profile editor). Provider pages show "Based in", "Also serves" and "On Kopii since".
  - **Verification you can see**: a Verified provider's page shows the date and the three checks ("Phone number confirmed, Portfolio reviewed, Pricing is clear"); an unverified one says "Not verified yet" and tells the customer to ask for past work. Providers see a **Get Verified** checklist (phone, 3+ real photos, clear pricing) on their dashboard overview.
  - **Shorter request forms**: a quote request, product message or hire brief needs only a name, a phone number and what is wanted. Email is optional (replies go by phone or WhatsApp; the lead alert shows "not given").
  - **Safety tips (`/safety`)**: written for services and Mobile Money (check real past work, agree the scope in writing, pay in steps, check the recipient name, never pay to unlock a job, providers should check their own balance), linked from the footer, every listing, the quote forms and the Report box.
  - **Everyday categories**: Home Repairs & Maintenance, Solar & Electrical, Cleaning & Home Services and Agricultural Services were added next to the original ten (the migration only adds a category whose slug is missing, and the admin can rename or delete them under *Admin > Categories*). A category nobody has joined yet shows an invitation to list or to post an opportunity.
  - **Lighter pages**: text is compressed by the server (`mod_deflate`; the CSS is about 91 KB, roughly 17 KB compressed), photos are lazy-loaded, and CSS/JS addresses carry a `?v=` version so a deploy reaches returning visitors at once instead of after the 7-day cache.
  - **Admin**: *Listings* has a **Thin portfolio** filter (fewer than 3 photos) to review the likeliest low-effort or fake profiles first.
- **Account settings (`/account`, any signed-in person)**: change name and phone, change the sign-in email (asks for the current password, sends a confirmation link to the new address and a notice to the old one), change the password (signs out every other device), and **delete the account** (password plus typing DELETE). Deleting removes the listing, photos, products, opportunities, proposals and the reviews the person wrote; quote requests they sent stay in those providers' inboxes, unlinked from the account. Administrators cannot delete themselves here.
- **Terms, Privacy and Contact (`/terms`, `/privacy`, `/contact`)**: linked from the footer and from the register, quote, hire and enquiry forms. The contact form and every **Report this listing** box (provider, product and opportunity pages) are saved to the database first and emailed second, so nothing is lost if mail fails.
- **Search engines and sharing**: `/robots.txt` and `/sitemap.xml` are generated (listings, products and open opportunities; a local copy disallows everything), every page has a canonical address and Open Graph / Twitter tags so a shared link on WhatsApp shows a title, description and photo, and private areas (admin, dashboard, account, sign-in, reset links) are marked `noindex`.
- **Shop (`/shop`, `/product/{slug}`)**: any provider (a freelancer or a business) can list products with up to five photos, a price in UGX, condition and availability. Buyers browse by category, city and price, open a product, and message the seller; the message lands in the seller's **Client Leads** inbox (and by email) tagged with the product, exactly like a quote request. **There is no checkout or payment yet**; each product page says so. Sellers manage products on the **Shop Products** tab of their dashboard (add, edit, pause, delete). A product is public only while both it and its seller's listing are active.
- **Opportunities board (`/opportunities`)**: a client or business with a *confirmed email* posts a project, job, tender or supply request (budget range, place, closing date; at most 5 posts a day). Providers answer with a proposal (message, price, timeline; one per opportunity). The poster's phone and email are never shown publicly; proposals arrive in `/dashboard/opportunities/{id}` and by email, where the poster can shortlist, decline or choose one, then close it or mark it filled. "Open" is a status *and* a date, so old posts drop off the board without any scheduled job.
- **Admin Console (`/admin`)**, nine sections:
  - **Overview**: what needs a decision (messages and reports, reviews waiting, listings to verify, unconfirmed emails), a **go-live checklist** (see below), the last 7 days, and recent admin activity. The *Admin* link in the nav carries a badge with the number of reviews and messages waiting.
  - **Inbox** (`/admin/inbox`): contact messages and reported listings, with a link to the page and to its admin page, and "mark as dealt with" (with a note).
  - **Categories** (`/admin/categories`): add, rename, reorder and delete the service categories (what providers are listed under) and the shop categories. A category's web address is fixed when it is made; one that is in use cannot be deleted.
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

- **One font, one outside host.** Pages use Plus Jakarta Sans (weights 300 to 700), loaded from Google Fonts: the only third-party host the site talks to, and the only one the Content-Security-Policy allows for fonts and styles. There is no Adobe Fonts / Typekit kit to license or keep alive. Self-hosting the font files later would remove even that.
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

## Plans, fees and Featured

Admin console, **Plans & fees** (`/admin/plans`). Kopii does **not** take payment through the site yet, so this is a manual workflow:

1. A business pays you by Mobile Money (outside the site).
2. Open its listing in `/admin/providers/{id}`, card **Plan and featuring**: choose the plan, leave the end date empty to use the plan's length (counted from today) or type one, and put the Mobile Money reference in the note. Saving emails the owner and writes the reference to the Activity log.
3. When the end date passes the listing falls back to the free default plan by itself. Nothing is deleted, and the provider's dashboard says the plan ended.

What the pieces do:

- **Plans** (`plans` table). The default plan (*Basic*, free) always exists and cannot be retired or priced. A paid plan has a price (0 is allowed, for a free founding-member period), the months that price covers, and an optional product limit. A plan with no limit uses the site-wide limit (`ProductModel::MAX_PER_PROVIDER`, 100); the limit is enforced when a provider adds a shop product. A retired plan cannot be given to more listings; listings already on it keep it until their end date.
- **Featured until a date** (`providers.featured_until`). Featured listings rank first in search and in the home spotlight and carry a visible "Featured" label. With an end date the spot ends by itself; with none it stays until removed (the old behaviour).
- **Commission rates** (`platform_settings`). Two numbers, services and products, both **10% by default**, edited on the same page. **They are stored for the booking flow and are not charged or shown to visitors.** `FeeModel::commissionFor($amount, 'service'|'product')` is the one place the arithmetic lives, ready for when bookings and checkout are paid through Kopii.
- **Wording.** Public pages must not quote a commission or promise "no commission" while the model is undecided: say "free to browse and contact" and "free to list at launch", and keep `app/views/pages/terms.php` section 2 in step with what is really charged. Update the Terms date in `PageController::LEGAL_UPDATED` when you change it.

Migrator version 6 adds `providers.featured_until`, `plan_id`, `plan_expires_on` and the two tables, and seeds the Basic plan and the two rates.

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
5. **Sign in as the administrator and open `/admin`: the "Is the site ready to go live?" panel** checks the things that usually go wrong on a first deploy and says what to do about each (HTTPS, `app_url`, a leftover `install.php`, the demo admin password, MySQL vs SQLite, outgoing email, folder permissions, PHP extensions, Cloudflare). Fix every red *Fix* item. Then press **Send a test email to me** and confirm it reaches your inbox (not spam): lead alerts, password resets and confirmation links all depend on it.
6. **Check the legal pages.** `/terms` and `/privacy` are plain-language drafts written from what the site actually does. They are not legal advice: have a lawyer read them (and check whether Kopii must register with Uganda's Personal Data Protection Office) before launch, and change `LEGAL_UPDATED` in `app/controllers/PageController.php` whenever you edit them. Set `'support_email'` in `env.php` so the Contact page shows a real address.

---

## Security notes

- **No silent fallback on a live site.** If `app/config/env.php` is missing, Kopii shows a "not set up yet" page (HTTP 503) instead of creating a demo database. The throw-away SQLite database with documented demo passwords is only used by `php -S` or from `127.0.0.1` / `::1`.
- **Demo shortcuts are local-only.** The one-click demo sign-in on `/login` only appears when `app_env` is `local`. Sample providers created by the web installer get a random password nobody knows.
- **Errors are never shown to visitors.** Anything unexpected is logged (cPanel → Errors, or your PHP error log) and visitors get a plain apology page. Only `app_env = local` shows details on screen.
- **Rate limiting.** Sign-in (per account and per connection), registration, quote requests, hire briefs, reviews, and password resets are throttled. Behind Cloudflare or another proxy, set `'trusted_ip_header'` in `app/config/env.php` (for Cloudflare, `'HTTP_CF_CONNECTING_IP'`); otherwise every visitor looks like the proxy and shares one allowance.
- **Email addresses must be confirmed** before they are used to find a client's earlier quote requests. New accounts get a confirmation link by email; opening a password-reset link also confirms the address. Without this, anyone could register somebody else's address and read their requests.
- **Browser protections on every page.** A Content-Security-Policy lets scripts run only from the site itself (inline `<script>` and third-party scripts are refused), restricts where fonts, styles and images may come from, and stops other sites framing or posting forms to Kopii. On a live site served over HTTPS it also sends `Strict-Transport-Security` (180 days) and `upgrade-insecure-requests`. Both are tunable in `env.php` (`'csp'`, `'hsts_max_age'`). If you add a new third-party script, font or image host, add it to the policy in `send_security_headers()` (`app/core/Helpers.php`) and try it with `'csp' => 'report-only'` first.
- **A password change signs out every other device.** Each session remembers a fingerprint of the password it was opened with and is re-checked on every request. This closes the "pre-registered address" trick: if someone creates an account with an address that is not theirs and stays signed in, the real owner taking the account over with *Forgot your password?* also ends the squatter's session. (An address can still be registered by a stranger first; the owner recovers it through the reset email, which proves they hold the mailbox.)
- **Review moderation.** A review from a signed-in person who really asked that provider for a quote through Kopii is published immediately. Every other review waits as *pending* in `/admin/reviews` and does not count toward ratings or ranking until you approve it (you also get an email). Providers cannot review themselves, and each account can review a provider once.
- **Suspending an account takes effect immediately.** The signed-in account is re-checked against the database on every request (`refresh_session_user()`), so a suspended person is signed out on their next click, cannot sign in again, and their listing is hidden. Reinstating brings the listing back *paused*; you make it public on purpose. Administrator accounts cannot be suspended from the console.
- **Every admin action is logged** in the `admin_log` table (who, what, when, and the note), shown on *Activity* and on each listing's page. Deleted reviews keep their text in the log.
- **Admin forms can only return to `/admin` pages.** The hidden `return` field is checked against a strict pattern, so it cannot be used to bounce an admin to another site.
- **Set `'support_email'`** in `app/config/env.php` (the installer asks for it). It is shown on the Contact page, named in the emails about a paused listing or suspended account, and is where contact messages and reports are emailed. Without it they go to every administrator, and the emails say "get in touch with the Kopii team". Everything is kept in *Admin → Inbox* either way.
- **Reports and contact messages are rate limited and have a hidden spam trap field.** An anonymous report never tells the listing's owner who sent it.
- **Files that must never be downloaded** (`.md`, `.sql`, `.sqlite`, `.log`, `.lock`, backups) are blocked by `.htaccess`, in addition to the blocked `app/`, `database/` and `storage/` folders.
- **Upgrading an existing site:** new columns and tables are added automatically the first time the site runs (`app/core/Migrator.php`; progress is remembered in `storage/schema.version`, currently version 4: the `support_messages` table for the contact form and reports; version 3 was the shop and opportunities tables, and `product_id` / `product_name` on `inquiries`; version 2 was account suspension, who-verified-when and the admin log). The database user needs the `ALTER` and `CREATE` privileges, which cPanel's "All Privileges" includes. The MySQL side of the migration has not been run against a real MySQL server yet, so after uploading check `/admin/activity` loads, and look at your PHP error log once.

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
