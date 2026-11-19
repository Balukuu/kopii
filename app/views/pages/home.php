<?php
defined('KOPII_APP') or exit('Direct access denied.');

// PLACEHOLDER CONTENT: the people, studios and client names below are sample data for the hero.
// Replace them with real providers and real, permissioned client names before launch.
$trustedClients = require KOPII_ROOT . '/app/config/clients.php';
$clientByKey = [];
foreach ($trustedClients as $tc) {
    $clientByKey[$tc['name']] = $tc;
}

$heroMetaBySlug = [
    'nakasero-atelier-bespoke' => [
        'person'   => 'Amina Nabirye',
        'role'     => 'Bespoke Couture Designer',
        'vertical' => 'Fashion & Couture',
        'client'   => 'Speke Resort Munyonyo',
        'portrait' => 'uploads/seed/talent-amina.jpg',
        'pin_x'    => '56.8%',
        'pin_y'    => '57.2%',
    ],
    'pearl-lens-studios' => [
        'person'   => 'Sarah Namukasa',
        'role'     => 'Brand Photographer',
        'vertical' => 'Photography',
        'client'   => 'Serena Hotels',
        'portrait' => 'uploads/seed/talent-sarah.jpg',
        'pin_x'    => '57.0%',
        'pin_y'    => '57.0%',
    ],
    'kato-visuals' => [
        'person'   => 'Daniel Kato',
        'role'     => 'Portrait Photographer',
        'vertical' => 'Photography',
        'client'   => 'Rwenzori',
        'portrait' => 'uploads/seed/talent-daniel.jpg',
        'pin_x'    => '57.3%',
        'pin_y'    => '56.8%',
    ],
    'cranecraft-digital-studio' => [
        'person'   => 'Trevor Mwebaze',
        'role'     => 'Brand & UI/UX Designer',
        'vertical' => 'Design',
        'client'   => 'Centenary Bank',
        'portrait' => 'uploads/seed/talent-trevor.jpg',
        'pin_x'    => '55.8%',
        'pin_y'    => '58.4%',
    ],
    'nile-savour-catering' => [
        'person'   => 'Patrick Okello',
        'role'     => 'Event Hospitality Chef',
        'vertical' => 'Events & Catering',
        'client'   => 'Stanbic Bank',
        'portrait' => 'uploads/seed/talent-patrick.jpg',
        'pin_x'    => '58.4%',
        'pin_y'    => '56.5%',
    ],
];

// Order slides so index 2 sits dead-center on load with full neighbors + outer faded peek cards
$preferredOrder = [
    'nakasero-atelier-bespoke',
    'pearl-lens-studios',
    'kato-visuals',
    'cranecraft-digital-studio',
    'nile-savour-catering',
];
$bySlug = [];
$extraSlides = [];
foreach ($heroSpotlight as $sp) {
    if (in_array($sp['slug'], $preferredOrder, true)) {
        $bySlug[$sp['slug']] = $sp;
    } else {
        $extraSlides[] = $sp;
    }
}
$orderedRaw = [];
foreach ($preferredOrder as $sKey) {
    if (isset($bySlug[$sKey])) {
        $orderedRaw[] = $bySlug[$sKey];
    }
}
foreach ($extraSlides as $ex) {
    $orderedRaw[] = $ex;
}
if (empty($orderedRaw)) {
    $orderedRaw = $heroSpotlight;
}

$heroSlides = [];
foreach ($orderedRaw as $sp) {
    $meta = $heroMetaBySlug[$sp['slug']] ?? [];
    $portrait = $meta['portrait'] ?? (!empty($sp['avatar_path']) ? $sp['avatar_path'] : ($sp['cover_path'] ?? 'uploads/seed/talent-daniel.jpg'));
    $personName = $meta['person'] ?? preg_replace('/^Chef\s+/i', '', (string) ($sp['owner_name'] ?? $sp['business_name']));
    $clientName = $meta['client'] ?? ($sp['city'] . ', Uganda');
    $clientRecord = $clientByKey[$clientName] ?? null;
    $heroSlides[] = [
        'slug'        => $sp['slug'],
        'url'         => route_url('/provider/' . $sp['slug']),
        'person'      => $personName,
        'studio'      => $sp['business_name'],
        'role'        => $meta['role'] ?? $sp['category_name'],
        'vertical'    => $meta['vertical'] ?? $sp['category_name'],
        'verified'    => !empty($sp['is_verified']),
        'icon'        => $sp['category_icon'] ?? 'sparkles',
        'client'      => $clientName,
        'client_logo' => $clientRecord ? asset_url($clientRecord['logo']) : '',
        'client_w'    => (int) ($clientRecord['width'] ?? 240),
        'client_h'    => (int) ($clientRecord['height'] ?? 64),
        'city'        => $sp['city'],
        'portrait'    => asset_url($portrait),
        'pin_x'       => $meta['pin_x'] ?? '57.0%',
        'pin_y'       => $meta['pin_y'] ?? '57.0%',
    ];
}

