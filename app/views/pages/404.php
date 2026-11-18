<?php
defined('KOPII_APP') or exit('Direct access denied.');
?>
<section class="section-pad">
    <div class="container">
        <div class="auth-card auth-card-narrow" style="text-align:center;">
            <h1 style="margin:0 0 0.5rem; color:var(--navy-900);">We couldn't find that page</h1>
            <p class="text-muted" style="margin:0 0 1.5rem;">
                The listing or page may have moved. Browse active freelancers and businesses across Uganda below.
            </p>
            <div style="display:flex; justify-content:center; gap:0.75rem; flex-wrap:wrap;">
                <a href="<?= e(route_url('/')) ?>" class="btn btn-outline">Back to Home</a>
                <a href="<?= e(route_url('/explore')) ?>" class="btn btn-primary">Browse providers</a>
            </div>
        </div>
    </div>
</section>
