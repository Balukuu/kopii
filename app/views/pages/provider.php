<?php
defined('KOPII_APP') or exit('Direct access denied.');
/** @var array $provider */
$portfolio = $provider['portfolio'] ?? [];
$packages  = $provider['packages'] ?? [];
$reviews   = $provider['reviews'] ?? [];
$tags      = array_filter(array_map('trim', explode(',', (string) ($provider['specialties'] ?? ''))));
$waMessage = 'Hello ' . $provider['business_name'] . '! I viewed your portfolio on Kopii and would like to ask about your availability and rates.';
$authUser  = current_user();
?>
<nav class="breadcrumb-bar" aria-label="Breadcrumb">
    <div class="container">
        <ol class="breadcrumb-list">
            <li><a href="<?= e(route_url('/')) ?>">Kopii</a></li>
            <li aria-hidden="true">/</li>
            <li><a href="<?= e(route_url('/explore', ['category' => $provider['category_slug']])) ?>"><?= e($provider['category_name']) ?></a></li>
            <li aria-hidden="true">/</li>
            <li aria-current="page"><?= e($provider['business_name']) ?></li>
        </ol>
    </div>
</nav>

<div class="profile-banner">
    <?php if (!empty($provider['cover_path'])): ?>
        <img
            class="profile-cover-media"
            src="<?= e(asset_url($provider['cover_path'])) ?>"
            alt="<?= e($provider['business_name']) ?> cover banner"
            width="1280"
            height="420"
            fetchpriority="high"
        >
    <?php else: ?>
        <div style="block-size: 9rem;"></div>
    <?php endif; ?>
</div>

