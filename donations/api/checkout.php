<?php
/**
 * Checkout endpoints used by the donate page.
 *   POST api/checkout.php?action=create   – validate donor, create Razorpay subscription / order
 *   POST api/checkout.php?action=verify   – verify Razorpay signature after payment
 */
require __DIR__ . '/lib.php';

if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) {
	fail( 'Invalid request.', 405 );
}

// Only accept requests coming from our own pages (basic CSRF protection).
$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? $_SERVER['HTTP_ORIGIN'] : ( isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : '' );
if ( $origin ) {
	$oh = parse_url( $origin, PHP_URL_HOST );
	$me = isset( $_SERVER['HTTP_HOST'] ) ? preg_replace( '/:\d+$/', '', $_SERVER['HTTP_HOST'] ) : '';
	if ( $oh && $me && strcasecmp( preg_replace( '/^www\./', '', $oh ), preg_replace( '/^www\./', '', $me ) ) !== 0 ) {
		fail( 'Invalid request origin.', 403 );
	}
}

$action = in( 'action' );
$o      = opt();

/* ------------------------------------------------------------------ */
if ( 'create' === $action ) {
	if ( '' !== in( 'website' ) ) {
		fail( 'Unable to process the request.' ); // honeypot
	}
	if ( ! rate_ok( 'create', 12, 600 ) ) {
		fail( 'Too many attempts. Please wait a few minutes and try again.', 429 );
	}
	$freq = key_clean( in( 'frequency' ) );
	if ( ! in_array( $freq, allowed_freqs(), true ) ) {
		fail( 'This donation type is not available.' );
	}
	$once   = 'onetime' === $freq;
	$amount = (int) floor( (float) in( 'amount' ) );
	$min    = max( 1, (int) $o['min_amount'] );
	$max    = $once ? (int) $o['max_onetime'] : (int) $o['max_amount'];
	if ( $amount < $min ) {
		fail( 'Please enter at least ' . inr( $min * 100 ) . '.' );
	}
	if ( $max > 0 && $amount > $max ) {
		fail( 'Please enter at most ' . inr( $max * 100 ) . '.' );
	}

	$name  = in( 'name', 100 );
	$email = strtolower( in( 'email', 150 ) );
	$phone = preg_replace( '/\D/', '', in( 'phone', 20 ) );
	if ( 12 === strlen( $phone ) && 0 === strpos( $phone, '91' ) ) {
		$phone = substr( $phone, 2 );
	} elseif ( 11 === strlen( $phone ) && '0' === $phone[0] ) {
		$phone = substr( $phone, 1 );
	}
	$pan        = strtoupper( preg_replace( '/\s/', '', in( 'pan', 12 ) ) );
	$address    = in( 'address', 250 );
	$dedication = $o['allow_dedication'] ? in( 'dedication', 200 ) : '';
	$anonymous  = ( $o['allow_anonymous'] && '1' === in( 'anonymous' ) ) ? 1 : 0;
	$campaign   = in( 'campaign', 100 );

	if ( '' === $name ) {
		fail( 'Please enter your name.' );
	}
	if ( ! is_email( $email ) ) {
		fail( 'Please enter a valid email address.' );
	}
	if ( ! preg_match( '/^[6-9]\d{9}$/', $phone ) ) {
		fail( 'Please enter a valid 10-digit mobile number.' );
	}
	if ( '' !== $pan && ! preg_match( '/^[A-Z]{5}[0-9]{4}[A-Z]$/', $pan ) ) {
		fail( 'Please enter a valid PAN (e.g. ABCDE1234F) or leave it empty.' );
	}
	if ( $o['require_consent'] && ! $once && '1' !== in( 'consent' ) ) {
		fail( 'Please accept the AutoPay authorisation.' );
	}

	$paise = $amount * 100;
	$notes = array_filter( array(
		'source'     => 'ssy_ad',
		'name'       => $name,
		'email'      => $email,
		'phone'      => $phone,
		'pan'        => $pan,
		'campaign'   => $campaign,
		'frequency'  => $freq,
		'amount'     => (string) $paise,
		'dedication' => $dedication,
		'address'    => $address,
		'anonymous'  => $anonymous ? '1' : '',
	), 'strlen' );

	$common = array(
		'key'     => $o['key_id'],
		'org'     => $o['org_name'],
		'logo'    => $o['org_logo'],
		'amount'  => $paise,
		'prefill' => array( 'name' => $name, 'email' => $email, 'contact' => '+91' . $phone ),
		'notes'   => array( 'campaign' => $campaign ),
	);

	if ( $once ) {
		$order = rzp( 'POST', 'orders', array(
			'amount'   => $paise,
			'currency' => 'INR',
			'receipt'  => 'ssyad_' . time() . '_' . random_int( 100, 999 ),
			'notes'    => $notes,
		) );
		if ( rzp_err( $order ) ) {
			log_error( 'order', rzp_err( $order ) );
			fail( 'Could not start the payment. Please try again in a moment.', 502 );
		}
		kv_set( 'order_' . $order['id'], array(
			'name' => $name, 'email' => $email, 'phone' => $phone, 'pan' => $pan, 'address' => $address,
			'campaign' => $campaign, 'dedication' => $dedication, 'anonymous' => $anonymous, 'amount' => $paise,
		), time() + 2 * 86400 );
		ok( array_merge( $common, array(
			'mode'        => 'order',
			'order_id'    => $order['id'],
			'description' => 'Donation to ' . $o['org_name'],
		) ) );
	}

	// Recurring AutoPay.
	$years = (int) in( 'duration' );
	if ( ! array_key_exists( $years, durations() ) ) {
		$years = (int) $o['default_duration'];
	}
	$total = total_count( $freq, $years );
	$plan  = plan_id( $freq, $paise );
	if ( is_array( $plan ) ) {
		log_error( 'plan', rzp_err( $plan ) );
		fail( 'Could not start AutoPay. Please try again in a moment.', 502 );
	}
	$args = array(
		'plan_id'         => $plan,
		'total_count'     => $total,
		'quantity'        => 1,
		'customer_notify' => $o['customer_notify'] ? 1 : 0,
		'notes'           => $notes,
	);
	$sub = rzp( 'POST', 'subscriptions', $args );
	if ( rzp_err( $sub ) ) {
		$plan = plan_id( $freq, $paise, true ); // cached plan may belong to other keys
		if ( ! is_array( $plan ) ) {
			$args['plan_id'] = $plan;
			$sub             = rzp( 'POST', 'subscriptions', $args );
		}
	}
	if ( rzp_err( $sub ) ) {
		log_error( 'subscription', rzp_err( $sub ) );
		fail( 'Could not start AutoPay. Please try again in a moment.', 502 );
	}
	insert_sub( array(
		'sub_id'      => $sub['id'],
		'plan_id'     => $plan,
		'name'        => $name,
		'email'       => $email,
		'phone'       => $phone,
		'pan'         => $pan,
		'address'     => $address,
		'amount'      => $paise,
		'frequency'   => $freq,
		'total_count' => $total,
		'status'      => 'created',
		'campaign'    => $campaign,
		'dedication'  => $dedication,
		'anonymous'   => $anonymous,
		'short_url'   => isset( $sub['short_url'] ) ? $sub['short_url'] : '',
	) );
	$f = frequencies()[ $freq ];
	ok( array_merge( $common, array(
		'mode'            => 'subscription',
		'subscription_id' => $sub['id'],
		'description'     => inr( $paise ) . $f['suffix'] . ' AutoPay donation',
	) ) );
}

