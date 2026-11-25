<?php
defined('KOPII_APP') or exit('Direct access denied.');
/**
 * "Report this listing": a small closed-by-default form at the bottom of a page's sidebar. It posts to
 * /report, which stores the report in the admin inbox and emails the team. The owner is never told who reported.
 *
 * @var string $targetType provider | product | opportunity
 * @var int    $targetId
 * @var string $noun       what to call it: "listing", "product", "opportunity"
 */
$reportUser = current_user();
$boxId      = 'report-' . $targetType . '-' . (int) $targetId;
?>
<details class="report-box">
    <summary><?= svg_icon('shield', 16) ?> Report this <?= e($noun) ?></summary>
    <form method="POST" action="<?= e(route_url('/report')) ?>" class="stack-form">
        <?= csrf_field() ?>
        <input type="hidden" name="target_type" value="<?= e($targetType) ?>">
        <input type="hidden" name="target_id" value="<?= (int) $targetId ?>">
        <div class="visually-hidden" aria-hidden="true">
            <label for="<?= e($boxId) ?>-hp">Leave empty</label>
            <input type="text" id="<?= e($boxId) ?>-hp" name="report_url_hp" tabindex="-1" autocomplete="off">
        </div>

        <p class="hint m-0">Tell the Kopii team if something is wrong. The owner is not told who reported it. If you already lost money, also read the <a href="<?= e(route_url('/safety')) ?>">safety tips</a>.</p>

        <div class="field">
            <label for="<?= e($boxId) ?>-reason">What is wrong? *</label>
            <select id="<?= e($boxId) ?>-reason" name="reason" required>
                <option value="">Choose a reason</option>
                <?php foreach (SupportModel::REPORT_REASONS as $key => $label): ?>
                    <option value="<?= e($key) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="<?= e($boxId) ?>-details">Details (optional)</label>
            <textarea id="<?= e($boxId) ?>-details" name="details" rows="3" maxlength="1500" placeholder="What did you see or what happened?"></textarea>
        </div>
        <?php if (!$reportUser): ?>
            <div class="field">
                <label for="<?= e($boxId) ?>-email">Your email (optional)</label>
                <input type="email" id="<?= e($boxId) ?>-email" name="reporter_email" maxlength="190" autocomplete="email" autocapitalize="none" spellcheck="false">
                <span class="hint">Only if you would like us to reply.</span>
            </div>
        <?php endif; ?>
        <button type="submit" class="btn btn-outline btn-sm">Send report</button>
    </form>
</details>
