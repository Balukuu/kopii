<?php
defined('KOPII_APP') or exit('Direct access denied.');

class Seeder
{
    /**
     * @param ?string $demoPassword Password for the sample provider accounts. Null means the documented
     *                              demo password, which is only acceptable on a developer's own machine.
     *                              The web installer passes a random one so a live site never has
     *                              demo accounts with a known password.
     */
    public static function run(PDO $pdo, bool $includeDemoListings = true, array $adminOverride = [], ?string $demoPassword = null): void
    {
        $stmt = $pdo->query('SELECT COUNT(*) FROM categories');
        if ((int) $stmt->fetchColumn() > 0) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        // 1. Seed Categories
        $categories = [
            ['Photography & Video', 'photography-video', 'camera', 'Wedding, portrait, event, product, and drone photographers & videographers across Uganda.', 1],
            ['Graphic & Web Design', 'graphic-web-design', 'palette', 'Logo design, brand identity, print packaging, UI/UX, and custom websites.', 2],
            ['Fashion & Tailoring', 'fashion-tailoring', 'scissors', 'Bespoke suits, bridal couture, Gomesi & Kanzu stylists, and Kampala fashion houses.', 3],
            ['Events & Catering', 'events-catering', 'utensils', 'Wedding & Kwanjula caterers, tent & decor planners, cake artists, and corporate hospitality.', 4],
            ['Beauty & Makeup Artistry', 'beauty-makeup', 'sparkles', 'Bridal makeup artists, hair stylists, skincare specialists, and mobile beauty pros.', 5],
            ['Software & IT Solutions', 'software-it', 'code', 'Mobile apps, custom software, POS setup, networking, and digital marketing specialists.', 6],
            ['DJs, Live Music & Sound', 'music-entertainment', 'music', 'Event DJs, live bands, MCs, PA sound systems, and stage lighting.', 7],
            ['Interior Design & Decor', 'interior-decor', 'home', 'Home & office interiors, custom Ugandan timber furniture, and space styling.', 8],
            ['Construction & Architecture', 'construction-architecture', 'layers', 'Architectural plans, civil builders, solar installation, plumbing, and electrical.', 9],
            ['Transport & Logistics', 'transport-logistics', 'truck', 'Bridal car hire, safari & executive vans, courier delivery, and home moving.', 10],
        ];

        $catStmt = $pdo->prepare(
            'INSERT INTO categories (name, slug, icon, description, sort_order) VALUES (:name, :slug, :icon, :desc, :sort)'
        );
        $catIds = [];
        foreach ($categories as [$name, $slug, $icon, $desc, $sort]) {
            $catStmt->execute([
                ':name' => $name,
                ':slug' => $slug,
                ':icon' => $icon,
                ':desc' => $desc,
                ':sort' => $sort,
            ]);
            $catIds[$slug] = (int) $pdo->lastInsertId();
        }

        // 2. Seed Admin Account
        $adminName  = $adminOverride['full_name'] ?? 'Kopii Admin';
        $adminEmail = strtolower(trim($adminOverride['email'] ?? 'admin@kopii.online'));
        $adminPass  = $adminOverride['password'] ?? 'KopiiAdmin2026!';

        $userStmt = $pdo->prepare(
            'INSERT INTO users (full_name, email, phone, password_hash, role, created_at)
             VALUES (:name, :email, :phone, :hash, :role, :created)'
        );
        $userStmt->execute([
            ':name'    => $adminName,
            ':email'   => $adminEmail,
            ':phone'   => '+256772100100',
            ':hash'    => password_hash($adminPass, PASSWORD_DEFAULT),
            ':role'    => 'admin',
            ':created' => $now,
        ]);

        if (!$includeDemoListings) {
            self::markUsersVerified($pdo);
            return;
        }

        // Ensure fallback seed SVG/JPG placeholders exist if custom artwork is still generating
        self::ensureSeedFallbacks();

        // 3. Seed Demo Providers (Freelancers & Businesses in Uganda)
        $demoProviders = [
            [
                'user' => ['Sarah Namukasa', 'pearl@kopii.online', '+256774210340'],
                'category' => 'photography-video',
                'business_name' => 'Pearl Lens Studios',
                'slug' => 'pearl-lens-studios',
                'provider_type' => 'business',
                'headline' => 'Editorial Weddings, Kwanjula Stories & Commercial Photography in Kampala',
                'bio' => "Pearl Lens Studios is a full-service photography and cinema team based in Kololo, Kampala. Over the past 7 years, we have documented over 180 Ugandan weddings, Kwanjula & Kuhingira ceremonies, executive portraits, and commercial campaigns.\n\nWe blend natural golden-hour storytelling with crisp studio lighting so every skin tone, fabric texture, and candid moment looks timeless.",
                'city' => 'Kampala',
                'neighborhood' => 'Kololo',
                'phone' => '+256774210340',
                'whatsapp' => '+256774210340',
                'public_email' => 'bookings@pearllens.ug',
                'website' => 'https://kopii.online',
                'instagram' => '@pearllensug',
                'starting_price_ugx' => 450000,
                'years_experience' => 7,
                'response_time' => 'Within 30 minutes',
                'specialties' => 'Wedding Photography, Kwanjula & Kuhingira, Studio Portraits, Commercial Campaigns',
                'cover_path' => 'uploads/seed/hero-kampala-creatives.jpg',
                'is_verified' => 1,
                'is_featured' => 1,
                'views_count' => 418,
                'portfolio' => [
                    ['Lakeside Golden-Hour Wedding', 'Wedding', 'Full-day documentary wedding coverage overlooking Lake Victoria with warm natural light.', 'uploads/seed/portfolio-wedding-uganda.jpg', 'Entebbe', 1],
                    ['Founder Executive Portrait Series', 'Studio Portrait', 'Dramatic studio portrait session for a Kampala creative tech founder.', 'uploads/seed/portfolio-portrait-studio.jpg', 'Kololo, Kampala', 1],
                    ['Rwenzori Artisanal Coffee Campaign', 'Commercial Product', 'Commercial product and lifestyle photography for an export specialty coffee roaster.', 'uploads/seed/portfolio-product-coffee.jpg', 'Bugolobi, Kampala', 0],
                ],
                'packages' => [
                    ['Executive & Creative Portrait Session', 450000, 'per session', '3 business days', 'Ideal for entrepreneurs, corporate teams, graduations, and personal branding.', "90-minute studio or outdoor shoot\n15 high-end retouched high-res photos\n2 outfit changes\nOnline private client gallery", 0],
                    ['Kwanjula / Engagement Full Coverage', 1800000, 'per event', '7 business days', 'Complete photography coverage for traditional introductions and engagement ceremonies.', "2 senior photographers (up to 10 hours)\n250+ color-graded high-resolution images\n20 same-day WhatsApp preview highlights\nCustom flash drive + online gallery", 1],
                    ['Signature Wedding Photo + Highlight Film', 3500000, 'per event', '14 business days', 'Our flagship all-inclusive wedding storytelling package anywhere in Uganda.', "2 photographers + 1 cinema videographer\n400+ edited photos + 6-minute 4K highlight film\nComplimentary pre-wedding couple shoot\nLuxury hardcover photo book (30 pages)", 0],
                ],
                'reviews' => [
                    ['Grace & Ronald Mukasa', 'Kampala', 'Signature Wedding Package', 5, 'Sarah and the Pearl Lens team made us feel completely relaxed from morning prep in Muyenga to our reception at Serena. Seeing 20 edited previews the very next morning blew our families away!'],
                    ['David Ochieng', 'Entebbe', 'Commercial Brand Shoot', 5, 'Superb attention to lighting and detail. Our product catalog conversion jumped immediately after refreshing our photos with Pearl Lens Studios.'],
                ],
            ],
            [
                'user' => ['Daniel Kato', 'kato@kopii.online', '+256752884190'],
                'category' => 'photography-video',
                'business_name' => 'Kato Visuals',
                'slug' => 'kato-visuals',
                'provider_type' => 'freelancer',
                'headline' => 'Freelance Portrait, Fashion & Product Photographer in Ntinda',
                'bio' => "Hi, I'm Daniel Kato — an independent Kampala portrait, fashion lookbook, and commercial product photographer.\n\nWhether you need sharp LinkedIn headshots, lookbook imagery for your clothing brand, or mouth-watering menu photography for your restaurant, I bring portable studio lighting directly to your location or host you at my Ntinda studio.",
                'city' => 'Kampala',
                'neighborhood' => 'Ntinda',
                'phone' => '+256752884190',
                'whatsapp' => '+256752884190',
                'public_email' => 'daniel@katovisuals.ug',
                'website' => '',
                'instagram' => '@katovisuals_ug',
                'starting_price_ugx' => 250, // Wait -> 250000!
                'years_experience' => 4,
                'response_time' => 'Within 1 hour',
                'specialties' => 'Studio Portraits, Product Photography, Fashion Lookbooks, Corporate Headshots',
                'cover_path' => 'uploads/seed/portfolio-portrait-studio.jpg',
                'is_verified' => 1,
                'is_featured' => 1,
                'views_count' => 289,
                'portfolio' => [
                    ['Contemporary Kampala Blazer Editorial', 'Fashion & Portrait', 'Studio portrait lighting with cobalt rim accent for a menswear launch.', 'uploads/seed/portfolio-portrait-studio.jpg', 'Ntinda Studio', 1],
                    ['Single-Origin Ugandan Coffee Shoot', 'Product', 'Macro texture and pour-over lifestyle shots for e-commerce & Instagram.', 'uploads/seed/portfolio-product-coffee.jpg', 'Nakasero, Kampala', 1],
                    ['Atelier Lookbook Showcase', 'Lookbook', 'Natural-light lookbook session inside a local fashion designer workshop.', 'uploads/seed/portfolio-fashion-kampala.jpg', 'Kamwokya, Kampala', 0],
                ],
                'packages' => [
                    ['Express Headshot & Portrait Mini', 250000, 'per session', '48 hours', 'Fast, sharp studio or on-location portraits for professionals and creatives.', "45-minute guided session\n8 retouched high-resolution images\n1 backdrop or outdoor setting\n48-hour digital delivery", 1],
                    ['Brand & E-Commerce Product Pack', 600000, 'per project', '4 business days', 'Clean white-background and styled lifestyle shots for up to 15 products.', "Up to 15 products (2 angles each)\nStyled props & studio lighting\nWeb + print ready exports", 0],
                ],
                'reviews' => [
                    ['Brenda Auma', 'Kampala', 'Brand Product Pack', 5, 'Daniel shot 18 skincare and coffee items for our online shop in one afternoon. Crisp, vibrant colors and super fast 48-hour turnaround!'],
                    [' Timothy Ssempijja', 'Wakiso', 'Express Headshot Mini', 4, 'Great direction during the shoot even though I usually hate being in front of cameras. Highly recommend Kato Visuals.'],
                ],
            ],
            [
                'user' => ['ivan Mugisha', 'lakebreeze@kopii.online', '+256783512900'],
                'category' => 'photography-video',
                'business_name' => 'LakeBreeze Wedding Films & Photo',
                'slug' => 'lakebreeze-wedding-films',
                'provider_type' => 'freelancer',
                'headline' => 'Destination Lakeside Weddings, Kuhingira & Aerial Drone Cinema',
                'bio' => "Based right on the Entebbe peninsula, I specialize in outdoor garden weddings, lakeside elopements, western Ugandan Kuhingira celebrations, and licensed 4K drone cinematography.\n\nAvailable across Entebbe, Kampala, Jinja, Fort Portal, and Mbarara with zero travel surcharge within Wakiso & Kampala.",
                'city' => 'Entebbe',
                'neighborhood' => 'akiwogo / Botanical Rd',
                'phone' => '+256783512900',
                'whatsapp' => '+256783512900',
                'public_email' => 'ivan@lakebreezefilms.ug',
                'website' => '',
                'instagram' => '@lakebreezefilms',
                'starting_price_ugx' => 800000,
                'years_experience' => 6,
                'response_time' => 'Within 2 hours',
                'specialties' => 'Outdoor Weddings, 4K Cinema Films, Drone Aerials, Kuhingira Ceremonies',
                'cover_path' => 'uploads/seed/portfolio-wedding-uganda.jpg',
                'is_verified' => 1,
                'is_featured' => 0,
                'views_count' => 214,
                'portfolio' => [
                    ['Sunset Vows Over Lake Victoria', 'Outdoor Wedding', 'Golden-hour portraits and drone aerials along the Entebbe shoreline.', 'uploads/seed/portfolio-wedding-uganda.jpg', 'Entebbe', 1],
                    ['Garden Reception Marquee Lights', 'Event Cinema', 'Evening garden reception ambiance and speeches under warm string lights.', 'uploads/seed/portfolio-event-catering.jpg', 'Munyonyo, Kampala', 0],
                ],
                'packages' => [
                    ['Intimate Garden / Civil Wedding Coverage', 800000, 'per event', '5 business days', 'Tailored for civil weddings, kukyala, and intimate lakeside receptions.', "Up to 5 hours coverage\n150+ edited high-res photos\nAerial drone couple portraits included", 1],
                    ['Full Documentary Wedding Cinema + Photo', 2400000, 'per event', '10 business days', 'Full-day photo + 4K cinematic film from bridal prep to evening dancefloor.', "Full day (12 hours) photo + video\n8-minute 4K wedding film + 60s social reel\n300+ high-res edited photographs", 0],
                ],
                'reviews' => [
                    ['Anita & Brian Tumwine', 'Mbarara', 'Full Documentary Cinema', 5, 'Ivan travelled to Mbarara for our Kuhingira and Entebbe for our wedding. The drone shots and audio clarity during our vows still bring tears to our eyes.'],
                ],
            ],
            [
                'user' => ['Amina Nabirye', 'amina@kopii.online', '+256701645220'],
                'category' => 'fashion-tailoring',
                'business_name' => 'Nakasero Atelier & Bespoke',
                'slug' => 'nakasero-atelier-bespoke',
                'provider_type' => 'business',
                'headline' => 'Custom Suits, Modern Bridal Couture & East African Tailoring',
                'bio' => "Nakasero Atelier crafts made-to-measure menswear, bridal reception gowns, groomsmen ensembles, and contemporary Kitenge/linen resort pieces in the heart of Kampala.\n\nEvery garment includes a private fabric consultation, precision measurements, and two dedicated fitting sessions.",
                'city' => 'Kampala',
                'neighborhood' => 'Nakasero',
                'phone' => '+256701645220',
                'whatsapp' => '+256701645220',
                'public_email' => 'studio@nakaseroatelier.ug',
                'website' => '',
                'instagram' => '@nakaseroatelier',
                'starting_price_ugx' => 380000,
                'years_experience' => 8,
                'response_time' => 'Within 1 hour',
                'specialties' => 'Bespoke Suits, Bridal Changing Gowns, Groomsmen Sets, Kitenge Couture',
                'cover_path' => 'uploads/seed/portfolio-fashion-kampala.jpg',
                'is_verified' => 1,
                'is_featured' => 1,
                'views_count' => 332,
                'portfolio' => [
                    ['Kampala Capsule Tailoring Collection', 'Bespoke Menswear & Womenswear', 'Hand-finished structured blazers and contemporary East African silhouettes.', 'uploads/seed/portfolio-fashion-kampala.jpg', 'Nakasero Atelier', 1],
                ],
                'packages' => [
                    ['Bespoke 2-Piece Executive Suit', 650000, 'per project', '10 business days', 'Premium wool-blend fabric included, custom lining, and monogramming.', "Fabric consultation & body measurement\n2 tailored fittings\nHand-pressed garment bag delivery", 1],
                    ['Custom Reception / Changing Dress', 550000, 'starting from', '14 business days', 'Designed around your silhouette for Kwanjula, kukyala, or wedding reception.', "Custom sketch & fabric sourcing\nBeadwork & corsetry detailing\n2 private studio fittings", 0],
                ],
                'reviews' => [
                    ['Kenneth Byaruhanga', 'Kampala', 'Groomsmen 5-Suit Set', 5, 'Amina dressed me and all four groomsmen. Every single suit fit like a glove on the first fitting!'],
                ],
            ],
            [
                'user' => ['Chef Patrick Okello', 'nilesavour@kopii.online', '+256776990310'],
                'category' => 'events-catering',
                'business_name' => 'Nile Savour Catering & Events',
                'slug' => 'nile-savour-catering',
                'provider_type' => 'business',
                'headline' => 'Luxury Wedding Buffets, Corporate Hospitality & Garden Event Styling',
                'bio' => "From 50-guest executive luncheons in Bugolobi to 600-guest garden weddings in Kampala and Jinja, Nile Savour combines elevated Ugandan favorites (luwombo, smoked tilapia, spiced goat) with continental live-cooking stations and full table decor.",
                'city' => 'Jinja',
                'neighborhood' => 'Nile Crescent & Kampala Branch',
                'phone' => '+256776990310',
                'whatsapp' => '+256776990310',
                'public_email' => 'events@nilesavour.ug',
                'website' => '',
                'instagram' => '@nilesavourug',
                'starting_price_ugx' => 35000,
                'years_experience' => 9,
                'response_time' => 'Within 2 hours',
                'specialties' => 'Wedding Catering, Corporate Cocktails, Live BBQ Stations, Marquee Decor',
                'cover_path' => 'uploads/seed/portfolio-event-catering.jpg',
                'is_verified' => 1,
                'is_featured' => 1,
                'views_count' => 275,
                'portfolio' => [
                    ['Garden Marquee Gourmet Buffet & Styling', 'Wedding Catering', 'Full buffet service, gold chafing stations, floral table styling, and uniformed service team.', 'uploads/seed/portfolio-event-catering.jpg', 'Kampala & Jinja', 1],
                ],
                'packages' => [
                    ['Corporate Executive Buffet (Min 40 Guests)', 35000, 'starting from', 'Booked 5+ days ahead', 'Per-plate rate including 3 meats, traditional & continental sides, fresh tropical fruit & soft drinks.', "Chafing dishes & linen setup\nUniformed servers\nComplimentary tasting for 100+ guests", 1],
                    ['Signature Wedding Banquet & Cocktail Hour', 55000, 'starting from', 'Booked 14+ days ahead', 'Per-guest luxury dining experience with welcome mocktails, canapés, and gourmet buffet.', "Welcome tropical juice & samosa bar\nFull Ugandan + Continental buffet\n porcelain crockery, glassware & service staff", 0],
                ],
                'reviews' => [
                    ['Martha Namono', 'Jinja', '250-Guest Lakeside Reception', 5, 'Food stayed piping hot, portions were generous, and guests are still talking about Chef Patrick’s chicken luwombo!'],
                ],
            ],
            [
                'user' => ['Trevor Mwebaze', 'cranecraft@kopii.online', '+256759402118'],
                'category' => 'graphic-web-design',
                'business_name' => 'CraneCraft Digital Studio',
                'slug' => 'cranecraft-digital-studio',
                'provider_type' => 'freelancer',
                'headline' => 'Brand Identity, Packaging Design & High-Converting Websites',
                'bio' => "Independent brand & web designer helping Ugandan SMEs, agribusinesses, hotels, and startups look world-class.\n\nI craft memorable logos, UNBS-compliant product packaging labels, pitch decks, and fast mobile-friendly websites.",
                'city' => 'Mbarara',
                'neighborhood' => 'Kamukuzi (Serves Nationwide)',
                'phone' => '+256759402118',
                'whatsapp' => '+256759402118',
                'public_email' => 'hello@cranecraft.ug',
                'website' => '',
                'instagram' => '@cranecraftug',
                'starting_price_ugx' => 300000,
                'years_experience' => 5,
                'response_time' => 'Within 1 hour',
                'specialties' => 'Logo & Brand Identity, Product Packaging, Company Profiles, Web Design',
                'cover_path' => 'uploads/seed/hero-kampala-creatives.jpg',
                'is_verified' => 0,
                'is_featured' => 0,
                'views_count' => 164,
                'portfolio' => [
                    ['Export Coffee Packaging & Brand System', 'Packaging Design', 'Pouch label, typography, and roast-level color system for Ugandan Arabica coffee.', 'uploads/seed/portfolio-product-coffee.jpg', 'Mbarara & Kampala', 1],
                ],
                'packages' => [
                    ['Complete Brand Identity Kit', 450000, 'per project', '5 business days', 'Everything a growing Ugandan business needs for print and social media.', "3 initial logo concepts\nVector source files (AI, PDF, PNG, SVG)\nLetterhead, business card & social media kit", 1],
                    ['Modern Business Website (5 Pages)', 1200000, 'per project', '10 business days', 'Mobile-first website with WhatsApp integration, SEO setup, and easy content updates.', "Custom responsive UI design\nWhatsApp & inquiry lead forms\n1-hour handover training", 0],
                ],
                'reviews' => [
                    ['Julius Kansiime', 'Mbarara', 'Packaging & Brand Kit', 5, 'Trevor redesigned our honey and coffee labels and supermarkets in Kampala accepted our products on the first pitch!'],
                ],
            ],
        ];

        // Fix Kato's starting price typo check: make sure it's 250000!
        $demoProviders[0]['avatar_path'] = 'uploads/seed/talent-sarah.jpg';
        $demoProviders[1]['starting_price_ugx'] = 250000;
        $demoProviders[1]['avatar_path'] = 'uploads/seed/talent-daniel.jpg';
        $demoProviders[2]['user'][0] = 'Ivan Mugisha';
        $demoProviders[2]['neighborhood'] = 'Kiwafu / Botanical Rd';
        $demoProviders[3]['avatar_path'] = 'uploads/seed/talent-amina.jpg';
        $demoProviders[4]['avatar_path'] = 'uploads/seed/talent-patrick.jpg';
        $demoProviders[5]['avatar_path'] = 'uploads/seed/talent-trevor.jpg';
        $demoProviders[5]['is_featured'] = 1;

        $provStmt = $pdo->prepare(
            'INSERT INTO providers (
                user_id, category_id, business_name, slug, provider_type, headline, bio,
                city, neighborhood, phone, whatsapp, public_email, website, instagram,
                starting_price_ugx, years_experience, response_time, specialties,
                avatar_path, cover_path, is_verified, is_featured, is_available, status,
                views_count, created_at, updated_at
            ) VALUES (
                :uid, :cid, :bname, :slug, :ptype, :headline, :bio,
                :city, :neighborhood, :phone, :whatsapp, :pemail, :web, :ig,
                :price, :exp, :resp, :spec,
                :avatar, :cover, :ver, :feat, 1, :status,
                :views, :created, :updated
            )'
        );

