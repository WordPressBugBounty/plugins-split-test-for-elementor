<?php

namespace SplitTestForElementor\Classes\Events;

use SplitTestForElementor\Classes\Http\RSTCookie;
use SplitTestForElementor\Classes\Http\RSTGet;
use SplitTestForElementor\Classes\Misc\SettingsManager;
use SplitTestForElementor\Classes\Services\ConversionTracker;
use SplitTestForElementor\Classes\Misc\Util;
use SplitTestForElementor\Classes\Repo\PostTestManager;
use SplitTestForElementor\Classes\Repo\PostTestRepo;
use SplitTestForElementor\Classes\Repo\TestRepo;
use SplitTestForElementor\Classes\Services\TestService;

class SendHeadersEvent {

	private static $postTestManager;
	/**
	 * @var PostTestRepo
	 */
	private static $postTestRepo;
	private static $testRepo;
	private static $conversionTrack;
	private static $settingsManager;
	private static $testService;

	public function __construct() {
		if (self::$postTestManager == null) {
			self::$postTestManager = new PostTestManager();
			self::$testRepo = new TestRepo();
			self::$conversionTrack = new ConversionTracker();
			self::$postTestRepo = new PostTestRepo();
			self::$settingsManager = new SettingsManager();
			self::$testService = new TestService();
		}
	}

	public function fire() {

		if (RSTGet::has('elementor-preview')) {
			return;
		}

		$postId = url_to_postid($_SERVER['REQUEST_URI']);
		$currentLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
		$currentLink = explode("?", $currentLink)[0];
		$currentLink = trim($currentLink, "/");
		$isHomepage = site_url() == $currentLink;
		if ($isHomepage && $postId == 0) {
			$postId = (int) get_option('page_on_front');
		}

		// TODO: Change this to $rocketSplitTestClientId
		global $clientId;
		global $rocketSplitTestClientId;
		if (!RSTCookie::has(RSTCookie::CLIENT_ID)) {
			$clientId = Util::generateV4UUID();
			// TODO@kberlau: We might remove that condition
            if (!self::$settingsManager->getRawValue(SettingsManager::CACHE_BUSTER_ACTIVE)) {
			    RSTCookie::set(RSTCookie::CLIENT_ID, $clientId, '+12 month', true);
            }
		} else {
			$clientId = RSTCookie::string(RSTCookie::CLIENT_ID, '', false);
		}
		$rocketSplitTestClientId = $clientId;

		// TODO@kberlau: Move this to class or something
		if (empty($postId) || $postId == null || $postId == 0 || $isHomepage) {
			$this->progressTestsForRedirect($clientId);
		}
		if (self::$settingsManager->getRawValue(SettingsManager::CACHE_BUSTER_ACTIVE)) {
			// TODO add url conversions
			$this->prepareJsTestsForPage( $postId, $clientId );
		} else {
			$this->progressConversions( $postId, $clientId, $currentLink );
			$this->progressTestsForPage( $postId, $clientId );
		}
	}

	private function progressConversions($postId, $clientId, $currentLink) {
		$tests = self::$testRepo->getTestsByConversionPagePostId($postId);
		$this->progressConversionsForTests($tests, $clientId);

		$tests = self::$testRepo->getTestsByConversionUrl($currentLink);
		$this->progressConversionsForTests($tests, $clientId);
	}

	private function progressConversionsForTests($tests, $clientId) {
		foreach ($tests as $test) {
			if (!RSTCookie::has($test->id . '_variation')) {
				continue;
			}

			$variationId = RSTCookie::int($test->id . '_variation');
			// TODO@kberlau: Is this necessary
			foreach ($test->variations as $variation) {
				if ($variationId == $variation->id) {
					self::$conversionTrack->trackConversion($test->id, $variationId, $clientId);
				}
			}
		}
	}

