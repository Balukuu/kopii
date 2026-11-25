<?php
defined('KOPII_APP') or exit('Direct access denied.');
/**
 * @var array  $messages rows from SupportModel::search(), each with 'target' (or null)
 * @var string $status   open | handled | all
 * @var string $kind     '' | contact | report
 * @var string $q
 * @var array  $counts   open, handled, all
 * @var int    $page
 * @var int    $pages
 * @var int    $total
 */
View::partial('admin_header', ['adminTab' => $adminTab]);

$tabs = ['open' => 'Open', 'handled' => 'Dealt with', 'all' => 'All'];
$query = array_filter(['status' => $status, 'kind' => $kind, 'q' => $q], static fn($v) => $v !== '');
?>
<section class="section-pad-sm">
    <div class="container">
        <p class="text-muted">What people wrote on the contact page, and listings they reported. Each one is also emailed to the team. Mark it as dealt with when you have answered or acted, so the badge on the menu clears.</p>

        <nav class="filter-tabs" aria-label="Inbox status">
            <?php foreach ($tabs as $key => $label): ?>
                <a class="filter-tab" href="<?= e(route_url('/admin/inbox', array_filter(['status' => $key, 'kind' => $kind, 'q' => $q], static fn($v) => $v !== ''))) ?>"<?= $status === $key ? ' aria-current="page"' : '' ?>>
                    <?= e($label) ?> (<?= (int) $counts[$key] ?>)
                </a>
            <?php endforeach; ?>
        </nav>

        <form class="admin-filters" method="GET" action="<?= e(route_url('/admin/inbox')) ?>" role="search">
            <input type="hidden" name="status" value="<?= e($status) ?>">
            <div class="field field-grow">
                <label for="ib-q">Search</label>
                <input id="ib-q" type="search" name="q" value="<?= e($q) ?>" placeholder="Name, email, words in the message or the listing" maxlength="80">
            </div>
            <div class="field">
                <label for="ib-kind">Type</label>
                <select id="ib-kind" name="kind">
                    <option value="">Any</option>
                    <option value="report"<?= $kind === 'report' ? ' selected' : '' ?>>Reports</option>
                    <option value="contact"<?= $kind === 'contact' ? ' selected' : '' ?>>Contact messages</option>
                </select>
            </div>
            <div class="cell-actions">
                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                <?php if ($kind !== '' || $q !== ''): ?>
                    <a class="btn btn-outline btn-sm" href="<?= e(route_url('/admin/inbox', ['status' => $status])) ?>">Clear</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if (empty($messages)): ?>
            <p class="empty-state"><?= $status === 'open' ? 'Nothing is waiting. Messages and reports will appear here.' : 'No messages match.' ?></p>
        <?php else: ?>
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">When</th>
                            <th scope="col">From</th>
                            <th scope="col">What</th>
                            <th scope="col">Manage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($messages as $m): ?>
                            <?php
                            $mid      = (int) $m['id'];
                            $isReport = (string) $m['kind'] === 'report';
                            $isOpen   = (string) $m['status'] === 'open';
                            $target   = $m['target'];
                            ?>
                            <tr>
                                <td>
                                    <?= e(time_ago((string) $m['created_at'])) ?>
                                    <span class="cell-sub"><?= e(date('j M Y, H:i', (int) strtotime((string) $m['created_at']))) ?></span>
                                </td>
                                <td>
                                    <strong><?= (string) $m['name'] !== '' ? e($m['name']) : 'Anonymous' ?></strong>
                                    <?php if ((string) $m['email'] !== ''): ?>
                                        <span class="cell-sub"><a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a></span>
                                    <?php endif; ?>
                                    <?php if (!empty($m['user_id'])): ?><span class="cell-sub">Signed in</span><?php endif; ?>
                                </td>
                                <td class="review-text">
                                    <span class="badge <?= $isReport ? 'badge-warn' : 'badge-type' ?>"><?= $isReport ? 'Report' : 'Message' ?></span>
                                    <strong><?= e($m['subject']) ?></strong>
                                    <?php if ($isReport): ?>
                                        <span class="cell-sub">
                                            About <?= e($m['target_type']) ?>: <?= e($m['target_label']) ?>
                                            <?php if ($target !== null): ?>
                                                &middot; <a href="<?= e(route_url($target['public_path'])) ?>">View page</a>
                                                &middot; <a href="<?= e(route_url($target['admin_path'])) ?>">Open in admin</a>
                                            <?php else: ?>
                                                &middot; no longer exists
                                            <?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                    <p style="margin:0.4rem 0 0;"><?= (string) $m['message'] !== '' ? e($m['message']) : '(no details given)' ?></p>
                                    <?php if (!$isOpen): ?>
                                        <span class="cell-sub">
                                            Dealt with<?= !empty($m['handled_by_name']) ? ' by ' . e($m['handled_by_name']) : '' ?><?= !empty($m['handled_at']) ? ', ' . e(time_ago((string) $m['handled_at'])) : '' ?><?= (string) $m['note'] !== '' ? ': &ldquo;' . e($m['note']) . '&rdquo;' : '' ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isOpen): ?>
                                        <details class="row-menu">
                                            <summary class="btn btn-outline btn-sm">Deal with it</summary>
                                            <div class="row-menu-panel">
                                                <form method="POST" action="<?= e(route_url('/admin/inbox/action')) ?>" class="form-stack">
                                                    <?= csrf_field() ?>
                                                    <?= admin_return_field() ?>
                                                    <input type="hidden" name="message_id" value="<?= $mid ?>">
                                                    <input type="hidden" name="inbox_action" value="handle">
                                                    <div class="field">
                                                        <label for="note-<?= $mid ?>">Note for the record (optional)</label>
                                                        <textarea id="note-<?= $mid ?>" name="note" rows="2" maxlength="500" placeholder="e.g. Replied by email / hid the listing"></textarea>
                                                    </div>
                                                    <button type="submit" class="btn btn-primary btn-sm">Mark as dealt with</button>
                                                </form>
                                            </div>
                                        </details>
                                    <?php else: ?>
                                        <form method="POST" action="<?= e(route_url('/admin/inbox/action')) ?>">
                                            <?= csrf_field() ?>
                                            <?= admin_return_field() ?>
                                            <input type="hidden" name="message_id" value="<?= $mid ?>">
                                            <input type="hidden" name="inbox_action" value="reopen">
                                            <button type="submit" class="btn btn-outline btn-sm">Reopen</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php View::partial('admin_pager', ['page' => $page, 'pages' => $pages, 'total' => $total, 'path' => '/admin/inbox', 'query' => $query]); ?>
    </div>
</section>
