<?php
/*
Plugin Name: NG Menu Packages for WooCommerce
Plugin URI: https://newgraphdesign.cpm/apps/
Description: NG Menu Packages provide a powerful kit-building solution for WooCommerce store.
Version: 1.0.1
Author: יהודה נויפלד
Author URI: https://newgraphdesign.cpm/apps/
Text Domain: wpc-composite-products
Domain Path: /languages/
WC requires at least: 3.0
WC tested up to: 3.6.5
 */

defined('ABSPATH') || exit;

!defined('WOOCO_VERSION') && define('WOOCO_VERSION', '1.0.1');
!defined('WOOCO_URI') && define('WOOCO_URI', plugin_dir_url(__FILE__));
!defined('WPC_URI') && define('WPC_URI', WOOCO_URI);

include 'includes/wpc-menu.php';

if (!function_exists('wooco_init')) {
    add_action('plugins_loaded', 'wooco_init', 11);

    function wooco_init() {
        // load text-domain
        load_plugin_textdomain('wpc-composite-products', false, basename(__DIR__) . '/languages/');

        if (!function_exists('WC') || !version_compare(WC()->version, '3.0.0', '>=')) {
            add_action('admin_notices', 'wooco_notice_wc');

            return;
        }

        if (!class_exists('WC_Product_Composite') && class_exists('WC_Product')) {
            class WC_Product_Composite extends WC_Product {
                public function __construct($product = 0) {
                    parent::__construct($product);
                }

                public function get_type() {
                    return 'composite';
                }

                public function add_to_cart_url() {
                    $product_id = $this->id;
                    return apply_filters('woocommerce_product_add_to_cart_url', get_permalink($product_id), $this);
                }

                public function add_to_cart_text() {
                    if ($this->is_purchasable() && $this->is_in_stock()) {
                        $text = get_option('_wooco_archive_button_select');
                        if (empty($text)) {
                            $text = esc_html__('Select options', 'wpc-composite-products');
                        }
                    } else {
                        $text = get_option('_wooco_archive_button_read');
                        if (empty($text)) {
                            $text = esc_html__('Read more', 'wpc-composite-products');
                        }
                    }

                    return apply_filters('wooco_product_add_to_cart_text', $text, $this);
                }

                public function single_add_to_cart_text() {
                    $text = get_option('_wooco_single_button_add');
                    if (empty($text)) {
                        $text = esc_html__('Add to cart', 'wpc-composite-products');
                    }

                    return apply_filters('wooco_product_single_add_to_cart_text', $text, $this);
                }

                // extra functions

                public function is_fixed_price() {
                    $product_id = $this->id;

                    return get_post_meta($product_id, 'wooco_pricing', 'exclude') === 'only';
                }

                public function get_pricing() {
                    $product_id = $this->id;

                    return get_post_meta($product_id, 'wooco_pricing', 'exclude');
                }

                public function get_components() {
                    $product_id = $this->id;
                    if (($wooco_components = get_post_meta($product_id, 'wooco_components', true)) && is_array($wooco_components) && count($wooco_components) > 0) {
                        return $wooco_components;
                    }

                    return false;
                }

                public function get_composite_price() {
                    // FB for WC
                    return $this->get_price();
                }

                public function get_composite_price_including_tax() {
                    // FB for WC
                    return $this->get_price();
                }
            }
        }

        if (!class_exists('WPcleverWooco')) {
            class WPcleverWooco {
                function __construct() {
                    // Menu
                    add_action('admin_menu', array($this, 'wooco_admin_menu'));

                    // Enqueue frontend scripts
                    add_action('wp_enqueue_scripts', array($this, 'wooco_wp_enqueue_scripts'));

                    // Enqueue backend scripts
                    add_action('admin_enqueue_scripts', array($this, 'wooco_admin_enqueue_scripts'));

                    add_action('wp_ajax_wooco_add_component', array($this, 'wooco_add_component'));

                    add_action('wp_ajax_wooco_ajax_search', array($this, 'wooco_ajax_search'));

                    // Add to selector
                    add_filter('product_type_selector', array($this, 'wooco_product_type_selector'));

                    // Deluxe pricing
                    add_action('woocommerce_product_options_pricing', array($this, 'deluxe_pricing'));
                    add_action('woocommerce_process_product_meta', array($this, 'deluxe_pricing_save'));

                    // Product data tabs
                    add_filter('woocommerce_product_data_tabs', array($this, 'wooco_product_data_tabs'), 10, 1);

                    // Product data panels
                    add_action('woocommerce_product_data_panels', array($this, 'wooco_product_data_panels'));
                    add_action('woocommerce_process_product_meta_composite', array($this, 'wooco_save_option_field'));

                    // Add to cart form & button
                    add_action('woocommerce_composite_add_to_cart', array($this, 'wooco_add_to_cart_form'));
                    add_action('woocommerce_before_add_to_cart_button', array($this, 'wooco_add_to_cart_button'));

                    // Add to cart
                    add_action('woocommerce_add_to_cart', array($this, 'wooco_add_to_cart'), 10, 6);
                    add_filter('woocommerce_add_cart_item_data', array($this, 'wooco_add_cart_item_data'), 10, 2);
                    add_filter('woocommerce_get_cart_item_from_session', array(
                        $this,
                        'wooco_get_cart_item_from_session',
                    ), 10, 2);

                    // Check in_stock
                    //add_filter( 'woocommerce_product_is_in_stock', array( $this, 'wooco_product_is_in_stock' ), 99, 2 );

                    // Check sold individually
                    add_filter('woocommerce_add_to_cart_sold_individually_found_in_cart', array(
                        $this,
                        'wooco_found_in_cart',
                    ), 10, 3);

                    // Cart item
                    add_filter('woocommerce_cart_item_name', array($this, 'wooco_cart_item_name'), 10, 2);
                    add_filter('woocommerce_cart_item_quantity', array($this, 'wooco_cart_item_quantity'), 10, 3);
                    add_filter('woocommerce_cart_item_remove_link', array(
                        $this,
                        'wooco_cart_item_remove_link',
                    ), 10, 2);
                    add_filter('woocommerce_cart_contents_count', array($this, 'wooco_cart_contents_count'));
                    add_action('woocommerce_after_cart_item_quantity_update', array(
                        $this,
                        'wooco_update_cart_item_quantity',
                    ), 1, 2);
                    add_action('woocommerce_before_cart_item_quantity_zero', array(
                        $this,
                        'wooco_update_cart_item_quantity',
                    ), 1);
                    add_action('woocommerce_cart_item_removed', array($this, 'wooco_cart_item_removed'), 10, 2);
                    add_filter('woocommerce_cart_item_price', array($this, 'wooco_cart_item_price'), 10, 2);
                    add_filter('woocommerce_cart_item_subtotal', array($this, 'wooco_cart_item_subtotal'), 10, 2);
                    add_filter('woocommerce_widget_shopping_cart_subtotal', array($this, 'wooco_cart_item_subtotal'), 10, 2);

                    // Hide on cart & checkout page
                    if (get_option('_wooco_hide_component', 'no') !== 'no') {
                        add_filter('woocommerce_cart_item_visible', array($this, 'wooco_item_visible'), 10, 2);
                        add_filter('woocommerce_order_item_visible', array($this, 'wooco_item_visible'), 10, 2);
                        add_filter('woocommerce_checkout_cart_item_visible', array(
                            $this,
                            'wooco_item_visible',
                        ), 10, 2);
                    }

                    // Hide on mini-cart
                    if (get_option('_wooco_hide_component_mini_cart', 'no') === 'yes') {
                        add_filter('woocommerce_widget_cart_item_visible', array(
                            $this,
                            'wooco_item_visible',
                        ), 10, 2);
                    }

                    // Item class
                    if (get_option('_wooco_hide_component', 'no') !== 'yes') {
                        add_filter('woocommerce_cart_item_class', array($this, 'wooco_item_class'), 10, 2);
                        add_filter('woocommerce_mini_cart_item_class', array($this, 'wooco_item_class'), 10, 2);
                        add_filter('woocommerce_order_item_class', array($this, 'wooco_item_class'), 10, 2);
                    }

                    // Get item data
                    if (get_option('_wooco_hide_component', 'no') === 'yes_text') {
                        add_filter('woocommerce_get_item_data', array(
                            $this,
                            'wooco_get_item_data',
                        ), 10, 2);
                        add_action('woocommerce_checkout_create_order_line_item', array(
                            $this,
                            'wooco_checkout_create_order_line_item',
                        ), 10, 4);
                    }

                    // Hide item meta
                    add_filter('woocommerce_order_item_get_formatted_meta_data', array(
                        $this,
                        'wooco_order_item_get_formatted_meta_data',
                    ), 10, 1);

                    // Order item
                    add_action('woocommerce_checkout_create_order_line_item', array(
                        $this,
                        'wooco_add_order_item_meta',
                    ), 10, 3);
                    add_filter('woocommerce_order_item_name', array($this, 'wooco_cart_item_name'), 10, 2);

                    // Admin order
                    add_filter('woocommerce_hidden_order_itemmeta', array(
                        $this,
                        'wooco_hidden_order_item_meta',
                    ), 10, 1);
                    add_action('woocommerce_before_order_itemmeta', array(
                        $this,
                        'wooco_before_order_item_meta',
                    ), 10, 1);

                    // Add settings link
                    add_filter('plugin_action_links', array($this, 'wooco_action_links'), 10, 2);

                    // Loop add-to-cart
                    add_filter('woocommerce_loop_add_to_cart_link', array(
                        $this,
                        'wooco_loop_add_to_cart_link',
                    ), 10, 2);

                    // Calculate totals
                    add_action('woocommerce_before_calculate_totals', array(
                        $this,
                        'wooco_before_calculate_totals',
                    ), 10, 1);

                    // Shipping
                    add_filter('woocommerce_cart_shipping_packages', array(
                        $this,
                        'wooco_cart_shipping_packages',
                    ));

                    // Price html
                    add_filter('woocommerce_get_price_html', array($this, 'wooco_get_price_html'), 99, 2);

                    // Order again
                    add_filter('woocommerce_order_again_cart_item_data', array(
                        $this,
                        'wooco_order_again_cart_item_data',
                    ), 99, 3);
                    add_action('woocommerce_cart_loaded_from_session', array(
                        $this,
                        'wooco_cart_loaded_from_session',
                    ));
                }

                function wooco_admin_menu() {
                    add_submenu_page('wpclever', 'Menu Packages', 'Menu Packages', 'manage_options', 'wpclever-wooco', array(
                        &$this,
                        'wooco_admin_menu_content',
                    ));
                }

                function wooco_admin_menu_content() {
                    add_thickbox();
                    $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'settings';
                    ?>
                    <div class="wpclever_settings_page wrap">
                        <h1 class="wpclever_settings_page_title"><?php echo esc_html__('WPC Menu Packages', 'wpc-composite-products') . ' ' . WOOCO_VERSION; ?></h1>
                        <div class="wpclever_settings_page_desc about-text">
                        </div>
                        <div class="wpclever_settings_page_nav">
                            <h2 class="nav-tab-wrapper">
                                <a href="<?php echo admin_url('admin.php?page=wpclever-wooco&tab=how'); ?>"
                                   class="<?php echo $active_tab === 'how' ? 'nav-tab nav-tab-active' : 'nav-tab'; ?>">
									<?php esc_html_e('How to use?', 'wpc-composite-products');?>
                                </a>
                                <a href="<?php echo admin_url('admin.php?page=wpclever-wooco&tab=settings'); ?>"
                                   class="<?php echo $active_tab === 'settings' ? 'nav-tab nav-tab-active' : 'nav-tab'; ?>">
									<?php esc_html_e('Settings', 'wpc-composite-products');?>
                                </a>
                            </h2>
                        </div>
                        <div class="wpclever_settings_page_content">
							<?php if ($active_tab === 'how') {?>
                                <div class="wpclever_settings_page_content_text">
                                    <p>
										<?php esc_html_e('When creating the product, please choose product data is "Menu Package" then you can see the search field to start search and add component products.', 'wpc-composite-products');?>
                                    </p>
                                    <p>
                                        <img src="<?php echo WOOCO_URI; ?>assets/images/how-01.jpg"/>
                                    </p>
                                </div>
							<?php } elseif ($active_tab === 'settings') {?>
                                <form method="post" action="options.php">
									<?php wp_nonce_field('update-options')?>
                                    <table class="form-table">
                                        <tr class="heading">
                                            <th colspan="2">
												<?php esc_html_e('General', 'wpc-composite-products');?>
                                            </th>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e('Price format', 'wpc-composite-products');?></th>
                                            <td>
                                                <select name="_wooco_price_format">
                                                    <option value="from_regular" <?php echo (get_option('_wooco_price_format', 'from_regular') === 'from_regular' ? 'selected' : ''); ?>><?php esc_html_e('From regular price', 'wpc-composite-products');?></option>
                                                    <option value="from_sale" <?php echo (get_option('_wooco_price_format', 'from_regular') === 'from_sale' ? 'selected' : ''); ?>><?php esc_html_e('From sale price', 'wpc-composite-products');?></option>
                                                    <option value="normal" <?php echo (get_option('_wooco_price_format', 'from_regular') === 'normal' ? 'selected' : ''); ?>><?php esc_html_e('Regular and sale price', 'wpc-composite-products');?></option>
                                                </select>
                                                <span class="description">
                                                    <?php esc_html_e('Choose the price format for composite on the shop page.', 'wpc-composite-products');?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e('Selector interface', 'wpc-composite-products');?></th>
                                            <td>
                                                <select name="_wooco_selector">
                                                    <option value="ddslick" <?php echo (get_option('_wooco_selector', 'ddslick') === 'ddslick' ? 'selected' : ''); ?>><?php esc_html_e('ddSlick', 'wpc-composite-products');?></option>
                                                    <option value="select" <?php echo (get_option('_wooco_selector', 'ddslick') === 'select' ? 'selected' : ''); ?>><?php esc_html_e('HTML select tag', 'wpc-composite-products');?></option>
                                                </select>
                                                <span class="description">
                                                    Read more about <a href="https://designwithpc.com/Plugins/ddSlick"
                                                                       target="_blank">ddSlick</a> and <a
                                                            href="https://www.w3schools.com/tags/tag_select.asp"
                                                            target="_blank">HTML select tag</a>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr class="heading">
                                            <th colspan="2">
												<?php esc_html_e('Menu Packages', 'wpc-composite-products');?>
                                            </th>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e('Total text', 'wpc-composite-products');?></th>
                                            <td>
                                                <input type="text" name="_wooco_total_text"
                                                       value="<?php echo get_option('_wooco_total_text'); ?>"
                                                       placeholder="<?php esc_html_e('Total price:', 'wpc-composite-products');?>"/>
                                                <span class="description">
											<?php esc_html_e('Leave blank if you want to use the default text and can be translated.', 'wpc-composite-products');?>
										</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e('Saved text', 'wpc-composite-products');?></th>
                                            <td>
                                                <input type="text" name="_wooco_saved_text"
                                                       value="<?php echo get_option('_wooco_saved_text'); ?>"
                                                       placeholder="<?php esc_html_e('(saved [d])', 'wpc-composite-products');?>"/>
                                                <span class="description">
											<?php esc_html_e('Leave blank if you want to use the default text and can be translated.', 'wpc-composite-products');?>
										</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e('Change price', 'wpc-composite-products');?></th>
                                            <td>
                                                <select name="_wooco_change_price">
                                                    <option
                                                            value="yes" <?php echo (get_option('_wooco_change_price', 'yes') === 'yes' ? 'selected' : ''); ?>>
														<?php esc_html_e('Yes', 'wpc-composite-products');?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo (get_option('_wooco_change_price', 'yes') === 'no' ? 'selected' : ''); ?>>
														<?php esc_html_e('No', 'wpc-composite-products');?>
                                                    </option>
                                                </select>
                                                <span class="description">
											<?php esc_html_e('Change the main product price when choosing the variation of component products. It uses JavaScript to change product price so it is very dependent on theme’s HTML. If it cannot find and update the product price, please contact us and we can help you adjust the JS file.', 'wpc-composite-products');?>
										</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e('Link to individual product', 'wpc-composite-products');?></th>
                                            <td>
                                                <select name="_wooco_product_link">
                                                    <option
                                                            value="yes" <?php echo (get_option('_wooco_product_link', 'no') === 'yes' ? 'selected' : ''); ?>>
														<?php esc_html_e('Yes, open product page', 'wpc-composite-products');?>
                                                    </option>
                                                    <option
                                                            value="yes_popup" <?php echo (get_option('_wooco_product_link', 'no') === 'yes_popup' ? 'selected' : ''); ?>>
														<?php esc_html_e('Yes, open quick view popup', 'wpc-composite-products');?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo (get_option('_wooco_product_link', 'no') === 'no' ? 'selected' : ''); ?>>
														<?php esc_html_e('No', 'wpc-composite-products');?>
                                                    </option>
                                                </select> <span class="description">
											<?php esc_html_e('Add the link to the selected product below the selection.', 'wpc-composite-products');?> If you choose "Open quick view popup", please install <a
                                                            href="<?php echo esc_url(admin_url('plugin-install.php?tab=plugin-information&plugin=woo-smart-quick-view&TB_iframe=true&width=800&height=550')); ?>"
                                                            class="thickbox" title="Install WPC Smart Quick View">WPC Smart Quick View</a> to make it work.
										</span>
                                            </td>
                                        </tr>
                                        <tr class="heading">
                                            <th>
												<?php esc_html_e('"Add to Cart" button labels', 'wpc-composite-products');?>
                                            </th>
                                            <td>
												<?php esc_html_e('Leave blank if you want to use the default text and can be translated.', 'wpc-composite-products');?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e('Archive/shop page', 'wpc-composite-products');?></th>
                                            <td>
                                                <input type="text" name="_wooco_archive_button_select"
                                                       value="<?php echo get_option('_wooco_archive_button_select'); ?>"
                                                       placeholder="<?php esc_html_e('Select options', 'wpc-composite-products');?>"/>
                                                <span class="description">
											<?php esc_html_e('For purchasable composite.', 'wpc-composite-products');?>
										</span><br/>
                                                <input type="text" name="_wooco_archive_button_read"
                                                       value="<?php echo get_option('_wooco_archive_button_read'); ?>"
                                                       placeholder="<?php esc_html_e('Read more', 'wpc-composite-products');?>"/>
                                                <span class="description">
											<?php esc_html_e('For un-purchasable composite.', 'wpc-composite-products');?>
										</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e('Single product page', 'wpc-composite-products');?></th>
                                            <td>
                                                <input type="text" name="_wooco_single_button_add"
                                                       value="<?php echo get_option('_wooco_single_button_add'); ?>"
                                                       placeholder="<?php esc_html_e('Add to cart', 'wpc-composite-products');?>"/>
                                            </td>
                                        </tr>
                                        <tr class="heading">
                                            <th colspan="2">
												<?php esc_html_e('Cart & Checkout', 'wpc-composite-products');?>
                                            </th>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e('Cart contents count', 'wpc-composite-products');?></th>
                                            <td>
                                                <select name="_wooco_cart_contents_count">
                                                    <option
                                                            value="composite" <?php echo (get_option('_wooco_cart_contents_count', 'composite') === 'composite' ? 'selected' : ''); ?>>
														<?php esc_html_e('Composite only', 'wpc-composite-products');?>
                                                    </option>
                                                    <option
                                                            value="component_products" <?php echo (get_option('_wooco_cart_contents_count', 'composite') === 'component_products' ? 'selected' : ''); ?>>
														<?php esc_html_e('Component products only', 'wpc-composite-products');?>
                                                    </option>
                                                    <option
                                                            value="both" <?php echo (get_option('_wooco_cart_contents_count', 'composite') === 'both' ? 'selected' : ''); ?>>
														<?php esc_html_e('Both composite and component products', 'wpc-composite-products');?>
                                                    </option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e('Hide composite name before component products', 'wpc-composite-products');?></th>
                                            <td>
                                                <select name="_wooco_hide_composite_name">
                                                    <option
                                                            value="yes" <?php echo (get_option('_wooco_hide_composite_name', 'no') === 'yes' ? 'selected' : ''); ?>>
														<?php esc_html_e('Yes', 'wpc-composite-products');?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo (get_option('_wooco_hide_composite_name', 'no') === 'no' ? 'selected' : ''); ?>>
														<?php esc_html_e('No', 'wpc-composite-products');?>
                                                    </option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e('Hide component products on cart & checkout page', 'wpc-composite-products');?></th>
                                            <td>
                                                <select name="_wooco_hide_component">
                                                    <option
                                                            value="yes" <?php echo (get_option('_wooco_hide_component', 'no') === 'yes' ? 'selected' : ''); ?>>
														<?php esc_html_e('Yes, just show the composite', 'wpc-composite-products');?>
                                                    </option>
                                                    <option
                                                            value="yes_text" <?php echo (get_option('_wooco_hide_component', 'no') === 'yes_text' ? 'selected' : ''); ?>>
														<?php esc_html_e('Yes, but show component product names under the composite', 'wpc-composite-products');?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo (get_option('_wooco_hide_component', 'no') === 'no' ? 'selected' : ''); ?>>
														<?php esc_html_e('No', 'wpc-composite-products');?>
                                                    </option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e('Hide component products on mini-cart', 'wpc-composite-products');?></th>
                                            <td>
                                                <select name="_wooco_hide_component_mini_cart">
                                                    <option
                                                            value="yes" <?php echo (get_option('_wooco_hide_component_mini_cart', 'no') === 'yes' ? 'selected' : ''); ?>>
														<?php esc_html_e('Yes', 'wpc-composite-products');?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo (get_option('_wooco_hide_component_mini_cart', 'no') === 'no' ? 'selected' : ''); ?>>
														<?php esc_html_e('No', 'wpc-composite-products');?>
                                                    </option>
                                                </select>
                                                <span class="description">
											<?php esc_html_e('Hide component products, just show the main composite on mini-cart.', 'wpc-composite-products');?>
										</span>
                                            </td>
                                        </tr>
                                        <tr class="submit">
                                            <th colspan="2">
                                                <input type="submit" name="submit" class="button button-primary"
                                                       value="<?php esc_html_e('Update Options', 'wpc-composite-products');?>"/>
                                                <input type="hidden" name="action" value="update"/>
                                                <input type="hidden" name="page_options"
                                                       value="_wooco_price_format,_wooco_selector,_wooco_cart_contents_count,_wooco_hide_composite_name,_wooco_hide_component,_wooco_hide_component_mini_cart,_wooco_total_text,_wooco_saved_text,_wooco_change_price,_wooco_product_link,_wooco_archive_button_select,_wooco_archive_button_read,_wooco_single_button_add"/>
                                            </th>
                                        </tr>
                                    </table>
                                </form>
							<?php }
                }

                function wooco_wp_enqueue_scripts() {
                    $total_text = get_option('_wooco_total_text');
                    if (empty($total_text)) {
                        $total_text = esc_html__('Total price:', 'wpc-composite-products');
                    }

                    $saved_text = get_option('_wooco_saved_text');
                    if (empty($saved_text)) {
                        $saved_text = esc_html__('(saved [d])', 'wpc-composite-products');
                    }

                    wp_enqueue_style('wooco-frontend', WOOCO_URI . 'assets/css/frontend.css');
                    if (get_option('_wooco_selector', 'ddslick') === 'ddslick') {
                        wp_enqueue_script('ddslick', WOOCO_URI . 'assets/libs/ddslick/jquery.ddslick.min.js', array('jquery'), WOOCO_VERSION, true);
                    }
                    wp_enqueue_script('wooco-frontend', WOOCO_URI . 'assets/js/frontend.js', array('jquery'), WOOCO_VERSION, true);
                    wp_localize_script('wooco-frontend', 'wooco_vars', array(
                        'total_text' => $total_text,
                        'saved_text' => $saved_text,
                        'selector' => get_option('_wooco_selector', 'ddslick'),
                        'change_price' => get_option('_wooco_change_price', 'yes'),
                        'product_link' => get_option('_wooco_product_link', 'no'),
                        'alert_min' => esc_html__('Please choose at least [min] of the whole products before adding to the cart.', 'wpc-composite-products'),
                        'alert_max' => esc_html__('Please choose maximum [max] of the whole products before adding to the cart.', 'wpc-composite-products'),
                        'alert_selection' => esc_html__('Please select a purchasable product in the required component before adding the Menu Packages to the cart.', 'wpc-composite-products'),
                        'price_format' => get_woocommerce_price_format(),
                        'price_decimals' => wc_get_price_decimals(),
                        'price_thousand_separator' => wc_get_price_thousand_separator(),
                        'price_decimal_separator' => wc_get_price_decimal_separator(),
                        'currency_symbol' => get_woocommerce_currency_symbol(),
                    )
                    );
                }

                function wooco_admin_enqueue_scripts() {
                    wp_enqueue_style('wooco-backend', WOOCO_URI . 'assets/css/backend.css');
                    wp_enqueue_style('tokeninput-wpc', WOOCO_URI . '/assets/libs/tokeninput/styles/token-input-wpc.css');
                    wp_enqueue_script('tokeninput', WOOCO_URI . '/assets/libs/tokeninput/src/jquery.tokeninput.js', array('jquery'), WOOCO_VERSION, false);
                    wp_enqueue_script('dragarrange', WOOCO_URI . 'assets/js/drag-arrange.js', array('jquery'), WOOCO_VERSION, true);
                    wp_enqueue_script('accounting', WOOCO_URI . 'assets/js/accounting.js', array('jquery'), WOOCO_VERSION, true);
                    wp_enqueue_script('wooco-backend', WOOCO_URI . 'assets/js/backend.js', array('jquery'), WOOCO_VERSION, true);
                }

                function wooco_action_links($links, $file) {
                    static $plugin;
                    if (!isset($plugin)) {
                        $plugin = plugin_basename(__FILE__);
                    }
                    if ($plugin === $file) {
                        $settings_link = '<a href="' . admin_url('admin.php?page=wpclever-wooco&tab=settings') . '">' . esc_html__('Settings', 'wpc-composite-products') . '</a>';
                        array_unshift($links, $settings_link);
                    }

                    return (array) $links;
                }

                function wooco_cart_contents_count($count) {
                    $cart_contents_count = get_option('_wooco_cart_contents_count', 'composite');

                    if ($cart_contents_count !== 'both') {
                        $cart_contents = WC()->cart->cart_contents;
                        foreach ($cart_contents as $cart_item_key => $cart_item) {
                            if (($cart_contents_count === 'component_products') && !empty($cart_item['wooco_ids'])) {
                                $count -= $cart_item['quantity'];
                            }
                            if (($cart_contents_count === 'composite') && !empty($cart_item['wooco_parent_id'])) {
                                $count -= $cart_item['quantity'];
                            }
                        }
                    }

                    return $count;
                }

                function wooco_cart_item_name($name, $item) {

                    $output = '';

                    if (isset($item['wooco_parent_id']) && !empty($item['wooco_parent_id']) && (get_option('_wooco_hide_composite_name', 'no') === 'no')) {

                        if (strpos($name, '</a>') !== false) {
                            $output .= '<a href="' . get_permalink($item['wooco_parent_id']) . '">' . get_the_title($item['wooco_parent_id']) . '</a> &rarr; ' . $name;
                        } else {
                            $output .= get_the_title($item['wooco_parent_id']) . ' &rarr; ' . strip_tags($name);
                        }
                    }

                    if (isset($item['is_deluxe'])) {
                        if ($item['is_deluxe'] == 'true') {
                            $output .= $name . '<span class="pill deluxe">Deluxe</span>';
                        } else {
                            $output .= $name . '<span class="pill">Basic</span>';
                        }
                    }

                    return ($output == '' ? $name : $output);
                }

                function wooco_cart_item_price($price, $cart_item) {

                    // composite
                    if (isset($cart_item['wooco_ids'], $cart_item['wooco_keys']) && method_exists($cart_item['data'], 'is_fixed_price') && !$cart_item['data']->is_fixed_price()) {
                        return wc_price($cart_item['wooco_total']);
                    }

                    // global $woocommerce;
                    // echo '<code>subtotal: '.print_r(WC()->cart->get_cart_contents()).'</code>';
                    // echo '<code>subtotal: '.print_r($woocommerce->cart->empty_cart()).'</code>';
                    return $price;
                }

                function wooco_cart_item_subtotal($subtotal, $cart_item = null) {
                    // composite
                    if (isset($cart_item['wooco_ids'], $cart_item['wooco_keys']) && method_exists($cart_item['data'], 'is_fixed_price') && !$cart_item['data']->is_fixed_price()) {
                        return wc_price($cart_item['wooco_total'] * $cart_item['quantity']);
                    }

                    return $subtotal;
                }

                function wooco_update_cart_item_quantity($cart_item_key, $quantity = 0) {
                    if (isset(WC()->cart->cart_contents[$cart_item_key]['wooco_keys'])) {
                        foreach (WC()->cart->cart_contents[$cart_item_key]['wooco_keys'] as $wooco_key) {
                            if (isset(WC()->cart->cart_contents[$wooco_key])) {
                                if ($quantity <= 0) {
                                    $wooco_qty = 0;
                                } else {
                                    $wooco_qty = $quantity * (WC()->cart->cart_contents[$wooco_key]['wooco_qty'] ?: 1);
                                }
                                WC()->cart->set_quantity($wooco_key, $wooco_qty, false);
                            }
                        }
                    }
                }

                function wooco_cart_item_removed($cart_item_key, $cart) {
                    if (isset($cart->removed_cart_contents[$cart_item_key]['wooco_keys'])) {
                        $wooco_keys = $cart->removed_cart_contents[$cart_item_key]['wooco_keys'];
                        foreach ($wooco_keys as $wooco_key) {
                            unset($cart->cart_contents[$wooco_key]);
                        }
                    }
                }

                function wooco_ready_add_to_cart($items) {
                    foreach ($items as $item) {
                        $wooco_item = explode('/', $item);
                        $wooco_item_id = absint(isset($wooco_item[0]) ? $wooco_item[0] : 0);
                        $wooco_product = wc_get_product($wooco_item_id);
                        if (!$wooco_product || $wooco_product->is_type('variable') || !$wooco_product->is_in_stock()) {
                            return false;
                        }
                    }

                    return true;
                }

                function wooco_found_in_cart($found_in_cart, $product_id, $variation_id) {
                    foreach (WC()->cart->get_cart() as $cart_item) {
                        $cart_product_id = $cart_item['product_id'];
                        $cart_variation_id = $cart_item['variation_id'];
                        if (($cart_product_id === $product_id) && ($cart_variation_id === $variation_id)) {
                            return true;
                        }
                    }

                    return $found_in_cart;
                }

                function wooco_add_cart_item_data($cart_item_data, $product_id) {
                    if (get_post_meta($product_id, 'wooco_components', true)) {
                        $wooco_ids = '';
                        if (isset($_POST['wooco_ids'])) {
                            $wooco_ids = $_POST['wooco_ids'];
                            unset($_POST['wooco_ids']);
                        }

                        if (isset($_POST['is_deluxe'])) {
                            $is_deluxe = $_POST['is_deluxe'];
                            unset($_POST['is_deluxe']);
                        }

                        if (isset($_POST['wooco_total'])) {
                            $wooco_total = $_POST['wooco_total'];
                            unset($_POST['wooco_total']);
                        }

                        $wooco_ids = $this->wooco_clean_ids($wooco_ids);
                        if (!empty($wooco_ids)) {
                            $cart_item_data['wooco_ids'] = $wooco_ids;
                        }
                        if (!empty($is_deluxe)) {
                            $cart_item_data['is_deluxe'] = $is_deluxe;
                        }
                        if (!empty($wooco_total)) {
                            $cart_item_data['wooco_total'] = $wooco_total;
                        }
                    }

                    return $cart_item_data;
                }

                function wooco_add_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
                    if (isset($cart_item_data['wooco_ids']) && ($cart_item_data['wooco_ids'] !== '')) {
                        $items = explode(',', $cart_item_data['wooco_ids']);

                        if (is_array($items) && (count($items) > 0)) {
                            if ($this->wooco_ready_add_to_cart($items)) {
                                // add child products
                                $wooco_i = 0; // for same component product
                                foreach ($items as $item) {
                                    $wooco_i++;
                                    $wooco_item = explode('/', $item);
                                    $wooco_item_id = absint(isset($wooco_item[0]) ? $wooco_item[0] : 0);
                                    $wooco_item_qty = absint(isset($wooco_item[1]) ? $wooco_item[1] : 1);
                                    if (($wooco_item_id > 0) && ($wooco_item_qty > 0)) {
                                        $wooco_item_variation_id = 0;
                                        $wooco_item_variation = array();
                                        // ensure we don't add a variation to the cart directly by variation ID
                                        if ('product_variation' === get_post_type($wooco_item_id)) {
                                            $wooco_item_variation_id = $wooco_item_id;
                                            $wooco_item_id = wp_get_post_parent_id($wooco_item_variation_id);
                                            $wooco_item_variation_product = wc_get_product($wooco_item_variation_id);
                                            $wooco_item_variation = $wooco_item_variation_product->get_attributes();
                                        }
                                        // add to cart
                                        $wooco_product_qty = $wooco_item_qty * $quantity;
                                        $wooco_item_data = array(
                                            'wooco_pos' => $wooco_i,
                                            'wooco_qty' => $wooco_item_qty,
                                            'wooco_parent_id' => $product_id,
                                            'wooco_parent_key' => $cart_item_key,
                                        );
                                        $wooco_cart_id = WC()->cart->generate_cart_id($wooco_item_id, $wooco_item_variation_id, $wooco_item_variation, $wooco_item_data);
                                        $wooco_item_key = WC()->cart->find_product_in_cart($wooco_cart_id);

                                        if (empty($wooco_item_key)) {
                                            $wooco_item_key = WC()->cart->add_to_cart($wooco_item_id, $wooco_product_qty, $wooco_item_variation_id, $wooco_item_variation, $wooco_item_data);
                                        }

                                        if (empty($wooco_item_key)) {
                                            // can't add the Menu Package
                                            if (isset(WC()->cart->cart_contents[$cart_item_key]['wooco_keys'])) {
                                                $wooco_keys = WC()->cart->cart_contents[$cart_item_key]['wooco_keys'];
                                                foreach ($wooco_keys as $wooco_key) {
                                                    // remove all components
                                                    WC()->cart->remove_cart_item($wooco_key);
                                                }
                                                // remove the composite
                                                WC()->cart->remove_cart_item($cart_item_key);
                                            }
                                        } elseif (!isset(WC()->cart->cart_contents[$cart_item_key]['wooco_keys']) || !in_array($wooco_item_key, WC()->cart->cart_contents[$cart_item_key]['wooco_keys'], true)) {
                                            // add keys
                                            WC()->cart->cart_contents[$cart_item_key]['wooco_keys'][] = $wooco_item_key;
                                        }
                                    }
                                }
                            } else {
                                WC()->cart->remove_cart_item($cart_item_key);
                                wc_add_notice(esc_html__('An error occured when adding this Menu Package to the cart.', 'wpc-composite-products'), 'error');

                                return false;
                            }
                        }
                    }
                }

                function wooco_before_calculate_totals($cart_object) {
                    // This is necessary for WC 3.0+
                    if (!defined('DOING_AJAX') && is_admin()) {
                        return;
                    }

                    // Avoiding hook repetition (when using price calculations for example)
                    if (did_action('woocommerce_before_calculate_totals') >= 2) {
                        return;
                    }

                    foreach ($cart_object->get_cart() as $cart_item_key => $cart_item) {
                        // cart total price
                        if (isset($cart_item['wooco_total']) && ($cart_item['wooco_total'] !== '') && $cart_item['data']->is_type('composite')) {
                            $base_package_price = wc_get_product($cart_item['product_id'])->get_price();
                            $deluxe_package_price = get_post_meta($cart_item['product_id'], '_deluxe_price', true);

                            $is_deluxe = $cart_item['is_deluxe'] == 'true';
                            $base_price = $is_deluxe ? $deluxe_package_price : $base_package_price;

                            if ($base_price <= $cart_item['wooco_total']) {
                                $cart_item['data']->set_price($cart_item['wooco_total']);
                            } else {
                                // some hacker hacked the price - sound the alarm!
                                $cart_item['data']->set_price($base_price);
                            }
                        }
                        // echo print_r($cart_item, true) . ' <| ';

                        // child product price
                        if (isset($cart_item['wooco_parent_id']) && ($cart_item['wooco_parent_id'] !== '')) {
                            $parent_product = wc_get_product($cart_item['wooco_parent_id']);
                            if (!$parent_product || !$parent_product->is_type('composite')) {
                                continue;
                            }
                            if (method_exists($parent_product, 'is_fixed_price') && $parent_product->is_fixed_price()) {
                                $cart_item['data']->set_price(0);
                            } else {

                                // update component prices
                                $price = 0;
                                // $price = $this->getFinalPrice($cart_item);
                                if (!is_checkout()) { // debugging
                                    // echo '<h2>Item: ' . wc_get_product($cart_item['product_id'])->get_title() . ' - Final price: ' . $price . '</h2>';
                                }
                                $cart_item['data']->set_price($price);

                                if (($wooco_discount_percent = get_post_meta($cart_item['wooco_parent_id'], 'wooco_discount_percent', true)) && is_numeric($wooco_discount_percent) && ((float) $wooco_discount_percent < 100) && ((float) $wooco_discount_percent > 0)) {
                                    if ($cart_item['variation_id'] > 0) {
                                        $wooco_product = wc_get_product($cart_item['variation_id']);
                                    } else {
                                        $wooco_product = wc_get_product($cart_item['product_id']);
                                    }
                                    $wooco_product_price = $wooco_product->get_price() * (100 - (float) $wooco_discount_percent) / 100;
                                    $cart_item['data']->set_price((float) $wooco_product_price);
                                }
                            }
                        }

                        // main product price
                        if (isset($cart_item['wooco_ids']) && ($cart_item['wooco_ids'] !== '') && $cart_item['data']->is_type('composite')) {
                            if (method_exists($cart_item['data'], 'get_pricing') && ($cart_item['data']->get_pricing() === 'exclude')) {
                                $cart_item['data']->set_price(0);
                            }
                        }
                    }
                }

                function getFinalPrice($cart_item) {
                    // check if is free in package & return price
                    $item_id = $cart_item['product_id'];
                    $price = floatval($cart_item['data']->get_price());

                    $components_arr = get_post_meta($cart_item['wooco_parent_id'], 'wooco_components'); // gets package menu components
                    foreach ($components_arr as $components) {
                        foreach ($components as $component) {

                            $found = false;

                            $prod_arr = explode(',', $component['products']);
                            if (is_array($prod_arr)) {
                                if (in_array($item_id, $prod_arr)) {
                                    $found = true;
                                }
                            } elseif ($item_id == $component['products']) {
                                $found = true;
                            }
                            // echo print_r($component, true) . ' | ' . $item_id . ' :: ' . ($found ? 'found' : 'not found') . $prod_arr;

                            if ($found) {

                                $qty_free = $component['qty_free'];
                                $final_price = (($cart_item['quantity'] - $qty_free) * $price) / $cart_item['quantity']; // this is the trick! [divide by qty]

                                if ($cart_item['quantity'] <= $qty_free) { // don't charge extra
                                    return 0;
                                } else { // only charge for extras
                                    return $final_price;
                                }
                            }
                        }
                    }
                    return $price;
                }

                function wooco_item_visible($visible, $item) {
                    if (isset($item['wooco_parent_id'])) {
                        return false;
                    }

                    return $visible;
                }

                function wooco_item_class($class, $item) {
                    $product_id = $item['product_id'];
                    if (isset($item['wooco_parent_id'])) {
                        $product_id = $item['wooco_parent_id'];
                        $class .= ' wooco-cart-item wooco-cart-child wooco-item-child';
                    } elseif (isset($item['wooco_ids'])) {
                        $class .= ' wooco-cart-item wooco-cart-parent wooco-item-parent';
                        if (get_option('_wooco_hide_component', 'no') !== 'no') {
                            $class .= ' wooco-hide-component';
                        }
                    }

                    if (isset($item['wooco_parent_id']) || isset($item['wooco_ids'])) {
                        $product = wc_get_product($product_id);
                        if ($product && $product->is_type('composite') && method_exists($product, 'is_fixed_price') && $product->is_fixed_price()) {
                            $class .= ' wooco-fixed-price';
                        } else {
                            $class .= ' wooco-auto-price';
                        }
                    }

                    return $class;
                }

                function wooco_get_item_data($item_data, $cart_item) {
                    if (empty($cart_item['wooco_ids'])) {
                        return $item_data;
                    }

                    $wooco_items = explode(',', $cart_item['wooco_ids']);
                    $wooco_items_str = '';
                    if (is_array($wooco_items) && count($wooco_items) > 0) {
                        foreach ($wooco_items as $wooco_item) {
                            $wooco_item_arr = explode('/', $wooco_item);
                            $wooco_item_id = absint(isset($wooco_item_arr[0]) ? $wooco_item_arr[0] : 0);
                            $wooco_item_qty = absint(isset($wooco_item_arr[1]) ? $wooco_item_arr[1] : 1);
                            $wooco_items_str .= ($wooco_item_qty * $cart_item['quantity']) . ' × ' . get_the_title($wooco_item_id) . '; ';
                        }
                    }
                    $wooco_items_str = trim($wooco_items_str, '; ');
                    $item_data[] = array(
                        'key' => esc_html__('Courses', 'wpc-composite-products'),
                        'value' => $wooco_items_str,
                        'display' => '',
                    );

                    return $item_data;
                }

                function wooco_checkout_create_order_line_item($item, $cart_item_key, $values, $order) {
                    if (empty($values['wooco_ids'])) {
                        return;
                    }
                    $wooco_items = explode(',', $values['wooco_ids']);
                    $wooco_items_str = '';
                    if (is_array($wooco_items) && count($wooco_items) > 0) {
                        foreach ($wooco_items as $wooco_item) {
                            $wooco_item_arr = explode('/', $wooco_item);
                            $wooco_item_id = absint(isset($wooco_item_arr[0]) ? $wooco_item_arr[0] : 0);
                            $wooco_item_qty = absint(isset($wooco_item_arr[1]) ? $wooco_item_arr[1] : 1);
                            $wooco_items_str .= $wooco_item_qty . ' × ' . get_the_title($wooco_item_id) . '; ';
                        }
                    }
                    $wooco_items_str = trim($wooco_items_str, '; ');
                    $item->add_meta_data(esc_html__('Courses', 'wpc-composite-products'), $wooco_items_str);
                }

                function wooco_order_item_get_formatted_meta_data($formatted_meta) {
                    foreach ($formatted_meta as $key => $meta) {
                        if (($meta->key === 'wooco_ids') || ($meta->key === 'wooco_parent_id')) {
                            unset($formatted_meta[$key]);
                        }
                    }

                    return $formatted_meta;
                }

                function wooco_add_order_item_meta($item, $cart_item_key, $values) {
                    if (isset($values['wooco_parent_id'])) {
                        $item->update_meta_data('wooco_parent_id', $values['wooco_parent_id']);
                    }
                    if (isset($values['wooco_ids'])) {
                        $item->update_meta_data('wooco_ids', $values['wooco_ids']);
                    }
                }

                function wooco_hidden_order_item_meta($hidden) {
                    return array_merge($hidden, array('wooco_parent_id', 'wooco_ids'));
                }

                function wooco_before_order_item_meta($item_id) {
                    if ($wooco_parent_id = wc_get_order_item_meta($item_id, 'wooco_parent_id', true)) {
                        echo sprintf(esc_html__('(in %s)', 'wpc-composite-products'), get_the_title($wooco_parent_id));
                    }
                }

                function wooco_get_cart_item_from_session($cart_item, $item_session_values) {
                    if (isset($item_session_values['wooco_ids']) && !empty($item_session_values['wooco_ids'])) {
                        $cart_item['wooco_ids'] = $item_session_values['wooco_ids'];
                    }
                    if (isset($item_session_values['wooco_parent_id'])) {
                        $cart_item['wooco_parent_id'] = $item_session_values['wooco_parent_id'];
                        $cart_item['wooco_parent_key'] = $item_session_values['wooco_parent_key'];
                        $cart_item['wooco_qty'] = $item_session_values['wooco_qty'];
                        if (isset($cart_item['data']->subscription_sign_up_fee)) {
                            $cart_item['data']->subscription_sign_up_fee = 0;
                        }
                    }

                    return $cart_item;
                }

                function wooco_product_is_in_stock($in_stock, $_product) {
                    if ($_product->is_type('composite') && ($wooco_components = $_product->get_components())) {
                        foreach ($wooco_components as $wooco_component) {
                            if (($wooco_component['optional'] === 'yes') || (($wooco_component_type = $wooco_component['type']) === '') || empty($wooco_component[$wooco_component_type])) {
                                continue;
                            }
                            $wooco_i = false;
                            $wooco_component_default = isset($wooco_component['default']) ? (int) $wooco_component['default'] : 0;
                            if ($wooco_products = $this->wooco_get_products($wooco_component['type'], $wooco_component[$wooco_component_type], $wooco_component_default)) {
                                foreach ($wooco_products as $wooco_product) {
                                    if ($wooco_i) {
                                        continue;
                                    }
                                    $wooco_product_wc = wc_get_product($wooco_product['id']);
                                    if ($wooco_product_wc && !$wooco_product_wc->is_type('composite') && $wooco_product_wc->is_in_stock() && $wooco_product_wc->is_purchasable() && $wooco_product_wc->has_enough_stock($wooco_component['qty'])) {
                                        $wooco_i = true;
                                    }
                                }
                            }
                            if (!$wooco_i) {
                                return false;
                            }
                        }
                    }

                    return $in_stock;
                }

                function wooco_cart_item_remove_link($link, $cart_item_key) {
                    if (isset(WC()->cart->cart_contents[$cart_item_key]['wooco_parent_key'])) {
                        $wooco_parent_key = WC()->cart->cart_contents[$cart_item_key]['wooco_parent_key'];
                        if (isset(WC()->cart->cart_contents[$wooco_parent_key])) {
                            return '';
                        }
                    }

                    return $link;
                }

                function wooco_cart_item_quantity($quantity, $cart_item_key, $cart_item) {
                    // add qty as text - not input
                    if (isset($cart_item['wooco_parent_id'])) {
                        return $cart_item['quantity'];
                    }

                    return $quantity;
                }

                function wooco_ajax_search() {
                    $q = isset($_GET['q']) ? $_GET['q'] : '';
                    $ajax_type = urldecode(isset($_GET['ajax_type']) ? $_GET['ajax_type'] : 'post_type');
                    $ajax_get = urldecode(isset($_GET['ajax_get']) ? $_GET['ajax_get'] : 'post');
                    $ajax_field = urldecode(isset($_GET['ajax_field']) ? $_GET['ajax_field'] : 'id');
                    $ajax_get_arr = explode(',', $ajax_get);
                    $arr = array();
                    if ($ajax_type === 'post_type') {
                        $params = array(
                            'posts_per_page' => 10,
                            'post_type' => $ajax_get_arr,
                            'ignore_sticky_posts' => 1,
                            's' => $q,
                        );
                        $wooco_loop = new WP_Query($params);
                        if ($wooco_loop->have_posts()) {
                            while ($wooco_loop->have_posts()) {
                                $wooco_loop->the_post();
                                $_product = wc_get_product(get_the_ID());
                                if (!$_product || $_product->is_type('composite')) {
                                    continue;
                                }
                                $arr[] = array(
                                    'id' => $_product->get_id(),
                                    'name' => $_product->get_name(),
                                );
                                if ($_product->is_type('variable')) {
                                    $childs = $_product->get_children();
                                    if (!empty($childs)) {
                                        foreach ($childs as $child) {
                                            $_product_child = wc_get_product($child);
                                            if (!$_product_child) {
                                                continue;
                                            }
                                            $arr[] = array(
                                                'id' => $_product_child->get_id(),
                                                'name' => $_product_child->get_name(),
                                            );
                                        }
                                    }
                                }
                            }
                            wp_reset_postdata();
                        }
                    } elseif ($ajax_type === 'taxonomy') {
                        $terms = get_terms(array(
                            'taxonomy' => $ajax_get_arr,
                            'hide_empty' => false,
                        ));
                        if (!empty($terms) && !is_wp_error($terms)) {
                            foreach ($terms as $term) {
                                if ($ajax_field === 'id') {
                                    $arr[] = array(
                                        'id' => $term->term_id,
                                        'name' => $term->name,
                                    );
                                } elseif ($ajax_field === 'slug') {
                                    $arr[] = array(
                                        'id' => $term->slug,
                                        'name' => $term->name,
                                    );
                                }
                            }
                        }
                    }
                    echo json_encode($arr);
                    die();
                }

                public function deluxe_pricing() {
                    global $post;
                    echo '</div><div class="options_group show_if_composite">';
                    $deluxe_price = get_post_meta($post->ID, '_deluxe_price', true);

                    woocommerce_wp_text_input(array(
                        'id' => '_deluxe_price',
                        'label' => 'Deluxe Price',
                        'type' => 'number',
                        'value' => $deluxe_price,
                        'desc_tip' => true,
                        'description' => 'Insert the Deluxe Package price here.',
                    ));
                }

                public function deluxe_pricing_save($post_id) {
                    $_deluxe_price = !empty($_POST['_deluxe_price']) ? $_POST['_deluxe_price'] : '';
                    update_post_meta($post_id, '_deluxe_price', $_deluxe_price);
                }

                function wooco_product_type_selector($types) {
                    $types['composite'] = esc_html__('Menu Package', 'wpc-composite-products');
                    return $types;
                }

                function wooco_product_data_tabs($tabs) {
                    $tabs['composite'] = array(
                        'label' => esc_html__('Courses', 'wpc-composite-products'),
                        'target' => 'wooco_settings',
                        'class' => array('show_if_composite'),
                    );

                    return $tabs;
                }

                function wooco_add_component() {
                    $this->wooco_component(true);
                    die();
                }

                function wooco_component(
                    $active = false,
                    $component = array(
                        'name' => 'Name',
                        'desc' => 'Description',
                        'type' => 'products',
                        'categories' => '',
                        'products' => '',
                        'default' => '',
                        'deluxe_only' => 'no',
                        'optional' => 'no',
                        'qty' => 1,
                        'custom_qty' => 'no',
                        'min' => 0,
                        'max' => 100,
                        'qty_free' => 1,
                        'qty_free_deluxe' => 1,
                        'section' => 'night',
                    )
                ) {
                    $wooco_search_products_id = uniqid('wooco_search_products-', false);
                    $wooco_search_default_id = uniqid('wooco_search_default-', false);
                    $pre_populate_products = $pre_populate_categories = $pre_populate_default = '';
                    if ($component['products'] !== '') {
                        $value_items = explode(',', $component['products']);
                        foreach ($value_items as $value_item) {
                            $value_item_info = get_post($value_item);
                            $pre_populate_products .= '{id: ' . $value_item_info->ID . ', name: "' . $value_item_info->post_title . '"},';
                        }
                    }
                    if ($component['default'] !== '') {
                        $value_item_default = get_post($component['default']);
                        $pre_populate_default = '{id: ' . $value_item_default->ID . ', name: "' . $value_item_default->post_title . '"}';
                    }
                    ?>
                    <tr class="wooco_component">
                        <td>
                            <div class="wooco_component_inner <?php echo ($active ? 'active' : ''); ?>">
                                <div class="wooco_component_heading">
                                    <span class="wooco_move_component"> # </span>
                                    <span class="wooco_component_name"><?php echo $component['name']; ?></span>
                                    <a class="wooco_remove_component"
                                       href="#"><?php esc_html_e('remove', 'wpc-composite-products');?></a>
                                </div>
                                <div class="wooco_component_content">
                                   <div class="wooco_component_content_line">
                                        <div class="wooco_component_content_line_label">In which Section?</div>
                                        <div class="wooco_component_content_line_value">
                                        <select name="wooco_components[section][]">
                                          <option value="night" <?php echo ($component['section'] === 'night' ? 'selected' : ''); ?>>Night Meal</option>
                                          <option value="day" <?php echo ($component['section'] === 'day' ? 'selected' : ''); ?>>Day Meal</option>
                                          <option value="extras" <?php echo ($component['section'] === 'extras' ? 'selected' : ''); ?>>Extras</option>
                                        </select>
                                        </div>
                                    </div>
                                    <div class="wooco_component_content_line">
                                        <div class="wooco_component_content_line_label">Course Name</div>
                                        <div class="wooco_component_content_line_value">
                                            <input name="wooco_components[name][]" type="text" class="wooco_input_name"
                                                   value="<?php echo $component['name']; ?>"/>
                                        </div>
                                    </div>
                                    <div class="wooco_component_content_line">
                                        <div class="wooco_component_content_line_label">Course Description</div>
                                        <div class="wooco_component_content_line_value">
                                            <textarea
                                                    name="wooco_components[desc][]"><?php echo $component['desc']; ?></textarea>
                                        </div>
                                    </div>
                                    <div class="wooco_component_content_line">
                                        <div class="wooco_component_content_line_label">Products in this Course</div>
                                        <div class="wooco_component_content_line_value">
                                          <input name="wooco_components[type][]" type="hidden" value="products"/>

                                          <input id="<?php echo $wooco_search_products_id; ?>"
                                                  class="wooco_search_products"
                                                  name="wooco_components[products][]" type="text"
                                                  value="<?php echo $component['products']; ?>"/>
                                        </div>
                                    </div>
                                    <div class="wooco_component_content_line">
                                        <div class="wooco_component_content_line_label">Default selected option</div>
                                        <div class="wooco_component_content_line_value">
                                            <input id="<?php echo $wooco_search_default_id; ?>"
                                                   class="wooco_search_products"
                                                   name="wooco_components[default][]" type="text"
                                                   value="<?php echo $component['default']; ?>"/>
                                        </div>
                                    </div>
									<?php echo '<script>jQuery("#' . $wooco_search_products_id . '").tokenInput("' . esc_js(admin_url('admin-ajax.php')) . '?action=wooco_ajax_search&ajax_type=post_type&ajax_get=product&ajax_field=id", {
                prePopulate: [' . $pre_populate_products . '], theme: "wpc", hintText: "Type to search product"}); jQuery("#' . $wooco_search_default_id . '").tokenInput("' . esc_js(admin_url('admin-ajax.php')) . '?action=wooco_ajax_search&ajax_type=post_type&ajax_get=product&ajax_field=id", {
                prePopulate: [' . $pre_populate_default . '], tokenLimit: 1, theme: "wpc", hintText: "Type to search product"});</script>'; ?>
                                    <div class="wooco_component_content_line">
                                        <div class="wooco_component_content_line_label">Only in Deluxe?</div>
                                        <div class="wooco_component_content_line_value">
                                            <select name="wooco_components[deluxe_only][]">
                                              <option value="no" <?php echo ($component['deluxe_only'] === 'no' ? 'selected' : ''); ?>>No</option>
                                              <option value="yes" <?php echo ($component['deluxe_only'] === 'yes' ? 'selected' : ''); ?>>Yes</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="wooco_component_content_line">
                                        <div class="wooco_component_content_line_label">Optional?</div>
                                        <div class="wooco_component_content_line_value">
                                            <select name="wooco_components[optional][]">
                                              <option value="no" <?php echo ($component['optional'] === 'no' ? 'selected' : ''); ?>>No</option>
                                              <option value="yes" <?php echo ($component['optional'] === 'yes' ? 'selected' : ''); ?>>Yes</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="wooco_component_content_line">
                                        <div class="wooco_component_content_line_label">Can add extra?</div>
                                        <div class="wooco_component_content_line_value">
                                            <select name="wooco_components[custom_qty][]">
                                              <option value="no" <?php echo ($component['custom_qty'] === 'no' ? 'selected' : ''); ?>>No</option>
                                              <option value="yes" <?php echo ($component['custom_qty'] === 'yes' ? 'selected' : ''); ?>>Yes</option>
                                            </select>
                                        </div>
                                    </div>
                                    <!-- SilverSpoon Settings -->
                                    <div class="wooco_component_content_line">
                                        <div class="wooco_component_content_line_label">How much included for free in Basic package?</div>
                                        <div class="wooco_component_content_line_value">
                                            <input name="wooco_components[qty_free][]" type="number" min="0" max="100" value="<?php echo $component['qty_free']; ?>"/>
                                        </div>
                                        <div class="wooco_component_content_line_label">How much included for free in Deluxe package?</div>
                                        <div class="wooco_component_content_line_value">
                                            <input name="wooco_components[qty_free_deluxe][]" type="number" min="0" max="100" value="<?php echo $component['qty_free_deluxe']; ?>"/>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </td>
                    </tr>
				<?php }

                function wooco_product_data_panels() {
                    global $post;
                    $post_id = $post->ID;
                    ?>
                    <div id='wooco_settings' class='panel woocommerce_options_panel wooco_table'>
                        <table class="wooco_components">
                            <thead></thead>
                            <tbody>
							<?php
$wooco_components = get_post_meta($post_id, 'wooco_components', true);
                    if (is_array($wooco_components)) {
                        foreach ($wooco_components as $wooco_component) {
                            $this->wooco_component(false, $wooco_component);
                        }
                    } else {
                        $this->wooco_component(true);
                    }
                    ?>
                            </tbody>
                            <tfoot>
                            <tr>
                                <td>
                                    <a href="#" class="wooco_add_component button">
										<?php esc_html_e('+ Add Course', 'wpc-composite-products');?>
                                    </a>
                                </td>
                            </tr>
                            </tfoot>
                        </table>
                        <table>
                            <tr class="wooco_tr_space">
                                <th><?php esc_html_e('Pricing', 'wpc-composite-products');?></th>
                                <td>
                                    <select id="wooco_pricing" name="wooco_pricing">
                                        <option value="only" <?php echo (get_post_meta($post_id, 'wooco_pricing', 'exclude') === 'only' ? 'selected' : ''); ?>><?php esc_html_e('Only base price', 'wpc-composite-products');?></option>
                                        <option value="include" <?php echo (get_post_meta($post_id, 'wooco_pricing', 'exclude') === 'include' ? 'selected' : ''); ?>><?php esc_html_e('Include base price', 'wpc-composite-products');?></option>
                                        <option value="exclude" <?php echo (get_post_meta($post_id, 'wooco_pricing', 'exclude') === 'exclude' ? 'selected' : ''); ?>><?php esc_html_e('Exclude base price', 'wpc-composite-products');?></option>
                                    </select>
                                    <div style="display: inline-block; width: 100%">
										<?php esc_html_e('"Base price" is the price was set in the General tab.', 'wpc-composite-products');?>
                                        <br/>
										<?php esc_html_e('If you choose "Only base price", the price don\'t change when selecting the component product.', 'wpc-composite-products');?>
                                    </div>
                                </td>
                            </tr>
                            <tr class="wooco_tr_space">
                                <th><?php esc_html_e('Discount', 'wpc-composite-products');?></th>
                                <td style="vertical-align: middle; line-height: 30px;">
                                    <input id="wooco_discount_percent" name="wooco_discount_percent" type="number"
                                           min="0.0001" step="0.0001"
                                           max="99.9999"
                                           value="<?php echo (get_post_meta($post_id, 'wooco_discount_percent', true) ?: ''); ?>"
                                           style="width: 80px"/>%. <?php esc_html_e('Only applied for Courses.', 'wpc-composite-products');?>
                                </td>
                            </tr>
                            <tr class="wooco_tr_space">
                                <th><?php esc_html_e('Quantity', 'wpc-composite-products');?></th>
                                <td style="vertical-align: middle; line-height: 30px;">
                                    Min <input name="wooco_qty_min" type="number"
                                               min="1" step="1" max="999999"
                                               value="<?php echo (get_post_meta($post_id, 'wooco_qty_min', true) ?: ''); ?>"
                                               style="width: 80px"/> Max <input name="wooco_qty_max" type="number"
                                                                                min="1" step="1" max="999999"
                                                                                value="<?php echo (get_post_meta($post_id, 'wooco_qty_max', true) ?: ''); ?>"
                                                                                style="width: 80px"/>
                                </td>
                            </tr>
                            <tr class="wooco_tr_space">
                                <th><?php esc_html_e('Shipping fee', 'wpc-composite-products');?></th>
                                <td>
                                    <select id="wooco_shipping_fee" name="wooco_shipping_fee">
                                        <option value="whole" <?php echo (get_post_meta($post_id, 'wooco_shipping_fee', true) === 'whole' ? 'selected' : ''); ?>><?php esc_html_e('Apply to the whole composite', 'wpc-composite-products');?></option>
                                        <option value="each" <?php echo (get_post_meta($post_id, 'wooco_shipping_fee', true) === 'each' ? 'selected' : ''); ?>><?php esc_html_e('Apply to each component product', 'wpc-composite-products');?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr class="wooco_tr_space">
                                <th><?php esc_html_e('Before text', 'wpc-composite-products');?></th>
                                <td>
                                    <div class="w100">
								<textarea name="wooco_before_text"
                                          placeholder="<?php esc_html_e('The text before Menu Packages', 'wpc-composite-products');?>"><?php echo stripslashes(get_post_meta($post_id, 'wooco_before_text', true)); ?></textarea>
                                    </div>
                                </td>
                            </tr>
                            <tr class="wooco_tr_space">
                                <th><?php esc_html_e('After text', 'wpc-composite-products');?></th>
                                <td>
                                    <div class="w100">
								<textarea name="wooco_after_text"
                                          placeholder="<?php esc_html_e('The text after Menu Packages', 'wpc-composite-products');?>"><?php echo stripslashes(get_post_meta($post_id, 'wooco_after_text', true)); ?></textarea>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>
					<?php
}

                function wooco_save_option_field($post_id) {
                    if (isset($_POST['wooco_components'])) {
                        update_post_meta($post_id, 'wooco_components', $this->wooco_format_array($_POST['wooco_components']));
                    } else {
                        delete_post_meta($post_id, 'wooco_components');
                    }
                    if (isset($_POST['wooco_pricing'])) {
                        update_post_meta($post_id, 'wooco_pricing', sanitize_text_field($_POST['wooco_pricing']));
                    }
                    if (isset($_POST['wooco_discount_percent'])) {
                        update_post_meta($post_id, 'wooco_discount_percent', sanitize_text_field($_POST['wooco_discount_percent']));
                    }
                    if (isset($_POST['wooco_qty_min'])) {
                        update_post_meta($post_id, 'wooco_qty_min', sanitize_text_field($_POST['wooco_qty_min']));
                    }
                    if (isset($_POST['wooco_qty_max'])) {
                        update_post_meta($post_id, 'wooco_qty_max', sanitize_text_field($_POST['wooco_qty_max']));
                    }
                    if (isset($_POST['wooco_shipping_fee'])) {
                        update_post_meta($post_id, 'wooco_shipping_fee', sanitize_text_field($_POST['wooco_shipping_fee']));
                    }
                    if (isset($_POST['wooco_before_text']) && ($_POST['wooco_before_text'] !== '')) {
                        update_post_meta($post_id, 'wooco_before_text', addslashes($_POST['wooco_before_text']));
                    } else {
                        delete_post_meta($post_id, 'wooco_before_text');
                    }
                    if (isset($_POST['wooco_after_text']) && ($_POST['wooco_after_text'] !== '')) {
                        update_post_meta($post_id, 'wooco_after_text', addslashes($_POST['wooco_after_text']));
                    } else {
                        delete_post_meta($post_id, 'wooco_after_text');
                    }
                }

                function wooco_add_to_cart_form() {
                    $this->wooco_show_items();
                    // wc_get_template('single-product/add-to-cart/simple.php');
                }

                function wooco_add_to_cart_button() {
                    global $product;
                    if ($product->is_type('composite')) {
                        echo '<input name="wooco_ids" class="wooco_ids wooco-ids" type="hidden" value=""/>';
                    }
                }

                function wooco_loop_add_to_cart_link($link, $product) {
                    if ($product->is_type('composite')) {
                        $link = str_replace('ajax_add_to_cart', '', $link);
                    }

                    return $link;
                }

                function wooco_cart_shipping_packages($packages) {
                    if (!empty($packages)) {
                        foreach ($packages as $package_key => $package) {
                            if (!empty($package['contents'])) {
                                foreach ($package['contents'] as $cart_item_key => $cart_item) {
                                    if (isset($cart_item['wooco_parent_id']) && ($cart_item['wooco_parent_id'] !== '')) {
                                        if (get_post_meta($cart_item['wooco_parent_id'], 'wooco_shipping_fee', true) !== 'each') {
                                            unset($packages[$package_key]['contents'][$cart_item_key]);
                                        }
                                    }
                                    if (isset($cart_item['wooco_ids']) && ($cart_item['wooco_ids'] !== '')) {
                                        if (get_post_meta($cart_item['data']->get_id(), 'wooco_shipping_fee', true) === 'each') {
                                            unset($packages[$package_key]['contents'][$cart_item_key]);
                                        }
                                    }
                                }
                            }
                        }
                    }

                    return $packages;
                }

                function wooco_get_price_html($price, $product) {
                    if ($product->is_type('composite') && !$product->is_fixed_price()) {
                        switch (get_option('_wooco_price_format', 'from_regular')) {
                            case 'from_regular':
                                return esc_html__('From', 'wpc-composite-products') . ' ' . wc_price($product->get_regular_price());
                                break;
                            case 'from_sale':
                                return esc_html__('From', 'wpc-composite-products') . ' ' . wc_price($product->get_price());
                                break;
                        }
                    }

                    return $price;
                }

                function wooco_order_again_cart_item_data($item_data, $item, $order) {
                    if (isset($item['wooco_ids'])) {
                        $item_data['wooco_order_again'] = 'yes';
                    }

                    return $item_data;
                }

                function wooco_cart_loaded_from_session() {
                    foreach (WC()->cart->cart_contents as $cart_item_key => $cart_item) {
                        if (isset($cart_item['wooco_order_again'])) {
                            WC()->cart->remove_cart_item($cart_item_key);
                            wc_add_notice(sprintf(esc_html__('The composite "%s" could not be added to your cart from order again button. Please buy it directly.', 'wpc-composite-products'), $cart_item['data']->get_name()), 'error');
                        }
                    }
                }

                // outputs the product html
                function wooco_show_items() {
                    global $product;
                    $product_id = $product->get_id();
                    $category_name = get_the_category_by_ID($product->get_category_ids()[0]);

                    $is_shabbos = $category_name == 'Shabbos';
                    $shabbos_sections = ['night' => 'Night Meal', 'day' => 'Day Meal', 'extras' => 'Extras'];

                    $is_supper = $category_name == 'Gourmet Meals';
                    $supper_sections = ['first' => 'First', 'main' => 'Main', 'extras' => 'Extras'];

                    $sections = ($is_shabbos) ? $shabbos_sections : $supper_sections;

                    if ($wooco_components = $product->get_components()) {

                        // Send data to Vue!!!======================================================================
                        vue_output_menu_packages($product, $sections);
                        // =========================================================================================
                    }
                }

                function wooco_get_products($type, $data, $default = 0) {
                    $wooco_products = $wooco_args = array();
                    $ids = explode(',', $data);
                    switch ($type) {
                        case 'products':
                            if (!in_array($default, $ids)) {
                                //check default value
                                array_unshift($ids, $default);
                            }

                            foreach ($ids as $id) {
                                $wooco_product = wc_get_product($id);

                                if (!$wooco_product) {
                                    continue;
                                }

                                if ($wooco_product->is_type('simple') || $wooco_product->is_type('variation')) {
                                    $wooco_product_img = wp_get_attachment_image_src($wooco_product->get_image_id(), 'thumbnail');
                                    $wooco_products[] = array(
                                        'id' => $wooco_product->get_id(),
                                        'name' => $wooco_product->get_name(),
                                        'price' => $wooco_product->get_price(),
                                        'link' => get_permalink($wooco_product->get_id()),
                                        'price_html' => htmlentities($wooco_product->get_price_html()),
                                        'image' => $wooco_product_img[0],
                                        'purchasable' => $wooco_product->is_in_stock() && $wooco_product->is_purchasable() ? 'yes' : 'no',
                                    );
                                }

                                if ($wooco_product->is_type('variable')) {
                                    $childs = $wooco_product->get_children();
                                    if (!empty($childs)) {
                                        foreach ($childs as $child) {
                                            $wooco_product_child = wc_get_product($child);
                                            if (!$wooco_product_child) {
                                                continue;
                                            }
                                            $wooco_product_child_img = wp_get_attachment_image_src($wooco_product_child->get_image_id(), 'thumbnail');
                                            $wooco_products[] = array(
                                                'id' => $wooco_product_child->get_id(),
                                                'name' => $wooco_product_child->get_name(),
                                                'price' => $wooco_product_child->get_price(),
                                                'link' => get_permalink($wooco_product_child->get_id()),
                                                'price_html' => htmlentities($wooco_product_child->get_price_html()),
                                                'image' => $wooco_product_child_img[0],
                                                'purchasable' => $wooco_product_child->is_in_stock() && $wooco_product_child->is_purchasable() ? 'yes' : 'no',
                                            );
                                        }
                                    }
                                }
                            }
                            break;
                        case 'categories':
                            $has_default = false;

                            $wooco_args = array(
                                'post_type' => 'product',
                                'post_status' => 'publish',
                                'ignore_sticky_posts' => 1,
                                'posts_per_page' => '100',
                                'tax_query' => array(
                                    array(
                                        'taxonomy' => 'product_cat',
                                        'field' => 'term_id',
                                        'terms' => $ids,
                                        'operator' => 'IN',
                                    ),
                                ),
                            );

                            $wooco_loop = new WP_Query($wooco_args);
                            if ($wooco_loop->have_posts()) {
                                while ($wooco_loop->have_posts()) {
                                    $wooco_loop->the_post();
                                    $wooco_id = get_the_ID();
                                    $wooco_product = wc_get_product($wooco_id);

                                    if (!$wooco_product) {
                                        continue;
                                    }

                                    if ($wooco_product->is_type('simple')) {
                                        $wooco_product_img = wp_get_attachment_image_src($wooco_product->get_image_id(), 'thumbnail');
                                        $wooco_products[] = array(
                                            'id' => $wooco_product->get_id(),
                                            'name' => $wooco_product->get_name(),
                                            'price' => $wooco_product->get_price(),
                                            'link' => get_permalink($wooco_product->get_id()),
                                            'price_html' => htmlentities($wooco_product->get_price_html()),
                                            'image' => $wooco_product_img[0],
                                            'purchasable' => $wooco_product->is_in_stock() && $wooco_product->is_purchasable() ? 'yes' : 'no',
                                        );
                                        if ($wooco_product->get_id() == $default) {
                                            $has_default = true;
                                        }
                                    }

                                    if ($wooco_product->is_type('variable')) {
                                        $childs = $wooco_product->get_children();
                                        if (!empty($childs)) {
                                            foreach ($childs as $child) {
                                                $wooco_product_child = wc_get_product($child);
                                                if (!$wooco_product_child) {
                                                    continue;
                                                }
                                                $wooco_product_child_img = wp_get_attachment_image_src($wooco_product_child->get_image_id(), 'thumbnail');
                                                $wooco_products[] = array(
                                                    'id' => $wooco_product_child->get_id(),
                                                    'name' => $wooco_product_child->get_name(),
                                                    'price' => $wooco_product_child->get_price(),
                                                    'link' => get_permalink($wooco_product_child->get_id()),
                                                    'price_html' => htmlentities($wooco_product_child->get_price_html()),
                                                    'image' => $wooco_product_child_img[0],
                                                    'purchasable' => $wooco_product_child->is_in_stock() && $wooco_product_child->is_purchasable() ? 'yes' : 'no',
                                                );
                                                if ($wooco_product_child->get_id() == $default) {
                                                    $has_default = true;
                                                }
                                            }
                                        }
                                    }
                                }
                                wp_reset_postdata();
                            }

                            if (!$has_default) {
                                //add default product
                                $wooco_product_default = wc_get_product($default);
                                if ($wooco_product_default) {
                                    $wooco_product_default_img = wp_get_attachment_image_src($wooco_product_default->get_image_id(), 'thumbnail');
                                    array_unshift($wooco_products, array(
                                        'id' => $wooco_product_default->get_id(),
                                        'name' => $wooco_product_default->get_name(),
                                        'price' => $wooco_product_default->get_price(),
                                        'link' => get_permalink($wooco_product_default->get_id()),
                                        'price_html' => htmlentities($wooco_product_default->get_price_html()),
                                        'image' => $wooco_product_default_img[0],
                                        'purchasable' => $wooco_product_default->is_in_stock() && $wooco_product_default->is_purchasable() ? 'yes' : 'no',
                                    ));
                                }
                            }

                            break;
                    }

                    if (count($wooco_products) > 0) {
                        return $wooco_products;
                    }

                    return false;
                }

                function wooco_clean_ids($ids) {
                    $ids = preg_replace('/[^,\/0-9]/', '', $ids);

                    return $ids;
                }

                function wooco_format_array($array) {
                    $formatted_array = array();
                    foreach (array_keys($array) as $fieldKey) {
                        foreach ($array[$fieldKey] as $key => $value) {
                            $formatted_array[$key][$fieldKey] = $value;
                        }
                    }

                    return $formatted_array;
                }
            }

            new WPcleverWooco();
        }
    }
}

if (!function_exists('wooco_notice_wc')) {
    function wooco_notice_wc() {
        ?>
        <div class="error">
            <p><strong>WPC Menu Packages</strong> requires WooCommerce version 3.0.0 or greater.</p>
        </div>
		<?php
}
}
