<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250819
 */

namespace JeffreyBostoenExtensions\News;

/**
 * Class JsonPage. JSON output only.
 */
class JsonPage {

	/**
	 * @inheritDoc
	 */
	public function __construct() {

		// No cache.
		header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
		header('Cache-Control: post-check=0, pre-check=0', false);
		header('Pragma: no-cache');

		// JSON.
		header('Content-type: application/json; charset=utf-8');
		
	}

	/**
	 * @inheritDoc
	 */
	public function output($sText) {
		echo $sText;
	}

	
}