$slideCount = count($heroSlides);
$initialIdx = $slideCount >= 3 ? (int) floor($slideCount / 2) : 0;
$activeHero = $heroSlides[$initialIdx] ?? null;
$topVerticals = array_slice($categories, 0, 6);

// Crisp Dot-Matrix World Map Coordinates (viewBox 0 0 220 102; Uganda at ~125, 58)
$worldDots = [
    // North America
    [26,20],[32,20],[38,20],[44,20],[22,26],[28,26],[34,26],[40,26],[46,26],[52,26],[58,26],
    [26,32],[32,32],[38,32],[44,32],[50,32],[56,32],[62,32],[32,38],[38,38],[44,38],[50,38],[56,38],
    [40,44],[46,44],[52,44],[48,50],
    // South America
    [56,56],[62,56],[56,62],[62,62],[68,62],[58,68],[64,68],[70,68],[60,74],[66,74],[60,80],[64,86],
    // Greenland
    [72,14],[78,14],[75,20],
    // Europe
    [100,20],[106,20],[112,20],[118,20],[124,20],[96,26],[102,26],[108,26],[114,26],[120,26],[126,26],
    [98,32],[104,32],[110,32],[116,32],[122,32],
    // Africa (with East Africa / Uganda right around 124-126, 56-60)
    [96,40],[102,40],[108,40],[114,40],[120,40],[94,46],[100,46],[106,46],[112,46],[118,46],[124,46],[130,46],
    [98,52],[104,52],[110,52],[116,52],[122,52],[128,52],[134,52],
    [106,58],[112,58],[118,58],[124,58],[130,58],
    [110,64],[116,64],[122,64],[128,64],[112,70],[118,70],[124,70],[116,76],[122,76],[136,70],
    // Asia
    [132,20],[138,20],[144,20],[150,20],[156,20],[162,20],[168,20],[174,20],[180,20],
    [132,26],[138,26],[144,26],[150,26],[156,26],[162,26],[168,26],[174,26],[180,26],[186,26],
    [130,32],[136,32],[142,32],[148,32],[154,32],[160,32],[166,32],[172,32],[178,32],[184,32],
    [132,38],[138,38],[144,38],[150,38],[156,38],[162,38],[168,38],[174,38],[180,38],
    [142,44],[148,44],[154,44],[160,44],[166,44],[172,44],[178,44],
    [152,50],[164,50],[170,50],[176,50],[168,56],[176,56],[182,56],
    // Australia
    [172,70],[178,70],[184,70],[190,70],[170,76],[176,76],[182,76],[188,76],[194,76],[178,82],[186,82],
];
?>
<!-- SVG Mask Definition for Chamfered Bottom-Left Floating Card (`shaped-cutouts` Adjacent Element SVG Masking) -->
<svg width="0" height="0" style="position:absolute;" aria-hidden="true">
    <defs>
        <mask id="hero-card-chamfer-mask" maskContentUnits="objectBoundingBox">
            <rect width="1" height="1" fill="white" />
            <polygon points="-0.02,-0.06 0.11,1.06 -0.02,1.06" fill="black" />
        </mask>
    </defs>
</svg>

