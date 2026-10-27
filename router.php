<?php
/**
 * LOCAL TESTING ONLY (used by START-WEBSITE.bat with "php -S"). Do not upload.
 * Serves the files in this folder; anything not in the zip (images, CSS, JS)
 * is loaded from the live website so the pages look complete.
 */
$path = urldecode( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) );
if ( preg_match( '#/api/(data/|config[^/]*\.php|lib\.php)#i', $path ) || strpos( $path, '..' ) !== false ) {
	http_response_code( 403 );
	exit( 'Forbidden' );
}
if ( '/' === $path ) {
	header( 'Location: /index.html' );
	exit;
}
$file = __DIR__ . str_replace( '/', DIRECTORY_SEPARATOR, $path );
if ( is_dir( $file ) && '/' !== substr( $path, -1 ) ) {
	header( 'Location: ' . $path . '/' );
	exit;
}
if ( file_exists( $file ) || is_dir( $file ) ) {
	return false; // serve local file (PHP files are executed)
}
header( 'Location: https://www.sanjeevinisparshyoga.com' . $_SERVER['REQUEST_URI'], true, 302 );
exit;
