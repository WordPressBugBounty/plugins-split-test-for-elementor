<?php

namespace SplitTestForElementor\Classes\Misc;


use SplitTestForElementor\Classes\Http\RSTPost;

class SecurityHelper {

	public static function userHasTestEditPermission(): bool
	{
		return current_user_can('publish_pages');
	}

	/**
	 * @return void
	 */
	public static function verifyUserPermissionsAndDieOnForbidden(): void
	{
		if (!is_user_logged_in() || !self::userHasTestEditPermission()) {
			wp_die(esc_html__('You do not have permission to access this page.', 'split-test-for-elementor'), '', ['response' => 403]);
		}
	}

	/**
	 * @return bool
	 */
	public static function validNoncePresent(): bool
	{
		return RSTPost::has('nonce') && wp_verify_nonce(RSTPost::string('nonce', '', false), 'test-nonce');
	}

	public static function verifyNonceAndDieOnInvalid(): void
	{
		if (!self::validNoncePresent()) {
			wp_die(esc_html__('Security check failed.', 'split-test-for-elementor'), '', ['response' => 403]);
		}
	}

}