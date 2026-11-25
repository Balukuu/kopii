<?php
defined('KOPII_APP') or exit('Direct access denied.');
$authUser = current_user();
$flashes  = get_flashes();
$curRoute = $currentRoute ?? '/';

// Page title, description and picture, as shown in search results and in WhatsApp / Facebook previews.
$seoTitle       = (string) ($pageTitle ?? 'Kopii — Hire Talent & Studios in Uganda');
$seoDescription = (string) ($metaDescription ?? 'Find a real, verified provider in Uganda for the work you need, from home repairs and solar to events, design and photography. See their past work and UGX prices, then talk on WhatsApp. Free, with no commission.');
$seoImagePath   = (string) ($ogImage ?? 'assets/images/og-default.jpg');
$seoImage       = site_origin() . asset_url($seoImagePath);
$seoCanonical   = (string) ($canonicalUrl ?? canonical_url());
$seoNoindex     = !empty($noindex) || request_is_private_area();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Kopii — Hire Talent & Studios in Uganda') ?></title>
    <meta name="description" content="<?= e($seoDescription) ?>">
    <link rel="canonical" href="<?= e($seoCanonical) ?>">
<?php if ($seoNoindex): ?>
    <meta name="robots" content="noindex, nofollow">
<?php endif; ?>
    <meta property="og:site_name" content="Kopii">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="en_UG">
    <meta property="og:title" content="<?= e($seoTitle) ?>">
    <meta property="og:description" content="<?= e($seoDescription) ?>">
    <meta property="og:url" content="<?= e($seoCanonical) ?>">
    <meta property="og:image" content="<?= e($seoImage) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($seoTitle) ?>">
    <meta name="twitter:description" content="<?= e($seoDescription) ?>">
    <meta name="twitter:image" content="<?= e($seoImage) ?>">
    <link rel="icon" href="<?= e(asset_url('assets/images/favicon.png')) ?>" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="<?= e(versioned_asset_url('assets/css/app.css')) ?>">
