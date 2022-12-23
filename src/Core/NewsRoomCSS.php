<?php

/**
 * @copyright   Copyright (c) 2019-2022 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2.7.221223
 *
 * Definition of NewsRoomProvider.
 */

	namespace jb_itop_extensions\NewsProvider;
	
	// iTop internals
	use \AbstractPageUIExtension;
	use \iTopWebPage;
	use \MetaModel;
	
	/**
	 * Class NewsPageUIExtension. Adds SCSS to pages to improve layout of newsroom messages.
	 */
	 class NewsPageUIExtension extends AbstractPageUIExtension {
		
		/**
		 * @inheritDoc
		 */
		public function GetNorthPaneHtml(iTopWebPage $oPage) {
			
			$oPage->add_saas('env-'.MetaModel::GetEnvironment().'/'.NewsRoomHelper::MODULE_CODE.'/css/newsroom.scss');
			return '';
			
		}
		
	}
	
	
