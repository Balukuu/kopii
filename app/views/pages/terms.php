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
            <li aria-current="page">Terms of Use</li>
        </ol>
    </div>
</nav>

<section class="section-pad">
    <div class="container">
        <article class="legal-doc">
            <h1>Terms of Use</h1>
            <p class="legal-updated">Last updated <?= e($updated) ?></p>

            <p>These terms are the rules for using Kopii (kopii.online). By creating an account, posting, or sending a request through Kopii you agree to them. If you do not agree, please do not use the site. They are written in plain language on purpose.</p>

            <h2>1. What Kopii is</h2>
            <p>Kopii is an online marketplace where people and businesses in Uganda list their services and products, post opportunities, and where customers find them and get in touch. Kopii is a place to meet. <strong>We are not a party to any deal</strong> between a customer and a provider, seller or poster, and we do not employ, represent or guarantee any of them.</p>

            <h2>2. Money and payments</h2>
            <p>Kopii does not currently take payments, hold money or charge commission. In future we may offer paid plans or featured placement to businesses, and charge a commission on bookings or orders that are paid through Kopii. If we do, we will say so on this page and by email before any fee applies to you. Prices shown are set by providers and sellers. You agree price, delivery and payment directly with the other person. Take the usual care: confirm who you are dealing with, put the agreement in writing (a WhatsApp message counts), and avoid paying the full amount for something you have not seen or received.</p>

            <h2>3. Your account</h2>
            <ul>
                <li>You must be at least 18 years old to create an account.</li>
                <li>Give true details and keep them up to date. Do not use someone else's name, business, photos or email address.</li>
                <li>Keep your password private. You are responsible for what happens under your account. Tell us at once if you think someone else has got into it.</li>
                <li>You may delete your account at any time from <em>Account</em> in the menu.</li>
            </ul>

            <h2>4. Listings, products and opportunities</h2>
            <p>If you publish a listing, a shop product, an opportunity or a proposal, you promise that:</p>
            <ul>
                <li>it is honest: the work, photos, prices, stock and contact details are really yours and really as described;</li>
                <li>you have the right to use every photo and text you upload, and you are not copying another provider's work;</li>
                <li>you are allowed to offer it. It must not be illegal, unsafe, counterfeit or stolen, and must not be a scheme that asks people to pay in advance for jobs, visas, loans or prizes;</li>
                <li>you will answer people who contact you politely, and keep their details to deal with their request only.</li>
            </ul>

            <h2>5. The Verified badge</h2>
            <p>Anyone can list on Kopii. Our team adds the Verified badge by hand after checking that the phone number works, that the work looks like the provider's own, and that the pricing is clear. <strong>Verified is not a guarantee of quality, honesty or availability</strong>, and it is not an endorsement. We can remove it at any time.</p>

            <h2>6. Reviews</h2>
            <p>Reviews must be truthful and based on your own experience. Do not review yourself, your family, your competitor, or in return for a reward. Reviews from people who sent a request through Kopii are published straight away; others are checked by our team first. We may remove a review that breaks these terms, but we do not remove a review just because it is critical.</p>

            <h2>7. What you must not do</h2>
            <ul>
                <li>Post anything unlawful, hateful, threatening, sexually explicit, or that exploits or endangers children.</li>
                <li>Harass, mislead or scam other people, or ask for payment through a false identity.</li>
                <li>Send spam, bulk messages or the same request to many providers to harvest replies.</li>
                <li>Collect other people's details from Kopii for any purpose other than dealing with their request.</li>
                <li>Try to break, overload or get around the site's security, or use automated tools to copy it.</li>
            </ul>

            <h2>8. Reporting and removing content</h2>
            <p>Every listing, product and opportunity has a <em>Report</em> button. We read reports and may hide content, pause a listing, or suspend an account, with or without notice, when something breaks these terms or puts people at risk. Where we can, we tell the owner why. If you think we made a mistake, write to us through <?= $contactLine ?> and we will look again.</p>

            <h2>9. Your content</h2>
            <p>You keep ownership of what you upload. You give Kopii a free, non-exclusive licence to show it on the site and in link previews (for example when someone shares your page on WhatsApp), for as long as it is on Kopii. When you delete it, we stop showing it.</p>

            <h2>10. Our responsibility</h2>
            <p>We work to keep Kopii running and accurate, but we provide it "as is". We do not promise it will always be available or error-free. To the extent the law allows, Kopii is not responsible for the quality, safety or legality of what providers and sellers offer, for what people say, or for losses from a deal made through the site. Nothing in these terms limits any right you have under Ugandan law that cannot be limited.</p>

            <h2>11. Changes</h2>
            <p>We may change these terms as Kopii grows (for example when payments are added). The date at the top shows the latest version. If you keep using Kopii after a change, you accept the new terms. For big changes we will tell signed-in users.</p>

            <h2>12. Law and contact</h2>
            <p>These terms are governed by the laws of Uganda, and the courts of Uganda can hear any dispute about them. Questions about these terms: <?= $contactLine ?>. How we handle personal information is in the <a href="<?= e(route_url('/privacy')) ?>">Privacy Policy</a>.</p>
        </article>
    </div>
</section>
