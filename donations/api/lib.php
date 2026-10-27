<?php
/**
 * Core library – replaces WordPress for the donation system.
 * You normally never need to edit this file; settings are in config.php.
 */

if ( ! defined( 'SSY' ) ) {
	define( 'SSY', 1 );
}
define( 'SSY_DIR', __DIR__ );
define( 'SSY_DATA', __DIR__ . '/data' );

/* Fallbacks in case the PHP 'mbstring' extension is not switched on. */
if ( ! function_exists( 'mb_substr' ) ) {
	function mb_substr( $s, $start, $len = null ) {
		return null === $len ? substr( (string) $s, $start ) : substr( (string) $s, $start, $len );
	}
}
if ( ! function_exists( 'mb_strtoupper' ) ) {
	function mb_strtoupper( $s ) {
		return strtoupper( (string) $s );
	}
}
if ( ! function_exists( 'curl_init' ) ) {
	// Razorpay calls need the PHP 'curl' extension (enabled by default in XAMPP and on hosting).
}

/* ================================================================ */
/* Settings                                                          */
/* ================================================================ */

function opt( $key = null ) {
	static $o = null;
	if ( null === $o ) {
		$o = require SSY_DIR . '/config.php';
		$host_now = isset( $_SERVER['HTTP_HOST'] ) ? preg_replace( '/:\d+$/', '', $_SERVER['HTTP_HOST'] ) : '';
		if ( in_array( $host_now, array( 'localhost', '127.0.0.1' ), true ) ) {
			$o['base_url'] = ''; // testing on this computer: build links from the local address
		}
		if ( empty( $o['base_url'] ) ) {
			$https       = ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== $_SERVER['HTTPS'] ) || ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO'] );
			$host        = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : 'localhost';
			$o['base_url'] = ( $https ? 'https://' : 'http://' ) . $host . rtrim( dirname( dirname( $_SERVER['SCRIPT_NAME'] ) ), '/\\' ) . '/';
		}
		$o['base_url'] = rtrim( $o['base_url'], '/' ) . '/';
	}
	if ( null === $key ) {
		return $o;
	}
	return isset( $o[ $key ] ) ? $o[ $key ] : null;
}

date_default_timezone_set( opt( 'timezone' ) ? opt( 'timezone' ) : 'Asia/Kolkata' );

function frequencies() {
	return array(
		'monthly'   => array( 'label' => 'Monthly', 'period' => 'monthly', 'interval' => 1, 'per_year' => 12, 'suffix' => '/month', 'word' => 'every month', 'plan' => 'Monthly Donation' ),
		'quarterly' => array( 'label' => 'Quarterly', 'period' => 'monthly', 'interval' => 3, 'per_year' => 4, 'suffix' => '/quarter', 'word' => 'every 3 months', 'plan' => 'Quarterly Donation' ),
		'yearly'    => array( 'label' => 'Yearly', 'period' => 'yearly', 'interval' => 1, 'per_year' => 1, 'suffix' => '/year', 'word' => 'every year', 'plan' => 'Yearly Donation' ),
	);
}

function freq_label( $key ) {
	if ( 'onetime' === $key ) {
		return 'One-time';
	}
	$f = frequencies();
	return isset( $f[ $key ] ) ? $f[ $key ]['label'] : (string) $key;
}

function allowed_freqs() {
	$all = array_keys( frequencies() );
	$out = array_values( array_intersect( (array) opt( 'frequencies' ), $all ) );
	if ( opt( 'allow_onetime' ) ) {
		$out[] = 'onetime';
	}
	return $out ? $out : array( 'monthly' );
}

function durations() {
	$out = array();
	foreach ( (array) opt( 'duration_choices' ) as $y ) {
		$y = (int) $y;
		if ( $y < 0 || $y > 30 ) {
			continue;
		}
		$out[ $y ] = 0 === $y ? 'Until I cancel' : ( 1 === $y ? 'For 1 year' : "For $y years" );
	}
	return $out ? $out : array( 0 => 'Until I cancel' );
}

function total_count( $freq, $years ) {
	$f     = frequencies();
	$years = (int) $years;
	if ( $years <= 0 ) {
		$years = max( 1, min( 30, (int) opt( 'forever_years' ) ) );
	}
	return max( 1, $years * ( isset( $f[ $freq ] ) ? $f[ $freq ]['per_year'] : 12 ) );
}

/* ================================================================ */
/* Formatting                                                        */
/* ================================================================ */

function inr( $paise ) {
	$paise = (int) round( (float) $paise );
	$int   = intdiv( $paise, 100 );
	$dec   = $paise % 100;
	$s     = (string) $int;
	if ( strlen( $s ) > 3 ) {
		$s = preg_replace( '/\B(?=(\d{2})+(?!\d))/', ',', substr( $s, 0, -3 ) ) . ',' . substr( $s, -3 );
	}
	return '₹' . $s . ( $dec ? '.' . str_pad( (string) $dec, 2, '0', STR_PAD_LEFT ) : '' );
}

