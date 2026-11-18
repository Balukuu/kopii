<?php
defined('KOPII_APP') or exit('Direct access denied.');
/** @var ?Throwable $detail  Set only on a developer's local copy. */
?>
<section class="section-pad">
    <div class="container">
        <div class="auth-card auth-card-narrow">
            <h1 class="auth-title">Something went wrong on our side</h1>
            <p class="auth-lede">
                Your request did not go through, and nothing you typed was lost on purpose. Please try again in a moment.
            </p>
            <div class="cta-band-actions" style="margin-block-start:1.5rem;">
                <a href="<?= e(route_url('/')) ?>" class="btn btn-primary">Back to home</a>
            </div>

            <?php if ($detail !== null): ?>
                <pre style="margin-block-start:1.5rem; padding:1rem; overflow:auto; background:var(--surface-alt); border-radius:var(--radius-control); font-size:0.8rem;"><?= e(get_class($detail) . ': ' . $detail->getMessage() . "\n" . $detail->getFile() . ':' . $detail->getLine()) ?></pre>
            <?php endif; ?>
        </div>
    </div>
</section>
