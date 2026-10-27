<?php
/**
 * Admin dashboard API (replaces WordPress admin screens).
 * Login with 'admin_password' from config.php.
 */
require __DIR__ . '/lib.php';

$https = ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== $_SERVER['HTTPS'] ) || ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO'] );
session_name( 'ssy_admin' );
session_set_cookie_params( array( 'lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Strict' ) );
session_start();

$action = in( 'action' );
$method = isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : 'GET';


/**
 * Admin accounts (email => password).
 *  - config.local.php: 'admins' => array( 'a@x.com' => 'Pass1', 'b@x.com' => 'Pass2' )
 *  - Render/hosting env: SSY_ADMINS = "a@x.com:Pass1,b@x.com:Pass2"
 *  - Always also: admin_email + admin_password (single admin).
 * Passwords may be plain text or a password_hash() value ($2y$...).
 */
function admin_accounts() {
	$list = array();
	$cfg  = opt( 'admins' );
	if ( is_array( $cfg ) ) {
		foreach ( $cfg as $e => $p ) {
			$list[ strtolower( trim( (string) $e ) ) ] = (string) $p;
		}
	}
	$env = getenv( 'SSY_ADMINS' );
	if ( $env ) {
		foreach ( explode( ',', $env ) as $pair ) {
			$bits = explode( ':', $pair, 2 );
			if ( 2 === count( $bits ) && '' !== trim( $bits[0] ) ) {
				$list[ strtolower( trim( $bits[0] ) ) ] = trim( $bits[1] );
			}
		}
	}
	if ( opt( 'admin_email' ) && '' !== (string) opt( 'admin_password' ) ) {
		$list[ strtolower( trim( (string) opt( 'admin_email' ) ) ) ] = (string) opt( 'admin_password' );
	}
	foreach ( $list as $e => $p ) {
		if ( '' === $e || '' === $p || 'change-this-password' === $p ) { // starter password never works
			unset( $list[ $e ] );
		}
	}
	return $list;
}

function is_admin() {
	return ! empty( $_SESSION['ssy_admin'] ) && ( time() - (int) $_SESSION['ssy_seen'] ) < 8 * 3600;
}

function need_admin() {
	if ( ! is_admin() ) {
		fail( 'Please log in.', 401 );
	}
	$_SESSION['ssy_seen'] = time();
}

function need_csrf() {
	$t = isset( $_SERVER['HTTP_X_CSRF'] ) ? $_SERVER['HTTP_X_CSRF'] : in( 'csrf', 80 );
	if ( empty( $_SESSION['ssy_csrf'] ) || ! is_string( $t ) || ! hash_equals( $_SESSION['ssy_csrf'], $t ) ) {
		fail( 'Your session expired. Please reload the page.', 403 );
	}
}

/* ---------------- Auth ---------------- */
if ( 'login' === $action ) {
	if ( 'POST' !== $method ) {
		fail( 'Invalid request.', 405 );
	}
	if ( ! rate_ok( 'login', 8, 900 ) ) {
		fail( 'Too many attempts. Try again in 15 minutes.', 429 );
	}
	$email = strtolower( trim( (string) ( isset( input()['email'] ) ? input()['email'] : '' ) ) );
	$pw    = isset( input()['password'] ) ? (string) input()['password'] : '';
	$admins = admin_accounts();
	if ( ! $admins ) {
		fail( 'No admin account is set up. Add admin_email and admin_password in config.local.php.', 403 );
	}
	$ok = false;
	foreach ( $admins as $a_email => $a_pass ) {
		$match_email = hash_equals( hash( 'sha256', $a_email ), hash( 'sha256', $email ) );
		$match_pass  = 0 === strpos( $a_pass, '$2y$' ) ? password_verify( $pw, $a_pass ) : hash_equals( hash( 'sha256', $a_pass ), hash( 'sha256', $pw ) );
		if ( $match_email && $match_pass ) {
			$ok = true;
		}
	}
	if ( ! $ok ) {
		fail( 'Wrong email or password.', 401 );
	}
	session_regenerate_id( true );
	$_SESSION['ssy_admin'] = 1;
	$_SESSION['ssy_seen']  = time();
	$_SESSION['ssy_csrf']  = bin2hex( random_bytes( 20 ) );
	$_SESSION['ssy_email'] = $email;
	ok( array( 'csrf' => $_SESSION['ssy_csrf'], 'email' => $email ) );
}

if ( 'logout' === $action ) {
	$_SESSION = array();
	session_destroy();
	ok();
}