function words( $num ) {
	$num   = (int) $num;
	$ones  = array( '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen' );
	$tens  = array( '', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety' );
	$two   = function ( $n ) use ( $ones, $tens ) {
		return $n < 20 ? $ones[ $n ] : trim( $tens[ intdiv( $n, 10 ) ] . ' ' . $ones[ $n % 10 ] );
	};
	$three = function ( $n ) use ( $two, $ones ) {
		$h = intdiv( $n, 100 );
		$r = $n % 100;
		return trim( ( $h ? $ones[ $h ] . ' Hundred' : '' ) . ( $r ? ' ' . $two( $r ) : '' ) );
	};
	if ( 0 === $num ) {
		return 'Zero';
	}
	$parts = array();
	$crore = intdiv( $num, 10000000 );
	$num  %= 10000000;
	$lakh  = intdiv( $num, 100000 );
	$num  %= 100000;
	$thou  = intdiv( $num, 1000 );
	$num  %= 1000;
	if ( $crore ) {
		$parts[] = ( $crore > 99 ? words( $crore ) : $two( $crore ) ) . ' Crore';
	}
	if ( $lakh ) {
		$parts[] = $two( $lakh ) . ' Lakh';
	}
	if ( $thou ) {
		$parts[] = $two( $thou ) . ' Thousand';
	}
	if ( $num ) {
		$parts[] = $three( $num );
	}
	return implode( ' ', $parts );
}

function rupees_words( $paise ) {
	$paise = (int) $paise;
	$w     = 'Rupees ' . words( intdiv( $paise, 100 ) );
	if ( $paise % 100 ) {
		$w .= ' and ' . words( $paise % 100 ) . ' Paise';
	}
	return $w . ' Only';
}

function fmt_date( $dt, $time = false ) {
	if ( empty( $dt ) ) {
		return '—';
	}
	return date( $time ? 'j M Y, g:i a' : 'j M Y', strtotime( $dt ) );
}

function now() {
	return date( 'Y-m-d H:i:s' );
}

function ts_to_dt( $ts ) {
	$ts = (int) $ts;
	return $ts > 0 ? date( 'Y-m-d H:i:s', $ts ) : null;
}

function fy_of( $dt ) {
	$ts = strtotime( $dt );
	$y  = (int) date( 'Y', $ts );
	return ( (int) date( 'n', $ts ) >= 4 ) ? $y : $y - 1;
}

function fy_label( $fy ) {
	return 'FY ' . $fy . '-' . substr( (string) ( $fy + 1 ), -2 );
}

function status_label( $s ) {
	$map = array(
		'created'       => 'Awaiting approval',
		'authenticated' => 'Approved',
		'active'        => 'Active',
		'pending'       => 'Payment retrying',
		'halted'        => 'Halted (payment failed)',
		'paused'        => 'Paused',
		'cancelled'     => 'Cancelled',
		'completed'     => 'Completed',
		'expired'       => 'Expired',
	);
	return isset( $map[ $s ] ) ? $map[ $s ] : ucfirst( (string) $s );
}

function method_label( $m ) {
	$map = array( 'upi' => 'UPI', 'card' => 'Card', 'emandate' => 'Bank eMandate', 'netbanking' => 'Net banking', 'wallet' => 'Wallet', 'nach' => 'NACH' );
	return isset( $map[ $m ] ) ? $map[ $m ] : ( $m ? ucfirst( $m ) : '—' );
}

function clean( $v, $max = 250 ) {
	$v = is_string( $v ) ? $v : '';
	$v = trim( preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', strip_tags( $v ) ) );
	return mb_substr( $v, 0, $max );
}

function key_clean( $v ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) );
}

function is_email( $e ) {
	return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL );
}

function h( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}

/* ================================================================ */
/* Secrets, signed links, rate limit                                 */
/* ================================================================ */

function ensure_data_dir() {
	if ( ! is_dir( SSY_DATA ) ) {
		@mkdir( SSY_DATA, 0750, true );
	}
	if ( ! file_exists( SSY_DATA . '/.htaccess' ) ) {
		@file_put_contents( SSY_DATA . '/.htaccess', "Require all denied\nDeny from all\n" );
	}
	if ( ! file_exists( SSY_DATA . '/index.html' ) ) {
		@file_put_contents( SSY_DATA . '/index.html', '' );
	}
}

function app_secret() {
	static $s = null;
	if ( null !== $s ) {
		return $s;
	}
	ensure_data_dir();
	$f = SSY_DATA . '/secret.php';
	if ( file_exists( $f ) ) {
		$s = (string) include $f;
	}
	if ( ! $s ) {
		$s = bin2hex( random_bytes( 32 ) );
		file_put_contents( $f, "<?php return '" . $s . "';\n" );
	}
	return $s;
}

function sign( $data ) {
	return substr( hash_hmac( 'sha256', (string) $data, app_secret() ), 0, 32 );
}

function check_sig( $data, $sig ) {
	return is_string( $sig ) && '' !== $sig && hash_equals( sign( $data ), $sig );
}

function receipt_url( $payment_id ) {
	return opt( 'base_url' ) . 'receipt.html?p=' . rawurlencode( $payment_id ) . '&k=' . sign( 'receipt|' . $payment_id );
}

function manage_url( $token ) {
	return opt( 'base_url' ) . 'manage.html?t=' . rawurlencode( $token );
}

function portal_url( $email, $expires = 0 ) {
	$email   = strtolower( $email );
	$expires = $expires ? (int) $expires : time() + 86400;
	return opt( 'base_url' ) . 'my-donations.html?e=' . rawurlencode( $email ) . '&x=' . $expires . '&k=' . sign( 'portal|' . $email . '|' . $expires );
}

function statement_url( $email, $fy, $expires ) {
	$email = strtolower( $email );
	return opt( 'base_url' ) . 'statement.html?e=' . rawurlencode( $email ) . '&fy=' . (int) $fy . '&x=' . (int) $expires . '&k=' . sign( 'statement|' . $email . '|' . (int) $fy . '|' . (int) $expires );
}

function donate_url() {
	return opt( 'base_url' );
}

function client_ip() {
	return isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
}

/** Simple per-IP rate limit stored in the database. */
function rate_ok( $bucket, $max = 10, $window = 600 ) {
	$k   = 'rl_' . $bucket . '_' . md5( client_ip() );
	$row = kv_get( $k );
	$now = time();
	if ( ! is_array( $row ) || $row['until'] < $now ) {
		$row = array( 'n' => 0, 'until' => $now + $window );
	}
	if ( $row['n'] >= $max ) {
		return false;
	}
	$row['n']++;
	kv_set( $k, $row, $row['until'] );
	return true;
}

