<?php
/**
 * Razorpay webhook:  https://www.sanjeevinisparshyoga.com/donations/api/webhook.php
 *
 * Razorpay Dashboard → Account & Settings → Webhooks → Add New Webhook
 *   URL:    the address above
 *   Secret: the same value as 'webhook_secret' in config.php
 *   Events: subscription.authenticated, subscription.activated, subscription.charged,
 *           subscription.pending, subscription.halted, subscription.cancelled,
 *           subscription.completed, subscription.paused, subscription.resumed, order.paid
 */
require __DIR__ . '/lib.php';

if ( 'GET' === ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) {
	$last = kv_get( 'last_webhook' );
	json_out( array(
		'ok'                  => true,
		'message'             => 'Webhook endpoint is working. Paste this URL in Razorpay Dashboard > Webhooks. Razorpay sends POST requests here; opening it in a browser only shows this status.',
		'secret_configured'   => '' !== (string) opt( 'webhook_secret' ),
		'last_event_received' => is_array( $last ) ? $last['event'] . ' at ' . $last['time'] : 'none yet',
	) );
}

$secret = (string) opt( 'webhook_secret' );
$body   = file_get_contents( 'php://input' );
$sig    = isset( $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ) ? $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] : '';

if ( '' === $secret ) {
	json_out( array( 'error' => 'webhook secret not configured' ), 500 );
}
if ( '' === $sig || ! hash_equals( hash_hmac( 'sha256', $body, $secret ), $sig ) ) {
	json_out( array( 'error' => 'invalid signature' ), 401 );
}

$event_id = isset( $_SERVER['HTTP_X_RAZORPAY_EVENT_ID'] ) ? $_SERVER['HTTP_X_RAZORPAY_EVENT_ID'] : '';
if ( $event_id && kv_get( 'evt_' . md5( $event_id ) ) ) {
	json_out( array( 'ok' => true, 'duplicate' => true ) );
}

$data  = json_decode( $body, true );
$event = isset( $data['event'] ) ? (string) $data['event'] : '';
$p     = isset( $data['payload'] ) && is_array( $data['payload'] ) ? $data['payload'] : array();
$sub_e = isset( $p['subscription']['entity'] ) ? $p['subscription']['entity'] : null;
$pay_e = isset( $p['payment']['entity'] ) ? $p['payment']['entity'] : null;

kv_set( 'last_webhook', array( 'time' => now(), 'event' => $event ) );

if ( 0 === strpos( $event, 'subscription.' ) && $sub_e ) {
	$res  = sync_entity( $sub_e );
	$sub  = $res['row'];
	$prev = $res['prev'];
	if ( $sub ) {
		switch ( $event ) {
			case 'subscription.charged':
				if ( $pay_e && ! empty( $pay_e['id'] ) && ( ! isset( $pay_e['status'] ) || 'captured' === $pay_e['status'] ) ) {
					if ( ! (int) $sub['amount'] && ! empty( $pay_e['amount'] ) ) {
						update_sub( $sub['sub_id'], array( 'amount' => (int) $pay_e['amount'] ) );
						$sub = get_sub( $sub['sub_id'] );
					}
					record_payment( array_merge( donor_fields( $sub ), array(
						'payment_id' => $pay_e['id'],
						'sub_id'     => $sub['sub_id'],
						'type'       => 'recurring',
						'frequency'  => $sub['frequency'],
						'amount'     => (int) $pay_e['amount'],
						'method'     => isset( $pay_e['method'] ) ? key_clean( $pay_e['method'] ) : '',
						'status'     => 'captured',
						'created_at' => ! empty( $pay_e['created_at'] ) ? ts_to_dt( $pay_e['created_at'] ) : now(),
					) ), $sub );
				}
				break;

			case 'subscription.pending':
			case 'subscription.halted':
				$kind = 'subscription.halted' === $event ? 'halted' : 'pending';
				if ( $prev !== $kind ) {
					email_payment_issue( $sub, $kind );
					admin_alert( 'halted' === $kind ? 'AutoPay halted – payment failed' : 'AutoPay payment failed (retrying)', array(
						'Name'   => $sub['name'],
						'Email'  => $sub['email'],
						'Phone'  => $sub['phone'],
						'Amount' => inr( $sub['amount'] ) . ' ' . freq_label( $sub['frequency'] ),
					) );
				}
				break;

			case 'subscription.cancelled':
				if ( 'cancelled' !== $prev ) {
					email_cancelled( $sub );
					admin_alert( 'AutoPay cancelled', array(
						'Name'   => $sub['name'],
						'Email'  => $sub['email'],
						'Amount' => inr( $sub['amount'] ) . ' ' . freq_label( $sub['frequency'] ),
						'Paid'   => $sub['paid_count'] . ' / ' . $sub['total_count'],
					) );
				}
				break;

			case 'subscription.completed':
				if ( 'completed' !== $prev ) {
					email_completed( $sub );
				}
				break;
		}
	}
} elseif ( 'order.paid' === $event && $pay_e ) {
	$order = isset( $p['order']['entity'] ) ? $p['order']['entity'] : null;
	$oid   = isset( $pay_e['order_id'] ) ? $pay_e['order_id'] : ( $order ? $order['id'] : '' );
	if ( $oid && empty( $pay_e['invoice_id'] ) ) {
		record_order_payment( $oid, $pay_e['id'], $pay_e, $order );
	}
}

if ( $event_id ) {
	kv_set( 'evt_' . md5( $event_id ), 1, time() + 3 * 86400 );
}
json_out( array( 'ok' => true ) );
