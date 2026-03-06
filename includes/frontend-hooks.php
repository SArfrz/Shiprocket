<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ── Enqueue Scripts & Styles ──────────────────────── */

function sr_pro_enqueue_scripts() {

    wp_enqueue_style(
        'sr-pro-css',
        SR_PRO_URL . 'assets/css/shiprocket-pro.css',
        [],
        SR_PRO_VERSION
    );

    wp_enqueue_script(
        'sr-pro-js',
        SR_PRO_URL . 'assets/js/shiprocket-enhanced.js',
        [ 'jquery' ],
        SR_PRO_VERSION,
        true
    );

    wp_localize_script( 'sr-pro-js', 'sr_ajax', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'sr-pincode-nonce' ),
    ] );
}
add_action( 'wp_enqueue_scripts', 'sr_pro_enqueue_scripts' );


/* ── Pincode Checker UI (Product Page) ─────────────── */

function sr_pro_display_pincode_checker() { ?>
<div class="sr-pincode-wrapper">
    <p class="sr-pincode-label">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
        Check Delivery
    </p>
    <div class="sr-pincode-input-group">
        <input type="text" id="sr_pincode_input" placeholder="Enter 6-digit pincode" maxlength="6" inputmode="numeric" pattern="[0-9]*" />
        <button type="button" id="sr_pincode_btn" class="button alt">Check</button>
    </div>
    <div id="sr_pincode_result"></div>
</div>
<?php }
add_action( 'woocommerce_before_add_to_cart_button', 'sr_pro_display_pincode_checker' );


/* ── AJAX: Pincode Check ────────────────────────────── */

function sr_pro_ajax_check_pincode() {

    check_ajax_referer( 'sr-pincode-nonce', 'security' );

    $pincode = sanitize_text_field( $_POST['pincode'] ?? '' );

    if ( ! preg_match( '/^\d{6}$/', $pincode ) ) {
        wp_send_json_error( [ 'message' => 'Please enter a valid 6-digit pincode.' ] );
    }

    $service = SR_API_Handler::check_serviceability( $pincode );

    if ( ! $service || empty( $service['is_serviceable'] ) ) {
        wp_send_json_error( [ 'message' => 'Sorry, delivery is not available at this pincode.' ] );
    }

    $states_map = [
        'Andhra Pradesh' => 'AP', 'Arunachal Pradesh' => 'AR', 'Assam' => 'AS',
        'Bihar' => 'BR', 'Chhattisgarh' => 'CT', 'Goa' => 'GA', 'Gujarat' => 'GJ',
        'Haryana' => 'HR', 'Himachal Pradesh' => 'HP', 'Jharkhand' => 'JH',
        'Karnataka' => 'KA', 'Kerala' => 'KL', 'Madhya Pradesh' => 'MP',
        'Maharashtra' => 'MH', 'Manipur' => 'MN', 'Meghalaya' => 'ML',
        'Mizoram' => 'MZ', 'Nagaland' => 'NL', 'Odisha' => 'OR', 'Punjab' => 'PB',
        'Rajasthan' => 'RJ', 'Sikkim' => 'SK', 'Tamil Nadu' => 'TN',
        'Telangana' => 'TS', 'Tripura' => 'TR', 'Uttar Pradesh' => 'UP',
        'Uttarakhand' => 'UK', 'West Bengal' => 'WB', 'Delhi' => 'DL',
        'Chandigarh' => 'CH',
    ];

    $state_name = $service['state'];
    $state_code = $states_map[ $state_name ] ?? ( $service['state_code'] ?? $state_name );

    $express_enabled = get_option( 'sr_express_enable', 'no' ) === 'yes';

    // Build HTML for product-page result
    $html  = '<p class="sr-result-location">Delivery available in <strong>' . esc_html( $service['city'] ) . ', ' . esc_html( $state_name ) . '</strong></p>';
    $html .= '<div class="sr-delivery-cards">';

    // Standard card — always shown
    if ( ! empty( $service['standard'] ) ) {
        $html .= '<div class="sr-delivery-card">';
        $html .= '<div class="sr-delivery-card-left"><span class="sr-delivery-card-icon">📦</span><div class="sr-delivery-card-info"><span class="sr-delivery-card-name">Standard Delivery</span><span class="sr-delivery-card-date">' . esc_html( $service['standard']['formatted_range'] ) . '</span></div></div>';
        $html .= '<span class="sr-delivery-card-tag sr-tag-standard">Free / ₹99</span>';
        $html .= '</div>';
    }

    // Express card
    if ( $express_enabled ) {
        if ( ! empty( $service['express'] ) ) {
            $html .= '<div class="sr-delivery-card sr-card-express">';
            $html .= '<div class="sr-delivery-card-left"><span class="sr-delivery-card-icon">⚡</span><div class="sr-delivery-card-info"><span class="sr-delivery-card-name">Express Delivery</span><span class="sr-delivery-card-date">By ' . esc_html( $service['express']['formatted'] ) . '</span></div></div>';
            $html .= '<span class="sr-delivery-card-tag sr-tag-express">Fastest</span>';
            $html .= '</div>';
        } else {
            // Express not available — greyed card
            $html .= '<div class="sr-delivery-card" style="opacity:.45;filter:grayscale(.6);">';
            $html .= '<div class="sr-delivery-card-left"><span class="sr-delivery-card-icon">⚡</span><div class="sr-delivery-card-info"><span class="sr-delivery-card-name" style="color:#6b7280;">Express Delivery</span><span class="sr-delivery-card-date" style="color:#9ca3af;">Not available at this pincode</span></div></div>';
            $html .= '<span class="sr-delivery-card-tag" style="background:#f3f4f6;color:#9ca3af;">Unavailable</span>';
            $html .= '</div>';
        }
    }

    $html .= '</div>'; // .sr-delivery-cards

    wp_send_json_success( [
        'message'    => $html,
        'city'       => $service['city'],
        'state_name' => $state_name,
        'state_code' => $state_code,
    ] );
}
add_action( 'wp_ajax_sr_check_pincode',                  'sr_pro_ajax_check_pincode' );
add_action( 'wp_ajax_nopriv_sr_check_pincode',           'sr_pro_ajax_check_pincode' );
add_action( 'wp_ajax_sr_fetch_city_state_by_pincode',    'sr_pro_ajax_check_pincode' );
add_action( 'wp_ajax_nopriv_sr_fetch_city_state_by_pincode', 'sr_pro_ajax_check_pincode' );


