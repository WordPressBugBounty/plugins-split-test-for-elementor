<?php

namespace SplitTestForElementor\Classes\Repo;

use SplitTestForElementor\Classes\Database\RSTQueryBuilder;

class PostTestRepo {

	public function updateTestRegistry($postId, $splitTestIds) {
		RSTQueryBuilder::table('elementor_splittest_post')
			->where('post_id', (int) $postId)
			->delete();

		foreach ($splitTestIds as $splitTestId => $active) {
			RSTQueryBuilder::table('elementor_splittest_post')
				->insert([
					'splittest_id' => (int) $splitTestId,
					'post_id'      => (int) $postId,
					'created_at'   => current_time('mysql'),
				]);
		}
	}

	public function getPostsForTest($testId) {
		$postTestTable = RSTQueryBuilder::prefix('elementor_splittest_post');
		$postsTable    = RSTQueryBuilder::prefix('posts');

		return RSTQueryBuilder::table('elementor_splittest_post')
			->join($postsTable, "{$postTestTable}.post_id", '=', "{$postsTable}.ID")
			->where('splittest_id', (int) $testId)
			->get();
	}

	public function getTestIdsForPost($postId) {
		$postTests = RSTQueryBuilder::table('elementor_splittest_post')
			->where('post_id', (int) $postId)
			->get();

		$testIds = [];
		foreach ($postTests as $postTest) {
			$testIds[] = $postTest->splittest_id;
		}

		return $testIds;
	}

	public function deletePostTestByTestId($splitTestID) {
		RSTQueryBuilder::table('elementor_splittest_post')
			->where('splittest_id', (int) $splitTestID)
			->delete();
	}

}
