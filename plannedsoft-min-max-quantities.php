<?php
/**
 * Plugin Name: Plannedsoft Min/Max Quantities
 * Plugin URI: https://plannedsoft.com/
 * Description: Create dynamic minimum/maximum allowed quantities for products per user group.
 * Version: 1.0.0
 * Author: Plannedsoft
 * Author URI: https://plannedsoft.com/
 * Requires at least: 5.6
 * Tested up to: 5.8
 * WC tested up to: 4.5
 * WC requires at least: 5.6
 *
 * Text Domain: woocommerce-min-max-quantities
 * Domain Path: /languages
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package woocommerce-min-max-quantities
 */

// Make sure WooCommerce is active

require_once ABSPATH . 'wp-admin/includes/plugin.php';

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', 'plannedsoft_install_woocommerce_admin_notice');
    deactivate_plugins(plugin_basename(__FILE__), true);
    return;
}

function plannedsoft_install_woocommerce_admin_notice()
{
    ?>
<div class="error">
    <p><?php esc_html_e('Plannedsoft Min/Max Quantities Plugin requires WooCommerce plugin active in order to work.', 'plannedsoft-core');?>
    </p>
</div>
<?php
}

if (!function_exists('plannedsoft_core_init')) {
    require_once plugin_dir_path(__FILE__) . 'plannedsoft-core/plannedsoft-core.php';
}

if (!class_exists('PS_Min_Max_Quantities')):

    define('PS_MIN_MAX_QUANTITIES', '2.4.18'); // WRCS: DEFINED_VERSION.

    /**
     * Min Max Quantities class.
     */
    class PS_Min_Max_Quantities
{

        /**
         * Minimum order quantity.
         *
         * @var int
         */
        public $minimum_order_quantity;

        /**
         * Maximum order quantity.
         *
         * @var int
         */
        public $maximum_order_quantity;

        /**
         * Minimum order value.
         *
         * @var int
         */
        public $minimum_order_value;

        /**
         * Maximum order value.
         *
         * @var int
         */
        public $maximum_order_value;

        /**
         * List of excluded product titles.
         *
         * @var array
         */
        public $excludes = array();

        /**
         * Instance of addons class.
         *
         * @var PS_Min_Max_Quantities_Addons
         */
        public $addons;

        /**
         * Class instance.
         *
         * @var object
         */
        private static $instance;

