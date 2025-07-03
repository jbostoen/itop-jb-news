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
 * Class Page. Web page with some associated CSS and scripts (jQuery) for a fancier display.
 */
class Page extends WebPage {
	
	var $m_aReadyScripts;
	
	/**
	 * @inheritDoc
	 */
	public function __construct($s_title, $bPrintable = false) {
		
		parent::__construct($s_title, $bPrintable);
		$this->m_aReadyScripts = [];
		
	}
	
	/**
	 * @inheritDoc
	 */
	public function add_ready_script($sScript) {
		
		$this->m_aReadyScripts[] = $sScript;
		
	}
	
	/**
	 * @inheritDoc
	 */
	public function output() {
		
		//$this->set_base($this->m_sRootUrl.'pages/');
		if(count($this->m_aReadyScripts) > 0) {
			
			$this->add_script("\$(document).ready(function() {\n".implode("\n", $this->m_aReadyScripts)."\n});");
			
		}
		
		parent::output();
	}
	
}
