<?php

/**
 * @copyright   Copyright (c) 2019-2021 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2.7.211212
 *
 * Definition of NewsRoomProvider.
 */

	/**
	 * Class NewsPageUIExtension. Adds SCSS to pages to improve layout of newsroom messages.
	 */
	 class NewsPageUIExtension extends AbstractPageUIExtension {
		
		/**
		 * @inheritDoc
		 */
		public function GetNorthPaneHtml(iTopWebPage $oPage) {
			
			$oPage->add_saas('env-'.MetaModel::GetEnvironment().'/'.utils::GetCurrentModuleName().'/css/newsroom.scss');
			return '';
			
		}
		
	}
	
	