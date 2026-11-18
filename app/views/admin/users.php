<?php
defined('KOPII_APP') or exit('Direct access denied.');
/**
 * @var array $users   rows from AdminModel::userList()
 * @var array $filters q, role, email, status
 * @var int   $myId    the signed-in administrator
 * @var int   $page
 * @var int   $pages
 * @var int   $total
 */
View::partial('admin_header', ['adminTab' => $adminTab]);
$activeFilters = array_filter($filters, static fn($v) => $v !== '');
$actionUrl     = route_url('/admin/users/action');
?>
<section class="section-pad-sm">
    <div class="container">
        <form class="admin-filters" method="GET" action="<?= e(route_url('/admin/users')) ?>" role="search">
            <div class="field field-grow">
                <label for="u-q">Search</label>
                <input id="u-q" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Name, email or business" maxlength="80">
            </div>
            <div class="field">
                <label for="u-role">Role</label>
                <select id="u-role" name="role">
                    <option value="">Any</option>
                    <option value="provider"<?= $filters['role'] === 'provider' ? ' selected' : '' ?>>Provider</option>
                    <option value="client"<?= $filters['role'] === 'client' ? ' selected' : '' ?>>Client</option>
                    <option value="admin"<?= $filters['role'] === 'admin' ? ' selected' : '' ?>>Admin</option>
                </select>
            </div>
            <div class="field">
                <label for="u-email">Email</label>
                <select id="u-email" name="email">
                    <option value="">Any</option>
                    <option value="confirmed"<?= $filters['email'] === 'confirmed' ? ' selected' : '' ?>>Confirmed</option>
                    <option value="unconfirmed"<?= $filters['email'] === 'unconfirmed' ? ' selected' : '' ?>>Not confirmed</option>
                </select>
            </div>
            <div class="field">
                <label for="u-status">Account</label>
                <select id="u-status" name="status">
                    <option value="">Any</option>
                    <option value="active"<?= $filters['status'] === 'active' ? ' selected' : '' ?>>Active</option>
                    <option value="suspended"<?= $filters['status'] === 'suspended' ? ' selected' : '' ?>>Suspended</option>
                </select>
            </div>
            <div class="cell-actions">
                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                <?php if (!empty($activeFilters)): ?>
                    <a class="btn btn-outline btn-sm" href="<?= e(route_url('/admin/users')) ?>">Clear</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if (empty($users)): ?>
            <p class="empty-state">No accounts match.<?= !empty($activeFilters) ? ' Try clearing a filter.' : '' ?></p>
        <?php else: ?>
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">Account</th>
                            <th scope="col">Role</th>
                            <th scope="col">Email</th>
                            <th scope="col">Listing</th>
                            <th scope="col">Joined</th>
                            <th scope="col">Manage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <?php
                            $uid         = (int) $u['id'];
                            $isSuspended = !empty($u['suspended_at']);
                            $isConfirmed = !empty($u['email_verified_at']);
                            $manageable  = $u['role'] !== 'admin' && $uid !== $myId;
                            ?>
                            <tr>
                                <td>
                                    <strong><?= e($u['full_name']) ?></strong>
                                    <span class="cell-sub"><?= e($u['email']) ?></span>
                                    <?php if ($isSuspended): ?><span class="badge badge-type">Suspended</span><?php endif; ?>
                                </td>
                                <td><?= e(ucfirst((string) $u['role'])) ?></td>
                                <td>
                                    <span class="badge <?= $isConfirmed ? 'badge-verified' : 'badge-type' ?>"><?= $isConfirmed ? 'Confirmed' : 'Not confirmed' ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($u['provider_id'])): ?>
                                        <a href="<?= e(route_url('/admin/providers/' . (int) $u['provider_id'])) ?>"><?= e($u['business_name']) ?></a>
                                        <span class="cell-sub"><?= e($u['provider_status'] === 'active' ? 'Live' : ucfirst((string) $u['provider_status'])) ?></span>
                                    <?php else: ?>
                                        <span class="cell-sub">None</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e(time_ago((string) $u['created_at'])) ?></td>
                                <td>
                                    <?php if (!$manageable): ?>
                                        <span class="cell-sub"><?= $uid === $myId ? 'This is you' : 'Administrator' ?></span>
                                    <?php else: ?>
                                        <details class="row-menu">
                                            <summary class="btn btn-outline btn-sm">Manage</summary>
                                            <div class="row-menu-panel">
                                                <?php if (!$isConfirmed): ?>
                                                    <form method="POST" action="<?= e($actionUrl) ?>">
                                                        <?= csrf_field() ?>
                                                        <?= admin_return_field() ?>
                                                        <input type="hidden" name="user_id" value="<?= $uid ?>">
                                                        <input type="hidden" name="user_action" value="resend_verification">
                                                        <button type="submit" class="btn btn-outline btn-sm">Resend confirmation email</button>
                                                    </form>
                                                <?php endif; ?>

                                                <?php if ($isSuspended): ?>
                                                    <form method="POST" action="<?= e($actionUrl) ?>" class="form-stack">
                                                        <?= csrf_field() ?>
                                                        <?= admin_return_field() ?>
                                                        <input type="hidden" name="user_id" value="<?= $uid ?>">
                                                        <input type="hidden" name="user_action" value="reinstate">
                                                        <p class="cell-sub">Reinstating lets them sign in again. Their listing stays paused until you make it live.</p>
                                                        <button type="submit" class="btn btn-outline btn-sm">Reinstate account</button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST" action="<?= e($actionUrl) ?>" class="form-stack" data-confirm="Yes, suspend and email">
                                                        <?= csrf_field() ?>
                                                        <?= admin_return_field() ?>
                                                        <input type="hidden" name="user_id" value="<?= $uid ?>">
                                                        <input type="hidden" name="user_action" value="suspend">
                                                        <div class="field">
                                                            <label for="suspend-<?= $uid ?>">Reason (they receive it by email)</label>
                                                            <textarea id="suspend-<?= $uid ?>" name="note" rows="2" maxlength="500" required minlength="5"></textarea>
                                                        </div>
                                                        <button type="submit" class="btn btn-danger-outline btn-sm">Suspend account</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </details>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php View::partial('admin_pager', ['page' => $page, 'pages' => $pages, 'total' => $total, 'path' => '/admin/users', 'query' => $activeFilters]); ?>
    </div>
</section>
