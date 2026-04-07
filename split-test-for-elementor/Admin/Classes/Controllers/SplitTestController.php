<?php

namespace SplitTestForElementor\Admin\Classes\Controllers;

use SplitTestForElementor\Classes\Http\RSTGet;
use SplitTestForElementor\Classes\Http\RSTPost;
use SplitTestForElementor\Classes\Misc\LicenceManager;
use SplitTestForElementor\Classes\Misc\SecurityHelper;
use SplitTestForElementor\Classes\Misc\Util;
use SplitTestForElementor\Classes\Repo\PostRepo;
use SplitTestForElementor\Classes\Repo\PostTestRepo;
use SplitTestForElementor\Classes\Repo\TestRepo;

class SplitTestController {

	private static $licenceManager;

	// Index; Create; Store; Show; Edit; Update; Delete

	/**
	 * SplitTestController constructor.
	 */
	public function __construct() {
		if (self::$licenceManager == null) {
			self::$licenceManager = new LicenceManager();
		}
	}

	public function run() {
		switch (RSTGet::string('action', 'index')) {
			case "index"    		:  $this->index(); break;
			case "create"   		:  $this->create(); break;
			case "show"     		:  $this->show(); break;
			case "edit"     		:  $this->edit(); break;
			default         		:  $this->index(); break;
		}
	}

	public function index() {
		SecurityHelper::verifyUserPermissionsAndDieOnForbidden();
		$testRepo = new TestRepo();
		$tests = $testRepo->getAllTests();
		include(__DIR__."/../../views/test/index.view.php");
	}

	public function create() {
		SecurityHelper::verifyUserPermissionsAndDieOnForbidden();
		$postRepo = new PostRepo();
		$posts = $postRepo->getAllPosts();
		include(__DIR__."/../../views/test/create.view.php");
	}

	public function store() {
		SecurityHelper::verifyUserPermissionsAndDieOnForbidden();
		SecurityHelper::verifyNonceAndDieOnInvalid();
		if (!RSTPost::has('test-name')) {
			// LOW@kberlau Log Error
			wp_redirect(admin_url('admin.php?page=splittest-for-elementor&message=error_store_data_missing'));
			return;
		}

		// TODO@kberlau: Input validation
		$conversionType = RSTPost::string('test-conversion-type');
		$conversionPage = RSTPost::string('test-conversion-page');
		$conversionUrl  = RSTPost::string('test-conversion-url', '', false);
		$testType       = RSTPost::string('test-type');
		$testUri        = RSTPost::string('test-uri', '', false);
		$testName       = RSTPost::string('test-name', '',false);
		$externalLink   = RSTPost::string('test-external-link', '', false);
		$methodology    = RSTPost::string('test-methodology', 'standard');

		// Validate conversion settings
		if (!$this->validateConversionSettings($conversionType, $conversionPage, $conversionUrl)) {
			return;
		}

		// Validate URLs before storing
		if (!$this->validateUrls($conversionUrl, $externalLink)) {
			return;
		}

		// Validate test URI
		if (!$this->validateTestUri($testType, $testUri, 'create')) {
			return;
		}

		$testRepo = new TestRepo();
		$externalLinkValue  = ($externalLink == "null" || $externalLink == "") ? null : esc_url_raw($externalLink);
		$conversionUrlValue = ($conversionUrl == "null" || $conversionUrl == "") ? null : esc_url_raw($conversionUrl);

		// TODO@kberlau: Create / update post for preventing conflicts (custom post type maybe)
		$testData = [
			'name'             => $testName,
			'methodology'      => $methodology,
			'testType'         => $testType,
			'conversionType'   => $conversionType,
			'conversionPageId' => $conversionPage,
			'externalLink'     => $externalLinkValue,
			'conversionUrl'    => $conversionUrlValue
		];

		if ($testType == "pages") {
			$testData['testUri'] = $testUri;
		}

		$id = $testRepo->createTest($testData);
		foreach (RSTPost::array('test-variation') as $variation) {
			if (!$this->validateVariationPostExists($variation['post-id'])) {
				wp_redirect(admin_url('admin.php?page=splittest-for-elementor&scope=test&action=create&message=error_variation_post_not_found'));
				return;
			}
			$variation['postId'] = $variation['post-id'];
			$testRepo->createTestVariation($id, $variation);
		}
		wp_redirect(admin_url('admin.php?page=splittest-for-elementor&scope=test&action=edit&id='.$id.'&message=store_success'));
	}