</head>
<body class="<?= $curRoute === '/' ? 'is-home' : '' ?>">
    <a class="skip-link" href="#main-content">Skip to main content</a>

    <header class="site-header">
        <div class="container header-inner">
            <a class="site-logo" href="<?= e(route_url('/')) ?>">
                <img src="<?= e(asset_url('assets/images/logo-header.png')) ?>" alt="Kopii — Talent &amp; Studios in Uganda" width="220" height="103">
            </a>

            <nav class="site-nav" aria-label="Primary">
                <button
                    class="menu-button"
                    type="button"
                    popovertarget="site-menu"
                    popovertargetaction="toggle"
                    aria-controls="site-menu"
                >
                    <?= svg_icon('menu', 20) ?>
                    <span>Menu</span>
                </button>

                <ul id="site-menu" class="site-menu" popover="auto">
                    <li>
                        <a class="nav-link" href="<?= e(route_url('/explore')) ?>"<?= ($curRoute === '/explore' && ($_GET['tab'] ?? '') !== 'work' && ($_GET['type'] ?? '') !== 'business') ? ' aria-current="page"' : '' ?>>Hire Talent</a>
                    </li>
                    <li>
                        <a class="nav-link" href="<?= e(route_url('/explore', ['type' => 'business'])) ?>"<?= ($curRoute === '/explore' && ($_GET['type'] ?? '') === 'business') ? ' aria-current="page"' : '' ?>>Business &amp; Services</a>
                    </li>
                    <li>
                        <a class="nav-link" href="<?= e(route_url('/explore', ['tab' => 'work'])) ?>"<?= ($curRoute === '/explore' && ($_GET['tab'] ?? '') === 'work') ? ' aria-current="page"' : '' ?>>Clients</a>
                    </li>
                    <li>
                        <a class="nav-link" href="<?= e(route_url('/shop')) ?>"<?= $curRoute === '/shop' ? ' aria-current="page"' : '' ?>>Shop</a>
                    </li>
                    <li>
                        <a class="nav-link" href="<?= e(route_url('/opportunities')) ?>"<?= $curRoute === '/opportunities' ? ' aria-current="page"' : '' ?>>Opportunities</a>
                    </li>
                    <li>
                        <a class="nav-link" href="<?= e(route_url('/about')) ?>"<?= $curRoute === '/about' ? ' aria-current="page"' : '' ?>>About Us</a>
                    </li>

                    <li class="nav-divider" aria-hidden="true"></li>

                    <?php if ($authUser): ?>
                        <?php if (($authUser['role'] ?? '') === 'admin'): ?>
                            <?php $adminTodo = admin_attention_total(); ?>
                            <li>
                                <a class="nav-link" href="<?= e(route_url('/admin')) ?>"<?= str_starts_with($curRoute, '/admin') ? ' aria-current="page"' : '' ?>>
                                    Admin<?php if ($adminTodo > 0): ?><span class="nav-badge tnum" role="img" aria-label="<?= (int) $adminTodo ?> items need attention"><?= $adminTodo > 99 ? '99+' : (int) $adminTodo ?></span><?php endif; ?>
                                </a>
                            </li>
                        <?php endif; ?>
                        <li>
                            <a class="nav-link" href="<?= e(route_url('/dashboard')) ?>"<?= $curRoute === '/dashboard' ? ' aria-current="page"' : '' ?>>
                                Studio Dashboard
                            </a>
                        </li>
                        <li>
                            <a class="nav-link" href="<?= e(route_url('/account')) ?>"<?= $curRoute === '/account' ? ' aria-current="page"' : '' ?>>Account</a>
                        </li>
                        <li class="nav-cta-item">
                            <a class="btn btn-primary btn-nav" href="<?= e(route_url('/hire')) ?>"<?= $curRoute === '/hire' ? ' aria-current="page"' : '' ?>>
                                Get matched
                            </a>
                        </li>
                        <li class="nav-cta-item">
                            <form method="POST" action="<?= e(route_url('/logout')) ?>" class="inline-logout-form">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-outline btn-nav">Log Out</button>
                            </form>
                        </li>
                    <?php else: ?>
                        <li>
                            <a class="nav-link" href="<?= e(route_url('/register')) ?>"<?= $curRoute === '/register' ? ' aria-current="page"' : '' ?>>
                                Apply as a Freelancer
                            </a>
                        </li>
                        <li class="nav-cta-item">
                            <a class="btn btn-primary btn-nav" href="<?= e(route_url('/hire')) ?>"<?= $curRoute === '/hire' ? ' aria-current="page"' : '' ?>>
                                Get matched
                            </a>
                        </li>
                        <li>
                            <a class="nav-link" href="<?= e(route_url('/login')) ?>"<?= $curRoute === '/login' ? ' aria-current="page"' : '' ?>>Log In</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <?php if (!empty($flashes)): ?>
        <div class="container" style="margin-block-start: 1.25rem;">
            <?php foreach ($flashes as $f): ?>
                <div class="alert alert-<?= e($f['type']) ?>" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>">
                    <?= e($f['message']) ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <main id="main-content" tabindex="-1">
        <?= $content ?>
    </main>

    <div class="container">
        <aside id="shortlist-tray" class="shortlist-tray" aria-label="Comparison shortlist" hidden>
            <div>
                <strong><span id="shortlist-count">0</span> specialist(s) saved to compare</strong>
                <span style="color:#d5defa; margin-inline-start:0.5rem; font-size:0.875rem;">Compare portfolios &amp; UGX rates side-by-side</span>
            </div>
            <div style="display:flex; gap:0.6rem; flex-wrap:wrap;">
                <a id="shortlist-compare-link" data-explore-url="<?= e(route_url('/explore')) ?>" href="<?= e(route_url('/explore')) ?>" class="btn btn-on-dark btn-sm">
                    Compare Shortlist
                </a>
                <button type="button" id="shortlist-clear-btn" class="btn btn-transparent btn-sm">
                    Clear
                </button>
            </div>
        </aside>
    </div>

    <!-- Shared Native Lightbox Modal (<dialog closedby="any">) -->
    <dialog id="portfolio-lightbox" class="modal-box lightbox-modal" closedby="any" aria-labelledby="lightbox-title">
        <div class="modal-head">
            <div>
                <span data-lb-tag class="badge badge-featured" style="margin-inline-end:0.5rem;"></span>
                <h2 id="lightbox-title" data-lb-title style="display:inline;">Portfolio Case Study</h2>
            </div>
            <button type="button" class="btn btn-sm btn-transparent" data-close-modal aria-label="Close portfolio viewer">
                <?= svg_icon('close', 20) ?> Close
            </button>
        </div>
        <div class="modal-body">
            <div class="lightbox-stage">
                <div>
                    <img data-lb-img src="" alt="" width="1080" height="806">
                    <div style="display:flex; justify-content:space-between; gap:0.75rem; margin-block-start:0.85rem;">
                        <button type="button" class="btn btn-sm btn-transparent" data-lb-prev>
                            <?= svg_icon('chevron-left', 20) ?> Previous
                        </button>
                        <button type="button" class="btn btn-sm btn-transparent" data-lb-next>
                            Next <?= svg_icon('chevron-right', 20) ?>
                        </button>
                    </div>
                </div>
                <div style="display:flex; flex-direction:column; gap:1rem;">
                    <p data-lb-desc style="margin:0; color:#ebeced; font-size:0.95rem;"></p>
                    <p data-lb-location style="margin:0; color:#00cc83; font-size:0.875rem; font-weight:600;"></p>
                    <div style="padding-block-start:0.75rem; border-block-start:1px solid rgba(255,255,255,0.16); display:grid; gap:0.65rem;">
                        <a data-lb-creator href="#" class="btn btn-transparent">View Talent Profile</a>
                        <?php if (!empty($provider)): ?>
                            <button type="button" data-lb-quote class="btn btn-on-dark">Hire for Similar Work</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </dialog>

    <footer class="site-footer">
        <div class="container">
            <div class="footer-grid">
                <div>
                    <h3>Popular Services</h3>
                    <ul class="footer-links">
                        <li><a href="<?= e(route_url('/explore', ['category' => 'home-repairs'])) ?>">Home Repairs &amp; Maintenance</a></li>
                        <li><a href="<?= e(route_url('/explore', ['category' => 'solar-electrical'])) ?>">Solar &amp; Electrical</a></li>
                        <li><a href="<?= e(route_url('/explore', ['category' => 'cleaning-home-services'])) ?>">Cleaning &amp; Home Services</a></li>
                        <li><a href="<?= e(route_url('/explore', ['category' => 'events-catering'])) ?>">Events &amp; Catering</a></li>
                        <li><a href="<?= e(route_url('/explore', ['category' => 'graphic-web-design'])) ?>">Graphic &amp; Web Design</a></li>
                    </ul>
                </div>
                <div>
                    <h3>Uganda Talent Hubs</h3>
                    <ul class="footer-links">
                        <li><a href="<?= e(route_url('/explore', ['city' => 'Kampala'])) ?>">Hire Talent in Kampala</a></li>
                        <li><a href="<?= e(route_url('/explore', ['city' => 'Entebbe'])) ?>">Hire Talent in Entebbe</a></li>
                        <li><a href="<?= e(route_url('/explore', ['city' => 'Jinja'])) ?>">Hire Talent in Jinja</a></li>
                        <li><a href="<?= e(route_url('/explore', ['city' => 'Mbarara'])) ?>">Hire Talent in Mbarara</a></li>
                        <li><a href="<?= e(route_url('/explore', ['city' => 'Gulu'])) ?>">Hire Talent in Gulu</a></li>
                    </ul>
                </div>
                <div>
                    <h3>About</h3>
                    <ul class="footer-links">
                        <li><a href="<?= e(route_url('/about')) ?>">Why Kopii &amp; the Verified Badge</a></li>
                        <li><a href="<?= e(route_url('/explore', ['tab' => 'work'])) ?>">Client Case Studies</a></li>
                        <li><a href="<?= e(route_url('/explore')) ?>">Browse All Studios</a></li>
                        <li><a href="<?= e(route_url('/hire')) ?>">Guided Talent Matching</a></li>
                        <li><a href="<?= e(route_url('/shop')) ?>">Shop Products</a></li>
                        <li><a href="<?= e(route_url('/opportunities')) ?>">Opportunities Board</a></li>
                    </ul>
                </div>
                <div>
                    <h3>Network Access</h3>
                    <ul class="footer-links">
                        <li><a href="<?= e(route_url('/hire')) ?>">Get matched</a></li>
                        <li><a href="<?= e(route_url('/register')) ?>">Apply as a Freelancer / Studio</a></li>
                        <li><a href="<?= e(route_url('/opportunities/new')) ?>">Post an Opportunity</a></li>
                        <li><a href="<?= e(route_url('/login')) ?>">Talent &amp; Client Log In</a></li>
                    </ul>
                </div>
            </div>

            <div class="footer-brand-bar">
                <div class="footer-brand-lockup">
                    <span class="footer-wordmark">Kopii</span>
                    <span class="footer-divider-pipe" aria-hidden="true"></span>
                    <span>Hire creative &amp; business talent in Uganda</span>
                </div>
                <div class="footer-legal">
                    <span>&copy; Copyright 2010 &ndash; <?= date('Y') ?> Kopii Online Uganda</span>
                    <span>&middot;</span>
                    <span>0% Commission &middot; Transparent UGX Pricing</span>
                    <span>&middot;</span>
                    <a href="<?= e(route_url('/safety')) ?>">Safety tips</a>
                    <a href="<?= e(route_url('/terms')) ?>">Terms</a>
                    <a href="<?= e(route_url('/privacy')) ?>">Privacy</a>
                    <a href="<?= e(route_url('/contact')) ?>">Contact</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="<?= e(versioned_asset_url('assets/js/app.js')) ?>" defer></script>
</body>
</html>