/* ================================================================ */
/* JSON responses                                                    */
/* ================================================================ */

function json_out( $data, $code = 200 ) {
	http_response_code( $code );
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Cache-Control: no-store, no-cache, must-revalidate' );
	header( 'X-Robots-Tag: noindex' );
	echo json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	exit;
}

function ok( $data = array() ) {
	json_out( array( 'success' => true, 'data' => $data ) );
}

function fail( $msg, $code = 400 ) {
	json_out( array( 'success' => false, 'data' => array( 'message' => $msg ) ), $code );
}

function input() {
	static $in = null;
	if ( null === $in ) {
		$in  = $_POST;
		$raw = file_get_contents( 'php://input' );
		if ( ! $in && $raw && ( $j = json_decode( $raw, true ) ) && is_array( $j ) ) {
			$in = $j;
		}
		$in = array_merge( $_GET, (array) $in );
	}
	return $in;
}

function in( $key, $max = 250 ) {
	$i = input();
	return isset( $i[ $key ] ) && ! is_array( $i[ $key ] ) ? clean( (string) $i[ $key ], $max ) : '';
}

function log_error( $what, $msg ) {
	$log   = kv_get( 'error_log' );
	$log   = is_array( $log ) ? $log : array();
	$log[] = array( 'time' => now(), 'what' => $what, 'msg' => (string) $msg );
	kv_set( 'error_log', array_slice( $log, -20 ) );
}

/* ================================================================ */
/* Database (SQLite by default, MySQL optional)                      */
/* ================================================================ */

function db() {
	static $pdo = null;
	if ( $pdo ) {
		return $pdo;
	}
	ensure_data_dir();
	$dsn = opt( 'db_dsn' );
	if ( ! $dsn ) {
		$dsn = 'sqlite:' . SSY_DATA . '/donations.sqlite';
	}
	$pdo = new PDO( $dsn, opt( 'db_user' ) ? opt( 'db_user' ) : null, opt( 'db_pass' ) ? opt( 'db_pass' ) : null, array(
		PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
	) );
	if ( 'sqlite' === $pdo->getAttribute( PDO::ATTR_DRIVER_NAME ) ) {
		$pdo->exec( 'PRAGMA journal_mode=WAL; PRAGMA busy_timeout=5000;' );
	}
	install( $pdo );
	return $pdo;
}

function install( $pdo ) {
	$sqlite = 'sqlite' === $pdo->getAttribute( PDO::ATTR_DRIVER_NAME );
	$id     = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
	$text   = $sqlite ? 'TEXT' : 'TEXT NULL';
	$tail   = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
	$pdo->exec( "CREATE TABLE IF NOT EXISTS ssy_subs (
		id $id,
		sub_id VARCHAR(40) NOT NULL UNIQUE,
		plan_id VARCHAR(40) NOT NULL DEFAULT '',
		name VARCHAR(190) NOT NULL DEFAULT '',
		email VARCHAR(190) NOT NULL DEFAULT '',
		phone VARCHAR(20) NOT NULL DEFAULT '',
		pan VARCHAR(12) NOT NULL DEFAULT '',
		address $text,
		amount BIGINT NOT NULL DEFAULT 0,
		frequency VARCHAR(20) NOT NULL DEFAULT 'monthly',
		total_count INT NOT NULL DEFAULT 0,
		paid_count INT NOT NULL DEFAULT 0,
		status VARCHAR(20) NOT NULL DEFAULT 'created',
		campaign VARCHAR(100) NOT NULL DEFAULT '',
		dedication VARCHAR(255) NOT NULL DEFAULT '',
		anonymous INT NOT NULL DEFAULT 0,
		short_url VARCHAR(255) NOT NULL DEFAULT '',
		token VARCHAR(64) NOT NULL DEFAULT '',
		next_charge_at DATETIME NULL,
		created_at DATETIME NOT NULL,
		updated_at DATETIME NOT NULL
	)$tail" );
	$pdo->exec( "CREATE TABLE IF NOT EXISTS ssy_payments (
		id $id,
		payment_id VARCHAR(40) NOT NULL UNIQUE,
		sub_id VARCHAR(40) NOT NULL DEFAULT '',
		order_id VARCHAR(40) NOT NULL DEFAULT '',
		type VARCHAR(12) NOT NULL DEFAULT 'recurring',
		frequency VARCHAR(20) NOT NULL DEFAULT '',
		name VARCHAR(190) NOT NULL DEFAULT '',
		email VARCHAR(190) NOT NULL DEFAULT '',
		phone VARCHAR(20) NOT NULL DEFAULT '',
		pan VARCHAR(12) NOT NULL DEFAULT '',
		address $text,
		campaign VARCHAR(100) NOT NULL DEFAULT '',
		dedication VARCHAR(255) NOT NULL DEFAULT '',
		anonymous INT NOT NULL DEFAULT 0,
		amount BIGINT NOT NULL DEFAULT 0,
		method VARCHAR(30) NOT NULL DEFAULT '',
		status VARCHAR(20) NOT NULL DEFAULT 'captured',
		receipt_no VARCHAR(40) NOT NULL DEFAULT '',
		created_at DATETIME NOT NULL
	)$tail" );
	$pdo->exec( "CREATE TABLE IF NOT EXISTS ssy_kv (
		k VARCHAR(190) NOT NULL PRIMARY KEY,
		v $text,
		expires BIGINT NOT NULL DEFAULT 0
	)$tail" );
	foreach ( array( 'ssy_subs' => array( 'email', 'status', 'token' ), 'ssy_payments' => array( 'sub_id', 'email', 'created_at' ) ) as $t => $cols ) {
		foreach ( $cols as $c ) {
			try {
				$pdo->exec( $sqlite ? "CREATE INDEX IF NOT EXISTS idx_{$t}_{$c} ON $t ($c)" : "CREATE INDEX idx_{$t}_{$c} ON $t ($c)" );
			} catch ( Exception $e ) {
				// Index exists (MySQL).
			}
		}
	}
}

