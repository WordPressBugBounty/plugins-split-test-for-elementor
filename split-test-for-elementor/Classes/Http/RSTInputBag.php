<?php

namespace SplitTestForElementor\Classes\Http;

/**
 * Abstract base for static superglobal accessors ($_GET, $_POST).
 *
 * All methods are static and use late static binding (static::source()) so
 * that each concrete subclass (RSTGet, RSTPost) transparently reads from its
 * own superglobal.  No instantiation is required — call methods directly on
 * the subclass:
 *
 *   RSTGet::string('scope', 'test')
 *   RSTPost::int('page')
 *   RSTPost::tryGetInt('id', $id)
 *
 * Typed accessors always return a safe default when the key is absent or the
 * raw value cannot be coerced to the requested type.
 *
 * tryGet* methods mirror the .NET TryGetValue pattern: they return a bool
 * indicating success and write the typed result into the $out reference.
 */
abstract class RSTInputBag {

	/**
	 * Returns the raw superglobal array this bag reads from.
	 * Subclasses override this to return $_GET or $_POST.
	 *
	 * @return array<string, mixed>
	 */
	protected static function source(): array {
		return [];
	}

	// -------------------------------------------------------------------------
	// Existence check
	// -------------------------------------------------------------------------

	/**
	 * Returns true when the key is present in the source (even if its value is
	 * empty or null).
	 */
	public static function has( string $key ): bool {
		return array_key_exists( $key, static::source() );
	}

	// -------------------------------------------------------------------------
	// Typed accessors (return $default on missing / non-coercible value)
	// -------------------------------------------------------------------------

	/**
	 * Returns the value cast to int, or $default when absent / not numeric.
	 */
	public static function int( string $key, int $default = 0 ): int {
		$raw = static::raw( $key );
		if ( $raw === null ) {
			return $default;
		}
		if ( ! is_numeric( $raw ) ) {
			return $default;
		}
		return (int) $raw;
	}

	/**
	 * Returns the value cast to float, or $default when absent / not numeric.
	 */
	public static function float( string $key, float $default = 0.0 ): float {
		$raw = static::raw( $key );
		if ( $raw === null ) {
			return $default;
		}
		if ( ! is_numeric( $raw ) ) {
			return $default;
		}
		return (float) $raw;
	}

	/**
	 * Returns the value cast to string, or $default when absent.
	 * Rejects arrays / objects — they return $default instead.
	 *
	 * @param bool $sanitize  When true (default) the value is passed through
	 *                        sanitize_text_field() before being returned.
	 *                        Pass false for nonces, URLs, or other values that
	 *                        must not be altered by text sanitization.
	 */
	public static function string( string $key, string $default = '', bool $sanitize = true ): string {
		$raw = static::raw( $key );
		if ( $raw === null ) {
			return $default;
		}
		if ( is_array( $raw ) || is_object( $raw ) ) {
			return $default;
		}
		$value = (string) $raw;
		return $sanitize ? sanitize_text_field( $value ) : $value;
	}

