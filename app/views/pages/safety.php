<?php
defined('KOPII_APP') or exit('Direct access denied.');
/**
 * @var string $supportEmail the support address from env.php, or ''
 */
?>
<nav class="breadcrumb-bar" aria-label="Breadcrumb">
    <div class="container">
        <ol class="breadcrumb-list">
            <li><a href="<?= e(route_url('/')) ?>">Kopii</a></li>
            <li aria-hidden="true">/</li>
            <li aria-current="page">Safety tips</li>
        </ol>
    </div>
</nav>

<section class="section-pad">
    <div class="container">
        <article class="legal-doc">
            <h1>Stay safe on Kopii</h1>
            <p class="legal-updated">Simple habits that protect your money and your time</p>

            <p>Kopii introduces you to a provider or seller. After that, <strong>you deal with each other directly</strong>, on WhatsApp or by phone, and you agree the price and how to pay. Kopii does not take payments, hold money or guarantee anyone, so a few habits matter. Most problems can be avoided by following them.</p>

            <div class="trust-box" style="margin-block-end:var(--space-6);">
                <strong><?= svg_icon('shield', 16) ?> The three rules</strong>
                <ul>
                    <li><?= svg_icon('check', 16) ?> <span>Look at real past work before you agree.</span></li>
                    <li><?= svg_icon('check', 16) ?> <span>Agree the scope and price in writing. A WhatsApp message counts.</span></li>
                    <li><?= svg_icon('check', 16) ?> <span>Never pay everything upfront, and never pay to "unlock" a booking or a job.</span></li>
                </ul>
            </div>

            <h2>If you are hiring someone</h2>
            <h3>1. Check the work</h3>
            <ul>
                <li>Open their Kopii page and look at the portfolio. Then ask for photos or a short video of <em>past work like yours</em>, sent to you on WhatsApp.</li>
                <li>Be careful with polished pictures that look like they come from a magazine or a stock website. Ask them to tell you about the job: where it was, who it was for.</li>
                <li>Ask for the phone number of a past customer you can call, and call them.</li>
            </ul>

            <h3>2. Agree it in writing</h3>
            <ul>
                <li>Write down what is included, the total price in UGX, the date, and what happens if something is late or goes wrong. Send it as a WhatsApp message and ask them to reply "agreed".</li>
                <li>Change the plan in writing too, so there are no surprises about the price at the end.</li>
            </ul>

            <h3>3. Pay in steps</h3>
            <ul>
                <li>A small deposit for materials or to book the date is normal. Pay the balance when the work is done and you have checked it.</li>
                <li>Do not pay the full amount before the work starts, and do not pay "registration", "transport" or "insurance" fees that were not in the agreement.</li>
                <li>With Mobile Money, check the name that shows before you confirm. It should match the person or business you agreed with. Never send to a different number than the one agreed, and keep the confirmation message.</li>
            </ul>

            <h3>4. Warning signs</h3>
            <ul>
                <li>A price far below everyone else's, or "special offer, pay today".</li>
                <li>Pressure to pay quickly, or a refusal to talk on the phone.</li>
                <li>The money must go to a person whose name is not on the listing, with no explanation.</li>
                <li>Portfolio photos they cannot talk about.</li>
            </ul>

            <h2>If you are the provider</h2>
            <ul>
                <li><strong>Get a deposit before you spend your own money</strong> on materials, transport or stock, and agree the scope before you begin.</li>
                <li><strong>Check the payment yourself.</strong> Look at your own Mobile Money balance or the network's own message. Do not trust a screenshot or a forwarded "confirmation" from the customer: fake ones are a common trick.</li>
                <li>Keep your chats and photos of the finished work. They protect you if there is an argument.</li>
                <li>Your phone number is public on your page. <strong>Nobody from Kopii or a Mobile Money company will ever ask for your PIN, password or a code sent to your phone.</strong> Hang up if they do.</li>
            </ul>

            <h2>If you are buying or selling products</h2>
            <ul>
                <li><strong>Buyers:</strong> meet in a busy public place in daytime and bring someone with you. Check the item (switch on a phone or a TV, look at the serial number, test it) before you pay. Be wary of a seller who wants payment first and promises delivery later.</li>
                <li><strong>Sellers:</strong> hand over the item only after the money has reached your own account.</li>
                <li>For delivery by a courier or boda boda, pay the courier for the delivery, and pay the seller when you have the item in your hands.</li>
            </ul>

            <h2>If you are answering a job or tender</h2>
            <ul>
                <li>A real employer or buyer never asks you to pay to apply, to be shortlisted or to "release" a contract.</li>
                <li>Find out who the organisation is before you share documents. Keep your ID numbers private until you are sure.</li>
            </ul>

            <h2>What "Verified" means</h2>
            <p>The Kopii team checked three things by hand: the phone number works, the portfolio looks like their own work, and their pricing is clear. We show the date on their page. It is a useful signal, but <strong>it is not a guarantee of quality or honesty</strong>, so the rules above still apply. A provider with no badge has simply not been checked yet.</p>

            <h2>If something goes wrong</h2>
            <ul>
                <li>Use the <strong>Report</strong> link on the listing. We read every report and can hide a listing or suspend an account. The owner is not told who reported them.</li>
                <li>If you lost money, <strong>report it to the police and to your Mobile Money provider's customer care as soon as you can</strong>, and keep the messages, receipts and photos. Kopii cannot recover money or settle a dispute, but we will act on the listing.</li>
                <li>Need to tell us something else? Use the <a href="<?= e(route_url('/contact')) ?>">contact page</a><?= $supportEmail !== '' ? ' or write to <a href="mailto:' . e($supportEmail) . '">' . e($supportEmail) . '</a>' : '' ?>.</li>
            </ul>

            <p class="hint">See also the <a href="<?= e(route_url('/terms')) ?>">Terms of Use</a> and the <a href="<?= e(route_url('/privacy')) ?>">Privacy Policy</a>.</p>
        </article>
    </div>
</section>
