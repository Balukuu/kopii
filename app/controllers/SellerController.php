<?php
defined('KOPII_APP') or exit('Direct access denied.');

/**
 * A provider managing their own shelf in the dashboard: list, edit, pause and remove products.
 * (The Products tab itself is rendered by DashboardController.)
 *
 * Every action looks the product up by BOTH its id and the signed-in provider's id, so nobody can
 * touch another seller's product by changing a number in the form.
 */
class SellerController
{
    public function save(): void
    {
        $user     = require_auth();
        $provider = $this->provider($user);
        verify_csrf();

        $providerId = (int) $provider['id'];
        $editId     = (int) ($_POST['product_id'] ?? 0);
        $product    = null;
        if ($editId > 0) {
            $product = ProductModel::findOwned($editId, $providerId);
            if ($product === null) {
                flash('error', 'That product no longer exists.');
                redirect_to('/dashboard', ['tab' => 'products']);
            }
        }
        $back = $product !== null ? ['tab' => 'products', 'edit' => (string) $editId] : ['tab' => 'products'];

        $uganda = require KOPII_ROOT . '/app/config/uganda.php';
        [$clean, $problems] = self::cleanProduct($_POST, $uganda);

        $files    = self::uploadedFiles($_FILES['product_images'] ?? []);
        $existing = $product !== null ? count($product['images']) : 0;
        if ($existing + count($files) > ProductModel::MAX_IMAGES) {
            $problems[] = 'A product can have at most ' . ProductModel::MAX_IMAGES . ' photos.';
        }
        if ($product === null && ProductModel::countForProvider($providerId) >= ProductModel::MAX_PER_PROVIDER) {
            $problems[] = 'You have reached the limit of ' . ProductModel::MAX_PER_PROVIDER . ' products. Remove one to add another.';
        }

        if (!empty($problems)) {
            set_old_input($_POST);
            flash('error', 'Nothing was saved. Please check: ' . implode(' ', $problems));
            redirect_to('/dashboard', $back);
        }

        // Photos are uploaded before the row is written, and removed again if anything fails, so a
        // failed save never leaves stray files behind.
        $uploaded = [];
        try {
            foreach ($files as $file) {
                $path = upload_image($file, 'products');
                if ($path !== null) {
                    $uploaded[] = $path;
                }
            }

            if ($product === null) {
                $newId = ProductModel::create($providerId, $clean);
                foreach ($uploaded as $path) {
                    ProductModel::addImage($newId, $path);
                }
                flash('success', '"' . $clean['name'] . '" is now in your shop.');
            } else {
                ProductModel::update($editId, $providerId, $clean);
                foreach ($uploaded as $path) {
                    ProductModel::addImage($editId, $path);
                }
                flash('success', 'Product updated.');
            }
            clear_old_input();
            redirect_to('/dashboard', ['tab' => 'products']);
        } catch (UserFacingException $e) {
            array_map('delete_upload', $uploaded);
            set_old_input($_POST);
            flash('error', $e->getMessage());
            redirect_to('/dashboard', $back);
        } catch (Throwable $e) {
            array_map('delete_upload', $uploaded);
            set_old_input($_POST);
            flash('error', report_exception($e, 'We could not save that product. Please try again.'));
            redirect_to('/dashboard', $back);
        }
    }

    /** Pause or re-list a product. An administrator's suspension is not the owner's to lift. */
    public function setStatus(): void
    {
        $user     = require_auth();
        $provider = $this->provider($user);
        verify_csrf();

        $id      = (int) ($_POST['product_id'] ?? 0);
        $status  = (string) ($_POST['status'] ?? '');
        $product = $id > 0 ? ProductModel::findOwned($id, (int) $provider['id']) : null;

        if ($product === null) {
            flash('error', 'That product no longer exists.');
        } elseif ($product['status'] === ProductModel::SUSPENDED) {
            flash('error', 'The Kopii team has hidden this product, so you cannot re-list it. Check your email for the reason.');
        } elseif (ProductModel::setOwnerStatus($id, (int) $provider['id'], $status)) {
            flash('success', $status === ProductModel::ACTIVE ? '"' . $product['name'] . '" is live again.' : '"' . $product['name'] . '" is paused and hidden from the shop.');
        }

        redirect_to('/dashboard', ['tab' => 'products']);
    }