function q( $sql, $args = array() ) {
	$st = db()->prepare( $sql );
	$st->execute( array_values( $args ) );
	return $st;
}

function row( $sql, $args = array() ) {
	$r = q( $sql, $args )->fetch();
	return $r ? $r : null;
}

function rows( $sql, $args = array() ) {
	return q( $sql, $args )->fetchAll();
}

function val( $sql, $args = array() ) {
	$v = q( $sql, $args )->fetchColumn();
	return false === $v ? null : $v;
}

function insert( $table, $data ) {
	$cols = array_keys( $data );
	q( "INSERT INTO $table (" . implode( ',', $cols ) . ') VALUES (' . implode( ',', array_fill( 0, count( $cols ), '?' ) ) . ')', array_values( $data ) );
	return (int) db()->lastInsertId();
}

function update( $table, $data, $where_col, $where_val ) {
	$set = array();
	foreach ( array_keys( $data ) as $c ) {
		$set[] = "$c = ?";
	}
	return q( "UPDATE $table SET " . implode( ',', $set ) . " WHERE $where_col = ?", array_merge( array_values( $data ), array( $where_val ) ) )->rowCount();
}

/* Key-value store (replaces WordPress options/transients). */
function kv_get( $k ) {
	$r = row( 'SELECT v, expires FROM ssy_kv WHERE k = ?', array( $k ) );
	if ( ! $r ) {
		return null;
	}
	if ( (int) $r['expires'] && (int) $r['expires'] < time() ) {
		q( 'DELETE FROM ssy_kv WHERE k = ?', array( $k ) );
		return null;
	}
	return json_decode( $r['v'], true );
}

function kv_set( $k, $v, $expires = 0 ) {
	$j = json_encode( $v );
	if ( row( 'SELECT k FROM ssy_kv WHERE k = ?', array( $k ) ) ) {
		q( 'UPDATE ssy_kv SET v = ?, expires = ? WHERE k = ?', array( $j, (int) $expires, $k ) );
	} else {
		q( 'INSERT INTO ssy_kv (k, v, expires) VALUES (?, ?, ?)', array( $k, $j, (int) $expires ) );
	}
	if ( 1 === random_int( 1, 50 ) ) {
		q( 'DELETE FROM ssy_kv WHERE expires > 0 AND expires < ?', array( time() ) );
	}
}

/* ---------- Subscriptions ---------- */

function insert_sub( $data ) {
	$now  = now();
	$data = array_merge( array( 'created_at' => $now, 'updated_at' => $now, 'token' => bin2hex( random_bytes( 20 ) ) ), $data );
	try {
		insert( 'ssy_subs', $data );
	} catch ( Exception $e ) {
		return get_sub( $data['sub_id'] );
	}
	return get_sub( $data['sub_id'] );
}

function get_sub( $sub_id ) {
	return row( 'SELECT * FROM ssy_subs WHERE sub_id = ?', array( $sub_id ) );
}

function get_sub_by_id( $id ) {
	return row( 'SELECT * FROM ssy_subs WHERE id = ?', array( (int) $id ) );
}

function get_sub_by_token( $t ) {
	if ( strlen( (string) $t ) < 20 ) {
		return null;
	}
	return row( 'SELECT * FROM ssy_subs WHERE token = ?', array( $t ) );
}

function update_sub( $sub_id, $data ) {
	$data['updated_at'] = now();
	return update( 'ssy_subs', $data, 'sub_id', $sub_id );
}

/* ---------- Payments ---------- */

function get_payment( $pid ) {
	return row( 'SELECT * FROM ssy_payments WHERE payment_id = ?', array( $pid ) );
}

/** Insert once; returns the stored row, or null if it already existed. */
function insert_payment( $data ) {
	if ( get_payment( $data['payment_id'] ) ) {
		return null;
	}
	if ( empty( $data['created_at'] ) ) {
		$data['created_at'] = now();
	}
	try {
		$id = insert( 'ssy_payments', $data );
	} catch ( Exception $e ) {
		return null; // duplicate (race)
	}
	$prefix  = preg_replace( '/[^A-Za-z0-9\-]/', '', (string) opt( 'receipt_prefix' ) );
	$receipt = ( $prefix ? $prefix . '-' : '' ) . date( 'Y', strtotime( $data['created_at'] ) ) . '-' . str_pad( (string) $id, 6, '0', STR_PAD_LEFT );
	update( 'ssy_payments', array( 'receipt_no' => $receipt ), 'id', $id );
	return get_payment( $data['payment_id'] );
}

function payments_for_sub( $sub_id ) {
	return rows( 'SELECT * FROM ssy_payments WHERE sub_id = ? ORDER BY created_at DESC', array( $sub_id ) );
}

function payments_for_email( $email, $from = '1970-01-01 00:00:00', $to = '9999-12-31 23:59:59' ) {
	return rows( "SELECT * FROM ssy_payments WHERE email = ? AND status = 'captured' AND created_at >= ? AND created_at < ? ORDER BY created_at DESC", array( strtolower( $email ), $from, $to ) );
}

/* ================================================================ */
/* Razorpay API                                                      */
/* ================================================================ */

