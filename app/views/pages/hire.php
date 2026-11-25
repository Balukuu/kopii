<?php
defined('KOPII_APP') or exit('Direct access denied.');
$authUser = current_user();
$timelines = [
    'Immediate / This Week',
    'Within 2 weeks',
    'Within a month',
    'Flexible / Planning ahead',
];
?>
<nav class="breadcrumb-bar" aria-label="Breadcrumb">
    <div class="container">
        <ol class="breadcrumb-list">
            <li><a href="<?= e(route_url('/')) ?>">Kopii</a></li>
            <li aria-hidden="true">/</li>
            <li aria-current="page">Get matched (Guided Matcher)</li>
        </ol>
    </div>
</nav>

<section class="section-pad-sm" style="background:var(--hero-slate); border-block-end:1px solid var(--slate-200);">
    <div class="container">
        <div class="section-head-center" style="margin-block-end: 1.75rem;">
            <h1 style="margin:0; font-size:clamp(1.85rem, 1.3rem + 1.9vw, 2.6rem); font-weight:400; color:var(--graphite-800); letter-spacing:-0.015em;">
                <?= $isMatched ? 'Your best matches in Uganda' : 'Tell us what you need and get matched' ?>
            </h1>
            <p>
                <?= $isMatched
                    ? 'Based on your project scope, Kopii scored the providers on the platform and listed your closest matches below.'
                    : 'Answer two quick steps to see the best-matching specialists and send one project brief to your top picks.' ?>
            </p>
        </div>

        <div class="wizard-shell">
            <div class="wizard-progress-head">
                <span id="wizard-progress-label">Kopii Talent Matching Intake</span>
                <span id="wizard-step-status" role="status" aria-live="polite">
                    <?= $isMatched ? 'Diagnostic Complete (Step 2 of 2)' : 'Step 1 of 2: Select Discipline & Specialist Type' ?>
                </span>
            </div>
            <progress
                id="hire-progress"
                aria-labelledby="wizard-progress-label"
                value="<?= $isMatched ? 100 : 50 ?>"
                max="100"
            ></progress>

            <form action="<?= e(route_url('/hire')) ?>#match-results" method="GET" class="stack-form" data-hire-wizard style="margin-block-start:1.5rem;">
                <input type="hidden" name="matched" value="1">

                <!-- Step 1: Discipline & Specialist Structure -->
                <div data-wizard-step="1"<?= $isMatched ? ' hidden' : '' ?>>
                    <div class="stack-form">
                        <fieldset class="form-fieldset">
                            <legend>1. What type of talent or business are you hiring?</legend>
                            <div class="wizard-role-grid">
                                <label class="choice-card">
                                    <input type="radio" name="category" value=""<?= $criteria['category'] === '' ? ' checked' : '' ?>>
                                    <div>
                                        <strong class="label-strong">All Disciplines</strong>
                                        <span class="hint">Match across every discipline on Kopii</span>
                                    </div>
                                </label>
                                <?php foreach ($categories as $cat): ?>
                                    <label class="choice-card">
                                        <input type="radio" name="category" value="<?= e($cat['slug']) ?>"<?= $criteria['category'] === $cat['slug'] ? ' checked' : '' ?>>
                                        <div>
                                            <strong class="label-strong"><?= e($cat['name']) ?></strong>
                                            <span class="hint"><?= (int) $cat['provider_count'] ?> active <?= (int) $cat['provider_count'] === 1 ? 'specialist' : 'specialists' ?></span>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>

                        <fieldset class="form-fieldset">
                            <legend>2. Preferred engagement structure</legend>
                            <div class="choice-grid">
                                <label class="choice-card">
                                    <input type="radio" name="type" value=""<?= $criteria['type'] === '' ? ' checked' : '' ?>>
                                    <div>
                                        <strong class="label-strong">Open to Either</strong>
                                        <span class="hint">Independent freelancers &amp; full studios</span>
                                    </div>
                                </label>
                                <label class="choice-card">
                                    <input type="radio" name="type" value="freelancer"<?= $criteria['type'] === 'freelancer' ? ' checked' : '' ?>>
                                    <div>
                                        <strong class="label-strong">Independent Freelancer</strong>
                                        <span class="hint">Agile solo creator or specialist</span>
                                    </div>
                                </label>
                                <label class="choice-card">
                                    <input type="radio" name="type" value="business"<?= $criteria['type'] === 'business' ? ' checked' : '' ?>>
                                    <div>
                                        <strong class="label-strong">Registered Studio / Business</strong>
                                        <span class="hint">Multi-person crew for larger productions</span>
                                    </div>
                                </label>
                            </div>
                        </fieldset>

                        <div style="display:flex; justify-content:flex-end; gap:0.75rem;">
                            <button type="button" class="btn btn-primary btn-lg" data-wizard-next enterkeyhint="next">
                                Next: Location &amp; UGX Budget <?= svg_icon('arrow-right', 20) ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Ugandan Location, Timeline, UGX Budget & Specific Skills -->
                <div data-wizard-step="2"<?= !$isMatched ? ' hidden' : '' ?>>
                    <div class="stack-form">
                        <h2 id="wizard-step2-heading" tabindex="-1" style="margin:0; font-size:1.2rem; color:var(--slate-950);">
                            Refine Location, Timeline &amp; Target UGX Budget
                        </h2>

                        <div class="form-row-2">
                            <div class="field">
                                <label for="w-city">Event or Project City in Uganda</label>
                                <select id="w-city" name="city">
                                    <option value="">Anywhere in Uganda / Remote</option>
                                    <?php foreach ($cities as $c): ?>
                                        <option value="<?= e($c) ?>"<?= strcasecmp((string) $criteria['city'], $c) === 0 ? ' selected' : '' ?>><?= e($c) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="field">
                                <label for="w-price">Target Starting Rate / Budget (UGX)</label>
                                <select id="w-price" name="max_price">
                                    <option value="0">Flexible / Show Best Matches</option>
                                    <?php foreach ([350000 => 'Up to UGX 350,000', 600000 => 'Up to UGX 600,000', 1200000 => 'Up to UGX 1,200,000', 2500000 => 'Up to UGX 2,500,000'] as $val => $lbl): ?>
                                        <option value="<?= $val ?>"<?= (int) $criteria['max_price'] === $val ? ' selected' : '' ?>><?= e($lbl) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <fieldset class="form-fieldset">
                            <legend>How soon do you need to start or book?</legend>
                            <div class="choice-grid">
                                <?php foreach ($timelines as $tl): ?>
                                    <label class="choice-card">
                                        <input type="radio" name="timeline" value="<?= e($tl) ?>"<?= $criteria['timeline'] === $tl ? ' checked' : '' ?>>
                                        <span style="font-weight:600; color:var(--slate-900);"><?= e($tl) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>

                        <div class="field">
                            <label for="w-q">Specific deliverables or style keywords (Optional)</label>
                            <input
                                type="search"
                                id="w-q"
                                name="q"
                                value="<?= e($criteria['q']) ?>"
                                placeholder="e.g. Drone video, Kwanjula introduction, corporate headshots, 3-piece suit..."
                                spellcheck="false"
                                autocorrect="off"
                            >
                        </div>

                        <div style="display:flex; flex-wrap:wrap; justify-content:space-between; gap:0.75rem;">
                            <button type="button" class="btn btn-outline" data-wizard-prev enterkeyhint="previous">
                                <?= svg_icon('chevron-left', 16) ?> Back to Discipline
                            </button>
                            <button type="submit" class="btn btn-primary btn-lg">
                                <?= svg_icon('sparkles', 20) ?> Match Me With Talent
                            </button>
                        </div>
                    </div>
                </div>
            </form>
            <p class="text-muted" style="margin:1.25rem 0 0; font-size:0.9rem;">
                Rather have providers come to you? <a href="<?= e(route_url('/opportunities/new')) ?>">Post it as a public opportunity</a> and compare their proposals.
            </p>
        </div>
    </div>
