<?php

/**
 * Plugin Name: WooCommerce Custom Shipping with Pickup
 * Description: Custom shipping rates based on country, postal code, weight, and local pickup options
 * Version: 1.4.0
 * Author: Tiran Chanuka
 * Text Domain: wc-custom-shipping
 */

if (!defined('ABSPATH')) {
    exit;
}

// Add JavaScript to handle add/remove buttons
function wc_custom_shipping_admin_scripts()
{
    if (
        isset($_GET['page']) && $_GET['page'] === 'wc-settings'
        && isset($_GET['tab']) && $_GET['tab'] === 'shipping'
    ) {
?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                var deletedRates = [];
                var deletedPickups = [];

                // Handle Add Rate button
                $(document).on('click', '.add_rate', function() {
                    var $row = $(this).closest('tr');
                    var $clone = $row.clone();

                    // Clear values in the cloned row
                    $clone.find('input').val('');
                    $clone.find('select').prop('selectedIndex', 0);

                    // Insert before the "new rate" row
                    $row.before($clone);
                });

                // Handle Remove Rate button
                $(document).on('click', '.remove_rate', function() {
                    var $row = $(this).closest('tr');
                    var rateId = $row.attr('data-rate-id');

                    if (confirm('Are you sure you want to delete this shipping rate? This action cannot be undone.')) {
                        if (rateId) {
                            // AJAX call to delete from database
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'delete_shipping_rate',
                                    rate_id: rateId,
                                    nonce: wc_custom_shipping.nonce
                                },
                                success: function(response) {
                                    if (response.success) {
                                        // Add to deleted rates array and update hidden input
                                        deletedRates.push(rateId);
                                        $('#deleted_rates').val(deletedRates.join(','));

                                        // Remove row from table
                                        $row.fadeOut(400, function() {
                                            $(this).remove();
                                        });

                                        // Show success message
                                        alert('Shipping rate deleted successfully!');
                                    } else {
                                        alert('Error deleting shipping rate. Please try again.');
                                    }
                                },
                                error: function() {
                                    alert('Error deleting shipping rate. Please try again.');
                                }
                            });
                        } else {
                            // For new unsaved rows, just remove from DOM
                            $row.fadeOut(400, function() {
                                $(this).remove();
                            });
                        }
                    }
                });

                // Handle Add Pickup button
                $(document).on('click', '.add_pickup', function() {
                    var $row = $(this).closest('tr');
                    var $clone = $row.clone();

                    // Clear values in the cloned row
                    $clone.find('input').val('');
                    $clone.find('select').prop('selectedIndex', 0);

                    // Insert before the "new pickup" row
                    $row.before($clone);
                });

                // Handle Remove Pickup button
                $(document).on('click', '.remove_pickup', function() {
                    var $row = $(this).closest('tr');
                    var pickupId = $row.attr('data-pickup-id');

                    if (confirm('Are you sure you want to delete this pickup location? This action cannot be undone.')) {
                        if (pickupId) {
                            // AJAX call to delete from database
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'delete_pickup_location',
                                    pickup_id: pickupId,
                                    nonce: wc_custom_shipping.nonce
                                },
                                success: function(response) {
                                    if (response.success) {
                                        // Add to deleted pickups array and update hidden input
                                        deletedPickups.push(pickupId);
                                        $('#deleted_pickups').val(deletedPickups.join(','));

                                        // Remove row from table
                                        $row.fadeOut(400, function() {
                                            $(this).remove();
                                        });

                                        // Show success message
                                        alert('Pickup location deleted successfully!');
                                    } else {
                                        alert('Error deleting pickup location. Please try again.');
                                    }
                                },
                                error: function() {
                                    alert('Error deleting pickup location. Please try again.');
                                }
                            });
                        } else {
                            // For new unsaved rows, just remove from DOM
                            $row.fadeOut(400, function() {
                                $(this).remove();
                            });
                        }
                    }
                });

                // Debug: Log when form is submitted
                $('form#mainform').on('submit', function() {
                    console.log('Form submitted. Deleted rates:', $('#deleted_rates').val());
                    console.log('Form submitted. Deleted pickups:', $('#deleted_pickups').val());
                });
            });
        </script>
    <?php
    }
}
// Add nonce for security
function wc_custom_shipping_admin_footer()
{
    if (
        isset($_GET['page']) && $_GET['page'] === 'wc-settings'
        && isset($_GET['tab']) && $_GET['tab'] === 'shipping'
    ) {
        $nonce = wp_create_nonce('wc_custom_shipping_delete');
    ?>
        <script type="text/javascript">
            var wc_custom_shipping = {
                nonce: '<?php echo esc_js($nonce); ?>'
            };
        </script>
        <?php
    }
}
add_action('admin_footer', 'wc_custom_shipping_admin_footer');