function rzp( $method, $path, $body = null ) {
	$key    = trim( (string) opt( 'key_id' ) );
	$secret = trim( (string) opt( 'key_secret' ) );
	if ( '' === $key || '' === $secret ) {
		return array( 'error' => 'Razorpay API keys are not set in api/config.php.' );
	}
	$base = getenv( 'SSY_RZP_BASE' ) ? getenv( 'SSY_RZP_BASE' ) : 'https://api.razorpay.com/v1/'; // env override only for local testing
	$ch   = curl_init( $base . ltrim( $path, '/' ) );
	curl_setopt_array( $ch, array(
		CURLOPT_CUSTOMREQUEST  => $method,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT        => 25,
		CURLOPT_USERPWD        => $key . ':' . $secret,
		CURLOPT_HTTPHEADER     => array( 'Content-Type: application/json' ),
	) );
	if ( null !== $body ) {
		curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $body ) );
	}
	$res  = curl_exec( $ch );
	$code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	$err  = curl_error( $ch );
	curl_close( $ch );
	if ( false === $res ) {
		return array( 'error' => 'Network: ' . $err );
	}
	$data = json_decode( $res, true );
	if ( $code < 200 || $code >= 300 ) {
		return array( 'error' => isset( $data['error']['description'] ) ? $data['error']['description'] : 'HTTP ' . $code );
	}
	return is_array( $data ) ? $data : array();
}

function rzp_err( $r ) {
	return is_array( $r ) && isset( $r['error'] ) && is_string( $r['error'] ) ? $r['error'] : '';
}

function rzp_mode() {
	return 0 === strpos( (string) opt( 'key_id' ), 'rzp_live_' ) ? 'live' : 'test';
}

/** Find (or create and cache) a plan for frequency + amount. Returns plan id or array('error'). */
function plan_id( $freq, $paise, $force = false ) {
	$freqs = frequencies();
	if ( ! isset( $freqs[ $freq ] ) ) {
		return array( 'error' => 'Invalid frequency' );
	}
	$ck = 'plan_' . substr( md5( (string) opt( 'key_id' ) ), 0, 8 ) . '_' . $freq . '_' . (int) $paise;
	if ( ! $force && ( $cached = kv_get( $ck ) ) ) {
		return $cached;
	}
	$f    = $freqs[ $freq ];
	$plan = rzp( 'POST', 'plans', array(
		'period'   => $f['period'],
		'interval' => $f['interval'],
		'item'     => array( 'name' => $f['plan'] . ' ' . inr( $paise ), 'amount' => (int) $paise, 'currency' => 'INR', 'description' => mb_substr( (string) opt( 'org_name' ), 0, 200 ) ),
		'notes'    => array( 'source' => 'ssy_ad' ),
	) );
	if ( rzp_err( $plan ) ) {
		return $plan;
	}
	kv_set( $ck, $plan['id'] );
	return $plan['id'];
}

/* ================================================================ */
/* Business logic                                                    */
/* ================================================================ */

function donor_fields( $src ) {
	$out = array();
	foreach ( array( 'name', 'email', 'phone', 'pan', 'address', 'campaign', 'dedication', 'anonymous' ) as $k ) {
		$out[ $k ] = isset( $src[ $k ] ) && null !== $src[ $k ] ? $src[ $k ] : ( 'anonymous' === $k ? 0 : '' );
	}
	$out['email']     = strtolower( (string) $out['email'] );
	$out['anonymous'] = (int) $out['anonymous'];
	return $out;
}

/** Record a successful payment exactly once and send emails. */
function record_payment( $data, $sub = null ) {
	$row = insert_payment( $data );
	if ( ! $row ) {
		return get_payment( $data['payment_id'] );
	}
	if ( $sub ) {
		$sub = get_sub( $sub['sub_id'] );
	}
	email_receipt( $row, $sub );
	$first = $sub ? ( 1 === (int) val( 'SELECT COUNT(*) FROM ssy_payments WHERE sub_id = ?', array( $sub['sub_id'] ) ) ) : true;
	if ( $first ) {
		$freqs = frequencies();
		$suf   = ( $sub && isset( $freqs[ $sub['frequency'] ] ) ) ? $freqs[ $sub['frequency'] ]['suffix'] : '';
		admin_alert(
			( $sub ? 'New AutoPay donor' : 'New one-time donation' ) . ': ' . inr( $row['amount'] ) . $suf,
			array(
				'Name'     => $row['name'],
				'Email'    => $row['email'],
				'Phone'    => $row['phone'],
				'Amount'   => inr( $row['amount'] ),
				'Type'     => freq_label( $sub ? $sub['frequency'] : 'onetime' ),
				'Campaign' => $row['campaign'] ? $row['campaign'] : '—',
				'Paid via' => method_label( $row['method'] ),
			)
		);
	}
	return $row;
}

/** Update our row from a Razorpay subscription entity. */
function sync_entity( $e ) {
	if ( empty( $e['id'] ) ) {
		return array( 'prev' => '', 'row' => null );
	}
	$r = get_sub( $e['id'] );
	if ( ! $r ) {
		$n = isset( $e['notes'] ) && is_array( $e['notes'] ) ? $e['notes'] : array();
		if ( empty( $n['source'] ) || 'ssy_ad' !== $n['source'] ) {
			return array( 'prev' => '', 'row' => null );
		}
		$r = insert_sub( array(
			'sub_id'      => $e['id'],
			'plan_id'     => isset( $e['plan_id'] ) ? $e['plan_id'] : '',
			'name'        => isset( $n['name'] ) ? clean( $n['name'], 190 ) : '',
			'email'       => isset( $n['email'] ) ? strtolower( clean( $n['email'], 190 ) ) : '',
			'phone'       => isset( $n['phone'] ) ? clean( $n['phone'], 20 ) : '',
			'pan'         => isset( $n['pan'] ) ? clean( $n['pan'], 12 ) : '',
			'address'     => isset( $n['address'] ) ? clean( $n['address'] ) : '',
			'campaign'    => isset( $n['campaign'] ) ? clean( $n['campaign'], 100 ) : '',
			'dedication'  => isset( $n['dedication'] ) ? clean( $n['dedication'] ) : '',
			'anonymous'   => ! empty( $n['anonymous'] ) ? 1 : 0,
			'amount'      => isset( $n['amount'] ) ? (int) $n['amount'] : 0,
			'frequency'   => isset( $n['frequency'] ) ? key_clean( $n['frequency'] ) : 'monthly',
			'total_count' => isset( $e['total_count'] ) ? (int) $e['total_count'] : 0,
			'status'      => 'created',
		) );
		if ( ! $r ) {
			return array( 'prev' => '', 'row' => null );
		}
	}
	$prev = $r['status'];
	$u    = array();
	if ( ! empty( $e['status'] ) ) {
		$u['status'] = key_clean( $e['status'] );
	}
	if ( isset( $e['paid_count'] ) ) {
		$u['paid_count'] = (int) $e['paid_count'];
	}
	if ( isset( $e['total_count'] ) ) {
		$u['total_count'] = (int) $e['total_count'];
	}
	if ( ! empty( $e['short_url'] ) ) {
		$u['short_url'] = clean( $e['short_url'] );
	}
	if ( array_key_exists( 'charge_at', $e ) ) {
		$u['next_charge_at'] = ts_to_dt( $e['charge_at'] );
	}
	if ( $u ) {
		update_sub( $e['id'], $u );
	}
	return array( 'prev' => $prev, 'row' => get_sub( $e['id'] ) );
}

