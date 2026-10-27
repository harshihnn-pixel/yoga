<?php
/** Public form settings for the donate page (no secrets). GET api/settings.php */
require __DIR__ . '/lib.php';

$o     = opt();
$all   = frequencies();
$freqs = array();
foreach ( allowed_freqs() as $k ) {
	$freqs[] = 'onetime' === $k
		? array( 'key' => 'onetime', 'label' => 'One-time', 'suffix' => '', 'word' => '' )
		: array( 'key' => $k, 'label' => $all[ $k ]['label'], 'suffix' => $all[ $k ]['suffix'], 'word' => $all[ $k ]['word'] );
}
$durs = array();
foreach ( durations() as $y => $label ) {
	$durs[] = array( 'value' => $y, 'label' => $label, 'phrase' => 0 === $y ? 'until you cancel' : ( 1 === $y ? 'for 1 year' : "for $y years" ) );
}
$dd = (int) $o['default_duration'];
if ( ! isset( durations()[ $dd ] ) ) {
	$dd = (int) array_key_first( durations() );
}

ok( array(
	'ready'      => '' !== trim( (string) $o['key_id'] ) && '' !== trim( (string) $o['key_secret'] ),
	'check'      => array(
		'key_id_found'     => '' !== trim( (string) $o['rzp_live_TplbSWgK6HZWPA'] ),
		'key_secret_found' => '' !== trim( (string) $o['8ayM03VRZFGuVXMZ67FOswgu'] ),
		'env_names_seen'   => isset( $o['_env_names_seen'] ) ? $o['_env_names_seen'] : array(),
	),
	'mode'       => rzp_mode(),
	'title'      => $o['form_title'],
	'freqs'      => $freqs,
	'amounts'    => array_values( array_map( 'intval', (array) $o['preset_amounts'] ) ),
	'default'    => (int) $o['default_amount'],
	'impacts'    => (object) $o['impact_texts'],
	'min'        => (int) $o['min_amount'],
	'max'        => (int) $o['max_amount'],
	'maxOnce'    => (int) $o['max_onetime'],
	'durations'  => $durs,
	'duration'   => $dd,
	'thankyou'   => $o['thankyou_url'],
	'share'      => $o['share_whatsapp'] ? str_replace( array( '{org}', '{url}' ), array( $o['org_name'], $o['base_url'] ), $o['share_text'] ) : '',
	'consent'    => (bool) $o['require_consent'],
	'consentText'=> $o['consent_text'],
	'pan'        => (bool) $o['g80_enabled'],
	'address'    => (bool) $o['g80_enabled'],
	'g80'        => (bool) $o['g80_enabled'],
	'dedication' => (bool) $o['allow_dedication'],
	'anonymous'  => (bool) $o['allow_anonymous'],
	'color'      => $o['primary_color'],
) );
