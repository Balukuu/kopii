<?php
defined('KOPII_APP') or exit('Direct access denied.');

class AdminController
{
    public function index(): void
    {
        require_admin();
        $providers = ProviderModel::adminAllProviders();
        $stats = ProviderModel::marketplaceStats();

        View::render('admin/index', [
            'pageTitle'      => 'Admin Moderation — Kopii Marketplace',
            'currentRoute'   => '/admin',
            'providers'      => $providers,
            'stats'          => $stats,
            'reviews'        => ReviewModel::adminList(),
            'pendingReviews' => ReviewModel::pendingCount(),
        ]);
    }

    public function moderate(): void
    {
        require_admin();
        verify_csrf();

        $providerId = (int) ($_POST['provider_id'] ?? 0);
        $action     = (string) ($_POST['mod_action'] ?? '');

        if ($providerId > 0) {
            if ($action === 'toggle_verified') {
                ProviderModel::adminToggleFlag($providerId, 'is_verified');
                flash('success', 'Verified badge updated.');
            } elseif ($action === 'toggle_featured') {
                ProviderModel::adminToggleFlag($providerId, 'is_featured');
                flash('success', 'Featured placement updated.');
            } elseif ($action === 'toggle_status') {
                ProviderModel::adminToggleStatus($providerId);
                flash('success', 'Listing visibility updated.');
            }
        }

        redirect_to('/admin');
    }

    /** Approve a waiting review (it then appears and counts toward the rating) or delete any review. */
    public function moderateReview(): void
    {
        require_admin();
        verify_csrf();

        $reviewId = (int) ($_POST['review_id'] ?? 0);
        $action   = (string) ($_POST['review_action'] ?? '');

        if ($reviewId > 0) {
            if ($action === 'approve') {
                ReviewModel::approve($reviewId);
                flash('success', 'Review approved. It is now public and counts toward the rating.');
            } elseif ($action === 'delete') {
                ReviewModel::delete($reviewId);
                flash('success', 'Review deleted.');
            }
        }

        redirect_to('/admin', ['tab' => 'reviews']);
    }
}