function sync_sub( $sub_id ) {
	$e = rzp( 'GET', 'subscriptions/' . rawurlencode( $sub_id ) );
	if ( rzp_err( $e ) ) {
		return $e;
	}
	return sync_entity( $e );
}

/** Cancel immediately. Returns true or array('error'). */
function cancel_sub( $sub, $by = 'donor' ) {
	if ( in_array( $sub['status'], array( 'cancelled', 'completed', 'expired' ), true ) ) {
		return true;
	}
	$res = rzp( 'POST', 'subscriptions/' . rawurlencode( $sub['sub_id'] ) . '/cancel', array( 'cancel_at_cycle_end' => 0 ) );
	if ( rzp_err( $res ) ) {
		$s = sync_sub( $sub['sub_id'] );
		if ( ! rzp_err( $s ) && $s['row'] && 'cancelled' === $s['row']['status'] ) {
			return true;
		}
		return $res;
	}
	sync_entity( $res );
	update_sub( $sub['sub_id'], array( 'status' => 'cancelled' ) );
	$fresh = get_sub( $sub['sub_id'] );
	email_cancelled( $fresh );
	admin_alert( 'AutoPay cancelled by ' . ( 'admin' === $by ? 'admin' : 'donor' ), array(
		'Name'   => $fresh['name'],
		'Email'  => $fresh['email'],
		'Amount' => inr( $fresh['amount'] ) . ' ' . freq_label( $fresh['frequency'] ),
		'Paid'   => $fresh['paid_count'] . ' / ' . $fresh['total_count'],
	) );
	return true;
}

/** Record a one-time donation (checkout verify + order.paid webhook). */
function record_order_payment( $order_id, $payment_id, $payment = null, $order = null ) {
	if ( $p = get_payment( $payment_id ) ) {
		return $p;
	}
	$data = kv_get( 'order_' . $order_id );
	if ( ! is_array( $data ) ) {
		if ( null === $order ) {
			$order = rzp( 'GET', 'orders/' . rawurlencode( $order_id ) );
		}
		$n = ( ! rzp_err( $order ) && isset( $order['notes'] ) && is_array( $order['notes'] ) ) ? $order['notes'] : array();
		if ( empty( $n['source'] ) || 'ssy_ad' !== $n['source'] ) {
			return null;
		}
		$data = array();
		foreach ( array( 'name', 'email', 'phone', 'pan', 'address', 'campaign', 'dedication' ) as $k ) {
			$data[ $k ] = isset( $n[ $k ] ) ? clean( $n[ $k ] ) : '';
		}
		$data['anonymous'] = ! empty( $n['anonymous'] ) ? 1 : 0;
	}
	if ( null === $payment ) {
		$payment = rzp( 'GET', 'payments/' . rawurlencode( $payment_id ) );
	}
	if ( rzp_err( $payment ) || ! isset( $payment['status'] ) || 'captured' !== $payment['status'] ) {
		return null; // the order.paid webhook will record it once captured
	}
	return record_payment( array_merge( donor_fields( $data ), array(
		'payment_id' => $payment_id,
		'order_id'   => $order_id,
		'type'       => 'onetime',
		'frequency'  => 'onetime',
		'amount'     => (int) $payment['amount'],
		'method'     => isset( $payment['method'] ) ? key_clean( $payment['method'] ) : '',
		'status'     => 'captured',
	) ) );
}

/* ================================================================ */
/* Emails                                                            */
/* ================================================================ */

function email_wrap( $heading, $inner ) {
	$c    = h( opt( 'primary_color' ) ? opt( 'primary_color' ) : '#7c5cc4' );
	$org  = h( opt( 'org_name' ) );
	$html = '<div style="background:#f4f5f7;padding:24px 12px;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#1f2937"><div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb">';
	$html .= '<div style="background:' . $c . ';padding:18px 24px;color:#ffffff">';
	$html .= '<div style="font-size:15px;opacity:.9">' . $org . '</div><div style="font-size:20px;font-weight:700;margin-top:4px">' . h( $heading ) . '</div></div>';
	$html .= '<div style="padding:24px;font-size:15px;line-height:1.6">' . $inner . '</div>';
	$html .= '<div style="padding:14px 24px;background:#f9fafb;color:#6b7280;font-size:12px;border-top:1px solid #e5e7eb">' . $org;
	if ( opt( 'org_email' ) ) {
		$html .= ' · ' . h( opt( 'org_email' ) );
	}
	if ( opt( 'org_phone' ) ) {
		$html .= ' · ' . h( opt( 'org_phone' ) );
	}
	return $html . '</div></div></div>';
}

