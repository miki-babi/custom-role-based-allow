<?php
/*
 * Plugin Name: WooCommerce Role-Based Allowance
 * Description: Allow specific user roles to purchase specific WooCommerce
 products
 * Version: 1.0
 * Author: Mikiyas Shiferaw
 * Author URI: https://t.me/mikiyas_sh
 * License: GPL2
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WC_Role_Based_Allowance {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_filter('woocommerce_is_purchasable', [$this, 'restrict_purchasable'], 10, 2);
        add_action('woocommerce_add_to_cart_validation', [$this, 'restrict_cart_addition'], 10, 2);
    }

    public function add_admin_menu() {
        add_menu_page(
            'Allowed Roles',
            'Allowed Roles',
            'manage_options',
            'wc-role-allowance',
            [$this, 'settings_page'],
            'dashicons-yes',
            58
        );
    }

    public function settings_page() {
        // Check if form is submitted and save the settings
        if (isset($_POST['save_allowance_settings'])) {
            $roles = isset($_POST['roles']) ? array_map('sanitize_text_field', $_POST['roles']) : [];
            $product_ids = sanitize_text_field($_POST['product_ids']);
            
            // Save site-specific settings
            update_option('wc_allowed_roles', $roles);
            update_option('wc_allowed_products', $product_ids);

            echo '<div class="updated"><p>Settings Saved.</p></div>';
        }

        // Retrieve site-specific settings
        $roles = get_option('wc_allowed_roles', []);
        if (!is_array($roles)) {
            $roles = [];
        }
        $product_ids = get_option('wc_allowed_products', '');

        ?>
        <div class="wrap">
            <h1>Role-Based Allowance</h1>
            <form method="post" action="">
                <table class="form-table">
                    <tr>
                        <th scope="row">Allowed Roles</th>
                        <td>
                            <?php
                            global $wp_roles;
                            foreach ($wp_roles->roles as $key => $role_data) {
                                $checked = in_array($key, $roles) ? 'checked' : '';
                                echo "<label><input type='checkbox' name='roles[]' value='{$key}' {$checked}> {$role_data['name']}</label><br>";
                            }
                            ?>
                            <p class="description">Select the roles allowed to purchase specified products.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Allowed Product IDs</th>
                        <td>
                            <input type="text" name="product_ids" value="<?php echo esc_attr($product_ids); ?>" placeholder="e.g., 12,45,78" required>
                            <p class="description">Enter product IDs, separated by commas.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Settings', 'primary', 'save_allowance_settings'); ?>
            </form>
        </div>
        <?php
    }

    public function restrict_purchasable($purchasable, $product) {
        // Get site-specific allowed roles and products
        $allowed_roles = get_option('wc_allowed_roles', []);
        if (!is_array($allowed_roles)) {
            $allowed_roles = [];
        }

        $allowed_products = array_map('trim', explode(',', get_option('wc_allowed_products', '')));
        $current_user = wp_get_current_user();

        if (in_array($product->get_id(), $allowed_products)) {
            foreach ($current_user->roles as $role) {
                if (in_array($role, $allowed_roles)) {
                    return true; // Allow purchase
                }
            }
            return false; // Restrict for other roles
        }

        return $purchasable; // If product is not in the allowed list, default WooCommerce logic
    }

    public function restrict_cart_addition($passed, $product_id) {
        // Get site-specific allowed roles and products
        $allowed_roles = get_option('wc_allowed_roles', []);
        if (!is_array($allowed_roles)) {
            $allowed_roles = [];
        }

        $allowed_products = array_map('trim', explode(',', get_option('wc_allowed_products', '')));
        $current_user = wp_get_current_user();

        if (in_array($product_id, $allowed_products)) {
            foreach ($current_user->roles as $role) {
                if (in_array($role, $allowed_roles)) {
                    return $passed; // Allow adding to cart
                }
            }
            wc_add_notice('You are not allowed to purchase this product.', 'error');
            return false; // Restrict cart addition
        }

        return $passed; // If product is not in the allowed list, default WooCommerce logic
    }
}

new WC_Role_Based_Allowance();