<!-- 1. Hero (#D5D9E5 Stage + Intent Pill + Cutout + Chamfered Map Card + Peek Carousel) -->
<section class="hero-home" data-home-hero>
    <div class="container">
        <!-- Top Centered Segmented Intent Pill ("I'm looking for [Talent | Studios & Services]") -->
        <div class="hero-intent-bar" role="group" aria-label="Select hiring model">
            <span class="hero-intent-label">I&rsquo;m looking for</span>
            <div class="hero-intent-pills">
                <button
                    type="button"
                    class="hero-intent-pill"
                    aria-pressed="true"
                    data-intent-mode="talent"
                    data-intent-cta-label="Hire Top Talent"
                    data-intent-cta-href="<?= e(route_url('/hire')) ?>"
                >
                    Talent
                </button>
                <button
                    type="button"
                    class="hero-intent-pill"
                    aria-pressed="false"
                    data-intent-mode="studios"
                    data-intent-cta-label="Explore Studios &amp; Services"
                    data-intent-cta-href="<?= e(route_url('/hire', ['type' => 'business'])) ?>"
                >
                    Studios &amp; Services
                </button>
            </div>
        </div>

        <!-- Main Hero 2-Zone Stage -->
        <div class="hero-home-stage">
            <div class="hero-home-copy">
                <h1 data-hero-heading>
                    Find the <span class="hero-underline">Right Talent</span><br>Across Uganda
                </h1>
                <p class="hero-home-lead" data-hero-lead>
                    Kopii is a free showcase marketplace for Ugandan photographers, brand designers, bespoke tailors, event producers, software developers, and other creatives. Browse real portfolios, compare itemized UGX packages, and hire directly with zero commission.
                </p>

                <a href="<?= e(route_url('/hire')) ?>" class="btn btn-hero-cta" data-hero-cta>
                    Hire Top Talent
                </a>
            </div>

            <?php if ($activeHero): ?>
                <div class="hero-visual-composition" data-hero-stage>
                    <div class="hero-cutout-wrap">
                        <img
                            class="hero-cutout-img"
                            data-hero-portrait
                            src="<?= e($activeHero['portrait']) ?>"
                            alt="<?= e($activeHero['person']) ?>, <?= e($activeHero['role']) ?> in <?= e($activeHero['city']) ?>, Uganda"
                            width="720"
                            height="720"
                            fetchpriority="high"
                        >
                    </div>

                    <article class="hero-floating-card" aria-label="Featured specialist profile summary">
                        <div class="hero-floating-body">
                            <div
                                class="hero-dotted-map"
                                data-hero-map
                                style="--pin-x: <?= e($activeHero['pin_x']) ?>; --pin-y: <?= e($activeHero['pin_y']) ?>;"
                                aria-hidden="true"
                            >
                                <svg viewBox="0 0 220 102" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <?php foreach ($worldDots as [$dx, $dy]): ?>
                                        <circle cx="<?= (int) $dx ?>" cy="<?= (int) $dy ?>" r="1.45" fill="#D5D9E4" />
                                    <?php endforeach; ?>
                                </svg>
                                <span class="hero-map-pin" title="<?= e($activeHero['city']) ?>, Uganda"></span>
                            </div>

                            <a class="hero-card-name" data-hero-name href="<?= e($activeHero['url']) ?>">
                                <?= e($activeHero['person']) ?>
                            </a>

                            <div class="hero-card-verified" data-hero-verified<?= empty($activeHero['verified']) ? ' style="display:none"' : '' ?>>
                                <?= svg_icon('verified', 13) ?>
                                <span><strong>Verified</strong> <span data-hero-vertical>in <?= e($activeHero['vertical']) ?></span></span>
                            </div>

                            <div class="hero-card-role">
                                <?= svg_icon($activeHero['icon'], 13) ?>
                                <span data-hero-role><?= e($activeHero['role']) ?></span>
                            </div>

                            <div class="hero-card-prev-label">Delivered For</div>
                            <div class="hero-card-client-logo">
                                <?php if (!empty($activeHero['client_logo'])): ?>
                                    <img
                                        class="hero-client-logo-img"
                                        data-hero-client-logo
                                        src="<?= e($activeHero['client_logo']) ?>"
                                        alt="<?= e($activeHero['client']) ?>"
                                        width="<?= (int) $activeHero['client_w'] ?>"
                                        height="<?= (int) $activeHero['client_h'] ?>"
                                    >
                                <?php endif; ?>
                                <span data-hero-client<?= !empty($activeHero['client_logo']) ? ' hidden' : '' ?>><?= e($activeHero['client']) ?></span>
                            </div>
                        </div>
                        <div class="hero-floating-chamfer" aria-hidden="true"></div>
                    </article>
                </div>
            <?php endif; ?>
        </div>

        <!-- Bottom Horizontal Peek Carousel Strip (`carousel` + `scrollability-affordance-hints`) -->
        <?php if ($slideCount > 1): ?>
            <div
                class="hero-carousel"
                role="region"
                aria-roledescription="carousel"
                aria-label="Featured Ugandan talent"
                data-hero-carousel
                data-initial-index="<?= (int) $initialIdx ?>"
            >
                <div class="hero-carousel-controls">
                    <button type="button" class="hero-carousel-arrow is-prev" data-direction="previous" aria-label="Previous talent">
                        <?= svg_icon('chevron-left', 16) ?>
                    </button>
                    <button type="button" class="hero-carousel-arrow is-next" data-direction="next" aria-label="Next talent">
                        <?= svg_icon('chevron-right', 16) ?>
                    </button>
                </div>

                <div class="hero-carousel-track" tabindex="0" role="group" aria-label="Featured specialists">
                    <?php foreach ($heroSlides as $idx => $sl): ?>
                        <?php $isOuterSlide = abs($idx - $initialIdx) > 1; ?>
                        <div
                            class="hero-talent-slide"
                            role="group"
                            aria-roledescription="slide"
                            aria-label="<?= ($idx + 1) . ' of ' . $slideCount ?>"
                            data-slide-index="<?= (int) $idx ?>"
                            data-marker-name="Show <?= e($sl['person']) ?>"
                            data-person="<?= e($sl['person']) ?>"
                            data-url="<?= e($sl['url']) ?>"
                            data-portrait="<?= e($sl['portrait']) ?>"
                            data-vertical="in <?= e($sl['vertical']) ?>"
                            data-verified="<?= !empty($sl['verified']) ? '1' : '0' ?>"
                            data-role="<?= e($sl['role']) ?>"
                            data-client="<?= e($sl['client']) ?>"
                            data-client-logo="<?= e($sl['client_logo']) ?>"
                            data-client-w="<?= (int) $sl['client_w'] ?>"
                            data-client-h="<?= (int) $sl['client_h'] ?>"
                            data-city="<?= e($sl['city']) ?>"
                            data-pin-x="<?= e($sl['pin_x']) ?>"
                            data-pin-y="<?= e($sl['pin_y']) ?>"
                        >
                            <button
                                type="button"
                                class="hero-slide-btn"
                                aria-current="<?= $idx === $initialIdx ? 'true' : 'false' ?>"
                            >
                                <span class="slide-photo-box" aria-hidden="true">
                                    <img src="<?= e($sl['portrait']) ?>" alt="" width="180" height="180"<?= $isOuterSlide ? ' fetchpriority="low"' : '' ?>>
                                    <span class="slide-corner-emblem"></span>
                                </span>
                                <span class="slide-meta-box">
                                    <span class="slide-person-name"><?= e($sl['person']) ?></span>
                                    <span class="slide-person-role">
                                        <?= svg_icon($sl['icon'], 12) ?>
                                        <span><?= e($sl['role']) ?></span>
                                    </span>
                                    <span class="slide-prev-caption">Delivered For</span>
                                    <span class="slide-client-mark">
                                        <?php if (!empty($sl['client_logo'])): ?>
                                            <img
                                                class="slide-client-logo"
                                                src="<?= e($sl['client_logo']) ?>"
                                                alt="<?= e($sl['client']) ?>"
                                                width="<?= (int) $sl['client_w'] ?>"
                                                height="<?= (int) $sl['client_h'] ?>"
                                                <?= $isOuterSlide ? 'fetchpriority="low"' : '' ?>
                                            >
                                        <?php else: ?>
                                            <span><?= e($sl['client']) ?></span>
                                        <?php endif; ?>
                                    </span>
                                </span>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <p class="visually-hidden" role="status" aria-live="polite" data-carousel-status></p>
                <nav class="hero-carousel-markers" aria-label="Choose featured talent" data-carousel-markers></nav>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- 2. Monochrome Institutional Trust Bar (#F6F7F9) -->
<div class="trust-sector-bar">
    <div class="container trust-sector-inner">
        <span class="trust-label">Trusted By Leading<br>Brands and Startups</span>
        <ul class="trust-wordmarks" role="list">
            <?php foreach ($trustedClients as $client): ?>
                <li class="trust-brand-item">
                    <img
                        class="trust-logo"
                        src="<?= e(asset_url($client['logo'])) ?>"
                        alt="<?= e($client['name']) ?>"
                        width="<?= (int) ($client['width'] ?? 240) ?>"
                        height="<?= (int) ($client['height'] ?? 64) ?>"
                        loading="lazy"
                    >
                    <?php if (!empty($client['case_study'])): ?>
                        <a class="trust-case-link" href="<?= e(route_url('/explore', ['tab' => 'work', 'q' => $client['query'] ?? $client['name']])) ?>">
                            Watch the Case Study<span class="visually-hidden"> for <?= e($client['name']) ?></span>
                        </a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php $topCategories = array_slice($categories, 0, 6); ?>

<section class="section-pad surface-white">
    <div class="container">
        <div class="section-head">
            <div>
                <h2>Browse by type of work</h2>
                <p>Every listing shows real portfolio work and a starting price in UGX.</p>
            </div>
        </div>

        <div class="category-grid">
            <?php foreach ($topCategories as $cat): ?>
                <a class="category-tile" href="<?= e(route_url('/explore', ['category' => $cat['slug']])) ?>">
                    <span class="category-icon"><?= svg_icon($cat['icon'], 24) ?></span>
                    <h3><?= e($cat['name']) ?></h3>
                    <p><?= e($cat['description']) ?></p>
                    <?php if ((int) ($cat['provider_count'] ?? 0) > 0): ?>
                        <span class="category-count tnum"><?= (int) $cat['provider_count'] ?> <?= (int) $cat['provider_count'] === 1 ? 'provider' : 'providers' ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section-pad surface-grey">
    <div class="container">
        <div class="section-head">
            <div>
                <h2>Providers on Kopii</h2>
                <p>Switch category to compare starting prices, or save providers to a shortlist.</p>
            </div>
        </div>

        <div class="vertical-tabs-shell" data-vertical-tabs>
            <div class="vertical-tablist" focusgroup="tablist nomemory" aria-label="Provider categories">
                <?php foreach ($verticalTabs as $i => $vt): ?>
                    <button
                        type="button"
                        id="tab-<?= e($vt['id']) ?>"
                        class="vertical-tab"
                        data-panel-id="panel-<?= e($vt['id']) ?>"
                        aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"<?= $i === 0 ? ' focusgroupstart' : '' ?>
                    >
                        <?= svg_icon($vt['icon'], 20) ?>
                        <span><?= e($vt['label']) ?></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="vertical-panels-stage">
                <?php foreach ($verticalTabs as $i => $vt): ?>
                    <div
                        id="panel-<?= e($vt['id']) ?>"
                        role="tabpanel"
                        aria-labelledby="tab-<?= e($vt['id']) ?>"<?= $i > 0 ? ' hidden="until-found"' : '' ?>
                    >
                        <div class="tabpanel-inner">
                            <div class="provider-grid">
                                <?php foreach ($vt['providers'] as $p): ?>
                                    <?php View::partial('provider_card', ['p' => $p]); ?>
                                <?php endforeach; ?>
                            </div>

                            <div class="panel-actions">
                                <a href="<?= e(route_url('/explore', $vt['slug'] !== '' ? ['category' => $vt['slug']] : [])) ?>" class="btn btn-outline">
                                    See all <?= e(strtolower($vt['label'])) ?> <?= svg_icon('arrow-right', 16) ?>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<?php if (!empty($latestProducts)): ?>
<section class="section-pad surface-white">
    <div class="container">
        <div class="section-head">
            <div>
                <h2>Shop from Ugandan businesses</h2>
                <p>New products with prices in UGX. Message the seller to ask questions and arrange delivery.</p>
            </div>
            <a href="<?= e(route_url('/shop')) ?>" class="btn btn-outline">Browse the shop <?= svg_icon('arrow-right', 16) ?></a>
        </div>
        <div class="product-grid">
            <?php foreach ($latestProducts as $p): ?>
                <?php View::partial('product_card', ['p' => $p]); ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($latestOpportunities)): ?>
<section class="section-pad surface-grey">
    <div class="container">
        <div class="section-head">
            <div>
                <h2>Open opportunities</h2>
                <p>Projects, jobs, tenders and supply requests. Send a proposal with your price and timeline.</p>
            </div>
            <a href="<?= e(route_url('/opportunities')) ?>" class="btn btn-outline">See all opportunities <?= svg_icon('arrow-right', 16) ?></a>
        </div>
        <div class="opp-list">
            <?php foreach ($latestOpportunities as $o): ?>
                <?php View::partial('opportunity_card', ['o' => $o, 'kinds' => $opportunityKinds]); ?>
            <?php endforeach; ?>
        </div>
        <div class="board-cta" style="margin-block-start:var(--space-5);">
            <div>
                <h3>Need something done?</h3>
                <p>Post what you need and let providers come to you.</p>
            </div>
            <a href="<?= e(route_url('/opportunities/new')) ?>" class="btn btn-primary">Post an opportunity</a>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($showcaseWork)): ?>