function email_button( $url, $label ) {
	$c = h( opt( 'primary_color' ) ? opt( 'primary_color' ) : '#7c5cc4' );
	return '<a href="' . h( $url ) . '" style="display:inline-block;background:' . $c . ';color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:600;margin:4px 8px 4px 0">' . h( $label ) . '</a>';
}

function email_table( $rows ) {
	$t = '<table style="width:100%;border-collapse:collapse;margin:12px 0;font-size:14px">';
	foreach ( $rows as $k => $v ) {
		$t .= '<tr><td style="padding:6px 0;color:#6b7280;border-bottom:1px solid #f0f0f0;width:45%">' . h( $k ) . '</td><td style="padding:6px 0;border-bottom:1px solid #f0f0f0;font-weight:600">' . h( $v ) . '</td></tr>';
	}
	return $t . '</table>';
}

function send_mail( $to, $subject, $heading, $inner ) {
	if ( ! is_email( $to ) ) {
		return false;
	}
	$html = email_wrap( $heading, $inner );
	$from = opt( 'mail_from' ) ? opt( 'mail_from' ) : opt( 'org_email' );
	$name = opt( 'org_name' );
	try {
		if ( opt( 'smtp_host' ) ) {
			return smtp_send( $to, $subject, $html, $from, $name, opt( 'org_email' ) );
		}
		$enc     = '=?UTF-8?B?' . base64_encode( $subject ) . '?=';
		$headers = 'MIME-Version: 1.0' . "\r\n" . 'Content-Type: text/html; charset=UTF-8' . "\r\n" . 'From: =?UTF-8?B?' . base64_encode( $name ) . '?= <' . $from . '>' . "\r\n";
		if ( is_email( opt( 'org_email' ) ) ) {
			$headers .= 'Reply-To: ' . opt( 'org_email' ) . "\r\n";
		}
		return @mail( $to, $enc, $html, $headers, '-f' . $from );
	} catch ( Exception $e ) {
		log_error( 'email', $e->getMessage() );
		return false;
	}
}

/** Minimal SMTP client (SSL on 465, STARTTLS on 587). */
function smtp_send( $to, $subject, $html, $from, $name, $reply ) {
	$host = opt( 'smtp_host' );
	$port = (int) opt( 'smtp_port' );
	$fp   = @stream_socket_client( ( 465 === $port ? 'ssl://' : 'tcp://' ) . $host . ':' . $port, $errno, $errstr, 20 );
	if ( ! $fp ) {
		throw new Exception( "SMTP connect failed: $errstr" );
	}
	stream_set_timeout( $fp, 20 );
	$read = function () use ( $fp ) {
		$data = '';
		while ( ( $line = fgets( $fp, 515 ) ) !== false ) {
			$data .= $line;
			if ( isset( $line[3] ) && ' ' === $line[3] ) {
				break;
			}
		}
		return $data;
	};
	$cmd = function ( $c, $expect ) use ( $fp, $read ) {
		fwrite( $fp, $c . "\r\n" );
		$r = $read();
		if ( (int) substr( $r, 0, 3 ) !== $expect ) {
			throw new Exception( 'SMTP error after ' . explode( ' ', $c )[0] . ': ' . trim( $r ) );
		}
		return $r;
	};
	$read();
	$me = isset( $_SERVER['SERVER_NAME'] ) ? $_SERVER['SERVER_NAME'] : 'localhost';
	$cmd( 'EHLO ' . $me, 250 );
	if ( 587 === $port ) {
		$cmd( 'STARTTLS', 220 );
		stream_socket_enable_crypto( $fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT );
		$cmd( 'EHLO ' . $me, 250 );
	}
	$cmd( 'AUTH LOGIN', 334 );
	$cmd( base64_encode( opt( 'smtp_user' ) ), 334 );
	$cmd( base64_encode( opt( 'smtp_pass' ) ), 235 );
	$cmd( 'MAIL FROM:<' . $from . '>', 250 );
	$cmd( 'RCPT TO:<' . $to . '>', 250 );
	$cmd( 'DATA', 354 );
	$hdr  = 'Date: ' . date( 'r' ) . "\r\n";
	$hdr .= 'From: =?UTF-8?B?' . base64_encode( $name ) . '?= <' . $from . ">\r\n";
	$hdr .= 'To: <' . $to . ">\r\n";
	if ( is_email( $reply ) ) {
		$hdr .= 'Reply-To: <' . $reply . ">\r\n";
	}
	$hdr .= 'Subject: =?UTF-8?B?' . base64_encode( $subject ) . "?=\r\n";
	$hdr .= 'Message-ID: <' . bin2hex( random_bytes( 8 ) ) . '@' . ( strpos( $from, '@' ) ? substr( $from, strpos( $from, '@' ) + 1 ) : 'localhost' ) . ">\r\n";
	$hdr .= "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n";
	fwrite( $fp, $hdr . "\r\n" . chunk_split( base64_encode( $html ) ) . "\r\n.\r\n" );
	$r = $read();
	if ( 250 !== (int) substr( $r, 0, 3 ) ) {
		throw new Exception( 'SMTP send failed: ' . trim( $r ) );
	}
	fwrite( $fp, "QUIT\r\n" );
	fclose( $fp );
	return true;
}