add_action('admin_footer', 'wc_custom_shipping_admin_scripts');

// Activation function
function wc_custom_shipping_activate()
{
    global $wpdb;

    // Table for shipping rates
    $rates_table = $wpdb->prefix . 'wc_custom_shipping_rates';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $rates_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        country varchar(2) NOT NULL,
        postal_code varchar(10) NOT NULL,
        min_weight decimal(10,2) NOT NULL,
        max_weight decimal(10,2) NOT NULL,
        standard_fee decimal(10,2) NOT NULL,
        one_day_fee decimal(10,2) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Check if we need to migrate existing data
    $columns = $wpdb->get_col("SHOW COLUMNS FROM $rates_table");
    if (in_array('rate', $columns)) {
        // Migrate existing data
        $wpdb->query("ALTER TABLE $rates_table 
                     ADD COLUMN standard_fee decimal(10,2) NOT NULL DEFAULT 0,
                     ADD COLUMN one_day_fee decimal(10,2) NOT NULL DEFAULT 0");
        $wpdb->query("UPDATE $rates_table SET standard_fee = rate, one_day_fee = rate * 1.5");
        $wpdb->query("ALTER TABLE $rates_table DROP COLUMN rate");
    }

    // Table for pickup locations
    $pickup_table = $wpdb->prefix . 'wc_custom_shipping_pickups';

    $sql = "CREATE TABLE IF NOT EXISTS $pickup_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        location_name varchar(255) NOT NULL,
        address text NOT NULL,
        country varchar(2) NOT NULL,
        city varchar(100) NOT NULL,
        postal_code varchar(20) NOT NULL,
        fee decimal(10,2) NOT NULL DEFAULT 0,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    dbDelta($sql);
}

register_activation_hook(__FILE__, 'wc_custom_shipping_activate');

function wc_custom_shipping_init()
{
    if (!class_exists('WC_Shipping_Method')) {
        return;
    }

    class WC_Custom_Shipping_Method extends WC_Shipping_Method
    {
        public function __construct($instance_id = 0)
        {
            parent::__construct($instance_id);

            $this->id = 'custom_shipping';
            $this->instance_id = absint($instance_id);
            $this->title = 'Custom Shipping';
            $this->method_title = 'Custom Shipping';
            $this->method_description = 'Custom shipping with rates based on country, postal code, weight, and local pickup options';
            $this->supports = array(
                'shipping-zones',
                'instance-settings',
            );

            $this->init();
        }

        public function init()
        {
            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->enabled = $this->get_option('enabled');

            add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
        }

        public function init_form_fields()
        {
            $this->instance_form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'wc-custom-shipping'),
                    'type' => 'checkbox',
                    'label' => __('Enable this shipping method', 'wc-custom-shipping'),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __('Title', 'wc-custom-shipping'),
                    'type' => 'text',
                    'description' => __('This controls the title customers see during checkout.', 'wc-custom-shipping'),
                    'default' => __('Custom Shipping', 'wc-custom-shipping'),
                    'desc_tip' => true
                ),
                'enable_local_pickup' => array(
                    'title' => __('Local Pickup', 'wc-custom-shipping'),
                    'type' => 'checkbox',
                    'label' => __('Enable local pickup options', 'wc-custom-shipping'),
                    'default' => 'yes',
                    'desc_tip' => true,
                    'description' => __('Allow customers to pick up orders from specific locations.', 'wc-custom-shipping')
                ),
                'shipping_rates' => array(
                    'title' => __('Shipping Rates', 'wc-custom-shipping'),
                    'type' => 'title',
                    'description' => ''
                )
            );
        }

        public function admin_options()
        {
        ?>
            <h2><?php echo esc_html($this->method_title); ?></h2>
            <p><?php echo esc_html($this->method_description); ?></p>
            <table class="form-table">
                <?php $this->generate_settings_html(); ?>
            </table>
        <?php
            $this->generate_rates_table();
            $this->generate_pickup_table();
        }

        private function generate_rates_table()
        {
            global $wpdb;
            $table_name = $wpdb->prefix . 'wc_custom_shipping_rates';
            $rates = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id ASC", ARRAY_A);
        ?>
            <h3><?php _e('Shipping Rates', 'wc-custom-shipping'); ?></h3>
            <p><?php _e('Define shipping rates based on country, postal code and weight.', 'wc-custom-shipping'); ?></p>
            <input type="hidden" id="deleted_rates" name="deleted_rates" value="">
            <table class="widefat" id="shipping_rates_table">
                <thead>
                    <tr>
                        <th><?php _e('Country', 'wc-custom-shipping'); ?></th>
                        <th><?php _e('Postal Code', 'wc-custom-shipping'); ?></th>
                        <th><?php _e('Min Weight (kg)', 'wc-custom-shipping'); ?></th>
                        <th><?php _e('Max Weight (kg)', 'wc-custom-shipping'); ?></th>
                        <th><?php _e('Standard Fee', 'wc-custom-shipping'); ?></th>
                        <th><?php _e('One Day Fee', 'wc-custom-shipping'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rates) : foreach ($rates as $rate) : ?>
                            <tr data-rate-id="<?php echo esc_attr($rate['id']); ?>">
                                <td>
                                    <select name="shipping_rate[<?php echo esc_attr($rate['id']); ?>][country]">
                                        <?php foreach (WC()->countries->get_countries() as $code => $name) : ?>
                                            <option value="<?php echo esc_attr($code); ?>" <?php selected($rate['country'], $code); ?>>
                                                <?php echo esc_html($name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" name="shipping_rate[<?php echo esc_attr($rate['id']); ?>][postal_code]"
                                        value="<?php echo esc_attr($rate['postal_code']); ?>" placeholder="* for all">
                                </td>
                                <td>
                                    <input type="number" step="0.01" name="shipping_rate[<?php echo esc_attr($rate['id']); ?>][min_weight]"
                                        value="<?php echo esc_attr($rate['min_weight']); ?>">
                                </td>
                                <td>
                                    <input type="number" step="0.01" name="shipping_rate[<?php echo esc_attr($rate['id']); ?>][max_weight]"
                                        value="<?php echo esc_attr($rate['max_weight']); ?>">
                                </td>
                                <td>
                                    <input type="number" step="0.01" name="shipping_rate[<?php echo esc_attr($rate['id']); ?>][standard_fee]"
                                        value="<?php echo esc_attr($rate['standard_fee']); ?>">
                                </td>
                                <td>
                                    <input type="number" step="0.01" name="shipping_rate[<?php echo esc_attr($rate['id']); ?>][one_day_fee]"
                                        value="<?php echo esc_attr($rate['one_day_fee']); ?>">
                                </td>
                                <td>
                                    <button type="button" class="button remove_rate"><?php _e('Remove', 'wc-custom-shipping'); ?></button>
                                </td>
                            </tr>
                    <?php endforeach;
                    endif; ?>
                    <tr class="new-rate">
                        <td>
                            <select name="shipping_rate[new][country]">
                                <option value=""><?php _e('Select country', 'wc-custom-shipping'); ?></option>
                                <?php foreach (WC()->countries->get_countries() as $code => $name) : ?>
                                    <option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input type="text" name="shipping_rate[new][postal_code]" placeholder="* for all"></td>
                        <td><input type="number" step="0.01" name="shipping_rate[new][min_weight]" placeholder="0"></td>
                        <td><input type="number" step="0.01" name="shipping_rate[new][max_weight]" placeholder="999999"></td>
                        <td><input type="number" step="0.01" name="shipping_rate[new][standard_fee]" value="0"></td>
                        <td><input type="number" step="0.01" name="shipping_rate[new][one_day_fee]" value="0"></td>
                        <td><button type="button" class="button add_rate"><?php _e('Add Rate', 'wc-custom-shipping'); ?></button></td>
                    </tr>
                </tbody>
            </table>
        <?php
        }

        private function generate_pickup_table()
        {
            global $wpdb;
            $table_name = $wpdb->prefix . 'wc_custom_shipping_pickups';
            $pickups = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id ASC", ARRAY_A);
        ?>
            <h3><?php _e('Local Pickup Locations', 'wc-custom-shipping'); ?></h3>
            <p><?php _e('Define locations where customers can pick up their orders.', 'wc-custom-shipping'); ?></p>
            <input type="hidden" id="deleted_pickups" name="deleted_pickups" value="">
            <table class="widefat" id="pickup_locations_table">
                <thead>
                    <tr>
                        <th><?php _e('Location Name', 'wc-custom-shipping'); ?></th>
                        <th><?php _e('Address', 'wc-custom-shipping'); ?></th>
                        <th><?php _e('Country', 'wc-custom-shipping'); ?></th>
                        <th><?php _e('City', 'wc-custom-shipping'); ?></th>
                        <th><?php _e('Postal Code', 'wc-custom-shipping'); ?></th>
                        <th><?php _e('Fee', 'wc-custom-shipping'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($pickups) : foreach ($pickups as $pickup) : ?>
                            <tr data-pickup-id="<?php echo esc_attr($pickup['id']); ?>">
                                <td>
                                    <input type="text" name="pickup_location[<?php echo esc_attr($pickup['id']); ?>][location_name]"
                                        value="<?php echo esc_attr($pickup['location_name']); ?>">
                                </td>
                                <td>
                                    <input type="text" name="pickup_location[<?php echo esc_attr($pickup['id']); ?>][address]"
                                        value="<?php echo esc_attr($pickup['address']); ?>">
                                </td>
                                <td>
                                    <select name="pickup_location[<?php echo esc_attr($pickup['id']); ?>][country]">
                                        <?php foreach (WC()->countries->get_countries() as $code => $name) : ?>
                                            <option value="<?php echo esc_attr($code); ?>" <?php selected($pickup['country'], $code); ?>>
                                                <?php echo esc_html($name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" name="pickup_location[<?php echo esc_attr($pickup['id']); ?>][city]"
                                        value="<?php echo esc_attr($pickup['city']); ?>">
                                </td>
                                <td>
                                    <input type="text" name="pickup_location[<?php echo esc_attr($pickup['id']); ?>][postal_code]"
                                        value="<?php echo esc_attr($pickup['postal_code']); ?>">
                                </td>
                                <td>
                                    <input type="number" step="0.01" name="pickup_location[<?php echo esc_attr($pickup['id']); ?>][fee]"
                                        value="<?php echo esc_attr($pickup['fee']); ?>">
                                </td>
                                <td>
                                    <button type="button" class="button remove_pickup"><?php _e('Remove', 'wc-custom-shipping'); ?></button>
                                </td>
                            </tr>
                    <?php endforeach;
                    endif; ?>
                    <tr class="new-pickup">
                        <td><input type="text" name="pickup_location[new][location_name]" placeholder="Store Name"></td>
                        <td><input type="text" name="pickup_location[new][address]" placeholder="123 Main St"></td>
                        <td>
                            <select name="pickup_location[new][country]">
                                <option value=""><?php _e('Select country', 'wc-custom-shipping'); ?></option>
                                <?php foreach (WC()->countries->get_countries() as $code => $name) : ?>
                                    <option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input type="text" name="pickup_location[new][city]" placeholder="City"></td>
                        <td><input type="text" name="pickup_location[new][postal_code]" placeholder="12345"></td>
                        <td><input type="number" step="0.01" name="pickup_location[new][fee]" value="0"></td>
                        <td><button type="button" class="button add_pickup"><?php _e('Add Pickup', 'wc-custom-shipping'); ?></button></td>
                    </tr>
                </tbody>
            </table>
<?php
        }


        public function process_admin_options()
        {
            parent::process_admin_options();

            global $wpdb;
            $rates_table = $wpdb->prefix . 'wc_custom_shipping_rates';
            $pickup_table = $wpdb->prefix . 'wc_custom_shipping_pickups';

            // Process shipping rate deletions
            if (!empty($_POST['deleted_rates'])) {
                $deleted_rates = array_filter(array_map('intval', explode(',', $_POST['deleted_rates'])));
                if (!empty($deleted_rates)) {
                    error_log('Deleting rates: ' . print_r($deleted_rates, true)); // Debug log
                    foreach ($deleted_rates as $rate_id) {
                        $wpdb->delete($rates_table, array('id' => $rate_id), array('%d'));
                    }
                }
            }

            // Process pickup location deletions
            if (!empty($_POST['deleted_pickups'])) {
                $deleted_pickups = array_filter(array_map('intval', explode(',', $_POST['deleted_pickups'])));
                if (!empty($deleted_pickups)) {
                    error_log('Deleting pickups: ' . print_r($deleted_pickups, true)); // Debug log
                    foreach ($deleted_pickups as $pickup_id) {
                        $wpdb->delete($pickup_table, array('id' => $pickup_id), array('%d'));
                    }
                }
            }

            // Process shipping rate updates and additions
            if (isset($_POST['shipping_rate'])) {
                $rates = wc_clean($_POST['shipping_rate']);

                foreach ($rates as $id => $rate) {
                    if (empty($rate['country']) && $id !== 'new') {
                        continue;
                    }

                    $rate_data = array(
                        'country' => $rate['country'],
                        'postal_code' => !empty($rate['postal_code']) ? strtoupper(wc_normalize_postcode($rate['postal_code'])) : '*',
                        'min_weight' => floatval($rate['min_weight']),
                        'max_weight' => floatval($rate['max_weight']),
                        'standard_fee' => floatval($rate['standard_fee']),
                        'one_day_fee' => floatval($rate['one_day_fee'])
                    );

                    if ($id === 'new' && !empty($rate['country'])) {
                        $wpdb->insert(
                            $rates_table,
                            $rate_data,
                            array('%s', '%s', '%f', '%f', '%f', '%f')
                        );
                    } elseif (is_numeric($id)) {
                        $wpdb->update(
                            $rates_table,
                            $rate_data,
                            array('id' => $id),
                            array('%s', '%s', '%f', '%f', '%f', '%f'),
                            array('%d')
                        );
                    }
                }
            }

            // Process pickup location updates and additions
            if (isset($_POST['pickup_location'])) {
                $pickups = wc_clean($_POST['pickup_location']);

                foreach ($pickups as $id => $pickup) {
                    if ((empty($pickup['location_name']) || empty($pickup['country'])) && $id !== 'new') {
                        continue;
                    }

                    $pickup_data = array(
                        'location_name' => $pickup['location_name'],
                        'address' => $pickup['address'],
                        'country' => $pickup['country'],
                        'city' => $pickup['city'],
                        'postal_code' => !empty($pickup['postal_code']) ? strtoupper(wc_normalize_postcode($pickup['postal_code'])) : '',
                        'fee' => floatval($pickup['fee'])
                    );

                    if ($id === 'new' && !empty($pickup['location_name']) && !empty($pickup['country'])) {
                        $wpdb->insert(
                            $pickup_table,
                            $pickup_data,
                            array('%s', '%s', '%s', '%s', '%s', '%f')
                        );
                    } elseif (is_numeric($id)) {
                        $wpdb->update(
                            $pickup_table,
                            $pickup_data,
                            array('id' => $id),
                            array('%s', '%s', '%s', '%s', '%s', '%f'),
                            array('%d')
                        );
                    }
                }
            }
        }

        public function calculate_shipping($package = array())
        {
            global $wpdb;
            $rates_table = $wpdb->prefix . 'wc_custom_shipping_rates';
            $pickup_table = $wpdb->prefix . 'wc_custom_shipping_pickups';

            $weight = 0;
            $country = $package['destination']['country'];
            $postcode = strtoupper(wc_normalize_postcode($package['destination']['postcode']));

            // Calculate total weight
            foreach ($package['contents'] as $item) {
                if ($item['data']->get_weight()) {
                    $weight += floatval($item['data']->get_weight()) * $item['quantity'];
                }
            }

            // Debug log
            error_log("Calculating shipping for: Country: $country, Postcode: $postcode, Weight: $weight");

            // Modified query to first try exact postal code match, then fallback to wildcard
            $rate = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $rates_table 
                WHERE country = %s 
                AND (
                    postal_code = %s 
                    OR postal_code = '*' 
                    OR postal_code = ''
                )
                AND %f >= min_weight 
                AND %f <= max_weight
                ORDER BY 
                    CASE 
                        WHEN postal_code = %s THEN 1
                        WHEN postal_code = '*' OR postal_code = '' THEN 2
                    END
                LIMIT 1",
                $country,
                $postcode,
                $weight,
                $weight,
                $postcode
            ));

            // Debug log
            error_log("Found rate: " . print_r($rate, true));

            if ($rate) {
                $this->add_rate(array(
                    'id' => $this->id . $this->instance_id . '_standard',
                    'label' => $this->title . ' Standard Delivery',
                    'cost' => $rate->standard_fee,
                    'calc_tax' => 'per_order'
                ));

                // Add one day shipping rate
                $this->add_rate(array(
                    'id' => $this->id . $this->instance_id . '_one_day',
                    'label' => $this->title . ' Express Delivery',
                    'cost' => $rate->one_day_fee . '(Within One Day)',
                    'calc_tax' => 'per_order'
                ));
            }

            // Local pickup
            $local_pickups = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $pickup_table  WHERE country = %s",
                $country
            ));

            if ($local_pickups) {
                foreach ($local_pickups as $pickup) {
                    $this->add_rate(array(
                        'id' => $this->id . $this->instance_id . '_local_pickup_' . $pickup->id,
                        'label' => sprintf('%s Click & Collect (%s)', $this->title, $pickup->address),
                        'cost' => $pickup->fee,
                        'calc_tax' => 'per_order'
                    ));
                }
            }
        }
    }

    // Add the shipping method
    function add_wc_custom_shipping_method($methods)
    {
        $methods['custom_shipping'] = 'WC_Custom_Shipping_Method';
        return $methods;
    }
    add_filter('woocommerce_shipping_methods', 'add_wc_custom_shipping_method');
}