/* ── Styled Shipping Label (via WC filter) ──────────── */
// $label at this point = "Method Name: <price HTML>" — we must keep the price HTML intact.
// We rebuild the label by using the rate's label (plain name) + extracting the price span from $label.
function sr_pro_shipping_label( $label, $method ) {
    if ( strpos( $method->get_id(), 'shiprocket_pro' ) === false ) return $label;

    $meta        = $method->get_meta_data();
    $edd         = $meta['sr_edd']         ?? '';
    $unavailable = $meta['sr_unavailable'] ?? '';

    // Extract the price HTML that WC appended (everything after the first ": ")
    // $label format: "Method Name: <span class="woocommerce-Price-amount...">...</span>"
    $rate_label  = $method->get_label(); // plain-text name, e.g. "Standard Delivery"
    $price_html  = '';
    $colon_pos   = strpos( $label, ': ' );
    if ( $colon_pos !== false ) {
        $price_html = substr( $label, $colon_pos + 2 ); // the raw price HTML
    }

    if ( $unavailable === '1' ) {
        return '<span class="sr-checkout-method-item sr-method-unavailable">'
             . '<span class="sr-method-title">' . esc_html( $rate_label ) . ( $price_html ? ': ' . $price_html : '' ) . '</span>'
             . '<span class="sr-method-edd sr-method-na">Not available for your location</span>'
             . '</span>';
    }

    if ( $edd ) {
        $type       = $meta['sr_type'] ?? '';
        $prefix     = ( $type === 'standard' ) ? 'Between ' : 'Estimated by ';
        $disclaimer = '<span class="sr-edd-disclaimer">Estimated delivery date &mdash; actual delivery may vary by 1&ndash;2 days due to courier or logistics conditions beyond our control.</span>';
        return '<span class="sr-checkout-method-item">'
             . '<span class="sr-method-title">' . esc_html( $rate_label ) . ( $price_html ? ': ' . $price_html : '' ) . '</span>'
             . '<span class="sr-method-edd">' . $prefix . esc_html( $edd ) . '</span>'
             . $disclaimer
             . '</span>';
    }

    return $label;
}
add_filter( 'woocommerce_cart_shipping_method_full_label', 'sr_pro_shipping_label', 10, 2 );



/* ── Disable Unavailable Express Option on Checkout ── */
function sr_pro_disable_unavailable_rate() {
    if ( ! is_checkout() ) return;
    ?>
    <script>
    jQuery(function($){
        function srGreyOutUnavailable(){
            jQuery('input[name^="shipping_method"]').each(function(){
                var $input = jQuery(this);
                var $label = jQuery('label[for="' + $input.attr('id') + '"]');
                if ($label.find('.sr-method-unavailable').length) {
                    $input.prop('disabled', true);
                    $label.closest('li').css({'opacity':'0.45','cursor':'not-allowed','pointer-events':'none'});
                    if ($input.is(':checked')) {
                        jQuery('input[name^="shipping_method"]').not($input).first().prop('checked', true).trigger('change');
                    }
                }
            });
        }
        jQuery(document.body).on('updated_checkout', srGreyOutUnavailable);
        srGreyOutUnavailable();
    });
    </script>
    <?php
}
add_action( 'woocommerce_after_checkout_form', 'sr_pro_disable_unavailable_rate' );


/* ── Free Shipping Notice ───────────────────────────── */

function sr_pro_display_shipping_notice() {
    if ( ! is_cart() && ! is_checkout() ) return;
    if ( ! WC()->cart ) return;

    $threshold = (float) get_option( 'sr_free_shipping_threshold', 299 );
    if ( $threshold <= 0 || WC()->cart->is_empty() ) return;

    $cart_total = WC()->cart->get_subtotal() + WC()->cart->get_subtotal_tax();

    if ( $cart_total < $threshold ) {
        $remaining = ceil( $threshold - $cart_total );
        $message   = 'Add ' . wc_price( $remaining ) . ' more to get <strong>Free Shipping!</strong>';
        $html      = '<tr><td colspan="2" style="color:#a00;font-weight:bold;text-align:center;padding:10px;background:#fff5f5;border:1px dashed #a00;">' . $message . '</td></tr>';
    } else {
        $html = '<tr><td colspan="2" style="color:green;font-weight:bold;text-align:center;padding:10px;background:#f5fff5;border:1px dashed green;">🎉 Yay! You\'ve unlocked <strong>Free Shipping!</strong> 🚚</td></tr>';
    }

    echo $html;
}
add_action( 'woocommerce_cart_totals_after_shipping',    'sr_pro_display_shipping_notice', 10 );
add_action( 'woocommerce_review_order_after_shipping',   'sr_pro_display_shipping_notice', 10 );
add_action( 'woocommerce_review_order_after_order_total','sr_pro_display_shipping_notice', 10 );
