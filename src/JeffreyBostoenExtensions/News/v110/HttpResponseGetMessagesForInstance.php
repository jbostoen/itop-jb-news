<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.251212
 */

namespace JeffreyBostoenExtensions\News\v110;

use JeffreyBostoenExtensions\News\v100\MessagesTrait;

use JeffreyBostoenExtensions\ServerCommunication\v110\HttpResponse;

use JeffreyBostoenExtensions\ServerCommunication\{
	eApiVersion,
	eCryptographyLibrary
};


/**
* Class HttpResponseGetMessagesForInstance. A base class for HTTP responses for getting messages for an instance.
*/
class HttpResponseGetMessagesForInstance extends HttpResponse {

	use MessagesTrait;


	/**
	 * @inheritDoc
	 */
	public function GetOutput(): string {

		
		$oRequest = $this->GetHttpRequest();
		$oResponse = $this->GetHttpResponse();
		
		if(
			$oRequest->GetCryptoLib() == eCryptographyLibrary::None &&
			($oRequest->GetApiVersion() == eApiVersion::v1_0_0 || $oRequest->GetApiVersion() == eApiVersion::v1_1_0)
		) {
			
			return json_encode($oResponse->messages);

		}
		
		return parent::GetOutput();
		
	}

}