        $portStmt = $pdo->prepare(
            'INSERT INTO portfolio_items (provider_id, title, project_tag, description, image_path, client_location, is_featured, sort_order, created_at)
             VALUES (:pid, :title, :tag, :desc, :img, :loc, :feat, :sort, :created)'
        );

        $pkgStmt = $pdo->prepare(
            'INSERT INTO service_packages (provider_id, name, price_ugx, price_unit, turnaround, description, deliverables, is_popular, sort_order)
             VALUES (:pid, :name, :price, :unit, :turn, :desc, :deliv, :pop, :sort)'
        );

        $revStmt = $pdo->prepare(
            'INSERT INTO reviews (provider_id, reviewer_name, reviewer_city, service_hired, rating, comment, created_at)
             VALUES (:pid, :rname, :rcity, :service, :rating, :comment, :created)'
        );

        $firstProviderId = null;

        foreach ($demoProviders as $p) {
            [$uName, $uEmail, $uPhone] = $p['user'];
            $userStmt->execute([
                ':name'    => $uName,
                ':email'   => $uEmail,
                ':phone'   => $uPhone,
                ':hash'    => password_hash($demoPassword ?? 'KopiiDemo2026!', PASSWORD_DEFAULT),
                ':role'    => 'provider',
                ':created' => $now,
            ]);
            $uid = (int) $pdo->lastInsertId();

            $provStmt->execute([
                ':uid'          => $uid,
                ':cid'          => $catIds[$p['category']],
                ':bname'        => $p['business_name'],
                ':slug'         => $p['slug'],
                ':ptype'        => $p['provider_type'],
                ':headline'     => $p['headline'],
                ':bio'          => $p['bio'],
                ':city'         => $p['city'],
                ':neighborhood' => $p['neighborhood'],
                ':phone'        => $p['phone'],
                ':whatsapp'     => $p['whatsapp'],
                ':pemail'       => $p['public_email'],
                ':web'          => $p['website'],
                ':ig'           => $p['instagram'],
                ':price'        => $p['starting_price_ugx'],
                ':exp'          => $p['years_experience'],
                ':resp'         => $p['response_time'],
                ':spec'         => $p['specialties'],
                ':avatar'       => $p['avatar_path'] ?? null,
                ':cover'        => $p['cover_path'],
                ':ver'          => $p['is_verified'],
                ':feat'         => $p['is_featured'],
                ':status'       => 'active',
                ':views'        => $p['views_count'],
                ':created'      => $now,
                ':updated'      => $now,
            ]);
            $pid = (int) $pdo->lastInsertId();
            if ($firstProviderId === null) {
                $firstProviderId = $pid;
            }

            foreach ($p['portfolio'] as $idx => [$title, $tag, $desc, $img, $loc, $feat]) {
                $portStmt->execute([
                    ':pid'     => $pid,
                    ':title'   => $title,
                    ':tag'     => $tag,
                    ':desc'    => $desc,
                    ':img'     => $img,
                    ':loc'     => $loc,
                    ':feat'    => $feat,
                    ':sort'    => $idx + 1,
                    ':created' => $now,
                ]);
            }

            foreach ($p['packages'] as $idx => [$name, $price, $unit, $turn, $desc, $deliv, $pop]) {
                $pkgStmt->execute([
                    ':pid'   => $pid,
                    ':name'  => $name,
                    ':price' => $price,
                    ':unit'  => $unit,
                    ':turn'  => $turn,
                    ':desc'  => $desc,
                    ':deliv' => $deliv,
                    ':pop'   => $pop,
                    ':sort'  => $idx + 1,
                ]);
            }

            foreach ($p['reviews'] as [$rname, $rcity, $service, $rating, $comment]) {
                $revStmt->execute([
                    ':pid'     => $pid,
                    ':rname'   => $rname,
                    ':rcity'   => $rcity,
                    ':service' => $service,
                    ':rating'  => $rating,
                    ':comment' => $comment,
                    ':created' => $now,
                ]);
            }
        }

