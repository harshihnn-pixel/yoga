<?php
/**
 * ================================================================
 *  SANJEEVINI SPARSH YOGA – DONATIONS: ALL SETTINGS IN ONE FILE
 * ================================================================
 *  Edit the values below in VS Code, save, and upload this file.
 *  (This replaces the WordPress "AutoPay Donations → Settings" screen.)
 *
 *  Never share this file – it contains your Razorpay secret key.
 * ================================================================
 */

/*
 * SECRETS (keys, passwords) are NOT written in this file, so it is safe to put on GitHub.
 * Put them in ONE of these places:
 *   - On your computer: copy config.local.example.php to config.local.php and fill it in
 *     (config.local.php is never uploaded to GitHub – see .gitignore).
 *   - On Render / hosting: Environment variables named SSY_KEY_ID, SSY_KEY_SECRET,
 *     SSY_WEBHOOK_SECRET, SSY_ADMIN_PASSWORD, SSY_ADMIN_EMAIL, SSY_SMTP_PASS, SSY_BASE_URL ...
 *     (any setting below can be set as SSY_ + its name in capitals).
 */
$ssy_config = array(

	/* ---------- 1. Razorpay (Dashboard → Account & Settings → API Keys) ---------- */
	'key_id'          => '',            // rzp_test_xxxxx  (later rzp_live_xxxxx)
	'key_secret'      => '',            // the secret shown once when you generate keys
	'webhook_secret'  => '',            // any strong password; type the SAME in Razorpay → Webhooks
	'customer_notify' => true,          // Razorpay also sends its own SMS/email to donors

	/* ---------- 2. Admin dashboard login (/donations/admin/) ---------- */
	'admin_password'  => 'change-this-password',   // set the real one in config.local.php / SSY_ADMIN_PASSWORD
	'admin_email'     => 'info@sanjeevinisparshyoga.com', // gets "new donor" / "payment failed" alerts
	'admin_alerts'    => true,

	/* ---------- 3. Organisation (printed on receipts & statements) ---------- */
	'org_name'        => 'Sanjeevini Sparsh Yoga',
	'org_address'     => '',            // e.g. "No. 12, ..., Bengaluru 560001"
	'org_reg_no'      => '',            // trust / society registration no.
	'org_pan'         => '',
	'org_email'       => 'info@sanjeevinisparshyoga.com',
	'org_phone'       => '',
	'org_logo'        => 'https://www.sanjeevinisparshyoga.com/new-img/yoga_main.png',
	'signatory'       => '',            // name of authorised signatory (blank = no signature line)
	'receipt_prefix'  => 'SSY',         // receipts look like SSY-2026-000001

	/* ---------- 4. Section 80G ---------- */
	'g80_enabled'     => false,         // true = show 80G text, ask PAN + address on the form
	'g80_number'      => '',
	'g80_text'        => 'Donations to this organisation are eligible for deduction under Section 80G of the Income Tax Act, 1961.',

	/* ---------- 5. Donation form ---------- */
	'frequencies'     => array( 'monthly' ),   // any of: 'monthly', 'quarterly', 'yearly'
	'allow_onetime'   => true,                 // also offer a one-time donation tab
	'preset_amounts'  => array( 100, 500, 1000, 2000, 5000 ),
	'default_amount'  => 1000,
	'min_amount'      => 100,
	'max_amount'      => 15000,                // max for AutoPay (UPI AutoPay limit is ₹15,000 without extra auth)
	'max_onetime'     => 500000,
	'duration_choices'=> array( 1, 3, 0 ),     // years; 0 = "Until I cancel"
	'default_duration'=> 0,
	'forever_years'   => 10,                   // "Until I cancel" = this many years in Razorpay
	'primary_color'   => '#7c5cc4',
	'form_title'      => 'Make your sacred offering',
	'require_consent' => false,
	'consent_text'    => 'I authorise Sanjeevini Sparsh Yoga to automatically debit the chosen amount through Razorpay AutoPay until I cancel.',
	'allow_dedication'=> true,
	'allow_anonymous' => true,
	'thankyou_url'    => '',                   // optional; blank = thank-you message inside the form
	'share_whatsapp'  => true,
	'share_text'      => 'I now support {org} every month – helping bring yoga and meditation to everyone. Join me: {url}',
	'impact_texts'    => array(
		100  => 'Supports yoga and meditation for someone seeking inner peace',
		500  => 'Helps share spiritual education and awareness with more seekers',
		1000 => 'Supports yoga and awareness activities for children and youth',
		2000 => 'Extends yoga, meditation and sadhana to those without access',
		5000 => 'Sustains our mission of service to society for a whole month',
	),

	/* ---------- 6. Emails ---------- */
	'donor_emails'    => true,
	'mail_from'       => 'donations@sanjeevinisparshyoga.com', // must be an email on your domain
	// Recommended: send through SMTP so receipts don't land in spam.
	// Create an email account in cPanel/Hostinger and fill in below. Leave smtp_host empty to use PHP mail().
	'smtp_host'       => '',            // e.g. smtp.hostinger.com  /  mail.sanjeevinisparshyoga.com
	'smtp_port'       => 465,           // 465 (SSL) or 587 (TLS)
	'smtp_user'       => '',            // e.g. donations@sanjeevinisparshyoga.com
	'smtp_pass'       => '',

	/* ---------- 7. Website ---------- */
	// Public address of the donations folder (used in email links). Ends with a slash.
	'base_url'        => '',                // blank = detected automatically (works on Render, hosting and your computer)
	'timezone'        => 'Asia/Kolkata',

	/* ---------- 8. Database ---------- */
	// Default: a single SQLite file in api/data/ (no setup needed).
	// To use MySQL instead: 'db_dsn' => 'mysql:host=localhost;dbname=xxx;charset=utf8mb4', plus user/pass.
	'db_dsn'          => '',
	'db_user'         => '',
	'db_pass'         => '',
);

/* ---- Apply config.local.php, then environment variables (SSY_...) ---- */
if ( file_exists( __DIR__ . '/config.local.php' ) ) {
	$ssy_local = require __DIR__ . '/config.local.php';
	if ( is_array( $ssy_local ) ) {
		$ssy_config = array_merge( $ssy_config, $ssy_local );
	}
}
foreach ( $ssy_config as $ssy_k => $ssy_v ) {
	$ssy_e = getenv( 'SSY_' . strtoupper( $ssy_k ) );
	if ( false === $ssy_e || '' === $ssy_e || is_array( $ssy_v ) ) {
		continue;
	}
	if ( is_bool( $ssy_v ) ) {
		$ssy_e = in_array( strtolower( $ssy_e ), array( '1', 'true', 'yes', 'on' ), true );
	} elseif ( is_int( $ssy_v ) ) {
		$ssy_e = (int) $ssy_e;
	}
	$ssy_config[ $ssy_k ] = $ssy_e;
}
return $ssy_config;
