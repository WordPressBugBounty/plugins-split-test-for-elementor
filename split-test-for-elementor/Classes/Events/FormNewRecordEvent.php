<?php

namespace SplitTestForElementor\Classes\Events;

use SplitTestForElementor\Classes\Http\RSTCookie;
use SplitTestForElementor\Classes\Services\ConversionTracker;

class FormNewRecordEvent
{
	private static $conversionTrack;

	public function __construct() {
		if (self::$conversionTrack == null) {
			self::$conversionTrack = new ConversionTracker();
		}
	}

	public function fire($record, $handler) {
		$clientId = RSTCookie::uuid(RSTCookie::CLIENT_ID);
		if ($clientId === null) {
			return;
		}

		$testId = $record->get_form_settings( 'split_test_control_test_id' );
		$variationId = $record->get_form_settings( 'split_test_control_variation_id' );

		if ($testId == null || $variationId == null) {
			return;
		}

		self::$conversionTrack->trackConversion($testId, $variationId, $clientId);
	}

}