    public function delete(): void
    {
        $user     = require_auth();
        $provider = $this->provider($user);
        verify_csrf();

        $id      = (int) ($_POST['product_id'] ?? 0);
        $product = $id > 0 ? ProductModel::findOwned($id, (int) $provider['id']) : null;
        if ($product === null) {
            flash('error', 'That product no longer exists.');
            redirect_to('/dashboard', ['tab' => 'products']);
        }

        try {
            $paths = ProductModel::delete($id, (int) $provider['id']);
            array_map('delete_upload', $paths);
            flash('success', '"' . $product['name'] . '" was removed from your shop.');
        } catch (Throwable $e) {
            flash('error', report_exception($e, 'We could not remove that product. Please try again.'));
        }
        redirect_to('/dashboard', ['tab' => 'products']);
    }

    public function deleteImage(): void
    {
        $user     = require_auth();
        $provider = $this->provider($user);
        verify_csrf();

        $productId = (int) ($_POST['product_id'] ?? 0);
        $imageId   = (int) ($_POST['image_id'] ?? 0);

        if ($productId > 0 && $imageId > 0 && ProductModel::findOwned($productId, (int) $provider['id']) !== null) {
            $path = ProductModel::deleteImage($imageId, $productId);
            if ($path !== null) {
                delete_upload($path);
                flash('success', 'Photo removed.');
            }
        }
        redirect_to('/dashboard', ['tab' => 'products', 'edit' => (string) $productId]);
    }

    // ---------------------------------------------------------------------------------------

    /** The signed-in account's provider profile; anyone without one is sent back to the dashboard. */
    private function provider(array $user): array
    {
        $provider = ProviderModel::findByUserId((int) $user['id']);
        if ($provider === null) {
            flash('warning', 'Activate your showcase profile first, then you can list products.');
            redirect_to('/dashboard');
        }
        return $provider;
    }

    /**
     * @return array{0: array, 1: list<string>} the cleaned values and a list of problems written for the visitor
     */
    private static function cleanProduct(array $post, array $uganda): array
    {
        $c = [
            'name'           => trim((string) ($post['name'] ?? '')),
            'category_id'    => (int) ($post['category_id'] ?? 0),
            'description'    => trim((string) ($post['description'] ?? '')),
            'price_ugx'      => (int) preg_replace('/\D+/', '', (string) ($post['price_ugx'] ?? '0')),
            'price_unit'     => trim((string) ($post['price_unit'] ?? 'each')),
            'item_condition' => trim((string) ($post['item_condition'] ?? 'new')),
            'stock_status'   => trim((string) ($post['stock_status'] ?? 'in_stock')),
        ];

        $problems = [];
        if (text_length($c['name']) < 2 || text_length($c['name']) > 160) {
            $problems[] = 'Give the product a name (2–160 characters).';
        }
        if ($c['category_id'] <= 0 || ProductModel::categoryById($c['category_id']) === null) {
            $problems[] = 'Choose a category from the list.';
        }
        if (text_length($c['description']) > 2000) {
            $problems[] = 'Keep the description to 2,000 characters or fewer.';
        }
        if ($c['price_ugx'] > 1000000000) {
            $problems[] = 'Enter a price up to UGX 1,000,000,000, or leave it empty to show "Ask for price".';
        }
        if (!in_array($c['price_unit'], $uganda['product_units'], true)) {
            $problems[] = 'Choose what the price is for from the list.';
        }
        if (!array_key_exists($c['item_condition'], $uganda['product_conditions'])) {
            $problems[] = 'Choose the condition from the list.';
        }
        if (!array_key_exists($c['stock_status'], $uganda['stock_statuses'])) {
            $problems[] = 'Choose the availability from the list.';
        }

        $c['price_ugx'] = min(max($c['price_ugx'], 0), 1000000000);
        return [$c, $problems];
    }

    /**
     * PHP lays a multi-file upload out column by column; this turns it back into one array per file,
     * dropping empty slots (a file input left blank still sends one "no file" entry).
     *
     * @return list<array>
     */
    private static function uploadedFiles(array $field): array
    {
        if (!isset($field['name']) || !is_array($field['name'])) {
            return [];
        }

        $files = [];
        foreach (array_keys($field['name']) as $i) {
            if (($field['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $files[] = [
                'name'     => $field['name'][$i] ?? '',
                'type'     => $field['type'][$i] ?? '',
                'tmp_name' => $field['tmp_name'][$i] ?? '',
                'error'    => $field['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $field['size'][$i] ?? 0,
            ];
        }
        return $files;
    }
}