	public function show() {

	}

	public function edit() {
		SecurityHelper::verifyUserPermissionsAndDieOnForbidden();

		if (!RSTGet::tryGetInt('id', $id)) {
			wp_redirect(admin_url('admin.php?page=splittest-for-elementor'));
			return;
		}

		$testRepo = new TestRepo();
		$test = $testRepo->getTest($id);

		if ($test === null) {
			wp_redirect(admin_url('admin.php?page=splittest-for-elementor'));
			return;
		}

		$postRepo = new PostRepo();
		$posts = $postRepo->getAllPosts();

		$testPostRepo = new PostTestRepo();
		$postsForTest = $testPostRepo->getPostsForTest($test->id);

		include(__DIR__."/../../views/test/edit.view.php");
	}

	public function update() {
		SecurityHelper::verifyUserPermissionsAndDieOnForbidden();
		SecurityHelper::verifyNonceAndDieOnInvalid();

		if (!RSTPost::has('test-id')) {
			// LOW@kberlau Log Error
			wp_redirect(admin_url('admin.php?page=splittest-for-elementor&message=error_update_data_missing'));
			return;
		}

		$testId         = RSTPost::int('test-id');
		$conversionType = RSTPost::string('test-conversion-type');
		$conversionPage = RSTPost::string('test-conversion-page');
		$conversionUrl  = RSTPost::string('test-conversion-url', '', false);
		$testType       = RSTPost::string('test-type');
		$testUri        = RSTPost::string('test-uri', '',false);
		$testName       = RSTPost::string('test-name', '',false);
		$externalLink   = RSTPost::string('test-external-link', '', false);
		$methodology    = RSTPost::string('test-methodology', 'standard');

		// Validate conversion settings
		if (!$this->validateConversionSettings($conversionType, $conversionPage, $conversionUrl)) {
			return;
		}

		// Validate URLs
		if (!$this->validateUrls($conversionUrl, $externalLink)) {
			return;
		}

		// Validate test URI for pages type
		if (!$this->validateTestUri($testType, $testUri, 'edit', $testId)) {
			return;
		}

		$testRepo = new TestRepo();

		$externalLinkValue  = ($externalLink == "null" || $externalLink == "") ? null : esc_url_raw($externalLink);
		$conversionUrlValue = ($conversionUrl == "null" || $conversionUrl == "") ? null : esc_url_raw($conversionUrl);
		$conversionPageId   = ($conversionPage == "null") ? null : (int) $conversionPage;

		// Build test data array
		$testData = array(
			'name'             => $testName,
			'methodology'      => $methodology,
			'testType'         => $testType,
			'conversionType'   => $conversionType,
			'externalLink'     => $externalLinkValue,
			'conversionPageId' => $conversionPageId,
			'conversionUrl'    => $conversionUrlValue
		);

		// Add testUri only for "urls" and "pages" types
		if ($testType == "urls" || $testType == "pages") {
			$testData['testUri'] = $testUri;
		}

		// TODO@kberlau: Create / update post for preventing conflicts
		$testRepo->updateTest($testId, $testData);

		if (RSTPost::has('test-delete-variation')) {
			foreach (RSTPost::array('test-delete-variation') as $id) {
				$testRepo->softDeleteTestVariation((int) $id);
			}
		}

		foreach (RSTPost::array('test-variation') as $variation) {
			if (!$this->validateVariationPostExists($variation['post-id'])) {
				wp_redirect(admin_url('admin.php?page=splittest-for-elementor&scope=test&action=edit&id='.$testId.'&message=error_variation_post_not_found'));
				return;
			}

			$variation['postId'] = (int) $variation['post-id'];
			if (Util::nullOrEmpty($variation['id'])) {
				$testRepo->createTestVariation($testId, $variation);
			} else {
				$testRepo->updateTestVariation((int) $variation['id'], $variation);
			}
		}

		wp_redirect(admin_url('admin.php?page=splittest-for-elementor&scope=test&action=edit&id='.$testId.'&message=save_success'));
	}