        /**
         * Get the class instance.
         */
        public static function get_instance()
    {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * Constructor.
         */
        public function __construct()
    {
            if (!class_exists('WooCommerce')) {
                return;
            }

            /**
             * Localisation.
             */
            $this->load_plugin_textdomain();

            if (is_admin()) {
                include_once __DIR__ . '/includes/class-wc-min-max-quantities-admin.php';
            }

            include_once __DIR__ . '/includes/class-wc-min-max-quantities-addons.php';

            $this->addons = new PS_Min_Max_Quantities_Addons();

            // get correct global rule to apply
            $global_rule_id_to_apply = $this->get_global_rule_id_to_apply();

            $this->minimum_order_quantity = absint(get_option($global_rule_id_to_apply . '_woocommerce_minimum_order_quantity'));
            $this->maximum_order_quantity = absint(get_option($global_rule_id_to_apply . '_woocommerce_maximum_order_quantity'));
            $this->minimum_order_value = absint(get_option($global_rule_id_to_apply . '_woocommerce_minimum_order_value'));
            $this->maximum_order_value = absint(get_option($global_rule_id_to_apply . '_woocommerce_maximum_order_value'));

            // Check items.
            add_action('woocommerce_check_cart_items', array($this, 'check_cart_items'));

            // Quantity selelectors (2.0+).
            add_filter('woocommerce_quantity_input_args', array($this, 'update_quantity_args'), 100, 2);
            add_filter('woocommerce_available_variation', array($this, 'available_variation'), 10, 3);

            // Prevent add to cart.
            add_filter('woocommerce_add_to_cart_validation', array($this, 'add_to_cart'), 10, 4);

            // Min add to cart ajax.
            add_filter('woocommerce_loop_add_to_cart_link', array($this, 'add_to_cart_link'), 100, 2);

            // Show a notice when items would have to be on back order because of min/max.
            add_filter('woocommerce_get_availability', array($this, 'maybe_show_backorder_message'), 10, 2);

            add_action('wp_enqueue_scripts', array($this, 'load_scripts'));

            // admin javascript
            add_action('admin_enqueue_scripts', array($this, 'load_admin_scripts'));

            // wordpress admin menu
            add_action('admin_menu', array($this, 'register_admin_menu'));

            // For plannedsoft Core Plugin Menu
            add_filter('plannedsoft_core_admin_menu_items', function ($items) {

                $items[] = array(
                    'name' => __('Min/Max Quantity', 'woocommerce-min-max-quantities'),
                    'url' => menu_page_url('minmax-quantity-rules', false),
                    'icon' => 'ti-settings',
                    'class' => isset($_GET['page']) && $_GET['page'] == 'minmax-quantity-rules' ? 'menu-active' : '',
                    'priority' => 20,
                );
                return $items;
            });

            // ajax handler
            add_action('wp_ajax_add_new_global_minmax_rule', array($this, 'ajax_add_new_global_minmax_rule_field'));
        }

        public function get_global_rule_id_to_apply()
    {
            $default_rule_id = 0;
            $minmax_rules = get_option('wc_minmax_rules');
            if (is_array($minmax_rules)) {
                foreach ($minmax_rules as $rule_id => $rule) {
                    if (isset($rule['woocommerce_minmax_applies_to'])) {
                        $applies_to = $rule['woocommerce_minmax_applies_to'];

                        if ($applies_to == 'unauthenticated') {
                            // check if current user is a guest
                            if (!is_user_logged_in()) {
                                return $rule_id;
                            }
                        } else if ($applies_to == 'roles') {
                        if (isset($rule['woocommerce_minmax_applies_to_roles'])) {
                            $applies_to_roles = $rule['woocommerce_minmax_applies_to_roles'];
                            if (is_array($applies_to_roles) && count($applies_to_roles) > 0) {
                                $user = wp_get_current_user();
                                if (array_intersect($applies_to_roles, (array) $user->roles)) {
                                    return $rule_id;
                                }
                            }
                        }
                    } else if ($applies_to == 'everyone') {
                        return $rule_id;
                    }
                }
            }
        }

        return $default_rule_id;
    }

    public function ajax_add_new_global_minmax_rule_field()
    {
        if (!isset($_POST['woocommerce_min_max_quantities_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['woocommerce_min_max_quantities_nonce']), 'woocommerce_min_max_quantities_action')) {
            exit("Sorry, You are not verified.");
        }

        if (isset($_POST['rule_id'])) {
            $this->cart_level_single_minmax_fields(sanitize_text_field($_POST['rule_id']));
        }
        wp_die();
    }

    public function register_admin_menu()
    {
        add_submenu_page('plannedsoft-core', __('Min/Max Quantity', 'woocommerce-min-max-quantities'), __('Min/Max Quantity', 'woocommerce-min-max-quantities'), 'manage_options', 'minmax-quantity-rules', array($this, 'render_minmax_page'));
    }

    // single cart level minmax rule field
    public function cart_level_single_minmax_fields($rule_id = 1)
    {
        ?>
<div class="single-minmax-section" id="minmax-rule-<?php echo esc_attr($rule_id); ?>">
    <?php
if (!isset($wp_roles)) {
            $wp_roles = new WP_Roles();
        }
        $all_roles = $wp_roles->roles;

        $all_rules = get_option('wc_minmax_rules');
        $applied_to = isset($all_rules[$rule_id]) && isset($all_rules[$rule_id]['woocommerce_minmax_applies_to']) ? $all_rules[$rule_id]['woocommerce_minmax_applies_to'] : 'everyone';
        $div_style = ($applied_to == 'roles') ? '' : 'display:none;';
        $rolesarray = isset($all_rules[$rule_id]) && isset($all_rules[$rule_id]['woocommerce_minmax_applies_to_roles']) ? $all_rules[$rule_id]['woocommerce_minmax_applies_to_roles'] : array();

        // show close button if not the first rule
        if ($rule_id != 1) {
            ?>
    <div class="single-global-minmax-close">
        <img src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'assets/images/delete.png'); ?>" alt="">
    </div>
    <?php
}
        ?>

    <p class="form-field">
        <label><?php esc_html_e('Applies To:', 'woocommerce-min-max-quantities');?>
            <span class="woocommerce-help-tip"
                data-tip="<?php echo esc_attr(__('Choose the audience you want this rule to be applied', 'woocommerce-min-max-quantities')); ?>"></span>
        </label>

        <select name="wc_minmax_rules[<?php echo esc_attr($rule_id); ?>][woocommerce_minmax_applies_to]"
            title="<?php echo esc_attr(__('Choose if this rule should apply to everyone, or to specific roles. Useful if you only give discounts to existing customers, or if you have tiered pricing based on the users role.', 'woocommerce-min-max-quantities')); ?>"
            class="pricing_rule_apply_to" id="product_minmax_apply_to">
            <option <?php selected('everyone', $applied_to);?> value="everyone">
                <?php esc_html_e('Everyone', 'woocommerce-min-max-quantities');?></option>
        </select>
    </p>

    <div id="roles-section" class="roles section" style="<?php echo esc_attr($div_style); ?>">
        <p class="form-field">
            <label><?php esc_html_e('Roles:', 'woocommerce-min-max-quantities');?>
                <span class="woocommerce-help-tip"
                    data-tip="<?php echo esc_attr(__('Choose the roles you want this rule to be applied.', 'woocommerce-min-max-quantities')); ?>"></span>
            </label>
            <select style="width:50%;"
                name="wc_minmax_rules[<?php echo esc_attr($rule_id); ?>][woocommerce_minmax_applies_to_roles][]"
                class="multiselect wc-enhanced-select" multiple="multiple">
                <?php foreach ($all_roles as $role_id => $role): ?>
                <?php $role_checked = is_array($rolesarray) && count($rolesarray) > 0 && in_array($role_id, $rolesarray);?>
                <option <?php selected($role_checked);?> value="<?php esc_attr_e($role_id);?>">
                    <?php esc_html_e($role['name']);?></option>
                <?php endforeach;?>
            </select>
        </p>
    </div>
    <p class="form-field">
        <label><?php esc_html_e('Minimum Order Qty:', 'woocommerce-min-max-quantities');?>
            <span class="woocommerce-help-tip"
                data-tip="<?php echo esc_attr(__('The minimum allowed quantity of items in an order.', 'woocommerce-min-max-quantities')); ?>"></span>
        </label>
        <input min="0" type="number" name="<?php echo esc_attr($rule_id); ?>_woocommerce_minimum_order_quantity" id=""
            value="<?php echo esc_attr(get_option($rule_id . '_woocommerce_minimum_order_quantity', '')); ?>">
    </p>
    <p class="form-field">
        <label><?php esc_html_e('Maximum Order Qty:', 'woocommerce-min-max-quantities');?>
            <span class="woocommerce-help-tip"
                data-tip="<?php echo esc_attr(__('The maximum allowed quantity of items in an order.', 'woocommerce-min-max-quantities')); ?>"></span>
        </label>
        <input min="0" type="number" name="<?php echo esc_attr($rule_id); ?>_woocommerce_maximum_order_quantity" id=""
            value="<?php echo esc_attr(get_option($rule_id . '_woocommerce_maximum_order_quantity', '')); ?>">
    </p>

    <div class="clear"></div>
</div>
<?php
}

    // content of the cart level minmax rule settings page
    public function render_minmax_page()
    {
        do_action('plannedsoft_core_admin_page_scripts');
        do_action('plannedsoft_core_admin_page_top');
        wp_enqueue_style('woocommerce_admin_styles', WC()->plugin_url() . '/assets/css/admin.css');
        wp_enqueue_script('select2');
        wp_enqueue_script('jquery-tiptip');

        // handle save
        if (isset($_POST['wc_minmax_rules'])) {

            if (!isset($_POST['woocommerce_min_max_quantities_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['woocommerce_min_max_quantities_nonce']), 'woocommerce_min_max_quantities_action')) {
                exit("Sorry, You are not verified.");
            }

            $_POST = array_map('stripslashes_deep', $_POST); //delete all auto added backslashes

            $postdata = $_POST;
            if (isset($postdata['wc_minmax_rules'])) {
                update_option('wc_minmax_rules', $postdata['wc_minmax_rules']);
            }

            if (is_array($_POST['wc_minmax_rules']) && count($_POST['wc_minmax_rules']) > 0) {

                foreach ($postdata['wc_minmax_rules'] as $rule_id => $rule) {
                    if (isset($_POST[$rule_id . '_woocommerce_minimum_order_quantity'])) {
                        update_option($rule_id . '_woocommerce_minimum_order_quantity', sanitize_text_field($_POST[$rule_id . '_woocommerce_minimum_order_quantity']));
                    }
                    if (isset($_POST[$rule_id . '_woocommerce_maximum_order_quantity'])) {
                        update_option($rule_id . '_woocommerce_maximum_order_quantity', sanitize_text_field($_POST[$rule_id . '_woocommerce_maximum_order_quantity']));
                    }
                    if (isset($_POST[$rule_id . '_woocommerce_minimum_order_value'])) {
                        update_option($rule_id . '_woocommerce_minimum_order_value', sanitize_text_field($_POST[$rule_id . '_woocommerce_minimum_order_value']));
                    }
                    if (isset($_POST[$rule_id . '_woocommerce_maximum_order_value'])) {
                        update_option($rule_id . '_woocommerce_maximum_order_value', sanitize_text_field($_POST[$rule_id . '_woocommerce_maximum_order_value']));
                    }
                }
            }
        }

        ?>

<form action="" method="post">

    <div class="woocommerce_options_panel">
        <h2><?php esc_html_e('Min/Max Quantity', 'woocommerce-min-max-quantities');?></h2>
        <div class="minmax-section-wrapper">
            <?php
if (isset($_POST['wc_minmax_rules'])) {
            ?>
            <div class="notice notice-success is-dismissible">
                <?php esc_html_e('Rules saved successfully.', 'woocommerce-min-max-quantities');?>
            </div>
            <?php
}
        ?>

            <?php
$minmax_rules = get_option('wc_minmax_rules');
        if (is_array($minmax_rules) && count($minmax_rules) > 0) {
            foreach ($minmax_rules as $rule_id => $rule) {
                $this->cart_level_single_minmax_fields($rule_id);
            }
        } else {
            $this->cart_level_single_minmax_fields();
        }
        ?>


        </div>
    </div>
    <?php wp_nonce_field('woocommerce_min_max_quantities_action', 'woocommerce_min_max_quantities_nonce');?>
    <?php submit_button(__('Save', 'woocommerce-min-max-quantities'), 'primary');?>
</form>

<style>
.single-minmax-section {
    background-color: #e4e4e4;
    margin: 10px 0;
    padding: 10px;
}

button.button.button-primary.addnew-global-minmax {
    float: right;
    margin: 0px 10px 10px 0;
}

.single-global-minmax-close {
    display: inline;
    float: right;
    cursor: pointer;
}

.woocommerce-help-tip {
    position: absolute !important;
    right: 7px;
    top: 7px;
}

.single-minmax-section label {
    position: relative;
}

.minmax-section-wrapper .notice-info,
.minmax-section-wrapper .notice-success {
    margin-left: 0;
    padding: 11px;
    margin-right: 0;
}
</style>

<?php
do_action('plannedsoft_core_admin_page_bottom');

    }

    public function load_admin_scripts()
    {
        wp_enqueue_script('wc_min_max_admin_js', plugin_dir_url(__FILE__) . 'assets/admin/admin-all.js', array('jquery'));
        wp_enqueue_script('jquery-ui-sortable');
    }

    /**
     * Load scripts.
     */
    public function load_scripts()
    {
        // Only load on single product page and cart page.
        if (is_product() || is_cart()) {
            wc_enqueue_js(
                "
					jQuery( 'body' ).on( 'show_variation', function( event, variation ) {
						const step = 'undefined' !== typeof variation.step ? variation.step : 1;
						jQuery( 'form.variations_form' ).find( 'input[name=quantity]' ).prop( 'step', step ).val( variation.input_value );
					});
					"
            );
        }
    }

    /**
     * Load Localisation files.
     *
     * Note: the first-loaded translation file overrides any following ones if the same translation is present.
     *
     * Frontend/global Locales found in:
     * - WP_LANG_DIR/woocommerce-min-max-quantities/woocommerce-min-max-quantities-LOCALE.mo
     * - woocommerce-min-max-quantities/woocommerce-min-max-quantities-LOCALE.mo (which if not found falls back to:)
     * - WP_LANG_DIR/plugins/woocommerce-min-max-quantities-LOCALE.mo
     */
    public function load_plugin_textdomain()
    {
        $locale = apply_filters('plugin_locale', get_locale(), 'woocommerce-min-max-quantities');

        load_textdomain('woocommerce-min-max-quantities', WP_LANG_DIR . '/woocommerce-min-max-quantities/woocommerce-min-max-quantities-' . $locale . '.mo');
        load_plugin_textdomain('woocommerce-min-max-quantities', false, plugin_basename(dirname(__FILE__)) . '/');
    }

    /**
     * Add an error.
     *
     * @since 1.0.0
     * @version 2.3.18
     * @param string $error Error text.
     */
    public function add_error($error = '')
    {
        if ($error && !wc_has_notice($error, 'error')) {
            wc_add_notice($error, 'error');
        }
    }

    /**
     * Add quantity property to add to cart button on shop loop for simple products.
     *
     * @param  string     $html    Add to cart link.
     * @param  WC_Product $product Product object.
     * @return string
     */
    public function add_to_cart_link($html, $product)
    {

        $rule_id_to_apply = $this->get_rule_id_to_apply($product->get_id());
        if ($rule_id_to_apply) {
            // only apply to products that are not variable..because variable products are added to cart using form..so the button does not have <a data-quantity
            if ('variable' !== $product->get_type()) {
                $quantity_attribute = 1;
                $minimum_quantity = absint(get_post_meta($product->get_id(), $rule_id_to_apply . '_minimum_allowed_quantity', true));
                $group_of_quantity = absint(get_post_meta($product->get_id(), $rule_id_to_apply . '_group_of_quantity', true));

                if ($minimum_quantity || $group_of_quantity) {

                    $quantity_attribute = $minimum_quantity;

                    if ($group_of_quantity > 0 && $minimum_quantity < $group_of_quantity) {
                        $quantity_attribute = $group_of_quantity;
                    }

                    $html = str_replace('<a ', '<a data-quantity="' . $quantity_attribute . '" ', $html);
                }
            }
        }

        return $html;
    }

    /**
     * Get product or variation ID to check
     *
     * @param array $values List of values.
     * @return int
     */
    public function get_id_to_check($values)
    {
        $checking_id = $values['product_id'];
        return $checking_id;
    }

    public function get_rule_id_to_apply($product_id)
    {
        $default_rule_id = false;
        $minmax_rules = get_post_meta($product_id, 'product_minmax_rules', true);
        if (is_array($minmax_rules)) {
            foreach ($minmax_rules as $rule_id => $rule) {
                if (isset($rule['product_minmax_apply_to'])) {
                    $applies_to = $rule['product_minmax_apply_to'];

                    if ($applies_to == 'unauthenticated') {
                        // check if current user is a guest
                        if (!is_user_logged_in()) {
                            return $rule_id;
                        }
                    }

                    if ($applies_to == 'roles') {
                        if (isset($rule['product_minmax_applied_roles_to'])) {
                            $applies_to_roles = $rule['product_minmax_applied_roles_to'];
                            if (is_array($applies_to_roles) && count($applies_to_roles) > 0) {
                                $user = wp_get_current_user();
                                if (array_intersect($applies_to_roles, (array) $user->roles)) {
                                    return $rule_id;
                                }
                            }
                        }
                    }

                    if ($applies_to == 'everyone') {
                        return $rule_id;
                    }
                }
            }
        }

        return $default_rule_id;
    }

    /**
     * Validate cart items against set rules
     */
    public function check_cart_items()
    {
        $checked_ids = array();
        $product_quantities = array();
        $category_quantities = array();
        $total_quantity = 0;
        $total_cost = 0;
        $apply_cart_rules = false;

        // Count items + variations first.
        foreach (WC()->cart->get_cart() as $cart_item_key => $values) {
            $product = $values['data'];
            $checking_id = $this->get_id_to_check($values);

            // find the correct rule to apply for this user
            $rule_id_to_apply = $this->get_rule_id_to_apply($checking_id);

            if (!$rule_id_to_apply) {
                $rule_id_to_apply = 'norule';
            }

            if ($rule_id_to_apply) {
                if (apply_filters('wc_min_max_cart_quantity_do_not_count', false, $checking_id, $cart_item_key, $values)) {
                    $values['quantity'] = 0;
                }

                if (!isset($product_quantities[$checking_id])) {
                    $product_quantities[$checking_id] = $values['quantity'];
                } else {
                    $product_quantities[$checking_id] += $values['quantity'];
                }

                // Do_not_count and cart_exclude from variation or product.
                $minmax_do_not_count = apply_filters('wc_min_max_quantity_minmax_do_not_count', (get_post_meta($values['product_id'], $rule_id_to_apply . '_minmax_do_not_count', true)), $checking_id, $cart_item_key, $values);

                $minmax_cart_exclude = apply_filters('wc_min_max_quantity_minmax_cart_exclude', (get_post_meta($values['product_id'], $rule_id_to_apply . '_minmax_cart_exclude', true)), $checking_id, $cart_item_key, $values);

                if ('yes' !== $minmax_do_not_count && 'yes' !== $minmax_cart_exclude) {
                    $total_cost += $product->get_price() * $values['quantity'];
                }
            }

        }

        // Check cart items.
        foreach (WC()->cart->get_cart() as $cart_item_key => $values) {
            $checking_id = $this->get_id_to_check($values); //returns product_id
            $terms = get_the_terms($values['product_id'], 'product_cat');
            $found_term_ids = array();
            $product = $values['data'];

            // find the correct rule to apply for this user
            $rule_id_to_apply = $this->get_rule_id_to_apply($checking_id);

            if (!$rule_id_to_apply) {
                $rule_id_to_apply = 'norule';
            }

            if ($rule_id_to_apply) {
                // $rules = get_post_meta( $checking_id, 'product_minmax_rules', true );
                // $rule = $rules[$rule_id_to_apply];
                if ($terms) {

                    foreach ($terms as $term) {

                        if ('yes' === get_post_meta($checking_id, $rule_id_to_apply . '_minmax_category_group_of_exclude', true)) {
                            continue;
                        }

                        if (in_array($term->term_id, $found_term_ids, true)) {
                            continue;
                        }

                        $found_term_ids[] = $term->term_id;
                        $category_quantities[$term->term_id] = isset($category_quantities[$term->term_id]) ? $category_quantities[$term->term_id] + $values['quantity'] : $values['quantity'];

                        // Record count in parents of this category too.
                        $parents = get_ancestors($term->term_id, 'product_cat');

                        foreach ($parents as $parent) {
                            if (in_array($parent, $found_term_ids, true)) {
                                continue;
                            }

                            $found_term_ids[] = $parent;
                            $category_quantities[$parent] = isset($category_quantities[$parent]) ? $category_quantities[$parent] + $values['quantity'] : $values['quantity'];
                        }
                    }
                }

                // Check item rules once per product ID.
                if (in_array($checking_id, $checked_ids, true)) {
                    continue;
                }

                // Do_not_count and cart_exclude from variation or product.
                $minmax_do_not_count = apply_filters('wc_min_max_quantity_minmax_do_not_count', (get_post_meta($values['product_id'], $rule_id_to_apply . '_minmax_do_not_count', true)), $checking_id, $cart_item_key, $values);

                $minmax_cart_exclude = apply_filters('wc_min_max_quantity_minmax_cart_exclude', (get_post_meta($values['product_id'], $rule_id_to_apply . '_minmax_cart_exclude', true)), $checking_id, $cart_item_key, $values);

                if ('yes' === $minmax_do_not_count || 'yes' === $minmax_cart_exclude) {
                    // Do not count.
                    $this->excludes[] = $product->get_title();

                } else {
                    $total_quantity += $product_quantities[$checking_id];
                }

                if ('yes' !== $minmax_cart_exclude) {
                    $apply_cart_rules = true;
                }

                $checked_ids[] = $checking_id;

                $minimum_quantity = absint(apply_filters('wc_min_max_quantity_minimum_allowed_quantity', get_post_meta($checking_id, $rule_id_to_apply . '_minimum_allowed_quantity', true), $checking_id, $cart_item_key, $values));

                $maximum_quantity = absint(apply_filters('wc_min_max_quantity_maximum_allowed_quantity', get_post_meta($checking_id, $rule_id_to_apply . '_maximum_allowed_quantity', true), $checking_id, $cart_item_key, $values));

                $group_of_quantity = absint(apply_filters('wc_min_max_quantity_group_of_quantity', get_post_meta($checking_id, $rule_id_to_apply . '_group_of_quantity', true), $checking_id, $cart_item_key, $values));

                // this is for showing product level error
                $this->check_rules($product, $product_quantities[$checking_id], $minimum_quantity, $maximum_quantity, $group_of_quantity);
            }

        }

        // this is for showing cart level error
        if ($apply_cart_rules) {

            $excludes = '';

            if (count($this->excludes) > 0) {
                $excludes = ' (' . __('excludes ', 'woocommerce-min-max-quantities') . implode(', ', $this->excludes) . ')';
            }

            if ($this->minimum_order_quantity > 0 && $total_quantity < $this->minimum_order_quantity) {
                /* translators: %d: Minimum amount of items in the cart */
                $this->add_error(sprintf(__('The minimum required items in cart is %d. Please add more items to your cart', 'woocommerce-min-max-quantities'), $this->minimum_order_quantity) . $excludes);

                return;

            }

            if ($this->maximum_order_quantity > 0 && $total_quantity > $this->maximum_order_quantity) {
                /* translators: %d: Maximum amount of items in the cart */
                $this->add_error(sprintf(__('The maximum allowed order quantity is %d. Please remove some items from your cart.', 'woocommerce-min-max-quantities'), $this->maximum_order_quantity));

                return;

            }

            // Check cart value.
            if ($this->minimum_order_value && $total_cost && $total_cost < $this->minimum_order_value) {
                /* translators: %s: Minimum order value */
                $this->add_error(sprintf(__('The minimum required order value is %s. Please add more items to your cart', 'woocommerce-min-max-quantities'), wc_price($this->minimum_order_value)) . $excludes);

                return;
            }

            if ($this->maximum_order_value && $total_cost && $total_cost > $this->maximum_order_value) {
                /* translators: %s: Maximum order value */
                $this->add_error(sprintf(__('The maximum allowed order value is %s. Please remove some items from your cart.', 'woocommerce-min-max-quantities'), wc_price($this->maximum_order_value)));

                return;
            }

        }

        // Check category rules.
        foreach ($category_quantities as $category => $quantity) {

            $group_of_quantity = intval(version_compare(WC_VERSION, '3.6', 'ge') ? get_term_meta($category, 'group_of_quantity', true) : get_woocommerce_term_meta($category, 'group_of_quantity', true));

            if ($group_of_quantity > 0 && (intval($quantity) % intval($group_of_quantity) > 0)) {

                $term = get_term_by('id', $category, 'product_cat');
                $product_names = array();

                foreach (WC()->cart->get_cart() as $cart_item_key => $values) {

                    // find the correct rule to apply for this user
                    $rule_id_to_apply = $this->get_rule_id_to_apply($values['product_id']);

                    if (!$rule_id_to_apply) {
                        $rule_id_to_apply = 'norule';
                    }

                    if ($rule_id_to_apply) {
                        // If exclude is enable, skip.
                        if ('yes' === get_post_meta($values['product_id'], $rule_id_to_apply . '_minmax_category_group_of_exclude', true)) {
                            continue;
                        }

                        if (has_term($category, 'product_cat', $values['product_id'])) {
                            $product_names[] = $values['data']->get_title();
                        }
                    }

                }

                if ($product_names) {
                    /* translators: %1$s: Category name, %2$s: Comma separated list of product names, %3$d: Group amount */
                    $this->add_error(sprintf(__('Items in the <strong>%1$s</strong> category (<em>%2$s</em>) must be bought in groups of %3$d. Please add or remove the items to continue.', 'woocommerce-min-max-quantities'), $term->name, implode(', ', $product_names), $group_of_quantity, $group_of_quantity - ($quantity % $group_of_quantity)));
                    return;
                }
            }
        }
    }

    /**
     * If the minimum allowed quantity for purchase is lower then the current stock, we need to
     * let the user know that they are on backorder, or out of stock.
     *
     * @param array      $args    List of arguments.
     * @param WC_Product $product Product object.
     */
    public function maybe_show_backorder_message($args, $product)
    {
        if (!$product->managing_stock()) {
            return $args;
        }

        // Figure out what our minimum_quantity is.
        $product_id = $product->get_id();

        $rule_id_to_apply = $this->get_rule_id_to_apply($product_id);
        if ($rule_id_to_apply) {
            $minimum_quantity = absint(get_post_meta($product_id, $rule_id_to_apply . '_minimum_allowed_quantity', true));

            // If the minimum quantity allowed for purchase is smaller then the amount in stock, we need
            // clearer messaging.
            if ($minimum_quantity > 0 && $product->get_stock_quantity() < $minimum_quantity) {
                if ($product->backorders_allowed()) {
                    return array(
                        'availability' => __('Available on backorder', 'woocommerce-min-max-quantities'),
                        'class' => 'available-on-backorder',
                    );
                } else {
                    return array(
                        'availability' => __('Out of stock', 'woocommerce-min-max-quantities'),
                        'class' => 'out-of-stock',
                    );
                }
            }
        }

        return $args;
    }

    /**
     * Add respective error message(product level) depending on rules checked.
     *
     * @param WC_Product $product           Product object.
     * @param int        $quantity          Quantity to check.
     * @param int        $minimum_quantity  Minimum quantity.
     * @param int        $maximum_quantity  Maximum quanitty.
     * @param int        $group_of_quantity Group quantity.
     * @return void
     */
    public function check_rules($product, $quantity, $minimum_quantity, $maximum_quantity, $group_of_quantity)
    {
        $parent_id = $product->get_id();

        $allow_combination = 'yes' === get_post_meta($parent_id, 'allow_combination', true);

        if ($minimum_quantity > 0 && $quantity < $minimum_quantity) {

            /* translators: %1$s: Product name, %2$s: Minimum order quantity */
            $this->add_error(sprintf(__('The minimum order quantity for %1$s is %2$s - please increase the quantity in your cart.', 'woocommerce-min-max-quantities'), $product->get_title(), $minimum_quantity));

        } elseif ($maximum_quantity > 0 && $quantity > $maximum_quantity) {

            /* translators: %1$s: Product name, %2$s: Maximum order quantity */
            $this->add_error(sprintf(__('The maximum allowed quantity for %1$s is %2$s - please decrease the quantity in your cart.', 'woocommerce-min-max-quantities'), $product->get_title(), $maximum_quantity));

        }

        if ($group_of_quantity > 0 && (intval($quantity) % intval($group_of_quantity) > 0)) {
            /* translators: %1$s: Product name, %2$d: Group amount */
            $this->add_error(sprintf(__('%1$s must be bought in groups of %2$d. Please add or decrease items to continue.', 'woocommerce-min-max-quantities'), $product->get_title(), $group_of_quantity, $group_of_quantity - ($quantity % $group_of_quantity)));
        }
    }

    /**
     * Add to cart validation
     *
     * @param  mixed $pass         Filter value.
     * @param  mixed $product_id   Product ID.
     * @param  mixed $quantity     Quantity.
     * @param  int   $variation_id Variation ID (default none).
     * @return mixed
     */
    public function add_to_cart($pass, $product_id, $quantity, $variation_id = 0)
    {
        $rule_for_variaton = false;

        $allow_combination = 'yes' === get_post_meta($product_id, 'allow_combination', true);

        if (0 < $variation_id && $allow_combination) {
            return $pass;
        }

        // Product level.
        // check current user exist on the rule or not

        $rule_id_to_apply = $this->get_rule_id_to_apply($product_id);

        if ($rule_id_to_apply) {
            $maximum_quantity = absint(get_post_meta($product_id, $rule_id_to_apply . '_maximum_allowed_quantity', true));
            $minimum_quantity = absint(get_post_meta($product_id, $rule_id_to_apply . '_minimum_allowed_quantity', true));

            $total_quantity = $quantity;

            // Count items.
            foreach (WC()->cart->get_cart() as $cart_item_key => $values) {

                $checking_id = $values['product_id'];

                if (apply_filters('wc_min_max_cart_quantity_do_not_count', false, $checking_id, $cart_item_key, $values)) {
                    continue;
                }

                if ($values['product_id'] === $product_id) {

                    $total_quantity += $values['quantity'];
                }
            }

            if (isset($maximum_quantity) && $maximum_quantity > 0) {
                if ($total_quantity > 0 && $total_quantity > $maximum_quantity) {

                    $_product = wc_get_product($product_id);

                    /* translators: %1$s: Product name, %2$d: Maximum quantity, %3$s: Currenty quantity */
                    $message = sprintf(__('The maximum allowed quantity for %1$s is %2$d (you currently have %3$s in your cart).', 'woocommerce-min-max-quantities'), $_product->get_title(), $maximum_quantity, $total_quantity - $quantity);

                    // If quantity requirement is met, show cart link.
                    if (intval($maximum_quantity) <= intval($total_quantity - $quantity)) {
                        /* translators: %1$s: Product name, %2$d: Maximum quantity, %3$s: Currenty quantity, %4$s: Cart link */
                        $message = sprintf(__('The maximum allowed quantity for %1$s is %2$d (you currently have %3$s in your cart). <a href="%4$s" class="woocommerce-min-max-quantities-error-cart-link button wc-forward">View cart</a>', 'woocommerce-min-max-quantities'), $_product->get_title(), $maximum_quantity, $total_quantity - $quantity, esc_url(wc_get_cart_url()));
                    }

                    $this->add_error($message);

                    $pass = false;
                }
            }

            if (isset($minimum_quantity) && $minimum_quantity > 0) {
                if ($total_quantity < $minimum_quantity) {

                    $_product = wc_get_product($product_id);

                    /* translators: %1$s: Product name, %2$d: Minimum quantity, %3$s: Currenty quantity */
                    $this->add_error(sprintf(__('The minimum allowed quantity for %1$s is %2$d (you currently have %3$s in your cart).', 'woocommerce-min-max-quantities'), $_product->get_title(), $minimum_quantity, $total_quantity - $quantity));

                    $pass = true;
                }
            }

            // If product level quantity are not set then check global order quantity.
            if (empty($maximum_quantity) && empty($minimum_quantity)) {
                $total_quantity = intval(WC()->cart->get_cart_contents_count() + $quantity);

                if ($this->maximum_order_quantity && $this->maximum_order_quantity > 0) {
                    if ($total_quantity > $this->maximum_order_quantity) {
                        if (0 === $total_quantity - $quantity) {
                            /* translators: %d: Maximum quantity in cart */
                            $this->add_error(sprintf(__('The maximum allowed items in cart is %d.', 'woocommerce-min-max-quantities'), $this->maximum_order_quantity));
                        } else {
                            /* translators: %1$d: Maximum quanity, %2$d: Current quantity */
                            $message = sprintf(__('The maximum allowed items in cart is %1$d (you currently have %2$d in your cart).', 'woocommerce-min-max-quantities'), $this->maximum_order_quantity, $total_quantity - $quantity);

                            if (intval($this->maximum_order_quantity) <= intval($total_quantity - $quantity)) {
                                /* translators: %1$d: Maximum quanity, %2$d: Current quantity, %3$s: Cart link */
                                $message = sprintf(__('The maximum allowed items in cart is %1$d (you currently have %2$d in your cart). <a href="%3$s" class="woocommerce-min-max-quantities-error-cart-link button wc-forward">View cart</a>', 'woocommerce-min-max-quantities'), $this->maximum_order_quantity, $total_quantity - $quantity, esc_url(wc_get_cart_url()));
                            }

                            $this->add_error($message);
                        }

                        $pass = false;
                    }
                }
            }
        }

        return $pass;
    }

    /**
     * Updates the quantity arguments.
     *
     * @param array      $data    List of data to update.
     * @param WC_Product $product Product object.
     * @return array
     */
    public function update_quantity_args($data, $product)
    {

        // Multiple shipping address product plugin compat
        // don't update the quantity args when on set multiple address page.
        if ($this->addons->is_multiple_shipping_address_page()) {
            return $data;
        }

        $allow_combination = 'yes' === get_post_meta(version_compare(WC_VERSION, '3.0', '<') ? $product->get_id() : $product->get_parent_id(), 'allow_combination', true);

        /*
         * If its a variable product and allow combination is enabled,
         * we don't need to set the quantity to default minimum.
         */
        if ($allow_combination && $product->is_type('variation')) {
            return $data;
        }

        $rule_id_to_apply = $this->get_rule_id_to_apply($product->get_id());

        if ($rule_id_to_apply) {

            $group_of_quantity = get_post_meta($product->get_id(), $rule_id_to_apply . '_group_of_quantity', true);
            $minimum_quantity = get_post_meta($product->get_id(), $rule_id_to_apply . '_minimum_allowed_quantity', true);
            $maximum_quantity = get_post_meta($product->get_id(), $rule_id_to_apply . '_maximum_allowed_quantity', true);

            if (isset($minimum_quantity) && $minimum_quantity) {

                if ($product->managing_stock() && !$product->backorders_allowed() && absint($minimum_quantity) > $product->get_stock_quantity()) {
                    $data['min_value'] = $product->get_stock_quantity();

                } else {
                    $data['min_value'] = $minimum_quantity;
                }
            }

            if (isset($maximum_quantity) && $maximum_quantity) {

                if ($product->managing_stock() && $product->backorders_allowed()) {
                    $data['max_value'] = $maximum_quantity;

                } elseif ($product->managing_stock() && absint($maximum_quantity) > $product->get_stock_quantity()) {
                    $data['max_value'] = $product->get_stock_quantity();

                } else {
                    $data['max_value'] = $maximum_quantity;
                }
            }

            if (isset($group_of_quantity) && $group_of_quantity) {
                $data['step'] = 1;

                // If both minimum and maximum quantity are set, make sure both are equally divisble by qroup of quantity.
                if ($maximum_quantity && $minimum_quantity) {

                    if (absint($maximum_quantity) % absint($group_of_quantity) === 0 && absint($minimum_quantity) % absint($group_of_quantity) === 0) {
                        $data['step'] = $group_of_quantity;

                    }
                } elseif (!$maximum_quantity || absint($maximum_quantity) % absint($group_of_quantity) === 0) {

                    $data['step'] = $group_of_quantity;
                }

                // Set a new minimum if group of is set but not minimum.
                if (!$minimum_quantity) {
                    $data['min_value'] = $group_of_quantity;
                }
            }

            // Don't apply for cart or checkout as cart/checkout form has qty already pre-filled.
            if (!is_cart() && !is_checkout()) {
                $data['input_value'] = !empty($minimum_quantity) ? $minimum_quantity : $data['input_value'];
            }
        }

        return $data;
    }

    /**
     * Adds variation min max settings to the localized variation parameters to be used by JS.
     *
     * @param array  $data      Available variation data.
     * @param object $product   Product object.
     * @param object $variation Variation object.
     * @return array $data
     */
    public function available_variation($data, $product, $variation)
    {
        $variation_id = (version_compare(WC_VERSION, '3.0', '<') && isset($variation->variation_id)) ? $variation->variation_id : $variation->get_id();

        $min_max_rules = get_post_meta($variation_id, 'min_max_rules', true);

        if ('no' === $min_max_rules || empty($min_max_rules)) {
            $min_max_rules = false;

        } else {
            $min_max_rules = true;

        }

        $rule_id_to_apply = $this->get_rule_id_to_apply($product->get_id());
        if ($rule_id_to_apply) {
            $minimum_quantity = get_post_meta($product->get_id(), $rule_id_to_apply . '_minimum_allowed_quantity', true);
            $maximum_quantity = get_post_meta($product->get_id(), $rule_id_to_apply . '_maximum_allowed_quantity', true);
            $group_of_quantity = get_post_meta($product->get_id(), $rule_id_to_apply . '_group_of_quantity', true);
            $allow_combination = 'yes' === get_post_meta($product->get_id(), 'allow_combination', true);

            if ($minimum_quantity) {

                if ($product->managing_stock() && $product->backorders_allowed() && absint($minimum_quantity) > $product->get_stock_quantity()) {
                    $data['min_qty'] = $product->get_stock_quantity();

                } else {
                    $data['min_qty'] = $minimum_quantity;
                }
            }

            if ($maximum_quantity) {

                if ($product->managing_stock() && $product->backorders_allowed()) {
                    $data['max_qty'] = $maximum_quantity;

                } elseif ($product->managing_stock() && absint($maximum_quantity) > $product->get_stock_quantity()) {
                    $data['max_qty'] = $product->get_stock_quantity();

                } else {
                    $data['max_qty'] = $maximum_quantity;
                }
            }

            if ($group_of_quantity) {
                $data['step'] = 1;

                // If both minimum and maximum quantity are set, make sure both are equally divisible by qroup of quantity.
                if ($maximum_quantity && $minimum_quantity) {

                    if (absint($maximum_quantity) % absint($group_of_quantity) === 0 && absint($minimum_quantity) % absint($group_of_quantity) === 0) {
                        $data['step'] = $group_of_quantity;

                    }
                } elseif (!$maximum_quantity || absint($maximum_quantity) % absint($group_of_quantity) === 0) {

                    $data['step'] = $group_of_quantity;
                }

                // Set the minimum only when minimum is not set.
                if (!$minimum_quantity) {
                    $data['min_qty'] = $group_of_quantity;
                }
            }

            // Don't apply for cart as cart has qty already pre-filled.
            if (!is_cart()) {
                if (!$minimum_quantity && $group_of_quantity) {
                    $data['input_value'] = $group_of_quantity;
                } else {
                    $data['input_value'] = !empty($minimum_quantity) ? $minimum_quantity : 1;
                }

                if ($allow_combination) {
                    $data['input_value'] = 1;
                    $data['min_qty'] = 1;
                    $data['max_qty'] = '';
                    $data['step'] = 1;
                }
            }
        }

        return $data;
    }
}

add_action('plugins_loaded', array('PS_Min_Max_Quantities', 'get_instance'));

endif;