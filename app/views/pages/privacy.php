<?php
defined('KOPII_APP') or exit('Direct access denied.');
/**
 * @var string $updated      date the text was last changed
 * @var string $supportEmail the support address from env.php, or ''
 */
$contactLine = $supportEmail !== ''
    ? '<a href="mailto:' . e($supportEmail) . '">' . e($supportEmail) . '</a> or the <a href="' . e(route_url('/contact')) . '">contact page</a>'
    : 'the <a href="' . e(route_url('/contact')) . '">contact page</a>';
?>
<nav class="breadcrumb-bar" aria-label="Breadcrumb">
    <div class="container">
        <ol class="breadcrumb-list">
            <li><a href="<?= e(route_url('/')) ?>">Kopii</a></li>
            <li aria-hidden="true">/</li>
            <li aria-current="page">Privacy Policy</li>
        </ol>
    </div>
</nav>

<section class="section-pad">
    <div class="container">
        <article class="legal-doc">
            <h1>Privacy Policy</h1>
            <p class="legal-updated">Last updated <?= e($updated) ?></p>

            <p>This page explains what personal information Kopii (kopii.online) collects, why, who can see it, how long we keep it, and what you can do about it. We have tried to say it plainly, and to describe only what the site really does.</p>

            <h2>1. What we collect</h2>
            <h3>When you create an account</h3>
            <p>Your name, email address and (optionally) phone number, and a password. We never store your password itself, only a scrambled version that cannot be turned back into it.</p>

            <h3>When you publish a listing, product or opportunity</h3>
            <p>The business or brand name, description, city, phone and WhatsApp numbers, public email, website, Instagram handle, prices, and the photos you upload. <strong>This is shown publicly on your listing</strong>, because that is the point of it. An opportunity shows its title, description, budget and city, but not the poster's phone or email.</p>

            <h3>When you ask for a quote, send a hire brief or message a seller</h3>
            <p>Your name, phone number and the message, plus an email address if you choose to give one, and any date, place or budget you add. This goes to the provider or seller you chose (a hire brief goes to the providers you pick, up to five) and is kept in their Kopii inbox. They can see it and may contact you by phone, WhatsApp or email.</p>

            <h3>When you write a review or a proposal</h3>
            <p>Your name, city, rating and comment. Published reviews are public. Proposals are seen by the person who posted the opportunity.</p>

            <h3>When you write to us or report a listing</h3>
            <p>Your name, email, and what you wrote. Our team reads it to help you or to act on the report. A report does not tell the owner who sent it.</p>

            <h3>Automatically</h3>
            <p>To block abuse we count how often a connection signs in, registers or sends forms; for that we keep a scrambled form of the IP address and email for about a day. When someone asks to reset a password, the IP address of the request is stored with it. Our hosting company also keeps ordinary server logs.</p>

            <h2>2. Photos</h2>
            <p>When you upload a photo we resize it and remove hidden camera data from it, including GPS location, before we show it.</p>

            <h2>3. Cookies and similar storage</h2>
            <ul>
                <li><strong>One session cookie</strong> (<code>kopii_session</code>) keeps you signed in and protects forms from being sent by other websites. It is not used for tracking and goes when you close your browser.</li>
                <li>Your <strong>comparison shortlist</strong> is saved in your own browser's storage. It is not sent to us.</li>
                <li>We use <strong>no advertising or analytics cookies</strong>.</li>
            </ul>

            <h2>4. Other companies involved</h2>
            <ul>
                <li><strong>Fonts.</strong> The page font (Plus Jakarta Sans) is loaded from Google Fonts, so Google receives your IP address and browser details when a page loads.</li>
                <li><strong>WhatsApp.</strong> If you tap "Chat on WhatsApp" you leave Kopii and WhatsApp's own terms and privacy policy apply.</li>
                <li><strong>Email.</strong> Emails (confirmation links, password resets, new-request alerts) are sent through our web host's mail system.</li>
                <li><strong>Hosting.</strong> The site and its database run on a web hosting company's servers.</li>
            </ul>
            <p>We do not sell your personal information and we do not send marketing emails.</p>

            <h2>5. Why we use it</h2>
            <p>To run the marketplace: to show listings, deliver your requests, let people sign in, send the emails above, keep the site safe, review reports and reviews, and answer you. We do not use your information for anything unrelated.</p>

            <h2>6. How long we keep it</h2>
            <ul>
                <li>Your account and everything you published stay until you delete your account.</li>
                <li>When you delete your account we delete your profile, listing, photos, products, opportunities, proposals and the reviews you wrote.</li>
                <li>Quote requests and messages you sent to providers stay in <em>their</em> inboxes (they are part of the provider's own records), but are no longer linked to your account.</li>
                <li>Messages to us and reports are kept for as long as needed to deal with them and to show what we did.</li>
            </ul>

            <h2>7. Your choices and rights</h2>
            <p>Uganda's Data Protection and Privacy Act, 2019 gives you rights over your personal data, including to see it, correct it and have it deleted. You can:</p>
            <ul>
                <li>change your name, phone, email and password, and edit or remove your listing, in <a href="<?= e(route_url('/account')) ?>">Account</a> and your dashboard;</li>
                <li>delete your account and everything on it, in <a href="<?= e(route_url('/account')) ?>">Account</a>;</li>
                <li>ask for a copy of your information, object to how it is used, or ask us anything else, through <?= $contactLine ?>.</li>
            </ul>
            <p>If you are not happy with our answer you may complain to Uganda's Personal Data Protection Office.</p>

            <h2>8. Keeping it safe</h2>
            <p>The site is served over HTTPS, passwords are stored scrambled, sign-in attempts are limited, and our team only sees what it needs to run the marketplace. No website is perfectly secure, so please choose a strong password you do not use elsewhere.</p>

            <h2>9. Children</h2>
            <p>Kopii is not meant for anyone under 18, and we do not knowingly collect information from children.</p>

            <h2>10. Changes</h2>
            <p>If we change what we collect or how we use it, we will update this page and the date at the top, and tell signed-in users about important changes. Questions: <?= $contactLine ?>. The rules for using the site are in the <a href="<?= e(route_url('/terms')) ?>">Terms of Use</a>.</p>
        </article>
    </div>
</section>
