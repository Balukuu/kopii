<?php
defined('KOPII_APP') or exit('Direct access denied.');
/**
 * @var array  $topics       key => label
 * @var string $supportEmail the support address from env.php, or ''
 * @var string $chosenTopic  topic preselected by ?topic=
 */
$authUser = current_user();
$topicNow = old('topic', $chosenTopic);
?>
<nav class="breadcrumb-bar" aria-label="Breadcrumb">
    <div class="container">
        <ol class="breadcrumb-list">
            <li><a href="<?= e(route_url('/')) ?>">Kopii</a></li>
            <li aria-hidden="true">/</li>
            <li aria-current="page">Contact</li>
        </ol>
    </div>
</nav>

<section class="section-pad">
    <div class="container">
        <div class="section-head">
            <div>
                <h1 style="margin:0; font-size:var(--text-2xl);">Contact Kopii</h1>
                <p class="text-muted">A question, a problem with your listing, or something that does not look right? Write to us and a real person will reply.</p>
            </div>
        </div>

        <div class="profile-layout" style="margin-block-start:0;">
            <div class="content-panel">
                <h2>Send us a message</h2>
                <form method="POST" action="<?= e(route_url('/contact')) ?>" class="stack-form">
                    <?= csrf_field() ?>
                    <div class="visually-hidden" aria-hidden="true">
                        <label for="contact_url_hp">Leave empty</label>
                        <input type="text" id="contact_url_hp" name="contact_url_hp" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="form-row-2">
                        <div class="field">
                            <label for="ct-name">Your name *</label>
                            <input type="text" id="ct-name" name="name" value="<?= e(old('name', $authUser['full_name'] ?? '')) ?>" maxlength="100" autocomplete="name" required>
                        </div>
                        <div class="field">
                            <label for="ct-email">Your email *</label>
                            <input type="email" id="ct-email" name="email" value="<?= e(old('email', $authUser['email'] ?? '')) ?>" maxlength="190" autocomplete="email" autocapitalize="none" spellcheck="false" required>
                            <span class="hint">We reply here.</span>
                        </div>
                    </div>

                    <div class="field">
                        <label for="ct-topic">What is it about? *</label>
                        <select id="ct-topic" name="topic" required>
                            <option value="">Choose a topic</option>
                            <?php foreach ($topics as $key => $label): ?>
                                <option value="<?= e($key) ?>"<?= $topicNow === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="ct-message">Your message *</label>
                        <textarea id="ct-message" name="message" rows="7" minlength="10" maxlength="3000" required placeholder="Tell us what happened or what you need. If it is about a listing, include its name or link."><?= e(old('message')) ?></textarea>
                    </div>

                    <p class="hint m-0">We use what you send only to answer you. See the <a href="<?= e(route_url('/privacy')) ?>">Privacy Policy</a>.</p>

                    <div>
                        <button type="submit" class="btn btn-primary btn-lg"><?= svg_icon('mail', 20) ?> Send message</button>
                    </div>
                </form>
            </div>

            <aside class="content-panel">
                <h2>Other ways to reach us</h2>
                <?php if ($supportEmail !== ''): ?>
                    <p class="m-0"><strong>Email</strong><br><a href="mailto:<?= e($supportEmail) ?>"><?= e($supportEmail) ?></a></p>
                <?php endif; ?>
                <p class="text-muted"<?= $supportEmail !== '' ? '' : ' style="margin-block-start:0;"' ?>>We usually reply within two working days.</p>

                <h3 style="font-size:1rem; margin-block:1.25rem 0.4rem;">Seen something wrong?</h3>
                <p class="text-muted m-0">Every provider page, product and opportunity has a <strong>Report</strong> link at the bottom of its sidebar. Use that one: it tells us exactly which listing you mean.</p>

                <h3 style="font-size:1rem; margin-block:1.25rem 0.4rem;">Staying safe</h3>
                <p class="text-muted m-0">Hiring or buying? Read the <a href="<?= e(route_url('/safety')) ?>">safety tips</a> before you pay anyone.</p>

                <h3 style="font-size:1rem; margin-block:1.25rem 0.4rem;">Your data</h3>
                <p class="text-muted m-0">You can change or delete your account yourself in <a href="<?= e(route_url('/account')) ?>">Account</a>. For a copy of your information, choose "My personal data" above.</p>

                <h3 style="font-size:1rem; margin-block:1.25rem 0.4rem;">The small print</h3>
                <p class="m-0"><a href="<?= e(route_url('/terms')) ?>">Terms of Use</a> &middot; <a href="<?= e(route_url('/privacy')) ?>">Privacy Policy</a></p>
            </aside>
        </div>
    </div>
</section>
