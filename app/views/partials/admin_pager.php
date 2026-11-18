<?php
defined('KOPII_APP') or exit('Direct access denied.');
/**
 * @var int    $page  current page (1-based)
 * @var int    $pages total pages
 * @var int    $total total rows
 * @var string $path  route of the list, e.g. /admin/users
 * @var array  $query the active filters (page is added here)
 */
$pageUrl = static fn(int $n): string => route_url($path, $query + ['page' => $n > 1 ? $n : null]);
?>
<nav class="pager" aria-label="Pagination">
    <span class="tnum"><?= number_format($total) ?> <?= $total === 1 ? 'result' : 'results' ?><?= $pages > 1 ? ' · Page ' . (int) $page . ' of ' . (int) $pages : '' ?></span>
    <?php if ($pages > 1): ?>
        <span class="pager-links">
            <?php if ($page > 1): ?>
                <a class="btn btn-outline btn-sm" href="<?= e($pageUrl($page - 1)) ?>" rel="prev">Previous</a>
            <?php endif; ?>
            <?php if ($page < $pages): ?>
                <a class="btn btn-outline btn-sm" href="<?= e($pageUrl($page + 1)) ?>" rel="next">Next</a>
            <?php endif; ?>
        </span>
    <?php endif; ?>
</nav>
