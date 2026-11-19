<?php
defined('KOPII_APP') or exit('Direct access denied.');
/**
 * @var array $opportunities rows from OpportunityModel::adminSearch()
 * @var array $kinds         key => label
 * @var array $filters       q, status, kind
 * @var int   $page
 * @var int   $pages
 * @var int   $total
 */
View::partial('admin_header', ['adminTab' => $adminTab]);
$activeFilters = array_filter($filters, static fn($v) => $v !== '');
?>
<section class="section-pad-sm">
    <div class="container">
        <form class="admin-filters" method="GET" action="<?= e(route_url('/admin/opportunities')) ?>" role="search">
            <div class="field field-grow">
                <label for="f-q">Search</label>
                <input id="f-q" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Title, poster name or email" maxlength="80">
            </div>
            <div class="field">
                <label for="f-status">Status</label>
                <select id="f-status" name="status">
                    <option value="">Any</option>
                    <?php foreach (['open' => 'Open', 'closed' => 'Closed', 'filled' => 'Filled', 'suspended' => 'Hidden by Kopii'] as $key => $label): ?>
                        <option value="<?= e($key) ?>"<?= $filters['status'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="f-kind">Kind</label>
                <select id="f-kind" name="kind">
                    <option value="">Any</option>
                    <?php foreach ($kinds as $key => $label): ?>
                        <option value="<?= e($key) ?>"<?= $filters['kind'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="cell-actions">
                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                <?php if (!empty($activeFilters)): ?>
                    <a class="btn btn-outline btn-sm" href="<?= e(route_url('/admin/opportunities')) ?>">Clear</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if (empty($opportunities)): ?>
            <p class="empty-state">No opportunities match.<?= !empty($activeFilters) ? ' Try clearing a filter.' : '' ?></p>
        <?php else: ?>
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">Opportunity</th>
                            <th scope="col">Posted by</th>
                            <th scope="col">Budget &amp; place</th>
                            <th scope="col">Status</th>
                            <th scope="col">Activity</th>
                            <th scope="col"><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($opportunities as $o): ?>
                            <?php
                            $oid = (int) $o['id'];
                            $isOpen = OpportunityModel::isOpen($o);
                            ?>
                            <tr>
                                <td>
                                    <a href="<?= e(route_url('/opportunities/' . $oid)) ?>"><strong><?= e($o['title']) ?></strong></a>
                                    <span class="cell-sub"><?= e($kinds[$o['kind']] ?? $o['kind']) ?><?= !empty($o['category_name']) ? ' &middot; ' . e($o['category_name']) : '' ?></span>
                                </td>
                                <td>
                                    <?= e($o['poster_name']) ?>
                                    <span class="cell-sub"><?= e($o['poster_email']) ?><?= !empty($o['poster_suspended_at']) ? ' (account suspended)' : '' ?></span>
                                </td>
                                <td>
                                    <span class="tnum"><?= e(budget_range($o['budget_min_ugx'], $o['budget_max_ugx'])) ?></span>
                                    <span class="cell-sub"><?= e($o['city']) ?> &middot; closes <?= e(date('j M Y', (int) strtotime((string) $o['closes_on']))) ?></span>
                                </td>
                                <td>
                                    <?php if ($o['status'] === 'suspended'): ?>
                                        <span class="badge badge-out">Hidden</span>
                                    <?php elseif ($o['status'] === 'open' && $isOpen): ?>
                                        <span class="badge badge-avail">Open</span>
                                    <?php elseif ($o['status'] === 'open'): ?>
                                        <span class="badge badge-type">Expired</span>
                                    <?php else: ?>
                                        <span class="badge badge-type"><?= e(ucfirst((string) $o['status'])) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="tnum"><?= (int) $o['response_count'] ?></span> proposals &middot; <span class="tnum"><?= (int) $o['views_count'] ?></span> views</td>
                                <td>
                                    <form method="POST" action="<?= e(route_url('/admin/opportunities/moderate')) ?>" class="cell-actions">
                                        <?= csrf_field() ?>
                                        <?= admin_return_field() ?>
                                        <input type="hidden" name="opportunity_id" value="<?= $oid ?>">
                                        <?php if ($o['status'] === 'suspended'): ?>
                                            <input type="hidden" name="mod_action" value="reinstate">
                                            <button type="submit" class="btn btn-outline btn-sm">Reinstate</button>
                                        <?php else: ?>
                                            <input type="hidden" name="mod_action" value="suspend">
                                            <label class="visually-hidden" for="note-<?= $oid ?>">Reason (emailed to the poster)</label>
                                            <input id="note-<?= $oid ?>" type="text" name="note" placeholder="Reason (emailed to poster)" maxlength="500" required style="min-inline-size:11rem;">
                                            <button type="submit" class="btn btn-danger-outline btn-sm">Hide</button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php View::partial('admin_pager', ['page' => $page, 'pages' => $pages, 'total' => $total, 'path' => '/admin/opportunities', 'query' => $activeFilters]); ?>
    </div>
</section>
