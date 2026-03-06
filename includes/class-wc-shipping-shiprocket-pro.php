<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WC_Shipping_Shiprocket_Pro extends WC_Shipping_Method {

    public function __construct( $instance_id = 0 ) {
        $this->id                 = 'shiprocket_pro';
        $this->instance_id        = absint( $instance_id );
        $this->method_title       = 'Shiprocket Pro';
        $this->method_description = 'Dynamic Express and Standard Shipping via Shiprocket';
        $this->enabled            = 'yes';
        $this->title              = 'Shiprocket Shipping';

        $this->init();
    }

    public function init() {
        $this->init_form_fields();
        $this->init_settings();
        add_action(
            'woocommerce_update_options_shipping_' . $this->id,
            [ $this, 'process_admin_options' ]
        );
    }

    public function calculate_shipping( $package = [] ) {

        if ( empty( $package['destination']['postcode'] ) ) return;

        $pincode = sanitize_text_field( $package['destination']['postcode'] );
        $service = SR_API_Handler::check_serviceability( $pincode );

        if ( ! $service || empty( $service['is_serviceable'] ) ) return;

        $threshold       = (float) get_option( 'sr_free_shipping_threshold', 299 );
        $standard_charge = (float) get_option( 'sr_standard_charge', 99 );
        $express_charge  = (float) get_option( 'sr_express_charge', 150 );
        $express_enabled = get_option( 'sr_express_enable', 'no' ) === 'yes';

        $cart_total = WC()->cart ? ( WC()->cart->get_subtotal() + WC()->cart->get_subtotal_tax() ) : 0;

        /* ── Standard Shipping ─────────────────────────────── */
        if ( ! empty( $service['standard'] ) ) {

            $standard_cost = ( $cart_total >= $threshold ) ? 0 : $standard_charge;

            // Plain text label — HTML is injected via the filter below
            $this->add_rate( [
                'id'        => $this->id . '_standard',
                'label'     => 'Standard Delivery',
                'cost'      => $standard_cost,
                'meta_data' => [
                    'sr_edd'  => $service['standard']['formatted_range'],
                    'sr_type' => 'standard',
                ],
            ] );
        }

        /* ── Express Shipping (Blue Dart Air) ──────────────── */
        if ( $express_enabled ) {

            if ( ! empty( $service['express'] ) ) {
                $this->add_rate( [
                    'id'        => $this->id . '_express',
                    'label'     => 'Express Delivery',
                    'cost'      => $express_charge,
                    'meta_data' => [
                        'sr_edd'  => $service['express']['formatted'],
                        'sr_type' => 'express',
                    ],
                ] );
            } else {
                // Air not available — still register so the row renders greyed out
                $this->add_rate( [
                    'id'        => $this->id . '_express',
                    'label'     => 'Express Delivery',
                    'cost'      => $express_charge,
                    'meta_data' => [
                        'sr_edd'         => '',
                        'sr_type'        => 'express',
                        'sr_unavailable' => '1',
                    ],
                ] );
            }
        }
    }
}
