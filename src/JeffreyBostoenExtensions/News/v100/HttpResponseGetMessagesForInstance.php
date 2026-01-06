<?php

/**
 * @copyright   Copyright (c) 2019-2026 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.260106
 */

namespace JeffreyBostoenExtensions\News\v100;

use JeffreyBostoenExtensions\ServerCommunication\{
	eApiVersion,
	eCryptographyLibrary
};

use JeffreyBostoenExtensions\ServerCommunication\Protocol\Base\HttpResponse as Base;

/**
* Class HttpResponseGetMessagesForInstance. A base class for HTTP responses for getting messages for an instance.
*/
class HttpResponseGetMessagesForInstance extends Base {

	use MessagesTrait;

	/**
	 * @inheritDoc
	 */
	public function GetOutput(): string {

		
		$oRequest = $this->GetHttpRequest();
		$oResponse = $this->GetHttpResponse();
		
		
		if(
			$oRequest->GetCryptoLib() == eCryptographyLibrary::None
		) {
			
			return json_encode($oResponse->messages);

		}
		
		return parent::GetOutput();
		
	}

}