add_action('plugins_loaded', 'wc_custom_shipping_init');

// Add AJAX handling for rate deletion
add_action('wp_ajax_delete_shipping_rate', 'handle_delete_shipping_rate');

function handle_delete_shipping_rate()
{
    // Check nonce and capabilities
    if (!check_ajax_referer('wc_custom_shipping_delete', 'nonce', false) || !current_user_can('manage_woocommerce')) {
        wp_send_json_error('Unauthorized access');
        return;
    }

    $rate_id = isset($_POST['rate_id']) ? absint($_POST['rate_id']) : 0;

    if (!$rate_id) {
        wp_send_json_error('Invalid rate ID');
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'wc_custom_shipping_rates';

    $result = $wpdb->delete(
        $table_name,
        array('id' => $rate_id),
        array('%d')
    );

    if ($result) {
        wp_send_json_success();
    } else {
        wp_send_json_error('Failed to delete shipping rate');
    }
}

// Add AJAX handling for pickup location deletion
add_action('wp_ajax_delete_pickup_location', 'handle_delete_pickup_location');

function handle_delete_pickup_location()
{
    // Check nonce and capabilities
    if (!check_ajax_referer('wc_custom_shipping_delete', 'nonce', false) || !current_user_can('manage_woocommerce')) {
        wp_send_json_error('Unauthorized access');
        return;
    }

    $pickup_id = isset($_POST['pickup_id']) ? absint($_POST['pickup_id']) : 0;

    if (!$pickup_id) {
        wp_send_json_error('Invalid pickup location ID');
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'wc_custom_shipping_pickups';

    $result = $wpdb->delete(
        $table_name,
        array('id' => $pickup_id),
        array('%d')
    );

    if ($result) {
        wp_send_json_success();
    } else {
        wp_send_json_error('Failed to delete pickup location');
    }
}

// Add pickup location info to order details and emails
add_action('woocommerce_after_shipping_rate', 'pickup_location_display_info', 10, 2);

function pickup_location_display_info($method, $index)
{
    if (strpos($method->get_id(), 'custom_shipping') === false || strpos($method->get_id(), '_pickup_') === false) {
        return;
    }

    $meta_data = $method->get_meta_data();

    if (isset($meta_data['pickup_address']) && !empty($meta_data['pickup_address'])) {
        echo '<div class="pickup-location-info">';
        echo '<strong>' . __('Pickup Address:', 'wc-custom-shipping') . '</strong><br>';
        echo esc_html($meta_data['pickup_address']) . '<br>';

        if (!empty($meta_data['pickup_city'])) {
            echo esc_html($meta_data['pickup_city']);

            if (!empty($meta_data['pickup_postal_code'])) {
                echo ', ' . esc_html($meta_data['pickup_postal_code']);
            }
        }

        echo '</div>';
    }
}

// Add selected pickup location to order meta
add_action('woocommerce_checkout_create_order', 'save_pickup_location_to_order', 10, 2);

function save_pickup_location_to_order($order, $data)
{
    $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');

    if (empty($chosen_shipping_methods)) {
        return;
    }

    foreach ($chosen_shipping_methods as $shipping_method) {
        if (strpos($shipping_method, 'custom_shipping') === false || strpos($shipping_method, '_pickup_') === false) {
            continue;
        }

        // Extract pickup ID from the method ID
        $parts = explode('_pickup_', $shipping_method);
        if (isset($parts[1]) && is_numeric($parts[1])) {
            $pickup_id = intval($parts[1]);

            global $wpdb;
            $table_name = $wpdb->prefix . 'wc_custom_shipping_pickups';
            $location = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d",
                $pickup_id
            ));

            if ($location) {
                $order->update_meta_data('_pickup_location_id', $pickup_id);
                $order->update_meta_data('_pickup_location_name', $location->location_name);
                $order->update_meta_data('_pickup_location_address', $location->address);
                $order->update_meta_data('_pickup_location_city', $location->city);
                $order->update_meta_data('_pickup_location_postal_code', $location->postal_code);
            }

            break;
        }
    }
}