        // Seed 2 realistic inquiries for Pearl Lens Studios so the demo inbox is alive
        if ($firstProviderId !== null) {
            $inqStmt = $pdo->prepare(
                'INSERT INTO inquiries (provider_id, client_name, client_email, client_phone, event_date, event_location, budget_ugx, project_details, status, created_at)
                 VALUES (:pid, :cname, :cemail, :cphone, :edate, :eloc, :budget, :details, :status, :created)'
            );
            $inqStmt->execute([
                ':pid'     => $firstProviderId,
                ':cname'   => 'Patricia Nakato',
                ':cemail'  => 'patricia.nakato@example.com',
                ':cphone'  => '+256772550890',
                ':edate'   => date('Y-m-d', strtotime('+28 days')),
                ':eloc'    => 'Munyonyo Commonwealth Resort, Kampala',
                ':budget'  => 3500000,
                ':details' => 'Hello Pearl Lens! We loved your Lakeside Golden-Hour Wedding album. We are hosting a 250-guest wedding in Munyonyo next month and would love to check your availability for the Signature Wedding Photo + Highlight Film package.',
                ':status'  => 'new',
                ':created' => $now,
            ]);
            $inqStmt->execute([
                ':pid'     => $firstProviderId,
                ':cname'   => 'Samuel Opio',
                ':cemail'  => 's.opio@example.com',
                ':cphone'  => '+256754119200',
                ':edate'   => date('Y-m-d', strtotime('+10 days')),
                ':eloc'    => 'Kololo, Kampala',
                ':budget'  => 600000,
                ':details' => 'Looking for executive team headshots for 6 leadership members at our Kololo office.',
                ':status'  => 'contacted',
                ':created' => $now,
            ]);
        }