/* ------------------------------------------------------------------ */
if ( 'verify' === $action ) {
	$secret     = (string) $o['key_secret'];
	$mode       = in( 'mode' );
	$payment_id = in( 'razorpay_payment_id', 60 );
	$signature  = in( 'razorpay_signature', 200 );
	if ( ! rate_ok( 'verify', 30, 600 ) ) {
		fail( 'Too many attempts.', 429 );
	}
	if ( '' === $payment_id || '' === $signature || '' === $secret ) {
		fail( 'Missing payment details.' );
	}

	if ( 'subscription' === $mode ) {
		$sub_id = in( 'razorpay_subscription_id', 60 );
		if ( ! hash_equals( hash_hmac( 'sha256', $payment_id . '|' . $sub_id, $secret ), $signature ) ) {
			fail( 'Payment verification failed. If money was debited, it will be refunded automatically.' );
		}
		$sub = get_sub( $sub_id );
		if ( ! $sub ) {
			$s   = sync_sub( $sub_id );
			$sub = ( ! rzp_err( $s ) && $s['row'] ) ? $s['row'] : null;
		}
		if ( ! $sub ) {
			fail( 'We could not find this AutoPay. Please contact us.' );
		}
		if ( 'created' === $sub['status'] ) {
			update_sub( $sub_id, array( 'status' => 'authenticated' ) );
		}
		sync_sub( $sub_id );
		$sub     = get_sub( $sub_id );
		$receipt = '';
		$payment = rzp( 'GET', 'payments/' . rawurlencode( $payment_id ) );
		if ( ! rzp_err( $payment ) && isset( $payment['status'] ) && 'captured' === $payment['status'] && (int) $payment['amount'] > 0 ) {
			$row = record_payment( array_merge( donor_fields( $sub ), array(
				'payment_id' => $payment_id,
				'sub_id'     => $sub_id,
				'type'       => 'recurring',
				'frequency'  => $sub['frequency'],
				'amount'     => (int) $payment['amount'],
				'method'     => isset( $payment['method'] ) ? key_clean( $payment['method'] ) : '',
				'status'     => 'captured',
			) ), $sub );
			if ( $row ) {
				$receipt = receipt_url( $payment_id );
			}
		}
		ok( array( 'receipt' => $receipt, 'manage' => manage_url( $sub['token'] ) ) );
	}

	if ( 'order' === $mode ) {
		$order_id = in( 'razorpay_order_id', 60 );
		if ( ! hash_equals( hash_hmac( 'sha256', $order_id . '|' . $payment_id, $secret ), $signature ) ) {
			fail( 'Payment verification failed. If money was debited, it will be refunded automatically.' );
		}
		$row = record_order_payment( $order_id, $payment_id );
		ok( array( 'receipt' => $row ? receipt_url( $payment_id ) : '', 'manage' => '' ) );
	}
}

fail( 'Invalid request.' );
