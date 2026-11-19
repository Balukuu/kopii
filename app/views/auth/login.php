<?php
defined('KOPII_APP') or exit('Direct access denied.');
?>
<section class="section-pad auth-body">
    <div class="container">
        <div class="auth-card auth-card-narrow">
            <div class="auth-head">
                <h1 class="auth-title">Log in</h1>
                <p class="auth-lede">
                    Manage your portfolio, packages, and quote requests.
                </p>
            </div>

            <form method="POST" action="<?= e(route_url('/login')) ?>" class="stack-form">
                <?= csrf_field() ?>

                <div class="field">
                    <label for="login-email">Email *</label>
                    <input
                        type="email"
                        id="login-email"
                        name="email"
                        value="<?= e(old('email')) ?>"
                        placeholder="you@studio.ug"
                        autocomplete="username"
                        autocorrect="off"
                        autocapitalize="none"
                        spellcheck="false"
                        enterkeyhint="next"
                        data-error="login-email-err"
                        required
                        autofocus
                    >
                    <div id="login-email-err" class="error-msg">Please enter your registered email address.</div>
                </div>

                <div class="field">
                    <label for="login-password">Password *</label>
                    <div class="password-wrap">
                        <input
                            type="password"
                            id="login-password"
                            name="current_password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            enterkeyhint="done"
                            data-error="login-pass-err"
                            required
                        >
                        <button
                            type="button"
                            class="password-toggle"
                            data-toggle-password="login-password"
                            aria-pressed="false"
                            aria-label="Show password"
                        >
                            <?= svg_icon('eye', 20, 'icon-eye') ?>
                            <?= svg_icon('eye-off', 20, 'icon-eye-off') ?>
                        </button>
                    </div>
                    <div id="login-pass-err" class="error-msg">Please enter your password.</div>
                    <div style="margin-block-start:0.4rem; text-align:end; font-size:0.85rem;">
                        <a href="<?= e(route_url('/forgot-password')) ?>">Forgot your password?</a>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-lg btn-block">Log in</button>

                <div style="display:flex; flex-wrap:wrap; justify-content:space-between; gap:0.5rem; padding-block-start:0.35rem; font-size:0.85rem; color:var(--graphite-700);">
                    <span>New specialist? <a href="<?= e(route_url('/register')) ?>"><strong>Apply as a Freelancer</strong></a></span>
                    <a href="<?= e(route_url('/hire')) ?>">Get matched <?= svg_icon('arrow-right', 16) ?></a>
                </div>
            </form>

            <?php if (app_is_local()): ?>
            <!-- Local development only: these accounts exist only in the throw-away SQLite demo database. -->
            <div style="margin-block-start:1.65rem; padding-block-start:1.15rem; border-block-start:1px solid var(--gray-300);">
                <p style="margin:0 0 0.65rem; font-size:0.72rem; font-weight:600; letter-spacing:0.06em; color:var(--gray-600); text-transform:uppercase;">
                    One-Click Demo Access
                </p>
                <div style="display:flex; flex-wrap:wrap; gap:0.5rem;">
                    <button
                        type="button"
                        class="btn btn-outline btn-sm"
                        data-demo-email="pearl@kopii.online"
                        data-demo-pass="KopiiDemo2026!"
                    >
                        Photographer Studio
                    </button>
                    <button
                        type="button"
                        class="btn btn-outline btn-sm"
                        data-demo-email="client@kopii.online"
                        data-demo-pass="KopiiDemo2026!"
                    >
                        Client
                    </button>
                    <button
                        type="button"
                        class="btn btn-outline btn-sm"
                        data-demo-email="admin@kopii.online"
                        data-demo-pass="KopiiAdmin2026!"
                    >
                        Platform Admin
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>
