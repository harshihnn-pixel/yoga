<?php
/**
 * Donor self-service data (receipt, manage/cancel AutoPay, my-donations portal, annual statement).
 * Every request is protected by a signed link or a secret token.
 */
require __DIR__ . '/lib.php';

$action = in( 'action' );
$method = isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : 'GET';

function pay_public( $p ) {
	return array(
		'receipt_no'  => $p['receipt_no'],
		'date'        => fmt_date( $p['created_at'] ),
		'amount'      => inr( $p['amount'] ),
		'type'        => freq_label( $p['frequency'] ? $p['frequency'] : $p['type'] ),
		'method'      => method_label( $p['method'] ),
		'payment_id'  => $p['payment_id'],
		'receipt_url' => receipt_url( $p['payment_id'] ),
	);
}

function sub_public( $s ) {
	$freqs = frequencies();
	$f     = isset( $freqs[ $s['frequency'] ] ) ? $freqs[ $s['frequency'] ] : $freqs['monthly'];
	$live  = in_array( $s['status'], array( 'authenticated', 'active', 'pending', 'halted', 'paused' ), true );
	return array(
		'name'        => $s['name'],
		'status'      => $s['status'],
		'status_text' => status_label( $s['status'] ),
		'amount'      => inr( $s['amount'] ),
		'amount_text' => inr( $s['amount'] ) . ' ' . $f['word'],
		'freq'        => freq_label( $s['frequency'] ),
		'paid'        => $s['paid_count'] . ' / ' . $s['total_count'],
		'started'     => fmt_date( $s['created_at'] ),
		'next'        => ( $live && $s['next_charge_at'] ) ? fmt_date( $s['next_charge_at'] ) : '',
		'campaign'    => $s['campaign'],
		'approve_url' => ( 'created' === $s['status'] && $s['short_url'] ) ? $s['short_url'] : '',
		'restart_url' => 'halted' === $s['status'] ? donate_url() : '',
		'can_cancel'  => in_array( $s['status'], array( 'created', 'authenticated', 'active', 'pending', 'halted', 'paused' ), true ),
		'manage_url'  => manage_url( $s['token'] ),
	);
}

/* ---------------- Receipt ---------------- */
if ( 'receipt' === $action ) {
	$pid = in( 'p', 60 );
	$p   = get_payment( $pid );
	if ( ! $p || ! check_sig( 'receipt|' . $pid, in( 'k', 64 ) ) ) {
		fail( 'This link is invalid or has expired', 404 );
	}
	$rows = array(
		array( 'Receipt no.', $p['receipt_no'] ),
		array( 'Date', fmt_date( $p['created_at'] ) ),
		array( 'Received from', $p['name'] ),
	);
	if ( $p['pan'] ) {
		$rows[] = array( 'Donor PAN', $p['pan'] );
	}
	if ( $p['address'] ) {
		$rows[] = array( 'Donor address', $p['address'] );
	}
	$rows[] = array( 'Email / Mobile', $p['email'] . ( $p['phone'] ? ' / ' . $p['phone'] : '' ) );
	$rows[] = array( 'Amount', inr( $p['amount'] ) );
	$rows[] = array( 'Amount in words', rupees_words( $p['amount'] ) );
	$rows[] = array( 'Donation type', 'recurring' === $p['type'] ? freq_label( $p['frequency'] ) . ' AutoPay' : 'One-time' );
	$rows[] = array( 'Mode of payment', method_label( $p['method'] ) . ' (Razorpay)' );
	$rows[] = array( 'Transaction ID', $p['payment_id'] );
	if ( $p['campaign'] ) {
		$rows[] = array( 'Towards', $p['campaign'] );
	}
	if ( $p['dedication'] ) {
		$rows[] = array( 'In honour / memory of', $p['dedication'] );
	}
	ok( array( 'org' => org_public(), 'receipt_no' => $p['receipt_no'], 'rows' => $rows ) );
}

/* ---------------- Manage AutoPay ---------------- */
if ( 'manage' === $action || 'cancel' === $action ) {
	$sub = get_sub_by_token( in( 't', 64 ) );
	if ( ! $sub ) {
		fail( 'This link is invalid or has expired', 404 );
	}
	$msg = '';
	$err = '';
	if ( 'cancel' === $action ) {
		if ( 'POST' !== $method ) {
			fail( 'Invalid request.', 405 );
		}
		if ( ! rate_ok( 'cancel', 5, 600 ) ) {
			fail( 'Too many attempts. Please try again later.', 429 );
		}
		$r = cancel_sub( $sub, 'donor' );
		if ( true === $r ) {
			$msg = 'Your AutoPay has been cancelled. No further amounts will be debited.';
		} else {
			$err = 'We could not cancel right now. Please try again or contact us.';
		}
		$sub = get_sub( $sub['sub_id'] );
	} elseif ( in_array( $sub['status'], array( 'created', 'authenticated', 'active', 'pending' ), true ) && opt( 'key_secret' ) ) {
		$k = 'sync_' . md5( $sub['sub_id'] );
		if ( ! kv_get( $k ) ) {
			kv_set( $k, 1, time() + 600 );
			sync_sub( $sub['sub_id'] );
			$sub = get_sub( $sub['sub_id'] );
		}
	}
	ok( array(
		'org'        => org_public(),
		'sub'        => sub_public( $sub ),
		'payments'   => array_map( 'pay_public', payments_for_sub( $sub['sub_id'] ) ),
		'portal_url' => portal_url( $sub['email'] ),
		'message'    => $msg,
		'error'      => $err,
	) );
}