</section>

<!-- Ranked Matches + Multi-Specialist Direct Brief Dispatch -->
<section id="match-results" class="section-pad">
    <div class="container">
        <div class="section-head">
            <div>
                <h2>
                    <?= $selectedCat ? 'Best ' . e($selectedCat['name']) . ' matches for your brief' : 'Best matches for your brief' ?>
                </h2>
                <p>Each specialist below is scored on verification status, Ugandan location alignment, UGX budget fit, and portfolio depth.</p>
            </div>
            <a href="<?= e(route_url('/explore', array_filter(['category' => $criteria['category'], 'city' => $criteria['city']]))) ?>" class="btn btn-outline btn-sm">
                Browse all providers
            </a>
        </div>

        <div class="match-layout">
            <div class="provider-grid">
                <?php foreach ($matches as $p): ?>
                    <?php View::partial('provider_card', ['p' => $p]); ?>
                <?php endforeach; ?>
            </div>

            <aside class="sticky-hire-card" aria-labelledby="brief-dispatch-heading">
                <h3 id="brief-dispatch-heading" style="margin:0 0 0.4rem; font-size:1.25rem; color:var(--slate-950);">
                    Send One Brief to Your Top Matches
                </h3>
                <p class="text-muted" style="margin:0 0 1.1rem; font-size:0.88rem;">
                    Select up to 5 matched specialists below to deliver your project brief straight into their Kopii Leads Inbox simultaneously.
                </p>

                <form method="POST" action="<?= e(route_url('/hire/brief')) ?>" class="stack-form" aria-labelledby="brief-dispatch-heading">
                    <?= csrf_field() ?>
                    <input type="hidden" name="category" value="<?= e($criteria['category']) ?>">
                    <input type="hidden" name="city" value="<?= e($criteria['city']) ?>">
                    <input type="hidden" name="max_price" value="<?= (int) $criteria['max_price'] ?>">
                    <input type="hidden" name="timeline" value="<?= e($criteria['timeline']) ?>">

                    <div class="visually-hidden" aria-hidden="true">
                        <label for="brief_confirm_hp">Leave blank</label>
                        <input type="text" id="brief_confirm_hp" name="brief_confirm_hp" tabindex="-1" autocomplete="off">
                    </div>

                    <fieldset class="form-fieldset" style="padding:0.95rem;">
                        <legend>Matched recipients (1–5) *</legend>
                        <div style="display:grid; grid-template-columns:minmax(0, 1fr); gap:0.5rem;">
                            <?php foreach (array_slice($matches, 0, 5) as $idx => $m): ?>
                                <label class="match-select-row">
                                    <input
                                        type="checkbox"
                                        name="provider_ids[]"
                                        value="<?= (int) $m['id'] ?>"<?= $idx < 3 ? ' checked' : '' ?>
                                    >
                                    <span style="flex:1; min-inline-size:0;">
                                        <strong style="display:block; color:var(--slate-950); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                            <?= e($m['business_name']) ?>
                                        </strong>
                                        <span class="text-muted" style="font-size:0.78rem;">
                                            <?= (int) ($m['match_score'] ?? 90) ?>% match &middot; <?= e($m['city']) ?> &middot; <?= e(format_ugx($m['starting_price_ugx'])) ?>
                                        </span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <div class="field">
                        <label for="b-name">Your Full Name *</label>
                        <input
                            type="text"
                            id="b-name"
                            name="client_name"
                            value="<?= e(old('client_name', $authUser['full_name'] ?? '')) ?>"
                            autocomplete="name"
                            data-error="b-name-err"
                            required
                        >
                        <div id="b-name-err" class="error-msg">Please enter your full name.</div>
                    </div>

                    <div class="form-row-2">
                        <div class="field">
                            <label for="b-phone">Phone / WhatsApp *</label>
                            <input
                                type="tel"
                                id="b-phone"
                                name="client_phone"
                                value="<?= e(old('client_phone')) ?>"
                                placeholder="+256 77..."
                                autocomplete="tel"
                                data-error="b-phone-err"
                                required
                            >
                            <div id="b-phone-err" class="error-msg">Enter your phone or WhatsApp number.</div>
                        </div>

                        <div class="field">
                            <label for="b-email">Email (Optional)</label>
                            <input
                                type="email"
                                id="b-email"
                                name="client_email"
                                value="<?= e(old('client_email', $authUser['email'] ?? '')) ?>"
                                autocomplete="email"
                                autocorrect="off"
                                autocapitalize="none"
                                spellcheck="false"
                                data-error="b-email-err"
                            >
                            <div id="b-email-err" class="error-msg">That email address does not look right. Fix it, or leave it blank.</div>
                        </div>
                    </div>

                    <div class="form-row-2">
                        <div class="field">
                            <label for="b-date">Target Date (Optional)</label>
                            <input type="date" id="b-date" name="event_date" value="<?= e(old('event_date')) ?>">
                        </div>
                        <div class="field">
                            <label for="b-budget">Budget in UGX (Optional)</label>
                            <input
                                type="text"
                                inputmode="numeric"
                                id="b-budget"
                                name="budget_ugx"
                                value="<?= e(old('budget_ugx', $criteria['max_price'] > 0 ? (string) $criteria['max_price'] : '')) ?>"
                                placeholder="e.g. 900000"
                            >
                        </div>
                    </div>

                    <div class="field">
                        <label for="b-loc">Venue or Ugandan City (Optional)</label>
                        <input
                            type="text"
                            id="b-loc"
                            name="event_location"
                            value="<?= e(old('event_location', $criteria['city'])) ?>"
                            placeholder="e.g. Kololo, Kampala or Entebbe"
                        >
                    </div>

                    <div class="field">
                        <label for="b-details">Project Scope &amp; Deliverables *</label>
                        <textarea
                            id="b-details"
                            name="project_details"
                            minlength="8"
                            data-error="b-details-err"
                            required
                            placeholder="Describe your event or project, key deliverables, hours/days needed, and any style references..."
                        ><?= e(old('project_details')) ?></textarea>
                        <div id="b-details-err" class="error-msg">Please describe your project (at least 8 characters).</div>
                    </div>

                    <p class="hint form-privacy">Your details go to the providers you selected so they can reply. See our <a href="<?= e(route_url('/privacy')) ?>">Privacy Policy</a>.</p>

                    <button type="submit" class="btn btn-primary btn-lg btn-block">
                        <?= svg_icon('mail', 20) ?> Dispatch Brief to Selected Talent
                    </button>

                    <div class="risk-free-box" style="margin-block-start:0.25rem;">
                        <?= svg_icon('shield', 20) ?>
                        <span><strong>Zero-Commission Direct Hire:</strong> Free to inquire. Specialists reply directly to your WhatsApp, phone, or email with tailored UGX quotes.</span>
                    </div>
                </form>
            </aside>
        </div>
    </div>
</section>