<div class="container">
    <div class="profile-header-card">
        <div class="profile-identity">
            <div style="display:flex; flex-wrap:wrap; gap:1.25rem; align-items:flex-start; flex:1; min-inline-size:260px;">
                <div class="avatar-status-wrap" aria-hidden="true">
                    <div class="profile-avatar-lg">
                        <?php if (!empty($provider['avatar_path'])): ?>
                            <img src="<?= e(asset_url($provider['avatar_path'])) ?>" alt="" width="84" height="84">
                        <?php else: ?>
                            <?= e(initials($provider['business_name'])) ?>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($provider['is_available'])): ?>
                        <span class="status-dot" style="inline-size:0.95rem; block-size:0.95rem; inset-block-end:4px; inset-inline-end:4px;" title="Available for bookings"></span>
                    <?php endif; ?>
                </div>
                <div style="flex:1; min-inline-size:220px;">
                    <h1 class="profile-name">
                        <?= e($provider['business_name']) ?>
                    </h1>

                    <p class="profile-kind">
                        <a href="<?= e(route_url('/explore', ['category' => $provider['category_slug']])) ?>"><?= e($provider['category_name']) ?></a>
                        <span><?= $provider['provider_type'] === 'business' ? 'Studio' : 'Freelancer' ?></span>
                        <?php if (!empty($provider['is_verified'])): ?>
                            <span class="profile-flag"><?= svg_icon('verified', 16) ?> Verified</span>
                        <?php endif; ?>
                        <?php if (!empty($provider['is_available'])): ?>
                            <span class="profile-flag is-available">Available for hire</span>
                        <?php endif; ?>
                    </p>

                    <p class="profile-headline">
                        <?= e($provider['headline']) ?>
                    </p>

                    <div class="card-meta-line profile-meta">
                        <span><?= svg_icon('map-pin', 16) ?> <span><strong><?= e($provider['city']) ?></strong><?= !empty($provider['neighborhood']) ? ', ' . e($provider['neighborhood']) : '' ?>, Uganda</span></span>
                        <span><?= svg_icon('clock', 16) ?> <span>Replies: <?= e($provider['response_time']) ?></span></span>
                        <span><span><strong class="tnum"><?= (int) $provider['years_experience'] ?>+ yrs</strong> experience</span></span>
                        <?php if ((int) $provider['review_count'] > 0): ?>
                            <span class="rating-pill"><?= svg_icon('star', 16) ?> <span><?= e(number_format((float) $provider['avg_rating'], 1)) ?> (<?= (int) $provider['review_count'] ?> client <?= (int) $provider['review_count'] === 1 ? 'review' : 'reviews' ?>)</span></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="profile-actions">
                <button
                    type="button"
                    class="btn btn-outline shortlist-btn"
                    data-provider-id="<?= (int) $provider['id'] ?>"
                    aria-pressed="false"
                >
                    <span data-shortlist-label>Compare</span>
                </button>
                <button type="button" class="btn btn-primary btn-lg" data-open-modal="quote-modal">
                    Hire <?= e($provider['business_name']) ?>
                </button>
            </div>
        </div>
    </div>

    <div class="profile-layout">
        <!-- Main Left Column: Portfolio Showcase, Service Packages, Bio, Reviews -->
        <div>
            <!-- 1. Portfolio Work Showcase -->
            <section class="content-panel" aria-labelledby="portfolio-heading">
                <div style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:0.75rem; margin-block-end:1.1rem;">
                    <h2 id="portfolio-heading" class="m-0">Portfolio &amp; Past Work (<?= count($portfolio) ?>)</h2>
                    <span class="text-muted" style="font-size:0.86rem;">Click any photo to view full-size details</span>
                </div>

                <?php if (empty($portfolio)): ?>
                    <p class="text-muted m-0"><?= e($provider['business_name']) ?> hasn't uploaded portfolio photos yet. Reach out directly via Quote Request or WhatsApp to request work samples!</p>
                <?php else: ?>
                    <div class="work-grid">
                        <?php foreach ($portfolio as $item): ?>
                            <?php View::partial('work_card', ['w' => $item]); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- 2. Service Packages & Transparent UGX Rates -->
            <?php if (!empty($packages)): ?>
                <section class="content-panel" aria-labelledby="packages-heading">
                    <h2 id="packages-heading">Service Packages &amp; UGX Pricing</h2>
                    <div class="package-grid">
                        <?php foreach ($packages as $pkg): ?>
                            <?php
                            $delivLines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) ($pkg['deliverables'] ?? '')) ?: []));
                            ?>
                            <div class="package-card<?= !empty($pkg['is_popular']) ? ' is-popular' : '' ?>">
                                <div>
                                    <?php if (!empty($pkg['is_popular'])): ?>
                                        <span class="badge badge-verified" style="margin-block-end:0.5rem;">Most Popular Choice</span>
                                    <?php endif; ?>
                                    <h3 style="margin:0 0 0.35rem; font-size:1.1rem; color:var(--navy-900);"><?= e($pkg['name']) ?></h3>
                                    <div style="margin-block-end:0.5rem;">
                                        <strong class="tnum" style="font-size:1.35rem; color:var(--cobalt-700);"><?= e(format_ugx($pkg['price_ugx'])) ?></strong>
                                        <span class="text-muted" style="font-size:0.84rem;"> / <?= e($pkg['price_unit']) ?></span>
                                    </div>
                                    <?php if (!empty($pkg['turnaround'])): ?>
                                        <div class="package-turnaround">
                                            <?= svg_icon('clock', 16) ?> <span>Delivery: <?= e($pkg['turnaround']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($pkg['description'])): ?>
                                        <p class="text-muted" style="margin:0; font-size:0.9rem;"><?= e($pkg['description']) ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($delivLines)): ?>
                                        <ul class="deliverables-list">
                                            <?php foreach ($delivLines as $line): ?>
                                                <li><?= svg_icon('check', 16) ?> <span><?= e($line) ?></span></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                                <button
                                    type="button"
                                    class="btn <?= !empty($pkg['is_popular']) ? 'btn-primary' : 'btn-outline' ?> btn-block"
                                    data-open-modal="quote-modal"
                                    data-package-id="<?= (int) $pkg['id'] ?>"
                                    data-package-price="<?= (int) $pkg['price_ugx'] ?>"
                                >
                                    Choose This Package
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <!-- 3. About & Specialties -->
            <section class="content-panel" aria-labelledby="about-provider-heading">
                <h2 id="about-provider-heading">About <?= e($provider['business_name']) ?></h2>
                <div style="white-space:pre-line; color:var(--slate-700); margin-block-end:1.25rem;"><?= e($provider['bio']) ?></div>

                <?php if (!empty($tags)): ?>
                    <h3 style="font-size:0.95rem; margin:0 0 0.6rem; color:var(--navy-900);">Specialties &amp; Services Offered</h3>
                    <div class="tag-row">
                        <?php foreach ($tags as $t): ?>
                            <span class="tag-chip"><?= e($t) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- 4. Client Reviews & Leave a Review -->
            <section class="content-panel" aria-labelledby="reviews-heading">
                <h2 id="reviews-heading">Client Reviews (<?= count($reviews) ?>)</h2>

                <?php if (empty($reviews)): ?>
                    <p class="text-muted">Worked with <?= e($provider['business_name']) ?> recently? Be the first client to share your experience below!</p>
                <?php else: ?>
                    <div style="display:grid; gap:1rem; margin-block-end:1.75rem;">
                        <?php foreach ($reviews as $rev): ?>
                            <article style="border:1px solid var(--slate-200); border-radius:var(--radius-md); padding:1.1rem; background:var(--slate-50);">
                                <div style="display:flex; flex-wrap:wrap; justify-content:space-between; gap:0.5rem; margin-block-end:0.45rem;">
                                    <div>
                                        <strong style="color:var(--navy-900);"><?= e($rev['reviewer_name']) ?></strong>
                                        <?php if (!empty($rev['reviewer_city'])): ?>
                                            <span class="text-muted" style="font-size:0.85rem;"> &middot; <?= e($rev['reviewer_city']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($rev['verified_request'])): ?>
                                            <span class="review-proof"><?= svg_icon('check', 16) ?> Requested a quote via Kopii</span>
                                        <?php endif; ?>
                                        <?php if (!empty($rev['service_hired'])): ?>
                                            <span class="badge badge-type" style="margin-inline-start:0.4rem;"><?= e($rev['service_hired']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="rating-pill" aria-label="<?= (int) $rev['rating'] ?> out of 5 stars">
                                        <?php for ($s = 1; $s <= 5; $s++): ?>
                                            <?= svg_icon($s <= (int) $rev['rating'] ? 'star' : 'star-outline', 16) ?>
                                        <?php endfor; ?>
                                    </span>
                                </div>
                                <p style="margin:0; color:var(--slate-700); font-size:0.94rem;"><?= e($rev['comment']) ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <details style="border-block-start:1px solid var(--slate-200); padding-block-start:1.1rem;">
                    <summary style="cursor:pointer; font-weight:700; color:var(--cobalt-700);">Worked with <?= e($provider['business_name']) ?>? Write a Client Review</summary>
                    <form method="POST" action="<?= e(route_url('/provider/' . $provider['slug'] . '/review')) ?>" class="stack-form" style="margin-block-start:1.1rem;">
                        <?= csrf_field() ?>
                        <div class="visually-hidden" aria-hidden="true">
                            <label for="review_hp_field">Leave blank</label>
                            <input type="text" id="review_hp_field" name="review_hp_field" tabindex="-1" autocomplete="off">
                        </div>

                        <p class="text-muted m-0" style="font-size:0.85rem;">
                            Reviews from people who requested a quote through Kopii appear straight away. Others are checked by our team first.
                        </p>

                        <fieldset class="form-fieldset">
                            <legend>Your Rating *</legend>
                            <div class="star-radio-group">
                                <?php foreach ([5 => '5 Stars (Excellent)', 4 => '4 Stars (Great)', 3 => '3 Stars (Good)', 2 => '2 Stars', 1 => '1 Star'] as $val => $lbl): ?>
                                    <label class="star-choice">
                                        <input type="radio" name="rating" value="<?= $val ?>"<?= $val === 5 ? ' checked' : '' ?>>
                                        <span><?= e($lbl) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>

                        <div class="form-row-2">
                            <div class="field">
                                <label for="rev-name">Your Full Name *</label>
                                <input type="text" id="rev-name" name="reviewer_name" value="<?= e($authUser['full_name'] ?? '') ?>" autocomplete="name" required>
                            </div>
                            <div class="field">
                                <label for="rev-city">Your City (Optional)</label>
                                <input type="text" id="rev-city" name="reviewer_city" placeholder="e.g. Kampala, Entebbe" autocomplete="address-level2">
                            </div>
                        </div>

                        <div class="field">
                            <label for="rev-service">Service or Event Hired For (Optional)</label>
                            <input type="text" id="rev-service" name="service_hired" placeholder="e.g. Wedding Photography, Corporate Portraits">
                        </div>

                        <div class="field">
                            <label for="rev-comment">Your Review *</label>
                            <textarea id="rev-comment" name="comment" minlength="10" required placeholder="Share what it was like working together, quality of deliverables, and timeliness..."></textarea>
                        </div>

                        <div>
                            <button type="submit" class="btn btn-primary">Publish Review</button>
                        </div>
                    </form>
                </details>
            </section>
        </div>

        <!-- Sticky Right Sidebar: Direct Hire, Quote & Instant WhatsApp/Phone -->
        <aside class="sticky-hire-card" aria-label="Connect and hire <?= e($provider['business_name']) ?>">
            <div class="rate-block">
                <span class="rate-label"><?= (int) $provider['starting_price_ugx'] > 0 ? 'Starting from' : 'Pricing' ?></span>
                <div class="rate-value tnum"><?= e(format_ugx($provider['starting_price_ugx'])) ?></div>
            </div>

            <div class="cta-stack">
                <button type="button" class="btn btn-primary btn-lg btn-block" data-open-modal="quote-modal">
                    <?= svg_icon('mail', 20) ?> Request a quote
                </button>

                <a
                    href="<?= e(whatsapp_url((string) $provider['whatsapp'], $waMessage)) ?>"
                    target="_blank"
                    rel="noopener"
                    class="btn btn-whatsapp-outline btn-lg btn-block"
                >
                    <?= svg_icon('whatsapp', 20) ?> Chat on WhatsApp
                </a>

                <a href="<?= e(tel_url((string) $provider['phone'])) ?>" class="btn btn-ghost btn-block">
                    <?= svg_icon('phone', 20) ?> Call <?= e(display_ug_phone((string) $provider['phone'])) ?>
                </a>
            </div>

            <div class="risk-free-box">
                <?= svg_icon('shield', 20) ?>
                <span><strong>Kopii Direct:</strong> 0% platform commission. Agree on scope and UGX deliverables directly with the provider, and confirm them in writing before you pay.</span>
            </div>

            <dl class="facts">
                <div class="row-between">
                    <dt class="text-muted">Base Location</dt>
                    <dd class="dd-strong"><?= e($provider['city']) ?>, Uganda</dd>
                </div>
                <div class="row-between">
                    <dt class="text-muted">Typical Response</dt>
                    <dd class="dd-strong"><?= e($provider['response_time']) ?></dd>
                </div>
                <div class="row-between">
                    <dt class="text-muted">Contact Person</dt>
                    <dd class="dd-strong"><?= e($provider['owner_name']) ?></dd>
                </div>
                <?php if (!empty($provider['instagram'])): ?>
                    <div class="row-between">
                        <dt class="text-muted">Instagram</dt>
                        <dd class="dd-strong"><?= e($provider['instagram']) ?></dd>
                    </div>
                <?php endif; ?>
                <?php $websiteUrl = safe_url($provider['website'] ?? ''); ?>
                <?php if ($websiteUrl !== ''): ?>
                    <div class="row-between">
                        <dt class="text-muted">Website</dt>
                        <dd style="margin:0; font-weight:700; min-inline-size:0; overflow-wrap:anywhere; text-align:end;">
                            <a href="<?= e($websiteUrl) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= e(url_host($websiteUrl)) ?></a>
                        </dd>
                    </div>
                <?php endif; ?>
            </dl>
        </aside>
    </div>

    <!-- Related Providers in Same Category -->
    <?php if (!empty($related)): ?>
        <section class="section-pad-sm" style="margin-block-start:1.5rem;">
            <div class="section-head">
                <div>
                    <h2>Compare More in <?= e($provider['category_name']) ?></h2>
                    <p>Explore other professionals on Kopii so you can pick the right fit for your style and budget.</p>
                </div>
                <a href="<?= e(route_url('/explore', ['category' => $provider['category_slug']])) ?>" class="btn btn-outline btn-sm">
                    View All in <?= e($provider['category_name']) ?>
                </a>
            </div>
            <div class="provider-grid">
                <?php foreach ($related as $p): ?>
                    <?php View::partial('provider_card', ['p' => $p]); ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>

<!-- Native Modal Dialog for Direct Quote / Booking Request (<dialog closedby="any">) -->
<dialog id="quote-modal" class="modal-box" closedby="any" aria-labelledby="quote-modal-title">
    <div class="modal-head">
        <h2 id="quote-modal-title">Request a Quote from <?= e($provider['business_name']) ?></h2>
        <button type="button" class="btn btn-ghost btn-sm" data-close-modal aria-label="Close quote dialog">
            <?= svg_icon('close', 20) ?>
        </button>
    </div>
    <div class="modal-body">
        <p class="text-muted" style="margin-block:0 1rem; font-size:0.9rem;">
            Your inquiry goes straight into <?= e($provider['business_name']) ?>'s Kopii inbox with your contact details so they can reply promptly.
        </p>

        <form method="POST" action="<?= e(route_url('/provider/' . $provider['slug'] . '/inquire')) ?>" class="stack-form">
            <?= csrf_field() ?>
            <div class="visually-hidden" aria-hidden="true">
                <label for="website_confirm_hp">Leave empty</label>
                <input type="text" id="website_confirm_hp" name="website_confirm_hp" tabindex="-1" autocomplete="off">
            </div>

            <?php if (!empty($packages)): ?>
                <div class="field">
                    <label for="q-package">Interested Package (Optional)</label>
                    <select id="q-package" name="package_id">
                        <option value="">Custom Quote / Not sure yet</option>
                        <?php foreach ($packages as $pkg): ?>
                            <option value="<?= (int) $pkg['id'] ?>">
                                <?= e($pkg['name']) ?> — <?= e(format_ugx($pkg['price_ugx'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="form-row-2">
                <div class="field">
                    <label for="q-name">Your Full Name *</label>
                    <input
                        type="text"
                        id="q-name"
                        name="client_name"
                        value="<?= e(old('client_name', $authUser['full_name'] ?? '')) ?>"
                        autocomplete="name"
                        data-error="q-name-err"
                        required
                        autofocus
                    >
                    <div id="q-name-err" class="error-msg">Please enter your full name.</div>
                </div>
                <div class="field">
                    <label for="q-phone">Phone / WhatsApp Number *</label>
                    <input
                        type="tel"
                        id="q-phone"
                        name="client_phone"
                        value="<?= e(old('client_phone', '')) ?>"
                        placeholder="+256 77..."
                        autocomplete="tel"
                        data-error="q-phone-err"
                        required
                    >
                    <div id="q-phone-err" class="error-msg">Enter a phone or WhatsApp number where the provider can reach you.</div>
                </div>
            </div>

            <div class="field">
                <label for="q-email">Your Email Address *</label>
                <input
                    type="email"
                    id="q-email"
                    name="client_email"
                    value="<?= e(old('client_email', $authUser['email'] ?? '')) ?>"
                    autocomplete="email"
                    autocorrect="off"
                    autocapitalize="none"
                    spellcheck="false"
                    data-error="q-email-err"
                    required
                >
                <div id="q-email-err" class="error-msg">Please enter a valid email address (e.g. name@example.com).</div>
            </div>

            <div class="form-row-2">
                <div class="field">
                    <label for="q-date">Event / Project Date (Optional)</label>
                    <input type="date" id="q-date" name="event_date" value="<?= e(old('event_date')) ?>">
                </div>
                <div class="field">
                    <label for="q-budget">Estimated Budget in UGX (Optional)</label>
                    <input type="text" inputmode="numeric" id="q-budget" name="budget_ugx" value="<?= e(old('budget_ugx')) ?>" placeholder="e.g. 850000">
                </div>
            </div>

            <div class="field">
                <label for="q-loc">Event Venue or City in Uganda (Optional)</label>
                <input type="text" id="q-loc" name="event_location" value="<?= e(old('event_location')) ?>" placeholder="e.g. Munyonyo, Kampala or Jinja">
            </div>

            <div class="field">
                <label for="q-details">Project / Event Details *</label>
                <textarea
                    id="q-details"
                    name="project_details"
                    minlength="8"
                    data-error="q-details-err"
                    required
                    placeholder="Tell <?= e($provider['business_name']) ?> what you're planning, guest count or deliverables needed, and any questions..."
                ><?= e(old('project_details')) ?></textarea>
                <div id="q-details-err" class="error-msg">Please share a brief description of what you need (at least 8 characters).</div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:0.65rem;">
                <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Send Direct Inquiry</button>
            </div>
        </form>
    </div>
</dialog>
