<?php

namespace SplitTestForElementor\Classes\Repo;

use SplitTestForElementor\Classes\Database\RSTQueryBuilder;

class TestRepo {

	public function getAllTests($withInActive = false, $withDeleted = false) {
		$qb = RSTQueryBuilder::table('elementor_splittest');
		if (!$withInActive) {
			$qb->where('active', true);
		}
		$tests = $qb->get();

		for ($i = 0; $i < count($tests); $i++) {
			$tests[$i]->variations = $this->getVariations($tests[$i]->id, $withInActive, $withDeleted);
		}

		return $tests;
	}

	public function getTest($testId, $withInActive = false, $withDeleted = false) {
		$tests = $this->getTests([$testId], $withInActive, $withDeleted);
		return count($tests) > 0 ? $tests[0] : null;
	}

	public function getTests($ids, $withInActive = false, $withDeleted = false) {
		if (count($ids) == 0) {
			return [];
		}

		$filteredIds = [];
		foreach ($ids as $id) {
			$filteredIds[] = intval($id);
		}

		$tests = RSTQueryBuilder::table('elementor_splittest')
			->whereIn('id', $filteredIds)
			->get();

		for ($i = 0; $i < count($tests); $i++) {
			$tests[$i]->variations = $this->getVariations($tests[$i]->id, $withInActive, $withDeleted);
		}

		return $tests;
	}

	public function getTestsByConversionPagePostId($postId) {
		$tests = RSTQueryBuilder::table('elementor_splittest')
			->where('conversion_page_id', (int) $postId)
			->where('conversion_type', 'page')
			->get();

		for ($i = 0; $i < count($tests); $i++) {
			$tests[$i]->variations = $this->getVariations($tests[$i]->id, false, false);
		}

		return $tests;
	}

	public function getRedirectTestsByUri($uri) {
		$uri   = str_replace("*", "%", $uri);
		$tests = RSTQueryBuilder::table('elementor_splittest')
			->where('test_uri', $uri)
			->get();

		for ($i = 0; $i < count($tests); $i++) {
			$tests[$i]->variations = $this->getVariations($tests[$i]->id, false, false);
		}

		return $tests;
	}

	public function getVariations($postId, $withInActive = false, $withDeleted = false) {
		$qb = RSTQueryBuilder::table('elementor_splittest_variations')
			->where('splittest_id', (int) $postId);

		$qb = $withInActive ? $qb : $qb->where('active', true);
		$qb = $withDeleted ? $qb : $qb->whereNull('deleted_at');

		$results = $qb->get();
		foreach ($results as $result) {
			$result->post_id = (int) $result->post_id;
		}

		return $results;
	}

	// TODO@kberlau: Trim conversion url on save
	public function updateTest($id, $data) {
		RSTQueryBuilder::table('elementor_splittest')
			->where('id', (int) $id)
			->update([
				'name'              => $data['name'],
				'test_type'         => $data['testType'],
				'test_uri'          => $data['testType'] == "pages" || $data['testType'] == "urls" ? $data['testUri'] : null,
				'conversion_type'   => $data['conversionType'],
				'conversion_page_id'=> isset($data['conversionPageId']) ? (int) $data['conversionPageId'] : null,
				'external_link'     => $data['externalLink'] ?? null,
				'conversion_url'    => $this->normalizeConversionUrl($data['conversionUrl']),
			]);
	}

	public function createTest($data) {
		return RSTQueryBuilder::table('elementor_splittest')
			->insert([
				'name'               => $data['name'],
				'active'             => true,
				'test_type'          => $data['testType'],
				'test_uri'           => $data['testType'] == "pages" || $data['testType'] == "urls" ? $data['testUri'] : null,
				'conversion_type'    => $data['conversionType'],
				'conversion_page_id' => isset($data['conversionPageId']) ? (int) $data['conversionPageId'] : null,
				'conversion_url'     => $this->normalizeConversionUrl($data['conversionUrl']),
				'external_link'      => $data['externalLink'] ?? null,
				'created_at'         => current_time('mysql'),
			]);
	}

	public function resetTestStatistics($id) {
		$test = $this->getTest($id, true, true);
		if ($test == null) {
			return;
		}

		RSTQueryBuilder::table('elementor_splittest_interactions')
			->where('splittest_id', (int) $id)
			->delete();
	}

	public function deleteTest($id) {
		$test = $this->getTest($id, true, true);
		if ($test == null) {
			return;
		}
		if (count($test->variations) > 0) {
			foreach ($test->variations as $variation) {
				$this->deleteTestVariation($variation->id);
			}
		}
		$postTestRepo = new PostTestRepo();
		$postTestRepo->deletePostTestByTestId($test->id);
		$this->deleteTestInteractions($test->id);

		RSTQueryBuilder::table('elementor_splittest')
			->where('id', (int) $id)
			->delete();
	}

	public function deleteTestInteractions($splitTestID) {
		RSTQueryBuilder::table('elementor_splittest_interactions')
			->where('splittest_id', (int) $splitTestID)
			->delete();
	}

	public function createTestVariation($testId, $data) {
		return RSTQueryBuilder::table('elementor_splittest_variations')
			->insert([
				'name'         => $data['name'],
				'percentage'   => (int) $data['percentage'],
				'post_id'      => isset($data['postId']) ? (int) $data['postId'] : null,
				'url'          => $data['url'] ?? null,
				'splittest_id' => (int) $testId,
				'active'       => true,
				'created_at'   => current_time('mysql'),
			]);
	}

	public function updateTestVariation($id, $data) {
		RSTQueryBuilder::table('elementor_splittest_variations')
			->where('id', (int) $id)
			->update([
				'name'       => $data['name'],
				'percentage' => (int) $data['percentage'],
				'post_id'    => isset($data['postId']) ? (int) $data['postId'] : null,
				'url'        => $data['url'] ?? null,
			]);
	}

	public function softDeleteTestVariation($id) {
		RSTQueryBuilder::table('elementor_splittest_variations')
			->where('id', (int) $id)
			->update(['deleted_at' => current_time('mysql')]);
	}

	public function getTestsByConversionUrl($conversionUrl) {
		$conversionUrl = $this->normalizeConversionUrl($conversionUrl);

		$tests = RSTQueryBuilder::table('elementor_splittest')
			->where('conversion_url', $conversionUrl)
			->where('conversion_type', 'url')
			->get();

		for ($i = 0; $i < count($tests); $i++) {
			$tests[$i]->variations = $this->getVariations($tests[$i]->id, false, false);
		}

		return $tests;
	}

	public function deleteTestVariation($id) {
		RSTQueryBuilder::table('elementor_splittest_variations')
			->where('id', (int) $id)
			->delete();
	}

	/**
	 * @param $conversionUrl
	 * @return string
	 */
	private function normalizeConversionUrl($conversionUrl) {
		if ($conversionUrl == null) {
			return null;
		}
		return rtrim($conversionUrl, "/") . "/";
	}

}