	/**
	 * Interprets the value as a boolean.
	 *
	 * Truthy strings: "1", "true", "yes", "on" (case-insensitive).
	 * Falsy  strings: "0", "false", "no", "off", "" (and absent).
	 * Any other string returns $default.
	 */
	public static function bool( string $key, bool $default = false ): bool {
		$raw = static::raw( $key );
		if ( $raw === null ) {
			return $default;
		}
		if ( is_bool( $raw ) ) {
			return $raw;
		}
		$filtered = filter_var( $raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
		if ( $filtered === null ) {
			return $default;
		}
		return $filtered;
	}

	/**
	 * Returns the value as an array, or $default when absent or not an array.
	 */
	public static function array( string $key, array $default = [] ): array {
		$raw = static::raw( $key );
		if ( $raw === null ) {
			return $default;
		}
		if ( ! is_array( $raw ) ) {
			return $default;
		}
		return $raw;
	}

	// -------------------------------------------------------------------------
	// .NET-style TryGet* methods
	// -------------------------------------------------------------------------

	/**
	 * Tries to retrieve the value as int.
	 *
	 * @param string   $key
	 * @param int|null $out  Set to the int value on success, null on failure.
	 * @return bool  True when the key exists and its value is numeric.
	 */
	public static function tryGetInt( string $key, ?int &$out ): bool {
		$raw = static::raw( $key );
		if ( $raw === null || ! is_numeric( $raw ) ) {
			$out = null;
			return false;
		}
		$out = (int) $raw;
		return true;
	}

	/**
	 * Tries to retrieve the value as float.
	 *
	 * @param string     $key
	 * @param float|null $out  Set to the float value on success, null on failure.
	 * @return bool  True when the key exists and its value is numeric.
	 */
	public static function tryGetFloat( string $key, ?float &$out ): bool {
		$raw = static::raw( $key );
		if ( $raw === null || ! is_numeric( $raw ) ) {
			$out = null;
			return false;
		}
		$out = (float) $raw;
		return true;
	}

	/**
	 * Tries to retrieve the value as string.
	 *
	 * @param string      $key
	 * @param string|null $out       Set to the string value on success, null on failure.
	 * @param bool        $sanitize  When true (default) the value is passed through
	 *                               sanitize_text_field() before being written to $out.
	 * @return bool  True when the key exists and is scalar (not array/object).
	 */
	public static function tryGetString( string $key, ?string &$out, bool $sanitize = true ): bool {
		$raw = static::raw( $key );
		if ( $raw === null || is_array( $raw ) || is_object( $raw ) ) {
			$out = null;
			return false;
		}
		$value = (string) $raw;
		$out   = $sanitize ? sanitize_text_field( $value ) : $value;
		return true;
	}

	/**
	 * Tries to retrieve the value as bool.
	 *
	 * Returns false (and sets $out = null) if the value cannot be mapped to a
	 * definitive true/false (i.e. ambiguous strings return failure).
	 *
	 * @param string    $key
	 * @param bool|null $out  Set to the bool value on success, null on failure.
	 * @return bool  True when the key exists and the value is unambiguously boolean.
	 */
	public static function tryGetBool( string $key, ?bool &$out ): bool {
		$raw = static::raw( $key );
		if ( $raw === null ) {
			$out = null;
			return false;
		}
		if ( is_bool( $raw ) ) {
			$out = $raw;
			return true;
		}
		$filtered = filter_var( $raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
		if ( $filtered === null ) {
			$out = null;
			return false;
		}
		$out = $filtered;
		return true;
	}

	/**
	 * Tries to retrieve the value as array.
	 *
	 * @param string     $key
	 * @param array|null $out  Set to the array on success, null on failure.
	 * @return bool  True when the key exists and its value is an array.
	 */
	public static function tryGetArray( string $key, ?array &$out ): bool {
		$raw = static::raw( $key );
		if ( $raw === null || ! is_array( $raw ) ) {
			$out = null;
			return false;
		}
		$out = $raw;
		return true;
	}

	/**
	 * Returns the value when it is a valid UUID v4, or null otherwise.
	 *
	 * A UUID v4 has the form: xxxxxxxx-xxxx-4xxx-[89ab]xxx-xxxxxxxxxxxx
	 * The method returns null when the key is absent, the value is not a
	 * string, or the string does not match the UUID v4 pattern.
	 */
	public static function uuid( string $key ): ?string {
		$raw = static::raw( $key );
		if ( $raw === null || is_array( $raw ) || is_object( $raw ) ) {
			return null;
		}
		$value = (string) $raw;
		if ( ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value ) ) {
			return null;
		}
		return $value;
	}

	// -------------------------------------------------------------------------
	// Internal helpers
	// -------------------------------------------------------------------------

	/**
	 * Returns the raw value for $key, or null when the key is absent.
	 *
	 * @return mixed|null
	 */
	protected static function raw( string $key ) {
		$source = static::source();
		return array_key_exists( $key, $source ) ? $source[ $key ] : null;
	}
}
