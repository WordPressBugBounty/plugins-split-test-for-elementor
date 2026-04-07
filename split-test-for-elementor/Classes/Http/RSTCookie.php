<?php

namespace SplitTestForElementor\Classes\Http;

/**
 * Static accessor for the $_COOKIE superglobal.
 *
 * All cookie keys are automatically prefixed with "elementor_split_test_",
 * so callers pass only the suffix:
 *
 *   RSTCookie::has('client_id')           → isset($_COOKIE['elementor_split_test_client_id'])
 *   RSTCookie::string('client_id')        → $_COOKIE['elementor_split_test_client_id']
 *   RSTCookie::int('1_variation')         → (int) $_COOKIE['elementor_split_test_1_variation']
 *   RSTCookie::uuid('client_id')          → validated UUID v4 or null
 */
class RSTCookie extends RSTInputBag {

	const PREFIX = 'elementor_split_test_';

	const CLIENT_ID = "client_id";

	protected static function source(): array {
		return $_COOKIE;
	}

	/**
	 * Returns true when the prefixed key exists in $_COOKIE.
	 */
	public static function has( string $key ): bool {
		return array_key_exists( self::PREFIX . $key, $_COOKIE );
	}

	/**
	 * Returns the raw cookie value for the prefixed key, or null when absent.
	 *
	 * @return mixed|null
	 */
	protected static function raw( string $key ) {
		$prefixedKey = self::PREFIX . $key;
		return array_key_exists( $prefixedKey, $_COOKIE ) ? $_COOKIE[ $prefixedKey ] : null;
	}

	/**
	 * Sets a cookie with the standard prefix applied to $key.
	 *
	 * @param string $key      Cookie key suffix (without prefix).
	 * @param mixed  $value    Cookie value.
	 * @param string $time     strtotime-compatible expiry string (default +12 month).
	 * @param bool   $httpOnly Set to true for cookies that must not be read by
	 *                         JavaScript (e.g. the client-tracking UUID).
	 *                         Variation cookies must stay false because the
	 *                         cache-buster reads them client-side.
	 */
	public static function set( string $key, $value, string $time = '+12 month', bool $httpOnly = false ): void {
		$name   = self::PREFIX . $key;
		$path   = parse_url( home_url( '/' ), PHP_URL_PATH );
		$host   = parse_url( home_url( '/' ), PHP_URL_HOST );
		$expiry = strtotime( $time );
		setcookie( $name, $value, $expiry, $path, $host, false, $httpOnly );
		$_COOKIE[ $name ] = $value;
	}
}