<section class="section-pad surface-white">
    <div class="container">
        <div class="section-head">
            <div>
                <h2>Recent work from providers</h2>
                <p>Open any photo to see the project, then request a quote from the person who made it.</p>
            </div>
            <a href="<?= e(route_url('/explore', ['tab' => 'work'])) ?>" class="btn btn-outline">
                See all work <?= svg_icon('arrow-right', 16) ?>
            </a>
        </div>

        <div class="work-grid">
            <?php foreach ($showcaseWork as $w): ?>
                <?php View::partial('work_card', ['w' => $w]); ?>
            <?php endforeach; ?>
        </div>

        <?php $testimonials = require KOPII_ROOT . '/app/config/testimonials.php'; ?>
        <?php if (!empty($testimonials)): ?>
            <div class="testimonial-grid">
                <?php foreach ($testimonials as $t): ?>
                    <article class="testimonial-card">
                        <blockquote>&ldquo;<?= e($t['quote']) ?>&rdquo;</blockquote>
                        <div class="testimonial-author">
                            <strong><?= e($t['name']) ?></strong>
                            <span><?= e($t['detail'] ?? '') ?></span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<section class="section-pad surface-grey">
    <div class="container">
        <div class="section-head">
            <div>
                <h2>From brief to booked in three steps</h2>
            </div>
        </div>

        <ol class="how-steps">
            <li class="how-step">
                <span class="how-step-num tnum" aria-hidden="true">1</span>
                <h3>Describe the job</h3>
                <p>Tell the <a href="<?= e(route_url('/hire')) ?>">matching wizard</a> what you need, where, by when, and your budget in UGX.</p>
            </li>
            <li class="how-step">
                <span class="how-step-num tnum" aria-hidden="true">2</span>
                <h3>Compare providers</h3>
                <p>See ranked matches with portfolio samples, itemized UGX packages, and client reviews.</p>
            </li>
            <li class="how-step">
                <span class="how-step-num tnum" aria-hidden="true">3</span>
                <h3>Contact them directly</h3>
                <p>Send one brief to up to five providers, or open WhatsApp. You agree the price with them, and Kopii takes no commission.</p>
            </li>
        </ol>
    </div>
