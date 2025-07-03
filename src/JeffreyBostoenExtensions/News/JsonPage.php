<?php

/**
 * @copyright   Copyright (c) 2019-2024 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.241108
 */

namespace JeffreyBostoenExtensions\News;

// iTop internals.
use Combodo\iTop\Application\WebPage\WebPage;

/**
 * Class Page. Web page with some associated CSS and scripts (jquery) for a fancier display
 */
class JsonPage extends WebPage {
	
	var $m_aReadyScripts;
	
	/**
	 * @inheritDoc
	 */
	public function __construct($s_title, $bPrintable = false) {
		
		parent::__construct($s_title, $bPrintable);
		$this->m_aReadyScripts = [];
		$this->no_cache();
		$this->SetContentType('application/json');
		
	}
	
}
