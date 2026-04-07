<?php

namespace SplitTestForElementor\Classes\Endpoints;

use SplitTestForElementor\Classes\Http\RSTPost;
use SplitTestForElementor\Classes\Misc\Errors;
use SplitTestForElementor\Classes\Misc\LicenceManager;
use SplitTestForElementor\Classes\Misc\ResponseHelper;
use SplitTestForElementor\Classes\Misc\SecurityHelper;
use SplitTestForElementor\Classes\Repo\TestRepo;

class VariationController {

	private static $licenceManager;

	/**
	 * TestController constructor.
	 */
	public function __construct() {
		if (self::$licenceManager == null) {
			self::$licenceManager = new LicenceManager();
		}
	}

	public function index() {

	}

	public function create() {

	}

	public function store() {
		if(!SecurityHelper::userHasTestEditPermission()) {
			return ResponseHelper::generateErrorResponse(Errors::$MISSING_RIGHTS, 'Could not save test. Current user has insufficient rights.');
		}

		$testId = RSTPost::int('testId');
		$variationName = RSTPost::string('name');
		$variationPercentage = RSTPost::int('percentage');

        if ($testId <= 0 || $variationPercentage < 0) {
            return ResponseHelper::generateErrorResponse(Errors::$INVALID_INPUT, 'Could not save variation. Invalid input.');
        }

		if (self::$licenceManager->isLiteVariationCountReached($testId)) {
			return ResponseHelper::generateErrorResponse(Errors::$MAXIMUM_VARIATION_COUNT_REACHED, 'Could not save test. Maximum variation count for pro version reached. Please buy licence.');
		}

		$repo = new TestRepo();
		$newVariationId = $repo->createTestVariation($testId, [
			'name' => $variationName,
			'percentage' => $variationPercentage
		]);

		return ['success' => true, 'id' => $newVariationId, 'name' => $variationName];
	}

	public function show() {

	}

	public function edit() {

	}

	public function update() {

	}

	public function delete() {

	}

}