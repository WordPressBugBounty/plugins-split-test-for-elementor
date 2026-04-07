<?php

namespace SplitTestForElementor\Admin\Classes\Controllers;

use SplitTestForElementor\Admin\Classes\Repo\StatisticsRepo;
use SplitTestForElementor\Classes\Http\RSTGet;
use SplitTestForElementor\Classes\Http\RSTPost;
use SplitTestForElementor\Classes\Misc\LicenceManager;
use SplitTestForElementor\Classes\Misc\SecurityHelper;
use SplitTestForElementor\Classes\Misc\Util;

class StatisticsController {

	private static $licenceManager;

	/**
	 * SplitTestController constructor.
	 */
	public function __construct() {
		if (self::$licenceManager == null) {
			self::$licenceManager = new LicenceManager();
		}
	}

	// Index; Create; Store; Show; Edit; Update; Delete

	public function run() {
		SecurityHelper::verifyUserPermissionsAndDieOnForbidden();
		switch (RSTGet::string('action', 'index')) {
			case "show"     :  $this->show(); break;
			case "edit"     :  $this->edit(); break;
			case "update"   :  $this->update(); break;
			default         :  $this->index(); break;
		}
	}

	public function index() {

		if (!RSTGet::tryGetInt('id', $testId)) {
			wp_die(esc_html__('Invalid Test Id.', 'split-test-for-elementor'), '', ['response' => 400]);
		}

		$startDate = null;
		if (RSTPost::has('start_date_day') && RSTPost::has('start_date_month') && RSTPost::has('start_date_year')) {

			SecurityHelper::verifyNonceAndDieOnInvalid();

			if (RSTPost::tryGetInt('start_date_day', $day) && RSTPost::tryGetInt('start_date_month', $month) && RSTPost::tryGetInt('start_date_year', $year)) {
				if ($day >= 1 && $day <= 31 && $month >= 1 && $month <= 12 && $year >= 1900 && $year <= 2100) {
					$startDate = $day."-".$month."-".$year;
				} else {
					$message = "invalid_date_input";
				}
			} else {
				$message = "invalid_date_input";
			}
		}

		$endDate = null;
		if (RSTPost::has('end_date_day') && RSTPost::has('end_date_month') && RSTPost::has('end_date_year')) {

			SecurityHelper::verifyNonceAndDieOnInvalid();

			if (RSTPost::tryGetInt('end_date_day', $day) && RSTPost::tryGetInt('end_date_month', $month) && RSTPost::tryGetInt('end_date_year', $year)) {
				if ($day >= 1 && $day <= 31 && $month >= 1 && $month <= 12 && $year >= 1900 && $year <= 2100) {
					$endDate = $day."-".$month."-".$year . " 23:59:59";
				} else {
					$message = "invalid_date_input";
				}
			} else {
				$message = "invalid_date_input";
			}
		}

		if ($endDate != null && $startDate != null && strtotime($endDate) < strtotime($startDate)) {
			$endDate = null;
			$startDate = null;
			$message = "end_date_to_small";
		}

		$statsRepo = new StatisticsRepo();
		$stats = $statsRepo->getStats($testId, $startDate, $endDate);

		$statsForChart = [];
		$variations = [];
		foreach ($stats as $stat) {
			$statsForDate = [];
			foreach ($stat->stats['dates'] as $date => $entry) {
				if ($entry['views'] > 0) {
					$statsForDate[$date] = ((int) ($entry['conversions'] / $entry['views'] * 1000)) / 10;
				} else {
					$statsForDate[$date] = 0;
				}
			}
			$statsForChart[] = [
				'id' => $stat->id,
				'name' => $stat->name,
				'stats' => $statsForDate
			];

			if ($stat->stats['allViews'] == 0) {
				$conversionRate = 0;
			} else {
				$conversionRate = ((int) ($stat->stats['allConversions'] * 100 / $stat->stats['allViews'] * 10)) / 10;
			}

			$variations[] = (object) [
				'name' => $stat->name,
				'id' => $stat->id,
				'percentage' => $stat->percentage,
				'allViews' => $stat->stats['allViews'],
				'allConversions' => $stat->stats['allConversions'],
				'conversionRate' => $conversionRate
			];
		}

		usort($variations, function ($a, $b) {
			return $a->conversionRate - $b->conversionRate;
		});

		for ($i = 0; $i < sizeof($variations); $i++) {
			$variations[$i]->ranking = $i + 1;
		}

		usort($variations, function ($a, $b) {
			return $a->id - $b->id;
		});

		include(__DIR__."/../../views/statistics/index.view.php");

	}

	public function create() {

	}

	public function store() {

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