</section>

<section class="section-pad surface-navy">
    <div class="container">
        <div class="section-head">
            <div>
                <h2>How a provider earns the Verified badge</h2>
                <p>Anyone can list for free. Our team adds the badge by hand after checking the three things below. A listing without it simply has not been checked yet.</p>
            </div>
            <a href="<?= e(route_url('/about')) ?>" class="btn btn-transparent">How verification works</a>
        </div>

        <div class="vetting-grid">
            <div class="vetting-stage">
                <span class="vetting-num tnum" aria-hidden="true">1</span>
                <h3>Reachable in Uganda</h3>
                <p>We confirm the provider&rsquo;s Ugandan phone or WhatsApp number works.</p>
            </div>
            <div class="vetting-stage">
                <span class="vetting-num tnum" aria-hidden="true">2</span>
                <h3>Original portfolio</h3>
                <p>We look for genuine work by the provider, with no stock images or other people&rsquo;s photos.</p>
            </div>
            <div class="vetting-stage">
                <span class="vetting-num tnum" aria-hidden="true">3</span>
                <h3>Clear UGX pricing</h3>
                <p>A starting rate or itemized packages, with deliverables and a realistic turnaround.</p>
            </div>
        </div>

        <p class="vetting-note">
            Verification is not a guarantee of quality. Agree scope, price, and deadlines in writing before you pay.
        </p>
    </div>
