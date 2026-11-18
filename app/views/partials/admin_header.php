<?php
defined('KOPII_APP') or exit('Direct access denied.');
/** @var string $adminTab overview | providers | reviews | users | activity */

$waiting = (int) admin_attention_counts()['pending_reviews'];
$tabs = [
    'overview'  => ['/admin', 'Overview', 0],
    'providers' => ['/admin/providers', 'Listings', 0],
    'reviews'   => ['/admin/reviews', 'Reviews', $waiting],
    'users'     => ['/admin/users', 'Users', 0],
    'activity'  => ['/admin/activity', 'Activity', 0],
];
?>
<section class="dash-header">
    <div class="container">
        <div class="admin-eyebrow">
            <span class="badge badge-featured"><?= svg_icon('shield', 16) ?> Platform Administration</span>
        </div>
        <h1 class="admin-title">Kopii admin</h1>
        <p class="admin-lede">Verify providers, moderate reviews and look after accounts.</p>

        <nav class="dash-tabs" aria-label="Admin sections">
            <?php foreach ($tabs as $key => [$path, $label, $count]): ?>
                <a class="dash-tab" href="<?= e(route_url($path)) ?>"<?= $adminTab === $key ? ' aria-current="page"' : '' ?>>
                    <?= e($label) ?>
                    <?php if ($count > 0): ?>
                        <span class="tab-count tnum"><?= (int) $count ?><span class="visually-hidden"> waiting</span></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>
</section>