        self::markUsersVerified($pdo);
    }

    /**
     * Accounts created here (the administrator and sample providers) were not self-service sign-ups,
     * so their email addresses count as confirmed.
     */
    private static function markUsersVerified(PDO $pdo): void
    {
        try {
            $pdo->prepare('UPDATE users SET email_verified_at = :now WHERE email_verified_at IS NULL')
                ->execute([':now' => date('Y-m-d H:i:s')]);
        } catch (Throwable) {
            // Older schema without the column: the Migrator adds it and handles administrators.
        }
    }

    /**
     * Generates lightweight gradient JPEG placeholders via GD only for any seed filename
     * not yet present on disk, ensuring zero broken images under any setup.
     */
    private static function ensureSeedFallbacks(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            return;
        }
        $seedDir = KOPII_ROOT . '/uploads/seed';
        if (!is_dir($seedDir)) {
            mkdir($seedDir, 0775, true);
        }

        $files = [
            'hero-kampala-creatives.jpg'  => [1280, 720, [11, 31, 77], [23, 92, 211], 'Kopii Creative Studio Showcase'],
            'portfolio-wedding-uganda.jpg'=> [1200, 900, [36, 26, 58], [217, 155, 67], 'Lakeside Wedding Photography'],
            'portfolio-portrait-studio.jpg'=>[1200, 900, [10, 22, 54], [11, 92, 255], 'Kampala Editorial Portrait'],
            'portfolio-product-coffee.jpg'=> [1200, 900, [42, 28, 20], [194, 132, 74], 'Ugandan Specialty Coffee Shoot'],
            'portfolio-fashion-kampala.jpg'=>[1200, 900, [25, 38, 66], [224, 169, 95], 'Bespoke Kampala Fashion Atelier'],
            'portfolio-event-catering.jpg'=> [1200, 900, [18, 45, 42], [212, 163, 89], 'Luxury Garden Event & Catering'],
        ];

        foreach ($files as $name => [$w, $h, $c1, $c2, $label]) {
            $path = $seedDir . '/' . $name;
            if (is_file($path) && filesize($path) > 1024) {
                continue;
            }
            $im = imagecreatetruecolor($w, $h);
            for ($y = 0; $y < $h; $y++) {
                $ratio = $y / max(1, $h - 1);
                $r = (int) round($c1[0] + ($c2[0] - $c1[0]) * $ratio);
                $g = (int) round($c1[1] + ($c2[1] - $c1[1]) * $ratio);
                $b = (int) round($c1[2] + ($c2[2] - $c1[2]) * $ratio);
                $col = imagecolorallocate($im, $r, $g, $b);
                imageline($im, 0, $y, $w, $y, $col);
            }
            $white = imagecolorallocate($im, 255, 255, 255);
            imagestring($im, 5, 48, (int) ($h / 2) - 8, $label, $white);
            imagejpeg($im, $path, 86);
            unset($im);
        }
    }
}
