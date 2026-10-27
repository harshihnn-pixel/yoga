<?php
/**
 * YOUR SECRETS – for running on your own computer.
 * 1. Copy this file and name the copy:  config.local.php   (same folder)
 * 2. Fill in the values below. config.local.php is never uploaded to GitHub.
 * Anything here overrides config.php.
 */
return array(
	'key_id'         => 'rzp_live_TplbSWgK6HZWPA',   // use TEST keys for testing
	'key_secret'     => '8ayM03VRZFGuVXMZ67FOswgu',
	'webhook_secret' => 'aurastha@Balaji',
	// Main admin login (Email + Password) – also receives donation alert emails:
	'admin_email'    => 'wdt.aurastha@gmail.com',
	'admin_password' => 'Webde@auras!23',
	// Extra admins (optional) – each logs in with their own email + password:
	// 'admins' => array(
	//     'person2@example.com' => 'Their-Password-1',
	//     'person3@example.com' => 'Their-Password-2',
	// ),
	// 'smtp_host' => 'smtp.hostinger.com', 'smtp_user' => '', 'smtp_pass' => '',
);
