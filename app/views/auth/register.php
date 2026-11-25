<?php
defined('KOPII_APP') or exit('Direct access denied.');
$selectedRole = old('account_role', 'provider');
$selectedType = old('provider_type', 'freelancer');
?>
<section class="section-pad auth-body">
    <div class="container">
        <div class="auth-split">
            <div class="auth-card" style="max-inline-size:none; margin:0;">
                <div style="margin-block-end:1.75rem;">
                    <h1 style="margin:0 0 0.45rem; color:var(--graphite-800); font-size:clamp(1.65rem, 2.5vw, 2.15rem); font-weight:600; line-height:1.2;">
                        List your business on Kopii
                    </h1>
                    <p style="margin:0; font-size:0.95rem; color:var(--graphite-700);">
                        Create a free profile with your portfolio and UGX prices. Clients contact you directly.
                    </p>
                </div>

                <form method="POST" action="<?= e(route_url('/register')) ?>" class="stack-form">
                    <?= csrf_field() ?>
                    <div class="visually-hidden" aria-hidden="true">
                        <label for="company_fax_hp">Leave empty</label>
                        <input type="text" id="company_fax_hp" name="company_fax_hp" tabindex="-1" autocomplete="off">
                    </div>

                    <fieldset class="form-fieldset">
                        <legend>I am joining Kopii to:</legend>
                        <div class="choice-grid">
                            <label class="choice-card">
                                <input type="radio" name="account_role" value="provider"<?= $selectedRole !== 'client' ? ' checked' : '' ?>>
                                <div>
                                    <strong class="label-strong">List my business or freelance work</strong>
                                    <span class="hint">Publish portfolio work, UGX packages &amp; receive direct client briefs</span>
                                </div>
                            </label>
                            <label class="choice-card">
                                <input type="radio" name="account_role" value="client"<?= $selectedRole === 'client' ? ' checked' : '' ?>>
                                <div>
                                    <strong class="label-strong">I want to hire (client account)</strong>
                                    <span class="hint">Manage project inquiries &amp; leave verified client reviews</span>
                                </div>
                            </label>
                        </div>
                    </fieldset>

                    <fieldset class="form-fieldset">
                        <legend>Applicant Contact &amp; Login</legend>
                        <div class="form-row-2">
                            <div class="field">
                                <label for="reg-name">Full Name *</label>
                                <input
                                    type="text"
                                    id="reg-name"
                                    name="full_name"
                                    value="<?= e(old('full_name')) ?>"
                                    placeholder="e.g. Joshua Kato"
                                    autocomplete="name"
                                    enterkeyhint="next"
                                    data-error="reg-name-err"
                                    required
                                >
                                <div id="reg-name-err" class="error-msg">Please enter your full name.</div>
                            </div>
                            <div class="field">
                                <label for="reg-phone">Phone / WhatsApp (+256)&nbsp;*</label>
                                <input
                                    type="tel"
                                    id="reg-phone"
                                    name="phone"
                                    value="<?= e(old('phone')) ?>"
                                    placeholder="+256 772 000 000"
                                    autocomplete="tel"
                                    enterkeyhint="next"
                                    data-error="reg-phone-err"
                                    required
                                >
                                <div id="reg-phone-err" class="error-msg">Enter a valid Ugandan phone or WhatsApp number.</div>
                            </div>
                        </div>

                        <div class="field">
                            <label for="reg-email">Work or Studio Email *</label>
                            <span id="reg-email-hint" class="hint">Used to sign in to your Kopii dashboard and receive inquiry alerts</span>
                            <input
                                type="email"
                                id="reg-email"
                                name="email"
                                value="<?= e(old('email')) ?>"
                                placeholder="hello@yourstudio.ug"
                                autocomplete="username"
                                autocorrect="off"
                                autocapitalize="none"
                                spellcheck="false"
                                aria-describedby="reg-email-hint"
                                data-error="reg-email-err"
                                enterkeyhint="next"
                                required
                            >
                            <div id="reg-email-err" class="error-msg">Please enter a valid email address (e.g. studio@example.com).</div>
                        </div>

                        <div class="field">
                            <label for="reg-password">Create Password *</label>
                            <span id="reg-pass-hint" class="hint">Minimum 8 characters (password manager paste is supported)</span>
                            <div class="password-wrap">
                                <input
                                    type="password"
                                    id="reg-password"
                                    name="new_password"
                                    autocomplete="new-password"
                                    minlength="8"
                                    aria-describedby="reg-pass-hint"
                                    data-error="reg-pass-err"
                                    enterkeyhint="next"
                                    required
                                >
                                <button
                                    type="button"
                                    class="password-toggle"
                                    data-toggle-password="reg-password"
                                    aria-pressed="false"
                                    aria-label="Show password"
                                    aria-describedby="reg-toggle-warn"
                                >
                                    <?= svg_icon('eye', 20, 'icon-eye') ?>
                                    <?= svg_icon('eye-off', 20, 'icon-eye-off') ?>
                                </button>
                            </div>
                            <div id="reg-pass-err" class="error-msg">Password must be at least 8 characters long.</div>
                            <span id="reg-toggle-warn" class="visually-hidden">Warning: displays your password on screen.</span>
                        </div>
                    </fieldset>

                    <fieldset id="provider-onboarding-fields" class="form-fieldset">
                        <legend>Professional Dossier &amp; Discipline</legend>

                        <div class="choice-grid">
                            <label class="choice-card">
                                <input type="radio" name="provider_type" value="freelancer"<?= $selectedType !== 'business' ? ' checked' : '' ?>>
                                <div>
                                    <strong class="label-strong">Independent Freelancer</strong>
                                    <span class="hint">Solo photographer, designer, stylist, developer, MC...</span>
                                </div>
                            </label>
                            <label class="choice-card">
                                <input type="radio" name="provider_type" value="business"<?= $selectedType === 'business' ? ' checked' : '' ?>>
                                <div>
                                    <strong class="label-strong">Studio / Agency / Business</strong>
                                    <span class="hint">Production house, atelier, catering team, creative agency...</span>
                                </div>
                            </label>
                        </div>

                        <div class="form-row-2">
                            <div class="field">
                                <label for="reg-biz">Public Talent or Studio Name *</label>
                                <input
                                    type="text"
                                    id="reg-biz"
                                    name="business_name"
                                    value="<?= e(old('business_name')) ?>"
                                    placeholder="e.g. Kampala Light Studio"
                                    autocomplete="organization"
                                    data-provider-required
                                    data-error="reg-biz-err"
                                    required
                                >
                                <div id="reg-biz-err" class="error-msg">Enter the public name clients will see on your dossier.</div>
                            </div>
                            <div class="field">
                                <label for="reg-cat">Primary Discipline *</label>
                                <select id="reg-cat" name="category_id" data-provider-required data-error="reg-cat-err" required>
                                    <option value="">Select primary vertical...</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= (int) $cat['id'] ?>"<?= (int) old('category_id') === (int) $cat['id'] ? ' selected' : '' ?>>
                                            <?= e($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="reg-cat-err" class="error-msg">Select your primary discipline.</div>
                            </div>
                        </div>

                        <div class="form-row-2">
                            <div class="field">
                                <label for="reg-city">Primary City in Uganda *</label>
                                <select id="reg-city" name="city">
                                    <?php foreach ($cities as $c): ?>
                                        <option value="<?= e($c) ?>"<?= old('city', 'Kampala') === $c ? ' selected' : '' ?>><?= e($c) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label for="reg-area">Neighborhood / Studio Area (Optional)</label>
                                <input type="text" id="reg-area" name="neighborhood" value="<?= e(old('neighborhood')) ?>" placeholder="e.g. Kololo, Ntinda, Bugolobi">
                            </div>
                        </div>

                        <div class="field">
                            <label for="reg-serves">Other places you serve (Optional)</label>
                            <input type="text" id="reg-serves" name="service_areas" value="<?= e(old('service_areas')) ?>" maxlength="200" placeholder="e.g. Wakiso, Mukono, Entebbe Road">
                            <span class="hint">Customers often want someone close by. Separate places with commas.</span>
                        </div>

                        <div class="form-row-2">
                            <div class="field">
                                <label for="reg-price">Starting Rate in UGX (Optional)</label>
                                <input type="text" inputmode="numeric" id="reg-price" name="starting_price_ugx" value="<?= e(old('starting_price_ugx')) ?>" placeholder="e.g. 500000">
                            </div>
                            <div class="field">
                                <label for="reg-spec">Expertise Tags (comma-separated)</label>
                                <input type="text" id="reg-spec" name="specialties" value="<?= e(old('specialties')) ?>" placeholder="Weddings, Commercial Video, Drone">
                            </div>
                        </div>

                        <div class="field">
                            <label for="reg-headline">Professional Headline *</label>
                            <input
                                type="text"
                                id="reg-headline"
                                name="headline"
                                value="<?= e(old('headline')) ?>"
                                placeholder="e.g. Documentary Wedding & Commercial Photographer in Kampala"
                                maxlength="200"
                                data-provider-required
                                required
                            >
                        </div>

                        <div class="field">
                            <label for="reg-bio">Experience &amp; Client Deliverables *</label>
                            <textarea
                                id="reg-bio"
                                name="bio"
                                placeholder="Describe your craft, notable Ugandan projects or events, cities you travel to, and what clients receive..."
                                data-provider-required
                                enterkeyhint="done"
                                required
                            ><?= e(old('bio')) ?></textarea>
                        </div>
                    </fieldset>

                    <button type="submit" class="btn btn-primary btn-lg btn-block">
                        Create my account
                    </button>

                    <p class="hint form-privacy" style="text-align:center;">By creating an account you agree to the <a href="<?= e(route_url('/terms')) ?>">Terms of Use</a> and the <a href="<?= e(route_url('/privacy')) ?>">Privacy Policy</a>.</p>

                    <p style="text-align:center; margin:0; font-size:0.875rem; color:var(--graphite-700);">
                        Already part of the network? <a href="<?= e(route_url('/login')) ?>"><strong>Log in</strong></a>
                    </p>
                </form>
            </div>

            <aside class="auth-aside" aria-label="Why list on Kopii">
                <h2 style="margin:0 0 0.85rem; color:#fff; font-size:1.45rem; font-weight:400; line-height:1.25;">
                    What you get as a listed provider
                </h2>
                <p style="margin:0 0 1.65rem; font-size:0.9rem; color:#d5defa; line-height:1.6;">
                    Your work, prices, and contact details on one page that clients can compare and share.
                </p>

                <ul class="deliverables-list" style="color:#fff; gap:1rem; margin:0 0 1.85rem;">
                    <li>
                        <?= svg_icon('check', 16) ?>
                        <div>
                            <strong class="label-strong on-dark">Free to list at launch</strong>
                            <span class="on-dark-sm">Clients hire and pay you directly. If we ever add a fee, we will tell you in advance.</span>
                        </div>
                    </li>
                    <li>
                        <?= svg_icon('check', 16) ?>
                        <div>
                            <strong class="label-strong on-dark">Structured client briefs</strong>
                            <span class="on-dark-sm">Receive structured inquiries with event dates, Ugandan cities, and target UGX budgets.</span>
                        </div>
                    </li>
                    <li>
                        <?= svg_icon('check', 16) ?>
                        <div>
                            <strong class="label-strong on-dark">Direct WhatsApp &amp; Quote Inbox</strong>
                            <span class="on-dark-sm">Respond instantly from your phone or manage leads inside your Studio Dashboard.</span>
                        </div>
                    </li>
                    <li>
                        <?= svg_icon('check', 16) ?>
                        <div>
                            <strong class="label-strong on-dark">Earn the Verified badge</strong>
                            <span class="on-dark-sm">Our team adds the green Verified badge to providers with a working Ugandan contact, original portfolio work, and clear UGX pricing.</span>
                        </div>
                    </li>
                </ul>

                <div style="padding-block-start:1.35rem; border-block-start:1px solid rgba(255,255,255,0.16);">
                    <p style="margin:0; font-size:0.86rem; font-weight:300; color:#ebeced; line-height:1.6;">
                        Listing is free while we launch. Clients reach you directly by quote request, WhatsApp, or phone.
                    </p>
                </div>
            </aside>
        </div>
    </div>
</section>