if ( 'me' === $action ) {
	if ( ! is_admin() ) {
		ok( array( 'logged_in' => false ) );
	}
	ok( array( 'logged_in' => true, 'csrf' => $_SESSION['ssy_csrf'], 'email' => isset( $_SESSION['ssy_email'] ) ? $_SESSION['ssy_email'] : '' ) );
}

need_admin();

function sub_row( $s ) {
	return array(
		'id'          => (int) $s['id'],
		'sub_id'      => $s['sub_id'],
		'name'        => $s['name'],
		'email'       => $s['email'],
		'phone'       => $s['phone'],
		'pan'         => $s['pan'],
		'address'     => (string) $s['address'],
		'amount'      => inr( $s['amount'] ),
		'freq'        => freq_label( $s['frequency'] ),
		'status'      => $s['status'],
		'status_text' => status_label( $s['status'] ),
		'paid'        => $s['paid_count'] . ' / ' . $s['total_count'],
		'next'        => in_array( $s['status'], array( 'authenticated', 'active' ), true ) ? fmt_date( $s['next_charge_at'] ) : '—',
		'campaign'    => $s['campaign'],
		'dedication'  => $s['dedication'],
		'anonymous'   => (int) $s['anonymous'],
		'started'     => fmt_date( $s['created_at'] ),
		'short_url'   => $s['short_url'],
		'manage_url'  => manage_url( $s['token'] ),
		'rzp_url'     => 'https://dashboard.razorpay.com/app/subscriptions/' . rawurlencode( $s['sub_id'] ),
	);
}

function pay_row( $p ) {
	return array(
		'date'        => fmt_date( $p['created_at'], true ),
		'receipt_no'  => $p['receipt_no'],
		'receipt_url' => receipt_url( $p['payment_id'] ),
		'name'        => $p['name'],
		'email'       => $p['email'],
		'phone'       => $p['phone'],
		'amount'      => inr( $p['amount'] ),
		'type'        => 'recurring' === $p['type'] ? freq_label( $p['frequency'] ) . ' AutoPay' : 'One-time',
		'method'      => method_label( $p['method'] ),
		'campaign'    => $p['campaign'],
		'payment_id'  => $p['payment_id'],
		'sub_id'      => $p['sub_id'],
	);
}

/** Build WHERE for subs list. */
function subs_where( &$vals ) {
	$w      = array( '1=1' );
	$status = key_clean( in( 'status' ) );
	if ( 'attention' === $status ) {
		$w[] = "status IN ('pending','halted')";
	} elseif ( 'live' === $status ) {
		$w[] = "status IN ('authenticated','active')";
	} elseif ( $status ) {
		$w[]    = 'status = ?';
		$vals[] = $status;
	}
	if ( in( 'campaign' ) ) {
		$w[]    = 'campaign = ?';
		$vals[] = in( 'campaign' );
	}
	if ( in( 's' ) ) {
		$like = '%' . in( 's' ) . '%';
		$w[]  = '(name LIKE ? OR email LIKE ? OR phone LIKE ? OR sub_id LIKE ?)';
		array_push( $vals, $like, $like, $like, $like );
	}
	return implode( ' AND ', $w );
}

function pays_where( &$vals ) {
	$w = array( '1=1' );
	if ( in_array( in( 'type' ), array( 'recurring', 'onetime' ), true ) ) {
		$w[]    = 'type = ?';
		$vals[] = in( 'type' );
	}
	if ( in( 'campaign' ) ) {
		$w[]    = 'campaign = ?';
		$vals[] = in( 'campaign' );
	}
	if ( preg_match( '/^\d{4}-\d{2}$/', in( 'month' ) ) ) {
		$start = in( 'month' ) . '-01 00:00:00';
		$w[]   = 'created_at >= ? AND created_at < ?';
		array_push( $vals, $start, date( 'Y-m-01 00:00:00', strtotime( $start . ' +1 month' ) ) );
	}
	if ( in( 's' ) ) {
		$like = '%' . in( 's' ) . '%';
		$w[]  = '(name LIKE ? OR email LIKE ? OR phone LIKE ? OR payment_id LIKE ? OR receipt_no LIKE ?)';
		array_push( $vals, $like, $like, $like, $like, $like );
	}
	return implode( ' AND ', $w );
}

function campaigns() {
	$a = array_merge(
		array_column( rows( "SELECT DISTINCT campaign FROM ssy_subs WHERE campaign <> ''" ), 'campaign' ),
		array_column( rows( "SELECT DISTINCT campaign FROM ssy_payments WHERE campaign <> ''" ), 'campaign' )
	);
	$a = array_values( array_unique( $a ) );
	sort( $a );
	return $a;
}