</section>

<section class="section-pad surface-white">
    <div class="container">
        <div class="section-head">
            <div>
                <h2>Popular searches</h2>
            </div>
        </div>

        <div class="skills-directory-grid">
            <div class="skills-col">
                <h3>Visual &amp; creative</h3>
                <ul>
                    <li><a href="<?= e(route_url('/explore', ['category' => 'photography-video'])) ?>">Wedding photographers in Kampala</a></li>
                    <li><a href="<?= e(route_url('/explore', ['q' => 'Drone'])) ?>">Aerial &amp; drone cinematographers</a></li>
                    <li><a href="<?= e(route_url('/explore', ['category' => 'graphic-web-design'])) ?>">Brand identity &amp; packaging designers</a></li>
                    <li><a href="<?= e(route_url('/explore', ['q' => 'Portrait'])) ?>">Executive &amp; studio portrait photographers</a></li>
                    <li><a href="<?= e(route_url('/explore', ['category' => 'beauty-makeup'])) ?>">Bridal makeup &amp; editorial stylists</a></li>
                </ul>
            </div>
            <div class="skills-col">
                <h3>Events, fashion &amp; hospitality</h3>
                <ul>
                    <li><a href="<?= e(route_url('/explore', ['category' => 'events-catering'])) ?>">Kwanjula &amp; reception caterers</a></li>
                    <li><a href="<?= e(route_url('/explore', ['category' => 'fashion-tailoring'])) ?>">Bespoke suits &amp; gomesi couture</a></li>
                    <li><a href="<?= e(route_url('/explore', ['category' => 'interior-decor'])) ?>">Interior design, timber &amp; space styling</a></li>
                    <li><a href="<?= e(route_url('/explore', ['category' => 'music-entertainment'])) ?>">Live bands, DJs &amp; bilingual MCs</a></li>
                    <li><a href="<?= e(route_url('/explore', ['q' => 'Corporate'])) ?>">Conference &amp; NGO event production</a></li>
                </ul>
            </div>
            <div class="skills-col">
                <h3>Digital, tech &amp; commercial</h3>
                <ul>
                    <li><a href="<?= e(route_url('/explore', ['category' => 'software-it'])) ?>">Web &amp; mobile app developers</a></li>
                    <li><a href="<?= e(route_url('/explore', ['category' => 'construction-architecture'])) ?>">Architectural plans &amp; solar builders</a></li>
                    <li><a href="<?= e(route_url('/explore', ['category' => 'transport-logistics'])) ?>">Bridal cars &amp; executive transport</a></li>
                    <li><a href="<?= e(route_url('/explore', ['q' => 'UI'])) ?>">UI/UX &amp; digital product designers</a></li>
                    <li><a href="<?= e(route_url('/explore', ['tab' => 'work'])) ?>">Browse all work</a></li>
                </ul>
            </div>
            <div class="skills-col">
                <h3>By city</h3>
                <ul>
                    <?php foreach (array_slice($cities, 0, 5) as $hubCity): ?>
                        <li><a href="<?= e(route_url('/explore', ['city' => $hubCity])) ?>">Providers in <?= e($hubCity) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</section>

<section class="section-pad-sm surface-light-grey">
    <div class="container cta-band">
        <h2>Tell us what you need and get matched</h2>
        <div class="cta-band-actions">
            <a href="<?= e(route_url('/hire')) ?>" class="btn btn-primary btn-lg">Get matched</a>
            <a href="<?= e(route_url('/register')) ?>" class="btn btn-ghost btn-lg">List your business</a>
        </div>
    </div>
</section>
