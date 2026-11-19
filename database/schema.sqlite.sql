-- Kopii Marketplace — SQLite Schema (Local Zero-Config Development Fallback)
PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    phone TEXT DEFAULT NULL,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'provider',
    avatar_path TEXT DEFAULT NULL,
    email_verified_at TEXT DEFAULT NULL,
    suspended_at TEXT DEFAULT NULL,
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    icon TEXT NOT NULL DEFAULT 'briefcase',
    description TEXT DEFAULT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS providers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL UNIQUE,
    category_id INTEGER NOT NULL,
    business_name TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    provider_type TEXT NOT NULL DEFAULT 'freelancer',
    headline TEXT NOT NULL,
    bio TEXT NOT NULL,
    city TEXT NOT NULL DEFAULT 'Kampala',
    neighborhood TEXT DEFAULT NULL,
    phone TEXT NOT NULL,
    whatsapp TEXT NOT NULL,
    public_email TEXT DEFAULT NULL,
    website TEXT DEFAULT NULL,
    instagram TEXT DEFAULT NULL,
    starting_price_ugx INTEGER NOT NULL DEFAULT 0,
    years_experience INTEGER NOT NULL DEFAULT 1,
    response_time TEXT NOT NULL DEFAULT 'Within 2 hours',
    specialties TEXT DEFAULT NULL,
    avatar_path TEXT DEFAULT NULL,
    cover_path TEXT DEFAULT NULL,
    is_verified INTEGER NOT NULL DEFAULT 0,
    verified_at TEXT DEFAULT NULL,
    verified_by INTEGER DEFAULT NULL,
    is_featured INTEGER NOT NULL DEFAULT 0,
    is_available INTEGER NOT NULL DEFAULT 1,
    status TEXT NOT NULL DEFAULT 'active',
    views_count INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS portfolio_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    provider_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    project_tag TEXT DEFAULT NULL,
    description TEXT DEFAULT NULL,
    image_path TEXT NOT NULL,
    client_location TEXT DEFAULT NULL,
    is_featured INTEGER NOT NULL DEFAULT 0,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS service_packages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    provider_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    price_ugx INTEGER NOT NULL DEFAULT 0,
    price_unit TEXT NOT NULL DEFAULT 'per session',
    turnaround TEXT DEFAULT NULL,
    description TEXT DEFAULT NULL,
    deliverables TEXT DEFAULT NULL,
    is_popular INTEGER NOT NULL DEFAULT 0,
    sort_order INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS inquiries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    provider_id INTEGER NOT NULL,
    client_user_id INTEGER DEFAULT NULL,
    package_id INTEGER DEFAULT NULL,
    client_name TEXT NOT NULL,
    client_email TEXT NOT NULL,
    client_phone TEXT NOT NULL,
    event_date TEXT DEFAULT NULL,
    event_location TEXT DEFAULT NULL,
    budget_ugx INTEGER DEFAULT NULL,
    project_details TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'new',
    provider_notes TEXT DEFAULT NULL,
    product_id INTEGER DEFAULT NULL,
    product_name TEXT DEFAULT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS reviews (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    provider_id INTEGER NOT NULL,
    reviewer_user_id INTEGER DEFAULT NULL,
    reviewer_name TEXT NOT NULL,
    reviewer_city TEXT DEFAULT NULL,
    service_hired TEXT DEFAULT NULL,
    rating INTEGER NOT NULL,
    comment TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'published',
    verified_request INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS password_resets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    token_hash TEXT NOT NULL UNIQUE,
    ip TEXT DEFAULT NULL,
    created_at TEXT NOT NULL,
    expires_at TEXT NOT NULL,
    used_at TEXT DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_password_resets_user ON password_resets (user_id, created_at);

CREATE TABLE IF NOT EXISTS rate_limits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bucket TEXT NOT NULL,
    subject TEXT NOT NULL,
    created_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_rate_limits_lookup ON rate_limits (bucket, subject, created_at);

CREATE TABLE IF NOT EXISTS email_verifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    token_hash TEXT NOT NULL UNIQUE,
    created_at TEXT NOT NULL,
    expires_at TEXT NOT NULL,
    used_at TEXT DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_email_verifications_user ON email_verifications (user_id, created_at);

-- Every action an administrator takes (verify, pause, suspend, delete a review...). target_label is a
-- snapshot of the name at the time, so the record still reads properly after the target is deleted.
CREATE TABLE IF NOT EXISTS admin_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    admin_user_id INTEGER DEFAULT NULL,
    action TEXT NOT NULL,
    target_type TEXT NOT NULL,
    target_id INTEGER DEFAULT NULL,
    target_label TEXT NOT NULL DEFAULT '',
    note TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL,
    FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_admin_log_created ON admin_log (created_at);
CREATE INDEX IF NOT EXISTS idx_admin_log_target ON admin_log (target_type, target_id);

-- The shop. Products belong to a provider profile (a freelancer or a business) and are bought by
-- contacting the seller: an enquiry goes into the same inbox as quote requests (inquiries.product_id).
CREATE TABLE IF NOT EXISTS product_categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    icon TEXT NOT NULL DEFAULT 'briefcase',
    sort_order INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    provider_id INTEGER NOT NULL,
    category_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    price_ugx INTEGER NOT NULL DEFAULT 0,
    price_unit TEXT NOT NULL DEFAULT 'each',
    item_condition TEXT NOT NULL DEFAULT 'new',
    stock_status TEXT NOT NULL DEFAULT 'in_stock',
    status TEXT NOT NULL DEFAULT 'active',
    views_count INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES product_categories(id) ON DELETE RESTRICT
);

CREATE INDEX IF NOT EXISTS idx_products_provider ON products (provider_id, status);
CREATE INDEX IF NOT EXISTS idx_products_category ON products (category_id, status);

CREATE TABLE IF NOT EXISTS product_images (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    product_id INTEGER NOT NULL,
    image_path TEXT NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_product_images_product ON product_images (product_id, sort_order);

-- The opportunities board. Anyone with a confirmed email can post what they need; providers answer
-- with a proposal. The poster's contact details are never shown publicly: proposals reach them
-- through the dashboard and by email.
CREATE TABLE IF NOT EXISTS opportunities (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    category_id INTEGER DEFAULT NULL,
    kind TEXT NOT NULL DEFAULT 'project',
    title TEXT NOT NULL,
    description TEXT NOT NULL,
    budget_min_ugx INTEGER DEFAULT NULL,
    budget_max_ugx INTEGER DEFAULT NULL,
    city TEXT NOT NULL DEFAULT 'Kampala',
    closes_on TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'open',
    views_count INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_opportunities_board ON opportunities (status, closes_on);
CREATE INDEX IF NOT EXISTS idx_opportunities_user ON opportunities (user_id, created_at);

CREATE TABLE IF NOT EXISTS opportunity_responses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    opportunity_id INTEGER NOT NULL,
    provider_id INTEGER NOT NULL,
    message TEXT NOT NULL,
    quote_ugx INTEGER DEFAULT NULL,
    timeline TEXT DEFAULT NULL,
    status TEXT NOT NULL DEFAULT 'new',
    created_at TEXT NOT NULL,
    UNIQUE (opportunity_id, provider_id),
    FOREIGN KEY (opportunity_id) REFERENCES opportunities(id) ON DELETE CASCADE,
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_opportunity_responses_provider ON opportunity_responses (provider_id, created_at);
