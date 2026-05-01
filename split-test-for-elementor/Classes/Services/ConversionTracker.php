<?php

namespace SplitTestForElementor\Classes\Services;

use SplitTestForElementor\Classes\Database\RSTQueryBuilder;
use SplitTestForElementor\Classes\Misc\Util;

class ConversionTracker {

	public function trackView($testId, $variationId, $clientId) {
		if (!$clientId) {
			return;
		}

		$interactions = RSTQueryBuilder::table('elementor_splittest_interactions')
			->where('splittest_id', (int) $testId)
			->where('client_id', $clientId)
			->get();

		if (count($interactions) == 0) {
			RSTQueryBuilder::table('elementor_splittest_interactions')
				->insert([
					'splittest_id' => (int) $testId,
					'variation_id' => (int) $variationId,
					'type'         => 'view',
					'client_id'    => $clientId,
					'created_at'   => current_time('mysql'),
				]);
		}
	}

	public function trackConversion($testId, $variationId, $clientId) {
		if (!$clientId) {
			return;
		}

		$interactions = RSTQueryBuilder::table('elementor_splittest_interactions')
			->where('splittest_id', (int) $testId)
			->where('client_id', $clientId)
			->get();

		if (count($interactions) > 0) {
			RSTQueryBuilder::table('elementor_splittest_interactions')
				->where('splittest_id', (int) $testId)
				->where('client_id', $clientId)
				->update([
					'type'         => 'conversion',
					'variation_id' => (int) $variationId,
				]);
		}
	}

}