// Display pickup location in order admin page
add_action('woocommerce_admin_order_data_after_shipping_address', 'display_pickup_location_in_admin', 10, 1);

function display_pickup_location_in_admin($order)
{
    $pickup_location_name = $order->get_meta('_pickup_location_name');
    $pickup_location_address = $order->get_meta('_pickup_location_address');

    if (!empty($pickup_location_name) && !empty($pickup_location_address)) {
        echo '<div class="pickup-location">';
        echo '<h4>' . __('Pickup Location', 'wc-custom-shipping') . '</h4>';
        echo '<p><strong>' . esc_html($pickup_location_name) . '</strong><br>';
        echo esc_html($pickup_location_address) . '<br>';

        $city = $order->get_meta('_pickup_location_city');
        $postal_code = $order->get_meta('_pickup_location_postal_code');

        if (!empty($city)) {
            echo esc_html($city);

            if (!empty($postal_code)) {
                echo ', ' . esc_html($postal_code);
            }
        }

        echo '</p></div>';
    }
}

// Add pickup location to order emails
add_action('woocommerce_email_after_shipping_address', 'display_pickup_location_in_email', 10, 1);

function display_pickup_location_in_email($order)
{
    $pickup_location_name = $order->get_meta('_pickup_location_name');
    $pickup_location_address = $order->get_meta('_pickup_location_address');

    if (!empty($pickup_location_name) && !empty($pickup_location_address)) {
        echo '<div class="pickup-location">';
        echo '<h2>' . __('Pickup Location', 'wc-custom-shipping') . '</h2>';
        echo '<p><strong>' . esc_html($pickup_location_name) . '</strong><br>';
        echo esc_html($pickup_location_address) . '<br>';

        $city = $order->get_meta('_pickup_location_city');
        $postal_code = $order->get_meta('_pickup_location_postal_code');

        if (!empty($city)) {
            echo esc_html($city);

            if (!empty($postal_code)) {
                echo ', ' . esc_html($postal_code);
            }
        }

        echo '</p></div>';
    }
}