/* ---------------- My donations: request magic link ---------------- */
if ( 'portal_request' === $action ) {
	if ( 'POST' !== $method ) {
		fail( 'Invalid request.', 405 );
	}
	$email = strtolower( in( 'email', 190 ) );
	if ( '' === in( 'website' ) && is_email( $email ) && rate_ok( 'portal', 5, 900 ) ) {
		$has = (int) val( 'SELECT COUNT(*) FROM ssy_payments WHERE email = ?', array( $email ) )
			+ (int) val( "SELECT COUNT(*) FROM ssy_subs WHERE email = ? AND status <> 'created'", array( $email ) );
		if ( $has ) {
			email_portal_link( $email, portal_url( $email ) );
		}
	}
	ok( array( 'message' => 'If we have donations under that email address, we have sent you a secure link. It is valid for 24 hours.' ) );
}

/* ---------------- My donations: dashboard ---------------- */
if ( 'portal' === $action ) {
	$email = strtolower( in( 'e', 190 ) );
	$x     = (int) in( 'x' );
	if ( ! $email || $x < time() || ! check_sig( 'portal|' . $email . '|' . $x, in( 'k', 64 ) ) ) {
		fail( 'This link is invalid or has expired', 404 );
	}
	$subs  = rows( "SELECT * FROM ssy_subs WHERE email = ? AND status <> 'created' ORDER BY created_at DESC", array( $email ) );
	$pays  = payments_for_email( $email );
	$total = 0;
	$fys   = array();
	foreach ( $pays as $p ) {
		$total += (int) $p['amount'];
		$fys[ fy_of( $p['created_at'] ) ] = true;
	}
	krsort( $fys );
	$stm = array();
	foreach ( array_keys( $fys ) as $fy ) {
		$stm[] = array( 'label' => fy_label( $fy ), 'url' => statement_url( $email, $fy, $x ) );
	}
	ok( array(
		'email'      => $email,
		'total'      => inr( $total ),
		'count'      => count( $pays ),
		'subs'       => array_map( 'sub_public', $subs ),
		'statements' => $stm,
		'payments'   => array_map( 'pay_public', $pays ),
	) );
}

/* ---------------- Annual statement ---------------- */
if ( 'statement' === $action ) {
	$email = strtolower( in( 'e', 190 ) );
	$fy    = (int) in( 'fy' );
	$x     = (int) in( 'x' );
	if ( ! $email || $fy < 2000 || $x < time() - 30 * 86400 || ! check_sig( 'statement|' . $email . '|' . $fy . '|' . $x, in( 'k', 64 ) ) ) {
		fail( 'This link is invalid or has expired', 404 );
	}
	$pays = array_reverse( payments_for_email( $email, $fy . '-04-01 00:00:00', ( $fy + 1 ) . '-04-01 00:00:00' ) );
	if ( ! $pays ) {
		fail( 'This link is invalid or has expired', 404 );
	}
	$total = 0;
	$pan   = '';
	$addr  = '';
	$list  = array();
	foreach ( $pays as $p ) {
		$total += (int) $p['amount'];
		$pan    = $p['pan'] ? $p['pan'] : $pan;
		$addr   = $p['address'] ? $p['address'] : $addr;
		$list[] = pay_public( $p );
	}
	$rows = array(
		array( 'Donor', $pays[0]['name'] ),
		array( 'Email', $email ),
		array( 'Period', '1 Apr ' . $fy . ' – 31 Mar ' . ( $fy + 1 ) ),
	);
	if ( $pan ) {
		$rows[] = array( 'Donor PAN', $pan );
	}
	if ( $addr ) {
		$rows[] = array( 'Donor address', $addr );
	}
	$rows[] = array( 'Total donated', inr( $total ) );
	$rows[] = array( 'In words', rupees_words( $total ) );
	ok( array( 'org' => org_public(), 'fy' => fy_label( $fy ), 'rows' => $rows, 'payments' => $list, 'total' => inr( $total ) ) );
}

fail( 'Invalid request.' );
