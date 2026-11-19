<?php
defined('KOPII_APP') or exit('Direct access denied.');
/**
 * @var array $o     a row from OpportunityModel::search()
 * @var array $kinds key => label
 */
$days    = OpportunityModel::daysLeft($o);
$closing = $days === 0 ? 'Closes today' : ($days === 1 ? 'Closes tomorrow' : 'Closes in ' . $days . ' days');
$count   = (int) ($o['response_count'] ?? 0);
?>
<article class="opp-card">
    <div class="card-badges">
        <span class="badge badge-featured"><?= e($kinds[$o['kind']] ?? ucfirst((string) $o['kind'])) ?></span>
        <?php if (!empty($o['category_name'])): ?>
            <span class="badge badge-type"><?= e($o['category_name']) ?></span>
        <?php endif; ?>
        <?php if ($days <= 3): ?>
            <span class="badge badge-warn"><?= e($closing) ?></span>
        <?php endif; ?>
    </div>
    <h3><a class="card-link" href="<?= e(route_url('/opportunities/' . (int) $o['id'])) ?>"><?= e($o['title']) ?></a></h3>
    <p><?= e(text_excerpt((string) $o['description'], 200)) ?></p>
    <div class="opp-meta">
        <span class="opp-budget tnum"><?= e(budget_range($o['budget_min_ugx'], $o['budget_max_ugx'])) ?></span>
        <span><?= svg_icon('map-pin', 16) ?> <?= e($o['city']) ?></span>
        <?php if ($days > 3): ?>
            <span><?= svg_icon('clock', 16) ?> <?= e($closing) ?></span>
        <?php endif; ?>
        <span><?= $count === 0 ? 'No proposals yet' : $count . ($count === 1 ? ' proposal' : ' proposals') ?></span>
        <span>Posted <?= e(time_ago((string) $o['created_at'])) ?></span>
    </div>
</article>
