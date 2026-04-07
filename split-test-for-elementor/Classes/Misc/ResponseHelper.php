<?php

namespace SplitTestForElementor\Classes\Misc;

class ResponseHelper
{
	public static function generateErrorResponse(string $errorKey, string $errorMessage, array $payload = [])
	{
		$response = ['success' => false, 'errors' => [
			['key' => $errorKey, 'message' => esc_html__( $errorMessage, 'split-test-for-elementor' )]
		]];
		return array_merge($response, $payload);
	}
}