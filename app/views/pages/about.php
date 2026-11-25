<?php
defined('KOPII_APP') or exit('Direct access denied.');
?>
<nav class="breadcrumb-bar" aria-label="Breadcrumb">
    <div class="container">
        <ol class="breadcrumb-list">
            <li><a href="<?= e(route_url('/')) ?>">Kopii</a></li>
            <li aria-hidden="true">/</li>
            <li aria-current="page">Why Kopii &amp; the Verified Badge</li>
        </ol>
    </div>
</nav>

<section class="hero">
    <div class="container">
        <h1>A free showcase for Ugandan creatives and businesses</h1>
        <p class="hero-lead">
            Kopii is where you find a real, verified provider for the work you need, from home repairs and solar to weddings, design and photography. We introduce you, then step aside: you talk on WhatsApp or by phone and agree the deal yourselves.
        </p>
        <div style="display:flex; flex-wrap:wrap; gap:0.85rem;">
            <a href="<?= e(route_url('/hire')) ?>" class="btn btn-on-dark btn-lg">Get matched</a>
            <a href="<?= e(route_url('/register')) ?>" class="btn btn-transparent btn-lg">Apply as a Freelancer</a>
        </div>
    </div>
</section>

<section class="section-pad">
    <div class="container">
        <div class="section-head-center">
            <h2>Real work, clear prices, direct contact</h2>
            <p>Great specialists in Kampala, Entebbe, Jinja, Mbarara, and Gulu deserve a standardized showcase where quality speaks louder than algorithms.</p>
        </div>

        <div class="value-matrix" style="margin-block-end: 3rem;">
            <div class="value-cell">
                <div class="value-icon"><?= svg_icon('shield', 24) ?></div>
                <h3>What &ldquo;Verified&rdquo; Means</h3>
                <p>Anyone can publish a showcase on Kopii. Our team adds the emerald Verified badge by hand once we have confirmed a working Ugandan phone / WhatsApp contact, original portfolio work, and clear UGX pricing. It is not a guarantee of quality, so always agree scope and price in writing.</p>
            </div>
            <div class="value-cell">
                <div class="value-icon"><?= svg_icon('image', 24) ?></div>
                <h3>Portfolios first</h3>
                <p>Every profile leads with full-size project photos, itemized deliverables, turnaround times, and client reviews.</p>
            </div>
            <div class="value-cell">
                <div class="value-icon"><?= svg_icon('sparkles', 24) ?></div>
                <h3>Close to you, within your budget</h3>
                <p>Search by city and neighbourhood to find someone nearby. Short on time? The Guided Hiring Wizard scores providers against your category, area, timeline, and UGX budget, and sends one brief to the best few.</p>
            </div>
            <div class="value-cell">
                <div class="value-icon"><?= svg_icon('whatsapp', 24) ?></div>
                <h3>No commission</h3>
                <p>Kopii never taxes local creators or inflates client quotes. Dispatch structured briefs into a studio's inbox or connect immediately via WhatsApp and phone.</p>
            </div>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card">
                <span>Active Ugandan Studios &amp; Talent</span>
                <strong class="tnum"><?= (int) ($stats['providers'] ?? 0) ?>+</strong>
            </div>
            <div class="kpi-card">
                <span>Published Portfolio Proofs</span>
                <strong class="tnum"><?= (int) ($stats['portfolios'] ?? 0) ?>+</strong>
            </div>
            <div class="kpi-card">
                <span>Marketplace Commission</span>
                <strong>0%</strong>
            </div>
            <div class="kpi-card">
                <span>Local Currency Standard</span>
                <strong>UGX</strong>
            </div>
        </div>
    </div>
</section>
