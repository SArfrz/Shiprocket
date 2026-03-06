jQuery(function ($) {

    /* ── Helper: populate billing/shipping fields ─── */
    function updateFields(type, city, stateCode, stateName) {
        var $city  = $('#' + type + '_city');
        var $state = $('#' + type + '_state');

        if (city && $city.length)       $city.val(city);
        if (stateCode && $state.length) $state.val(stateCode).trigger('change').trigger('selectWoo:select');

        sessionStorage.setItem('sr_last_checked_city',       city);
        sessionStorage.setItem('sr_last_checked_state_code', stateCode);
        sessionStorage.setItem('sr_last_checked_state_name', stateName);
    }

    /* ── Product Page: Pincode Checker ─────────────── */
    var $btn    = $('#sr_pincode_btn');
    var $input  = $('#sr_pincode_input');
    var $result = $('#sr_pincode_result');

    // Allow pressing Enter inside the input
    $input.on('keydown', function (e) {
        if (e.key === 'Enter') $btn.trigger('click');
    });

    // Only allow numeric input
    $input.on('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 6);
    });

    $btn.on('click', function () {
        var pin = $input.val().trim();

        if (!/^\d{6}$/.test(pin)) {
            $result.html('<span class="sr-result-error">&#x26A0; Please enter a valid 6-digit pincode.</span>');
            return;
        }

        $btn.addClass('sr-loading').text('Checking…');
        $result.html('');

        $.post(sr_ajax.ajax_url, {
            action:   'sr_check_pincode',
            pincode:  pin,
            security: sr_ajax.nonce
        }, function (res) {
            $btn.removeClass('sr-loading').text('Check');
            if (res.success) {
                $result.html(res.data.message);
                sessionStorage.setItem('sr_last_checked_pincode',    pin);
                sessionStorage.setItem('sr_last_checked_city',       res.data.city);
                sessionStorage.setItem('sr_last_checked_state_code', res.data.state_code);
                sessionStorage.setItem('sr_last_checked_state_name', res.data.state_name);
            } else {
                $result.html('<span class="sr-result-error">&#x26A0; ' + (res.data.message || 'Delivery not available.') + '</span>');
            }
        }).fail(function () {
            $btn.removeClass('sr-loading').text('Check');
            $result.html('<span class="sr-result-error">&#x26A0; Something went wrong. Please try again.</span>');
        });
    });

    /* ── Checkout: Auto-fill City & State on Postcode blur ── */
    $(document.body).on('blur change', '#billing_postcode, #shipping_postcode', function () {
        var $el  = $(this);
        var pin  = $el.val().trim();
        var type = $el.attr('id').indexOf('billing') !== -1 ? 'billing' : 'shipping';

        if (/^\d{6}$/.test(pin)) {
            $.post(sr_ajax.ajax_url, {
                action:   'sr_fetch_city_state_by_pincode',
                pincode:  pin,
                security: sr_ajax.nonce
            }, function (res) {
                if (res.success) {
                    updateFields(type, res.data.city, res.data.state_code, res.data.state_name);
                    $('body').trigger('update_checkout');
                }
            });
        }
    });

    /* ── Checkout: Restore session on load ─────────── */
    function restoreSession() {
        var pin = sessionStorage.getItem('sr_last_checked_pincode');
        if (!pin) return;
        if (!$('body').hasClass('woocommerce-checkout') && !$('body').hasClass('woocommerce-cart')) return;

        ['billing', 'shipping'].forEach(function (t) {
            var $pinField = $('#' + t + '_postcode');
            if ($pinField.length && !$pinField.val()) {
                $pinField.val(pin);
                updateFields(
                    t,
                    sessionStorage.getItem('sr_last_checked_city'),
                    sessionStorage.getItem('sr_last_checked_state_code'),
                    sessionStorage.getItem('sr_last_checked_state_name')
                );
            }
        });
    }

    restoreSession();
    $(document.body).on('updated_checkout', restoreSession);
});