function email_receipt( $p, $sub = null ) {
	if ( ! opt( 'donor_emails' ) ) {
		return;
	}
	$freqs = frequencies();
	$rows  = array(
		'Receipt no.' => $p['receipt_no'],
		'Amount'      => inr( $p['amount'] ),
		'Date'        => fmt_date( $p['created_at'] ),
		'Paid via'    => method_label( $p['method'] ),
		'Payment ID'  => $p['payment_id'],
	);
	$i = '<p>Dear ' . h( $p['name'] ) . ',</p>';
	if ( $sub && isset( $freqs[ $sub['frequency'] ] ) ) {
		$i .= '<p>Thank you! We have received your AutoPay donation of <strong>' . h( inr( $p['amount'] ) ) . '</strong>. Your donation will continue automatically ' . h( $freqs[ $sub['frequency'] ]['word'] ) . '. Your bank or UPI app will remind you before every debit.</p>';
		if ( ! empty( $sub['next_charge_at'] ) && in_array( $sub['status'], array( 'active', 'authenticated' ), true ) ) {
			$rows['Next debit'] = fmt_date( $sub['next_charge_at'] );
		}
	} else {
		$i .= '<p>Thank you for your generous donation of <strong>' . h( inr( $p['amount'] ) ) . '</strong>.</p>';
	}
	$i .= email_table( $rows );
	if ( opt( 'g80_enabled' ) ) {
		$i .= '<p style="font-size:13px;color:#4b5563">' . h( opt( 'g80_text' ) ) . '</p>';
	}
	$i .= '<p>' . email_button( receipt_url( $p['payment_id'] ), 'Download receipt' );
	if ( $sub ) {
		$i .= email_button( manage_url( $sub['token'] ), 'Manage AutoPay' );
	}
	$i .= '</p>';
	send_mail( $p['email'], 'Thank you for your donation – ' . opt( 'org_name' ) . ' (' . $p['receipt_no'] . ')', 'Donation received', $i );
}

function email_payment_issue( $sub, $kind ) {
	if ( ! opt( 'donor_emails' ) ) {
		return;
	}
	$amt = '<strong>' . h( inr( $sub['amount'] ) ) . '</strong>';
	if ( 'halted' === $kind ) {
		$msg   = 'We could not collect your AutoPay donation of ' . $amt . ' after several attempts, so your AutoPay has been paused by the bank. Your support means a lot to us – you can restart it in under a minute.';
		$title = 'Your AutoPay donation needs attention';
	} else {
		$msg   = 'We could not collect your AutoPay donation of ' . $amt . ' this time. It will be retried automatically – please make sure your account has sufficient balance and the mandate is active in your UPI app or card.';
		$title = 'AutoPay payment could not be collected';
	}
	$i = '<p>Dear ' . h( $sub['name'] ) . ',</p><p>' . $msg . '</p><p>';
	if ( 'halted' === $kind ) {
		$i .= email_button( donate_url(), 'Restart my AutoPay' );
	}
	$i .= email_button( manage_url( $sub['token'] ), 'View my AutoPay' ) . '</p>';
	send_mail( $sub['email'], $title . ' – ' . opt( 'org_name' ), $title, $i );
}

function email_cancelled( $sub ) {
	if ( ! opt( 'donor_emails' ) ) {
		return;
	}
	$i  = '<p>Dear ' . h( $sub['name'] ) . ',</p>';
	$i .= '<p>Your AutoPay donation of <strong>' . h( inr( $sub['amount'] ) ) . '</strong> has been cancelled. No further amounts will be debited. Thank you for everything you have contributed – it has made a real difference.</p>';
	$i .= '<p>' . email_button( donate_url(), 'Donate again' ) . '</p>';
	send_mail( $sub['email'], 'Your AutoPay donation is cancelled – ' . opt( 'org_name' ), 'AutoPay cancelled', $i );
}

function email_completed( $sub ) {
	if ( ! opt( 'donor_emails' ) ) {
		return;
	}
	$i  = '<p>Dear ' . h( $sub['name'] ) . ',</p><p>Your AutoPay donation plan has completed all its scheduled payments. Thank you for standing with us month after month!</p>';
	$i .= '<p>' . email_button( donate_url(), 'Continue my support' ) . '</p>';
	send_mail( $sub['email'], 'Thank you for your support – ' . opt( 'org_name' ), 'AutoPay completed', $i );
}

function email_payment_link( $sub ) {
	$url = $sub['short_url'] ? $sub['short_url'] : donate_url();
	$i   = '<p>Dear ' . h( $sub['name'] ) . ',</p><p>Please use the button below to approve your AutoPay donation of <strong>' . h( inr( $sub['amount'] ) ) . '</strong>. It takes less than a minute with any UPI app, debit card or credit card.</p><p>' . email_button( $url, 'Approve AutoPay' ) . '</p>';
	return send_mail( $sub['email'], 'Complete your AutoPay donation – ' . opt( 'org_name' ), 'One step left', $i );
}

function email_portal_link( $email, $url ) {
	$i  = '<p>Use the button below to view your donations, download receipts and annual statements, or manage your AutoPay. The link is valid for 24 hours.</p>';
	$i .= '<p>' . email_button( $url, 'Open my donations' ) . '</p><p style="font-size:13px;color:#6b7280">If you did not request this, you can ignore this email.</p>';
	send_mail( $email, 'Your donation history – ' . opt( 'org_name' ), 'Your donations', $i );
}

function admin_alert( $subject, $rows ) {
	if ( ! opt( 'admin_alerts' ) || ! opt( 'admin_email' ) ) {
		return;
	}
	$i = email_table( $rows ) . '<p>' . email_button( opt( 'base_url' ) . 'admin/', 'Open dashboard' ) . '</p>';
	send_mail( opt( 'admin_email' ), '[' . opt( 'org_name' ) . '] ' . $subject, $subject, $i );
}

/* ================================================================ */
/* Public organisation info (for receipts/statements)                */
/* ================================================================ */

function org_public() {
	$o = opt();
	return array(
		'name'        => $o['org_name'],
		'address'     => $o['org_address'],
		'reg_no'      => $o['org_reg_no'],
		'pan'         => $o['org_pan'],
		'logo'        => $o['org_logo'],
		'signatory'   => $o['signatory'],
		'g80_enabled' => (bool) $o['g80_enabled'],
		'g80_number'  => $o['g80_number'],
		'g80_text'    => $o['g80_text'],
		'color'       => $o['primary_color'],
	);
}