/* ---------------- Dashboard ---------------- */
if ( 'stats' === $action ) {
	$month = date( 'Y-m-01 00:00:00' );
	$freqs = frequencies();
	$mrr   = 0;
	foreach ( rows( "SELECT frequency, SUM(amount) AS total FROM ssy_subs WHERE status IN ('authenticated','active') GROUP BY frequency" ) as $r ) {
		$mrr += (int) $r['total'] * ( isset( $freqs[ $r['frequency'] ] ) ? $freqs[ $r['frequency'] ]['per_year'] : 12 ) / 12;
	}
	$o       = opt();
	$last    = kv_get( 'last_webhook' );
	$checks  = array(
		array( 'Razorpay API keys added (config.local.php)', '' !== trim( (string) $o['key_id'] ) && '' !== trim( (string) $o['key_secret'] ) ),
		array( 'Webhook secret added (config.local.php)', '' !== (string) $o['webhook_secret'] ),
		array( 'First webhook received from Razorpay', is_array( $last ) ),
		array( 'Admin password changed', 'change-this-password' !== $o['admin_password'] ),
		array( 'Organisation address added (for receipts)', '' !== (string) $o['org_address'] ),
		array( 'Using LIVE keys', 'live' === rzp_mode() ),
	);
	ok( array(
		'mode'      => rzp_mode(),
		'stats'     => array(
			'active'      => (int) val( "SELECT COUNT(*) FROM ssy_subs WHERE status IN ('authenticated','active')" ),
			'mrr'         => inr( round( $mrr ) ),
			'month_total' => inr( (int) val( "SELECT COALESCE(SUM(amount),0) FROM ssy_payments WHERE status = 'captured' AND created_at >= ?", array( $month ) ) ),
			'month_count' => (int) val( "SELECT COUNT(*) FROM ssy_payments WHERE status = 'captured' AND created_at >= ?", array( $month ) ),
			'all_total'   => inr( (int) val( "SELECT COALESCE(SUM(amount),0) FROM ssy_payments WHERE status = 'captured'" ) ),
			'new_donors'  => (int) val( "SELECT COUNT(*) FROM ssy_subs WHERE status <> 'created' AND created_at >= ?", array( $month ) ),
			'attention'   => (int) val( "SELECT COUNT(*) FROM ssy_subs WHERE status IN ('pending','halted')" ),
			'cancelled'   => (int) val( "SELECT COUNT(*) FROM ssy_subs WHERE status = 'cancelled' AND updated_at >= ?", array( $month ) ),
		),
		'recent'    => array_map( 'pay_row', rows( 'SELECT * FROM ssy_payments ORDER BY created_at DESC LIMIT 10' ) ),
		'webhook'   => is_array( $last ) ? $last['event'] . ' at ' . fmt_date( $last['time'], true ) : '',
		'webhook_url' => opt( 'base_url' ) . 'api/webhook.php',
		'errors'    => array_reverse( (array) kv_get( 'error_log' ) ),
		'checklist' => $checks,
		'donate_url'=> opt( 'base_url' ),
	) );
}

/* ---------------- Donors (subscriptions) ---------------- */
if ( 'subs' === $action ) {
	$vals  = array();
	$where = subs_where( $vals );
	$page  = max( 1, (int) in( 'page' ) );
	$total = (int) val( "SELECT COUNT(*) FROM ssy_subs WHERE $where", $vals );
	$list  = rows( "SELECT * FROM ssy_subs WHERE $where ORDER BY created_at DESC LIMIT 25 OFFSET " . ( ( $page - 1 ) * 25 ), $vals );
	ok( array( 'rows' => array_map( 'sub_row', $list ), 'total' => $total, 'page' => $page, 'pages' => max( 1, (int) ceil( $total / 25 ) ), 'campaigns' => campaigns() ) );
}

if ( 'sub' === $action ) {
	$s = get_sub_by_id( (int) in( 'id' ) );
	if ( ! $s ) {
		fail( 'Not found.', 404 );
	}
	ok( array( 'sub' => sub_row( $s ), 'payments' => array_map( 'pay_row', payments_for_sub( $s['sub_id'] ) ) ) );
}

if ( 'sub_action' === $action ) {
	if ( 'POST' !== $method ) {
		fail( 'Invalid request.', 405 );
	}
	need_csrf();
	$s = get_sub_by_id( (int) in( 'id' ) );
	if ( ! $s ) {
		fail( 'Not found.', 404 );
	}
	$do = in( 'do' );
	if ( 'sync' === $do ) {
		$r = sync_sub( $s['sub_id'] );
		rzp_err( $r ) ? fail( rzp_err( $r ) ) : ok( array( 'message' => 'Updated from Razorpay.' ) );
	}
	if ( 'cancel' === $do ) {
		$r = cancel_sub( $s, 'admin' );
		true === $r ? ok( array( 'message' => 'AutoPay cancelled. The donor has been emailed.' ) ) : fail( rzp_err( $r ) );
	}
	if ( 'link' === $do ) {
		sync_sub( $s['sub_id'] );
		email_payment_link( get_sub( $s['sub_id'] ) ) ? ok( array( 'message' => 'Payment link emailed to the donor.' ) ) : fail( 'Could not send the email.' );
	}
	fail( 'Unknown action.' );
}