	private function progressTestsForRedirect($clientId) {

        $urlBase = home_url();
        $requestUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

        $relativePath = str_replace($urlBase, "", $requestUrl);
        $relativePath = explode("?", $relativePath)[0];
        $relativePath = trim($relativePath, "/");

		$tests = self::$testRepo->getRedirectTestsByUri($relativePath);
		if (sizeof($tests) == 0) {
			return;
		}
		$test = $tests[0];

		$variations = $this->progressTests($tests, $clientId);

		$targetVariation = $variations[$test->id];
		$urlQueryParams = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);

		if (self::$settingsManager->getRawValue(SettingsManager::CACHE_BUSTER_ACTIVE)) {

			RSTCookie::set(RSTCookie::CLIENT_ID, $clientId, '+12 month', true);

			header('Cache-Control: no-store, private, no-cache, must-revalidate');     // HTTP/1.1
			header('Cache-Control: pre-check=0, post-check=0, max-age=0, max-stale=0', false);  // HTTP/1.1
			header('Pragma: public');
			header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');                  // Date in the past
			header('Expires: 0', false);
			header('Last-Modified: '.gmdate('D, d M Y H:i:s') . ' GMT');
			header('Pragma: no-cache');
			header('Vary: *');
			header("Connection: close");
		}


        // TODO@kberlau: Add no cache
		foreach ($test->variations as $variation) {
			if ($variation->id == $targetVariation->id) {
				// TODO@kberlau: Refactor for urlQueryParams
				if ($test->test_type == "pages") {
					$dest = get_permalink($variation->post_id);
					if ($urlQueryParams != "") {
						$dest .= '?' . $urlQueryParams;
					}
					wp_redirect($dest, 302);
				} else if ($test->test_type == "urls") {
					if (empty($variation->url) || !wp_http_validate_url($variation->url)) {
						exit;
					}
					$dest = esc_url_raw($variation->url);
					if ($urlQueryParams != "") {
						$dest .= '?' . $urlQueryParams;
					}
					wp_redirect($dest, 302);
				}
				exit;
			}
		}
	}

	private function progressTestsForPage($postId, $clientId) {
		$testIds = self::$postTestRepo->getTestIdsForPost($postId);
		if (sizeof($testIds) == 0) {
			return;
		}
		$tests = self::$testRepo->getTests($testIds);
		$this->progressTests($tests, $clientId);
	}

	/**
	 * @param $tests
	 * @param $clientId
	 *
	 * @return array|void
	 * @internal param $postId
	 */
	private function progressTests($tests, $clientId) {

		global $targetVariations;
		$targetVariations = [];

		foreach ($tests as $test) {
			$targetVariation = null;

			$splitTestId = null;
			if (RSTCookie::has($test->id . '_variation')) {
				$splitTestId = RSTCookie::int($test->id . '_variation');
			}
			if (RSTGet::has('stid')) {
				$splitTestId = RSTGet::int('stid'); // TODO@kberlau: Document this
			}

			if ($splitTestId != null)  {
					foreach ($test->variations as $variation) {
						if ($splitTestId == $variation->id) {
							$targetVariation = $variation;
							break;
						}
					}
			}

			if ($targetVariation == null) {
				$targetVariation = self::$testService->getTargetVariation($test);
			}

			if ($targetVariation != null) {
				RSTCookie::set($test->id . '_variation', $targetVariation->id);
			} else {
				// LOW@kberlau ERROR!
			}

			$targetVariations[$test->id] = $targetVariation;
			self::$conversionTrack->trackView($test->id, $targetVariation->id, $clientId);
		}

		return $targetVariations;
	}

	private function prepareJsTestsForPage($postId, $clientId) {
		global $rocketSplitTestRunningTests;
		$rocketSplitTestRunningTests = [];
		$testIds = self::$postTestRepo->getTestIdsForPost($postId);
		if (sizeof($testIds) == 0) {
			return;
		}

		$tests = self::$testRepo->getTests($testIds);
		foreach ($tests as $test) {
			$rocketSplitTestRunningTests[] = self::$testService->getTestDataForJs($test);
		}
	}


}