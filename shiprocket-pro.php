<?php
/**
 * Plugin Name: Shiprocket Pro Enhanced
 * Plugin URI:  https://yourstore.com
 * Description: Advanced Shiprocket integration with dynamic Express/Standard pricing, EDD calculation, and Rapidshyp-style checkout UI.
 * Version:     4.0.0
 * Author:      Sarfaraz Akhtar
 * Text Domain: shiprocket-pro
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SR_PRO_VERSION', '4.0.0' );
define( 'SR_PRO_PATH', plugin_dir_path( __FILE__ ) );
define( 'SR_PRO_URL',  plugin_dir_url( __FILE__ ) );

require_once SR_PRO_PATH . 'includes/class-sr-api-handler.php';
require_once SR_PRO_PATH . 'includes/frontend-hooks.php';

/* ── Shipping Method Bootstrap ─────────────────────── */

function sr_pro_init_shipping_method() {
    if ( ! class_exists( 'WC_Shipping_Shiprocket_Pro' ) ) {
        require_once SR_PRO_PATH . 'includes/class-wc-shipping-shiprocket-pro.php';
    }
}
add_action( 'woocommerce_shipping_init', 'sr_pro_init_shipping_method' );

function sr_pro_add_shipping_method( $methods ) {
    $methods['shiprocket_pro'] = 'WC_Shipping_Shiprocket_Pro';
    return $methods;
}
add_filter( 'woocommerce_shipping_methods', 'sr_pro_add_shipping_method' );


/* ── Admin Menu ────────────────────────────────────── */

function sr_pro_add_admin_submenu() {
    add_submenu_page(
        'woocommerce',
        'Shiprocket Pro Settings',
        'Shiprocket Pro',
        'manage_woocommerce',
        'shiprocket-pro',
        'sr_pro_render_admin_settings_page'
    );
}
add_action( 'admin_menu', 'sr_pro_add_admin_submenu' );

function sr_pro_render_admin_settings_page() { ?>
    <div class="wrap">
        <h1>Shiprocket Pro Settings</h1>
        <form method="post" action="options.php">
            <?php
                settings_fields( 'sr_pro_settings_group' );
                do_settings_sections( 'shiprocket-pro' );
                submit_button();
            ?>
        </form>
    </div>
<?php }


/* ── Register Settings ─────────────────────────────── */

function sr_pro_register_settings() {

    $group = 'sr_pro_settings_group';

    // API
    register_setting( $group, 'sr_api_email' );
    register_setting( $group, 'sr_api_password' );
    register_setting( $group, 'sr_pickup_postcode' );

    // Pricing
    register_setting( $group, 'sr_free_shipping_threshold', [ 'sanitize_callback' => 'floatval' ] );
    register_setting( $group, 'sr_standard_charge',         [ 'sanitize_callback' => 'floatval' ] );
    register_setting( $group, 'sr_express_enable' );
    register_setting( $group, 'sr_express_charge',          [ 'sanitize_callback' => 'floatval' ] );

    // Time
    register_setting( $group, 'sr_cutoff_time' );
    register_setting( $group, 'sr_standard_buffer', [ 'sanitize_callback' => 'intval' ] );
    register_setting( $group, 'sr_express_buffer',  [ 'sanitize_callback' => 'intval' ] );

    // Sections
    add_settings_section( 'sr_api_section',     'API Configuration',          null, 'shiprocket-pro' );
    add_settings_section( 'sr_pricing_section', 'Pricing & Threshold Logic',  null, 'shiprocket-pro' );
    add_settings_section( 'sr_time_section',    'Delivery Estimate Rules',    null, 'shiprocket-pro' );

    // API Fields
    add_settings_field( 'sr_api_email',       'API Email',          'sr_pro_text_field', 'shiprocket-pro', 'sr_api_section',     [ 'id' => 'sr_api_email',       'type' => 'email'    ] );
    add_settings_field( 'sr_api_password',    'API Password',       'sr_pro_text_field', 'shiprocket-pro', 'sr_api_section',     [ 'id' => 'sr_api_password',    'type' => 'password' ] );
    add_settings_field( 'sr_pickup_postcode', 'Pickup Pincode',     'sr_pro_text_field', 'shiprocket-pro', 'sr_api_section',     [ 'id' => 'sr_pickup_postcode', 'default' => '110011' ] );

    // Pricing Fields
    add_settings_field( 'sr_free_shipping_threshold', 'Free Shipping Minimum Order (₹)', 'sr_pro_text_field',    'shiprocket-pro', 'sr_pricing_section', [ 'id' => 'sr_free_shipping_threshold', 'type' => 'number', 'default' => '299'  ] );
    add_settings_field( 'sr_standard_charge',         'Standard Base Charge (₹)',        'sr_pro_text_field',    'shiprocket-pro', 'sr_pricing_section', [ 'id' => 'sr_standard_charge',         'type' => 'number', 'default' => '99'   ] );
    add_settings_field( 'sr_express_enable',          'Enable Express Delivery',         'sr_pro_checkbox_field','shiprocket-pro', 'sr_pricing_section', [ 'id' => 'sr_express_enable' ] );
    add_settings_field( 'sr_express_charge',          'Express Base Charge (₹)',         'sr_pro_text_field',    'shiprocket-pro', 'sr_pricing_section', [ 'id' => 'sr_express_charge',          'type' => 'number', 'default' => '150'  ] );

    // Time Fields
    add_settings_field( 'sr_cutoff_time',     'Same-Day Processing Cutoff Time',                                     'sr_pro_text_field', 'shiprocket-pro', 'sr_time_section', [ 'id' => 'sr_cutoff_time',    'type' => 'time',   'default' => '17:00' ] );
    add_settings_field( 'sr_standard_buffer', 'Standard Delivery Extra Buffer (Business Days)',                      'sr_pro_text_field', 'shiprocket-pro', 'sr_time_section', [ 'id' => 'sr_standard_buffer','type' => 'number', 'default' => '2', 'desc' => 'Added on top of courier EDD for Standard shipping (e.g. 2 business days).' ] );
    add_settings_field( 'sr_express_buffer',  'Express Delivery Extra Buffer (Business Days)',                       'sr_pro_text_field', 'shiprocket-pro', 'sr_time_section', [ 'id' => 'sr_express_buffer', 'type' => 'number', 'default' => '1', 'desc' => 'Added on top of courier EDD for Express / Blue Dart Air (e.g. 1 business day).' ] );
}
add_action( 'admin_init', 'sr_pro_register_settings' );


/* ── Field Renderers ────────────────────────────────── */

function sr_pro_text_field( $args ) {
    $val = get_option( $args['id'], $args['default'] ?? '' );
    printf(
        '<input type="%s" name="%s" value="%s" class="regular-text">',
        esc_attr( $args['type'] ?? 'text' ),
        esc_attr( $args['id'] ),
        esc_attr( $val )
    );
    if ( ! empty( $args['desc'] ) ) {
        printf( '<p class="description">%s</p>', esc_html( $args['desc'] ) );
    }
}

function sr_pro_checkbox_field( $args ) {
    $val = get_option( $args['id'], 'no' );
    printf(
        '<input type="checkbox" name="%s" value="yes" %s>',
        esc_attr( $args['id'] ),
        checked( $val, 'yes', false )
    );
}
