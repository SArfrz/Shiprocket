<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SR_API_Handler {

    /**
     * Get Shiprocket Auth Token (cached in transient for 1 hour)
     */
    public static function get_auth_token() {

        $token = get_transient( 'sr_api_token' );
        if ( $token ) return $token;

        $email    = get_option( 'sr_api_email' );
        $password = get_option( 'sr_api_password' );

        if ( empty( $email ) || empty( $password ) ) return false;

        $response = wp_remote_post(
            'https://apiv2.shiprocket.in/v1/external/auth/login',
            [
                'headers' => [ 'Content-Type' => 'application/json' ],
                'body'    => json_encode( [ 'email' => $email, 'password' => $password ] ),
                'timeout' => 15,
            ]
        );

        if ( is_wp_error( $response ) ) return false;

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['token'] ) ) {
            set_transient( 'sr_api_token', $body['token'], 3600 );
            return $body['token'];
        }

        return false;
    }

    /**
     * Add N business days to a timestamp, skipping weekends.
     * Returns a Unix timestamp.
     */
    public static function add_business_days( $start_ts, $days ) {
        $added = 0;
        $ts    = $start_ts;

        while ( $added < $days ) {
            $ts   += DAY_IN_SECONDS;
            $dow   = (int) date( 'N', $ts ); // 1=Mon … 7=Sun
            if ( $dow < 6 ) { // Mon–Fri
                $added++;
            }
        }

        return $ts;
    }

    /**
     * Check Serviceability + Calculate Dynamic EDD
     *
     * Returns array with keys:
     *   is_serviceable (bool)
     *   city, state
     *   standard  => [ 'formatted' => '15 Mar', 'days' => 3 ]  (may be absent)
     *   express   => [ 'formatted' => '14 Mar', 'days' => 2 ]  (may be absent — means unavailable)
     */
    public static function check_serviceability( $pincode ) {

        $token = self::get_auth_token();
        if ( ! $token ) return false;

        $pickup_postcode = get_option( 'sr_pickup_postcode', '110011' );
        $weight          = 0.5;

        $transient_key = 'sr_serviceability_v2_' . $pincode . '_' . $weight;
        $cached        = get_transient( $transient_key );
        if ( $cached !== false ) return $cached;

        $url = add_query_arg( [
            'pickup_postcode'   => $pickup_postcode,
            'delivery_postcode' => $pincode,
            'weight'            => $weight,
            'cod'               => 1,
        ], 'https://apiv2.shiprocket.in/v1/external/courier/serviceability/' );

        $response = wp_remote_get( $url, [
            'headers' => [ 'Authorization' => 'Bearer ' . $token ],
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) return false;

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if (
            empty( $data['status'] ) ||
            $data['status'] != 200   ||
            empty( $data['data']['available_courier_companies'] )
        ) {
            return [ 'is_serviceable' => false ];
        }

        $couriers = $data['data']['available_courier_companies'];

        $fastest_air     = null;
        $fastest_surface = null;

        foreach ( $couriers as $courier ) {
            if ( ! isset( $courier['etd_hours'] ) ) continue;

            $days = ceil( $courier['etd_hours'] / 24 );

            if ( isset( $courier['is_surface'] ) && $courier['is_surface'] ) {
                // Surface = Standard
                if ( ! $fastest_surface || $days < $fastest_surface['days'] ) {
                    $fastest_surface = [
                        'days'  => (int) $days,
                        'city'  => $courier['city']  ?? '',
                        'state' => $courier['state'] ?? '',
                    ];
                }
            } elseif ( isset( $courier['is_surface'] ) && ! $courier['is_surface'] ) {
                // Air = Express (Blue Dart Air etc.)
                if ( ! $fastest_air || $days < $fastest_air['days'] ) {
                    $fastest_air = [
                        'days'  => (int) $days,
                        'city'  => $courier['city']  ?? '',
                        'state' => $courier['state'] ?? '',
                    ];
                }
            }
        }

        if ( ! $fastest_air && ! $fastest_surface ) {
            return [ 'is_serviceable' => false ];
        }

        // ── Cutoff Time Logic ──────────────────────────────────
        // After cutoff, order is printed next day — add 1 extra processing day.
        $cutoff_time  = get_option( 'sr_cutoff_time', '17:00' );
        $current_time = current_time( 'H:i' );
        $cutoff_extra = ( $current_time > $cutoff_time ) ? 1 : 0;

        // ── Processing Buffers (business days, Sundays skipped) ──
        // This is YOUR internal time: printing + packing + label generation.
        // Courier API EDD already assumes label ready today, pickup tomorrow.
        // So: final EDD = today + buffer (business days) + courier API days (calendar).
        $standard_buffer = (int) get_option( 'sr_standard_buffer', 2 );
        $express_buffer  = (int) get_option( 'sr_express_buffer',  1 );

        $now_ts = current_time( 'timestamp' );

        $result = [
            'is_serviceable' => true,
            'city'           => $fastest_air['city']  ?? $fastest_surface['city'],
            'state'          => $fastest_air['state'] ?? $fastest_surface['state'],
        ];

        // ── Standard (Surface) EDD — shown as a date range ───
        if ( $fastest_surface ) {
            // Step 1: add your processing buffer as business days (+ cutoff if after 5 PM)
            $after_buffer_ts = self::add_business_days( $now_ts, $standard_buffer + $cutoff_extra );
            // Step 2: add courier transit days on top (calendar days — courier doesn't skip weekends)
            $earliest_ts     = $after_buffer_ts + ( $fastest_surface['days'] * DAY_IN_SECONDS );
            // Range end: +3 calendar days to account for natural courier variance
            $latest_ts       = $earliest_ts + ( 3 * DAY_IN_SECONDS );

            $result['standard'] = [
                'formatted'       => date( 'j M', $earliest_ts ),
                'formatted_range' => date( 'j', $earliest_ts ) . '–' . date( 'j M', $latest_ts ),
            ];
        }

        // ── Express / Blue Dart Air EDD — single date ────────
        if ( $fastest_air ) {
            $after_buffer_ts = self::add_business_days( $now_ts, $express_buffer + $cutoff_extra );
            $final_ts        = $after_buffer_ts + ( $fastest_air['days'] * DAY_IN_SECONDS );

            $result['express'] = [
                'formatted' => date( 'j M', $final_ts ),
            ];
        }
        // If $fastest_air is null, 'express' key is simply absent → signals unavailable

        set_transient( $transient_key, $result, 12 * HOUR_IN_SECONDS );

        return $result;
    }
}
