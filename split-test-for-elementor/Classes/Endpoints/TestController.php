<?php

namespace SplitTestForElementor\Classes\Endpoints;

use SplitTestForElementor\Classes\Http\RSTGet;
use SplitTestForElementor\Classes\Http\RSTPost;
use SplitTestForElementor\Classes\Misc\Errors;
use SplitTestForElementor\Classes\Misc\LicenceManager;
use SplitTestForElementor\Classes\Misc\ResponseHelper;
use SplitTestForElementor\Classes\Misc\SecurityHelper;
use SplitTestForElementor\Classes\Repo\TestRepo;
use SplitTestForElementor\Classes\Services\TestService;

class TestController {

	private static $licenceManager;
    private static $testService;

	/**
	 * TestController constructor.
	 */
	public function __construct() {
		if (self::$licenceManager == null) {
			self::$licenceManager = new LicenceManager();
            self::$testService = new TestService();
		}
	}

	public function index() {

	}

	public function create() {

	}

	public function store() {
		// TODO@kberlau: Centralize Logic
		if(!SecurityHelper::userHasTestEditPermission()) {
			return ResponseHelper::generateErrorResponse(Errors::$MISSING_RIGHTS, 'Could not save test. Current user has insufficient rights.');
		}

		$testName = RSTPost::string('name');
		if (!RSTPost::has('conversionType')) {
			return ResponseHelper::generateErrorResponse(Errors::$CONVERSION_TYPE_MISSING, 'Could not save test. Conversion type missing.');
		}

		if (self::$licenceManager->isLiteTestCountReached()) {
			return ResponseHelper::generateErrorResponse(Errors::$MAXIMUM_TEST_COUNT_REACHED, 'Could not save test. Maximum test count for pro version reached. Please buy licence.');
		}

		$conversionType = RSTPost::string('conversionType');

		if ($conversionType == "page") {
			$conversionPageId = RSTPost::string('conversionPageId');
			if (!RSTPost::has('conversionPageId') || $conversionPageId == "" || $conversionPageId == "null") {
				return ResponseHelper::generateErrorResponse( Errors::$CONVERSION_PAGE_MISSING,  'Could not save test. Conversion page missing.');
			}
		} else if ($conversionType == "url") {
			$conversionUrl = RSTPost::string('conversionUrl', '', false);
			if (!RSTPost::has('conversionUrl') || $conversionUrl == "" || $conversionUrl == "null") {
				return ResponseHelper::generateErrorResponse(Errors::$CONVERSION_URL_MISSING, 'Could not save test. Conversion url missing.');
			}
		} else {
			return ResponseHelper::generateErrorResponse(Errors::$INVALID_INPUT, 'Could not save test. Conversion type is invalid.');
		}

		$repo = new TestRepo();
		$newTestId = $repo->createTest([
			'name' => $testName,
			'testType' => 'elements',
			'conversionType' => $conversionType,
			'conversionPageId' => RSTPost::int('conversionPageId'),
			'conversionUrl' => RSTPost::has('conversionUrl') ? esc_url_raw(RSTPost::string('conversionUrl', '', false)) : ''
		]);

		return ['success' => true, 'id' => $newTestId, 'name' => $testName];
	}

	public function show() {

	}

	public function edit() {

	}

	public function update() {

	}

	public function delete() {

	}

    public function getVariationToDisplay() {

        if (!RSTGet::tryGetInt('testId', $testId)) {
            return [];
        }
        $result = self::$testService->getActiveVariation($testId);

        return [
            'variant' => [
                'id' => intval($result->id)
            ]
        ];

    }

}