	public function delete() {
		SecurityHelper::verifyUserPermissionsAndDieOnForbidden();
		SecurityHelper::verifyNonceAndDieOnInvalid();

		if (!RSTGet::has('id')) {
			// LOW@kberlau Log Error
			wp_redirect(admin_url('admin.php?page=splittest-for-elementor&message=error_delete'));
			return;
		}

		$testRepo = new TestRepo();
		$testRepo->deleteTest(RSTGet::int('id'));

		wp_redirect(admin_url('admin.php?page=splittest-for-elementor&scope=test&action=index&message=delete_success'));
	}

	public function resetStatistics()
	{
		SecurityHelper::verifyUserPermissionsAndDieOnForbidden();
		SecurityHelper::verifyNonceAndDieOnInvalid();

		if (!RSTGet::tryGetInt('id', $testId)) {
			// LOW@kberlau Log Error
			wp_redirect(admin_url('admin.php?page=splittest-for-elementor&message=error_update_data_missing'));
			return;
		}

		$testRepo = new TestRepo();
		$testRepo->resetTestStatistics($testId);

		wp_redirect(admin_url('admin.php?page=splittest-for-elementor&message=reset_success'));
	}

	/**
	 * Validate conversion settings based on conversion type
	 *
	 * @param string $conversionType
	 * @param string $conversionPage
	 * @param string $conversionUrl
	 * @return bool
	 */
	private function validateConversionSettings($conversionType, $conversionPage, $conversionUrl) {
		// TODO@kberlau: Go back to form
		if ($conversionType == "page" && ($conversionPage == "" || $conversionPage == "null")) {
			// LOW@kberlau Log Error
			wp_redirect(admin_url('admin.php?page=splittest-for-elementor&message=error_conversion_page_missing'));
			return false;
		}

		// TODO@kberlau: Go back to form
		if ($conversionType == "url" && ($conversionUrl == "" || $conversionUrl == "null")) {
			// LOW@kberlau Log Error
			wp_redirect(admin_url('admin.php?page=splittest-for-elementor&message=error_conversion_url_missing'));
			return false;
		}

		return true;
	}

	/**
	 * Validate URLs
	 *
	 * @param string $conversionUrl
	 * @param string $externalLink
	 * @return bool
	 */
	private function validateUrls($conversionUrl, $externalLink) {
		if ($conversionUrl !== '' && $conversionUrl !== 'null' && !wp_http_validate_url($conversionUrl)) {
			wp_redirect(admin_url('admin.php?page=splittest-for-elementor&message=error_conversion_url_invalid'));
			return false;
		}

		if ($externalLink !== '' && $externalLink !== 'null' && !wp_http_validate_url($externalLink)) {
			wp_redirect(admin_url('admin.php?page=splittest-for-elementor&message=error_external_link_url_invalid'));
			return false;
		}

		return true;
	}

	/**
	 * Validate test URI for pages type
	 *
	 * @param string $testType
	 * @param string $testUri
	 * @param string $action 'create' or 'edit'
	 * @param int|null $testId Required for edit action
	 * @return bool
	 */
	private function validateTestUri($testType, $testUri, $action = 'create', $testId = null) {
		// TODO@kberlau: Go back to form
		if ($testType == "pages" && strpos($testUri, '/') !== false) {
			if ($action === 'edit' && $testId) {
				wp_redirect(admin_url('admin.php?page=splittest-for-elementor&scope=test&action=edit&id='.$testId.'&message=error_test_page_invalid_chars'));
			} else {
				wp_redirect(admin_url('admin.php?page=splittest-for-elementor&scope=test&action=create&message=error_test_page_invalid_chars'));
			}
			return false;
		}

		return true;
	}

	/**
	 * Validate that the post referenced in variation exists
	 *
	 * @param string|int $postId
	 * @return bool
	 */
	private function validateVariationPostExists($postId) {
		if (empty($postId) || $postId === 'null') {
			return true;
		}

		$post = get_post((int) $postId);
		return $post !== null && $post->post_status !== 'trash';
	}

}