/* ---------------- Payments ---------------- */
if ( 'payments' === $action ) {
	$vals  = array();
	$where = pays_where( $vals );
	$page  = max( 1, (int) in( 'page' ) );
	$total = (int) val( "SELECT COUNT(*) FROM ssy_payments WHERE $where", $vals );
	$sum   = (int) val( "SELECT COALESCE(SUM(amount),0) FROM ssy_payments WHERE $where", $vals );
	$list  = rows( "SELECT * FROM ssy_payments WHERE $where ORDER BY created_at DESC LIMIT 25 OFFSET " . ( ( $page - 1 ) * 25 ), $vals );
	ok( array( 'rows' => array_map( 'pay_row', $list ), 'total' => $total, 'sum' => inr( $sum ), 'page' => $page, 'pages' => max( 1, (int) ceil( $total / 25 ) ), 'campaigns' => campaigns() ) );
}

/* ---------------- Excel / CSV export ---------------- */
if ( 'export' === $action ) {
	need_csrf();
	$what = 'subs' === in( 'what' ) ? 'subs' : 'payments';
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=donations-' . $what . '-' . date( 'Y-m-d' ) . '.csv' );
	header( 'Cache-Control: no-store' );
	$out = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" ); // Excel UTF-8
	$vals = array();
	if ( 'subs' === $what ) {
		$where = subs_where( $vals );
		fputcsv( $out, array( 'Subscription ID', 'Name', 'Email', 'Phone', 'PAN', 'Address', 'Amount (INR)', 'Frequency', 'Status', 'Paid', 'Total cycles', 'Next debit', 'Campaign', 'Dedication', 'Anonymous', 'Created' ) );
		foreach ( rows( "SELECT * FROM ssy_subs WHERE $where ORDER BY created_at DESC", $vals ) as $r ) {
			fputcsv( $out, array( $r['sub_id'], $r['name'], $r['email'], $r['phone'], $r['pan'], $r['address'], $r['amount'] / 100, $r['frequency'], $r['status'], $r['paid_count'], $r['total_count'], $r['next_charge_at'], $r['campaign'], $r['dedication'], $r['anonymous'] ? 'yes' : 'no', $r['created_at'] ) );
		}
	} else {
		$where = pays_where( $vals );
		fputcsv( $out, array( 'Date', 'Receipt no', 'Payment ID', 'Subscription ID', 'Order ID', 'Type', 'Frequency', 'Name', 'Email', 'Phone', 'PAN', 'Address', 'Amount (INR)', 'Method', 'Campaign', 'Dedication', 'Financial year' ) );
		foreach ( rows( "SELECT * FROM ssy_payments WHERE $where ORDER BY created_at DESC", $vals ) as $p ) {
			fputcsv( $out, array( $p['created_at'], $p['receipt_no'], $p['payment_id'], $p['sub_id'], $p['order_id'], $p['type'], $p['frequency'], $p['name'], $p['email'], $p['phone'], $p['pan'], $p['address'], $p['amount'] / 100, $p['method'], $p['campaign'], $p['dedication'], fy_label( fy_of( $p['created_at'] ) ) ) );
		}
	}
	fclose( $out );
	exit;
}

/* ---------------- Tests ---------------- */
if ( 'test_api' === $action ) {
	$r = rzp( 'GET', 'plans?count=1' );
	rzp_err( $r ) ? fail( 'Razorpay: ' . rzp_err( $r ) ) : ok( array( 'message' => 'Connected to Razorpay (' . strtoupper( rzp_mode() ) . ' mode).' ) );
}

if ( 'test_email' === $action ) {
	if ( 'POST' !== $method ) {
		fail( 'Invalid request.', 405 );
	}
	need_csrf();
	$to = opt( 'admin_email' );
	$ok = send_mail( $to, 'Test email – ' . opt( 'org_name' ), 'Test email', '<p>Emails from your donation system are working.</p>' );
	$ok ? ok( array( 'message' => 'Test email sent to ' . $to . '. Check inbox and spam.' ) ) : fail( 'Email could not be sent. Check SMTP settings in config.php. ' . ( ( $e = kv_get( 'error_log' ) ) ? end( $e )['msg'] : '' ) );
}

fail( 'Invalid request.' );
