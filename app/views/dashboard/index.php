<?php
defined('KOPII_APP') or exit('Direct access denied.');
/** @var array $provider */
$publicUrl = route_url('/provider/' . $provider['slug']);
?>
<section class="dash-header">
    <div class="container">
        <div style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:1rem;">
            <div>
                <div class="card-badges" style="margin-block-end:0.4rem;">
                    <span class="badge badge-featured">Provider Studio</span>
                    <?php if (!empty($provider['is_verified'])): ?>
                        <span class="badge badge-verified"><?= svg_icon('verified', 16) ?> Verified</span>
                    <?php endif; ?>
                </div>
                <h1 style="margin:0; font-size:clamp(1.45rem, 2.6vw, 2rem); color:#fff;">
                    <?= e($provider['business_name']) ?>
                </h1>
                <p style="margin:0.25rem 0 0; color:#cbd5e1; font-size:0.92rem;">
                    <?= e($provider['category_name']) ?> &middot; <?= e($provider['city']) ?>, Uganda
                </p>
            </div>
            <a href="<?= e($publicUrl) ?>" class="btn btn-outline btn-sm">
                <?= svg_icon('globe', 16) ?> View Public Showcase
            </a>
        </div>

        <nav class="dash-tabs" aria-label="Studio dashboard sections">
            <a class="dash-tab" href="<?= e(route_url('/dashboard', ['tab' => 'overview'])) ?>"<?= $tab === 'overview' ? ' aria-current="page"' : '' ?>>
                Overview
            </a>
            <a class="dash-tab" href="<?= e(route_url('/dashboard', ['tab' => 'inquiries'])) ?>"<?= $tab === 'inquiries' ? ' aria-current="page"' : '' ?>>
                <?= svg_icon('inbox', 16) ?> Client Leads (<?= (int) $inquiryCounts['new'] ?> new)
            </a>
            <a class="dash-tab" href="<?= e(route_url('/dashboard', ['tab' => 'portfolio'])) ?>"<?= $tab === 'portfolio' ? ' aria-current="page"' : '' ?>>
                <?= svg_icon('image', 16) ?> Portfolio Work (<?= count($portfolio) ?>)
            </a>
            <a class="dash-tab" href="<?= e(route_url('/dashboard', ['tab' => 'packages'])) ?>"<?= $tab === 'packages' ? ' aria-current="page"' : '' ?>>
                <?= svg_icon('layers', 16) ?> UGX Packages (<?= count($packages) ?>)
            </a>
            <a class="dash-tab" href="<?= e(route_url('/dashboard', ['tab' => 'profile'])) ?>"<?= $tab === 'profile' ? ' aria-current="page"' : '' ?>>
                Profile &amp; Branding
            </a>
        </nav>
    </div>
</section>

