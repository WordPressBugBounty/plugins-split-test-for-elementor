<?php

namespace SplitTestForElementor\Admin\Classes\Events;

use SplitTestForElementor\Admin\Classes\Controllers\SplitTestController;
use SplitTestForElementor\Classes\Http\RSTGet;
use SplitTestForElementor\Classes\Misc\LicenceManager;

class AdminInitEvent {

	/**
	 * @var LicenceManager
	 */
	private static $licenceManager;

	/**
	 * AdminInitEvent constructor.
	 */
	public function __construct() {
		if (self::$licenceManager == null) {
			self::$licenceManager = new LicenceManager();
		}
	}

	public function fire() {

		if (!is_admin()) {
			return;
		}

		if (!RSTGet::has('page')) {
			return;
		}

		if (RSTGet::string('page') != "splittest-for-elementor") {
			return;
		}

		wp_enqueue_script('split-test-for-elementor-chartjs', plugins_url('Admin/assets/js/chartjs/Chart.bundle.min.js', SPLIT_TEST_FOR_ELEMENTOR_MAIN_FILE), array(), SPLIT_TEST_FOR_ELEMENTOR_VERSION, false);
		wp_enqueue_style('split-test-for-elementor-google-fonts', "//fonts.googleapis.com/css?family=Roboto", array());
		wp_enqueue_style('split-test-for-elementor-css', plugins_url('Admin/assets/css/style.css', SPLIT_TEST_FOR_ELEMENTOR_MAIN_FILE), array(), SPLIT_TEST_FOR_ELEMENTOR_VERSION, 'all');

		if (!RSTGet::has('scope')) {
			return;
		}

		if (RSTGet::string('scope') == "test") {
			$controller = new SplitTestController();
			$action = RSTGet::string('action');
			if ($action == "delete") {
				$controller->delete();
			} else if ($action == "store") {
				$controller->store();
			} else if ($action == "update") {
				$controller->update();
			} else if ($action == "resetStatistics") {
				$controller->resetStatistics();
			}
		}

	}

}