<section class="section-pad-sm">
    <div class="container">
        <?php if (($provider['status'] ?? 'active') !== 'active'): ?>
            <div class="alert alert-warning" role="status">
                Your listing is paused by the Kopii team, so it is hidden from search and clients cannot send you quote requests.
                Check your email for the reason, or contact the Kopii team to have it made live again.
            </div>
        <?php endif; ?>

        <?php if ($tab === 'overview'): ?>
            <div class="kpi-grid">
                <div class="kpi-card">
                    <span>Profile Views</span>
                    <strong class="tnum"><?= number_format((int) $provider['views_count']) ?></strong>
                </div>
                <div class="kpi-card">
                    <span>New Quote Leads</span>
                    <strong class="tnum"><?= (int) $inquiryCounts['new'] ?></strong>
                </div>
                <div class="kpi-card">
                    <span>Portfolio Showcases</span>
                    <strong><?= count($portfolio) ?></strong>
                </div>
                <div class="kpi-card">
                    <span>Client Rating</span>
                    <strong class="tnum"><?= (int) $provider['review_count'] > 0 ? number_format((float) $provider['avg_rating'], 1) . ' ★' : 'New' ?></strong>
                </div>
            </div>

            <div class="profile-layout">
                <div>
                    <div class="content-panel">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-block-end:1rem;">
                            <h2 class="m-0">Recent Client Inquiries</h2>
                            <a href="<?= e(route_url('/dashboard', ['tab' => 'inquiries'])) ?>" class="btn btn-outline btn-sm">Open Full Inbox</a>
                        </div>
                        <?php if (empty($inquiries)): ?>
                            <p class="text-muted m-0">No direct inquiries yet. Keep uploading portfolio photos and packages so clients can discover your work!</p>
                        <?php else: ?>
                            <?php foreach (array_slice($inquiries, 0, 3) as $inq): ?>
                                <div class="lead-card<?= $inq['status'] === 'new' ? ' is-new' : '' ?>">
                                    <div style="display:flex; flex-wrap:wrap; justify-content:space-between; gap:0.5rem; margin-block-end:0.4rem;">
                                        <strong><?= e($inq['client_name']) ?></strong>
                                        <span class="badge <?= $inq['status'] === 'new' ? 'badge-verified' : 'badge-type' ?>"><?= e(ucfirst((string) $inq['status'])) ?></span>
                                    </div>
                                    <p style="margin:0 0 0.65rem; font-size:0.92rem; color:var(--slate-700);"><?= e($inq['project_details']) ?></p>
                                    <div style="display:flex; flex-wrap:wrap; gap:0.65rem; font-size:0.84rem; color:var(--slate-600);">
                                        <?php if (!empty($inq['event_date'])): ?>
                                            <span>Date: <strong><?= e($inq['event_date']) ?></strong></span>
                                        <?php endif; ?>
                                        <?php if (!empty($inq['budget_ugx'])): ?>
                                            <span>Budget: <strong class="tnum"><?= e(format_ugx($inq['budget_ugx'])) ?></strong></span>
                                        <?php endif; ?>
                                        <a href="<?= e(whatsapp_url((string) $inq['client_phone'], 'Hi ' . $inq['client_name'] . ', thanks for reaching out to ' . $provider['business_name'] . ' on Kopii!')) ?>" target="_blank" rel="noopener">Reply on WhatsApp</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <aside class="content-panel">
                    <h2 id="dash-strength-label">Showcase Strength: <?= (int) $completeness ?>%</h2>
                    <progress value="<?= (int) $completeness ?>" max="100" aria-labelledby="dash-strength-label" style="inline-size:100%; block-size:0.65rem; margin-block-end:1rem;"></progress>
                    <ul class="deliverables-list">
                        <?php foreach ($checks as $label => $done): ?>
                            <li>
                                <?= svg_icon($done ? 'check' : 'plus', 16) ?>
                                <span style="<?= $done ? '' : 'color:var(--slate-500);' ?>"><?= e($label) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div style="display:grid; gap:0.6rem; margin-block-start:1.25rem;">
                        <a href="<?= e(route_url('/dashboard', ['tab' => 'portfolio'])) ?>" class="btn btn-primary btn-sm">+ Upload Portfolio Photo</a>
                        <a href="<?= e(route_url('/dashboard', ['tab' => 'packages'])) ?>" class="btn btn-outline btn-sm">+ Add UGX Service Package</a>
                    </div>
                </aside>
            </div>

        <?php elseif ($tab === 'inquiries'): ?>
            <div class="content-panel">
                <div style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:1rem; margin-block-end:1.25rem;">
                    <h2 class="m-0">Client Quote &amp; Booking Leads (<?= (int) $inquiryCounts['all'] ?>)</h2>
                    <div style="display:flex; flex-wrap:wrap; gap:0.4rem;">
                        <?php foreach (['' => 'All (' . $inquiryCounts['all'] . ')', 'new' => 'New (' . $inquiryCounts['new'] . ')', 'contacted' => 'Contacted (' . $inquiryCounts['contacted'] . ')', 'booked' => 'Booked (' . $inquiryCounts['booked'] . ')'] as $stKey => $stLabel): ?>
                            <a
                                href="<?= e(route_url('/dashboard', ['tab' => 'inquiries', 'status' => $stKey])) ?>"
                                class="btn btn-sm <?= ($statusFilter ?? '') === $stKey ? 'btn-primary' : 'btn-outline' ?>"
                            >
                                <?= e($stLabel) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if (empty($inquiries)): ?>
                    <p class="text-muted">No leads in this view yet.</p>
                <?php else: ?>
                    <?php foreach ($inquiries as $inq): ?>
                        <?php
                        $replyText = 'Hello ' . $inq['client_name'] . '! Thank you for your inquiry on Kopii for ' . $provider['business_name'] . '. Let\'s discuss your project details!';
                        ?>
                        <article class="lead-card<?= $inq['status'] === 'new' ? ' is-new' : '' ?>">
                            <div style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:flex-start; gap:1rem; margin-block-end:0.75rem;">
                                <div>
                                    <span class="badge <?= $inq['status'] === 'new' ? 'badge-verified' : ($inq['status'] === 'booked' ? 'badge-avail' : 'badge-type') ?>">
                                        <?= e(strtoupper((string) $inq['status'])) ?>
                                    </span>
                                    <h3 style="margin:0.35rem 0 0.15rem; font-size:1.12rem; color:var(--navy-900);">
                                        <?= e($inq['client_name']) ?>
                                    </h3>
                                    <div style="font-size:0.86rem; color:var(--slate-600);">
                                        Received <?= e(time_ago((string) $inq['created_at'])) ?>
                                        &middot; Phone: <strong><?= e($inq['client_phone']) ?></strong>
                                        &middot; Email: <?= e($inq['client_email']) ?>
                                    </div>
                                </div>

                                <div style="display:flex; flex-wrap:wrap; gap:0.5rem;">
                                    <a href="<?= e(whatsapp_url((string) $inq['client_phone'], $replyText)) ?>" target="_blank" rel="noopener" class="btn btn-whatsapp btn-sm">
                                        <?= svg_icon('whatsapp', 16) ?> WhatsApp Client
                                    </a>
                                    <a href="<?= e(tel_url((string) $inq['client_phone'])) ?>" class="btn btn-outline btn-sm">
                                        <?= svg_icon('phone', 16) ?> Call
                                    </a>
                                </div>
                            </div>

                            <div style="display:flex; flex-wrap:wrap; gap:0.85rem; margin-block-end:0.75rem; font-size:0.86rem;">
                                <?php if (!empty($inq['package_name'])): ?>
                                    <span class="tag-chip">Selected Package: <?= e($inq['package_name']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($inq['event_date'])): ?>
                                    <span class="tag-chip">Target Date: <?= e($inq['event_date']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($inq['event_location'])): ?>
                                    <span class="tag-chip">Location: <?= e($inq['event_location']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($inq['budget_ugx'])): ?>
                                    <span class="tag-chip">Client Budget: <?= e(format_ugx($inq['budget_ugx'])) ?></span>
                                <?php endif; ?>
                            </div>

                            <p style="margin:0 0 1rem; padding:0.85rem; background:var(--slate-50); border-radius:var(--radius-md); color:var(--slate-900);">
                                <?= e($inq['project_details']) ?>
                            </p>

                            <form method="POST" action="<?= e(route_url('/dashboard/inquiries/status')) ?>" style="display:flex; flex-wrap:wrap; align-items:center; gap:0.5rem;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="inquiry_id" value="<?= (int) $inq['id'] ?>">
                                <label for="st-<?= (int) $inq['id'] ?>" style="font-size:0.85rem; font-weight:700;">Lead Status:</label>
                                <select id="st-<?= (int) $inq['id'] ?>" name="status" style="inline-size:auto; min-block-size:40px; padding:0.35rem 0.7rem;">
                                    <?php foreach (['new' => 'New', 'contacted' => 'Contacted', 'booked' => 'Booked / Won', 'archived' => 'Archived'] as $k => $v): ?>
                                        <option value="<?= $k ?>"<?= $inq['status'] === $k ? ' selected' : '' ?>><?= e($v) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-outline btn-sm">Save Status</button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        <?php elseif ($tab === 'portfolio'): ?>
            <div class="profile-layout">
                <div>
                    <div class="content-panel">
                        <h2>Your Published Portfolio (<?= count($portfolio) ?>)</h2>
                        <?php if (empty($portfolio)): ?>
                            <p class="text-muted">Upload your best photographs, designs, or completed projects using the form so customers can see your craft!</p>
                        <?php else: ?>
                            <div class="work-grid">
                                <?php foreach ($portfolio as $item): ?>
                                    <article class="work-card">
                                        <img src="<?= e(thumb_url($item['image_path'])) ?>" alt="<?= e($item['title']) ?>" width="640" height="480" loading="lazy" style="aspect-ratio:4/3; object-fit:cover;">
                                        <div class="work-card-meta">
                                            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:0.5rem;">
                                                <h3><?= e($item['title']) ?></h3>
                                                <?php if (!empty($item['project_tag'])): ?>
                                                    <span class="tag-chip"><?= e($item['project_tag']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($item['description'])): ?>
                                                <p class="text-muted" style="margin:0; font-size:0.84rem;"><?= e($item['description']) ?></p>
                                            <?php endif; ?>
                                            <form method="POST" action="<?= e(route_url('/dashboard/portfolio/delete')) ?>" style="margin-block-start:0.5rem;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                                <button type="submit" class="btn btn-danger-outline btn-sm">
                                                    <?= svg_icon('trash', 16) ?> Remove
                                                </button>
                                            </form>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <aside class="content-panel">
                    <h2>Upload New Work Sample</h2>
                    <form method="POST" action="<?= e(route_url('/dashboard/portfolio/add')) ?>" enctype="multipart/form-data" class="stack-form">
                        <?= csrf_field() ?>
                        <div class="field">
                            <label for="p-img">Work Photograph (JPG, PNG, WebP up to 6 MB) *</label>
                            <input type="file" id="p-img" name="portfolio_image" accept="image/jpeg,image/png,image/webp" required>
                        </div>
                        <div class="field">
                            <label for="p-title">Project / Shoot Title *</label>
                            <input type="text" id="p-title" name="title" placeholder="e.g. Lakeside Sunset Wedding" required>
                        </div>
                        <div class="field">
                            <label for="p-tag">Category Tag (Optional)</label>
                            <input type="text" id="p-tag" name="project_tag" placeholder="e.g. Wedding, Portrait, Product">
                        </div>
                        <div class="field">
                            <label for="p-loc">Location in Uganda (Optional)</label>
                            <input type="text" id="p-loc" name="client_location" placeholder="e.g. Entebbe, Kololo">
                        </div>
                        <div class="field">
                            <label for="p-desc">Short Story / Details (Optional)</label>
                            <textarea id="p-desc" name="description" placeholder="Lighting, brief, or what you delivered for this client..."></textarea>
                        </div>
                        <div class="checkbox-row">
                            <input type="checkbox" id="p-feat" name="is_featured" value="1" checked>
                            <label for="p-feat">Pin to top 3 card preview strip</label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">
                            <?= svg_icon('plus', 16) ?> Publish to Portfolio
                        </button>
                    </form>
                </aside>
            </div>

        <?php elseif ($tab === 'packages'): ?>
            <div class="profile-layout">
                <div>
                    <div class="content-panel">
                        <h2>Service Packages &amp; UGX Pricing (<?= count($packages) ?>)</h2>
                        <?php if (empty($packages)): ?>
                            <p class="text-muted">Adding clear UGX packages helps customers compare options and send higher-intent booking requests.</p>
                        <?php else: ?>
                            <div class="package-grid">
                                <?php foreach ($packages as $pkg): ?>
                                    <div class="package-card<?= !empty($pkg['is_popular']) ? ' is-popular' : '' ?>">
                                        <div>
                                            <?php if (!empty($pkg['is_popular'])): ?>
                                                <span class="badge badge-verified" style="margin-block-end:0.4rem;">Popular</span>
                                            <?php endif; ?>
                                            <h3 style="margin:0 0 0.25rem;"><?= e($pkg['name']) ?></h3>
                                            <strong class="tnum" style="font-size:1.25rem; color:var(--cobalt-700);"><?= e(format_ugx($pkg['price_ugx'])) ?></strong>
                                            <span class="text-muted" style="font-size:0.84rem;"> / <?= e($pkg['price_unit']) ?></span>
                                            <?php if (!empty($pkg['description'])): ?>
                                                <p class="text-muted" style="margin:0.5rem 0 0; font-size:0.88rem;"><?= e($pkg['description']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <form method="POST" action="<?= e(route_url('/dashboard/packages/delete')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="package_id" value="<?= (int) $pkg['id'] ?>">
                                            <button type="submit" class="btn btn-danger-outline btn-sm">
                                                <?= svg_icon('trash', 16) ?> Delete Package
                                            </button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <aside class="content-panel">
                    <h2>Add Service Package</h2>
                    <form method="POST" action="<?= e(route_url('/dashboard/packages/add')) ?>" class="stack-form">
                        <?= csrf_field() ?>
                        <div class="field">
                            <label for="pkg-name">Package Name *</label>
                            <input type="text" id="pkg-name" name="name" placeholder="e.g. Half-Day Event Coverage" required>
                        </div>
                        <div class="form-row-2">
                            <div class="field">
                                <label for="pkg-price">Price (UGX) *</label>
                                <input type="text" inputmode="numeric" id="pkg-price" name="price_ugx" placeholder="e.g. 650000" required>
                            </div>
                            <div class="field">
                                <label for="pkg-unit">Pricing Unit</label>
                                <select id="pkg-unit" name="price_unit">
                                    <?php foreach ($priceUnits as $u): ?>
                                        <option value="<?= e($u) ?>"><?= e($u) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="field">
                            <label for="pkg-turn">Delivery Turnaround</label>
                            <input type="text" id="pkg-turn" name="turnaround" placeholder="e.g. 5 business days">
                        </div>
                        <div class="field">
                            <label for="pkg-desc">Short Summary</label>
                            <input type="text" id="pkg-desc" name="description" placeholder="Who is this package best for?">
                        </div>
                        <div class="field">
                            <label for="pkg-deliv">Deliverables (One item per line)</label>
                            <textarea id="pkg-deliv" name="deliverables" placeholder="Up to 4 hours coverage&#10;120 retouched high-res photos&#10;Online gallery link"></textarea>
                        </div>
                        <div class="checkbox-row">
                            <input type="checkbox" id="pkg-pop" name="is_popular" value="1">
                            <label for="pkg-pop">Highlight as "Most Popular Choice"</label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">Add Package</button>
                    </form>
                </aside>
            </div>

        <?php elseif ($tab === 'profile'): ?>
            <div class="content-panel" style="max-inline-size:52rem;">
                <h2>Edit Public Showcase &amp; Contact Settings</h2>
                <form method="POST" action="<?= e(route_url('/dashboard/profile')) ?>" enctype="multipart/form-data" class="stack-form">
                    <?= csrf_field() ?>

                    <div class="form-row-2">
                        <div class="field">
                            <label for="ed-name">Business / Freelancer Brand Name *</label>
                            <input type="text" id="ed-name" name="business_name" value="<?= e($provider['business_name']) ?>" required>
                        </div>
                        <div class="field">
                            <label for="ed-cat">Primary Category *</label>
                            <select id="ed-cat" name="category_id" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= (int) $cat['id'] ?>"<?= (int) $provider['category_id'] === (int) $cat['id'] ? ' selected' : '' ?>>
                                        <?= e($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row-2">
                        <div class="field">
                            <label for="ed-type">Provider Type</label>
                            <select id="ed-type" name="provider_type">
                                <option value="freelancer"<?= $provider['provider_type'] === 'freelancer' ? ' selected' : '' ?>>Independent Freelancer</option>
                                <option value="business"<?= $provider['provider_type'] === 'business' ? ' selected' : '' ?>>Business / Studio / Agency</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="ed-price">Starting Rate in UGX</label>
                            <input type="text" inputmode="numeric" id="ed-price" name="starting_price_ugx" value="<?= (int) $provider['starting_price_ugx'] ?>">
                        </div>
                    </div>

                    <div class="field">
                        <label for="ed-head">Tagline / Headline *</label>
                        <input type="text" id="ed-head" name="headline" value="<?= e($provider['headline']) ?>" maxlength="200" required>
                    </div>

                    <div class="field">
                        <label for="ed-bio">Full Bio &amp; Story *</label>
                        <textarea id="ed-bio" name="bio" rows="5" required><?= e($provider['bio']) ?></textarea>
                    </div>

                    <div class="form-row-2">
                        <div class="field">
                            <label for="ed-city">City in Uganda *</label>
                            <select id="ed-city" name="city">
                                <?php foreach ($cities as $c): ?>
                                    <option value="<?= e($c) ?>"<?= $provider['city'] === $c ? ' selected' : '' ?>><?= e($c) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="ed-area">Neighborhood / Street</label>
                            <input type="text" id="ed-area" name="neighborhood" value="<?= e($provider['neighborhood'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-row-2">
                        <div class="field">
                            <label for="ed-phone">Phone Number *</label>
                            <input type="tel" id="ed-phone" name="phone" value="<?= e($provider['phone']) ?>" autocomplete="tel" required>
                        </div>
                        <div class="field">
                            <label for="ed-wa">WhatsApp Number *</label>
                            <input type="tel" id="ed-wa" name="whatsapp" value="<?= e($provider['whatsapp']) ?>" required>
                        </div>
                    </div>

                    <div class="form-row-2">
                        <div class="field">
                            <label for="ed-exp">Years of Experience</label>
                            <input type="text" inputmode="numeric" id="ed-exp" name="years_experience" value="<?= (int) $provider['years_experience'] ?>">
                        </div>
                        <div class="field">
                            <label for="ed-resp">Typical Response Time</label>
                            <select id="ed-resp" name="response_time">
                                <?php foreach ($responseTimes as $rt): ?>
                                    <option value="<?= e($rt) ?>"<?= $provider['response_time'] === $rt ? ' selected' : '' ?>><?= e($rt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row-2">
                        <div class="field">
                            <label for="ed-spec">Specialties (Comma-separated)</label>
                            <input type="text" id="ed-spec" name="specialties" value="<?= e($provider['specialties'] ?? '') ?>">
                        </div>
                        <div class="field">
                            <label for="ed-ig">Instagram Handle</label>
                            <input type="text" id="ed-ig" name="instagram" value="<?= e($provider['instagram'] ?? '') ?>" placeholder="@yourstudio">
                        </div>
                    </div>

                    <div class="field">
                        <label for="ed-web">Website</label>
                        <input type="text" id="ed-web" name="website" value="<?= e($provider['website'] ?? '') ?>" placeholder="https://yourstudio.ug" inputmode="url" autocomplete="url" autocapitalize="none" spellcheck="false" maxlength="255">
                        <span class="hint">Shown on your public profile. Optional.</span>
                    </div>

                    <div class="form-row-2">
                        <div class="field">
                            <label for="ed-avatar">Profile Logo / Avatar (JPG, PNG, WebP)</label>
                            <input type="file" id="ed-avatar" name="avatar_image" accept="image/jpeg,image/png,image/webp">
                        </div>
                        <div class="field">
                            <label for="ed-cover">Cover Banner Photo (JPG, PNG, WebP)</label>
                            <input type="file" id="ed-cover" name="cover_image" accept="image/jpeg,image/png,image/webp">
                        </div>
                    </div>

                    <div class="checkbox-row">
                        <input type="checkbox" id="ed-avail" name="is_available" value="1"<?= !empty($provider['is_available']) ? ' checked' : '' ?>>
                        <label for="ed-avail">Show "Available for Bookings" badge on my profile</label>
                    </div>

                    <div>
                        <button type="submit" class="btn btn-primary btn-lg">Save Profile Changes</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</section>
