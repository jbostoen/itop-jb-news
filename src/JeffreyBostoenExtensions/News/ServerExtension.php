<?php

/**
 * @copyright   Copyright (c) 2019-2022 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2.7.221223
 *
 */

namespace JeffreyBostoenExtensions\News;

use JeffreyBostoenExtensions\ServerCommunication\{
	eApiVersion,
	Helper,
	ServerWorker,
	ServerWorkerTrait,
	Extensions\ServerExtension as BaseServerExtension,
	v100\HttpRequest as Request_v100,
	v110\HttpRequest as Request_v110,
	v200\HttpRequest as Request_v200,
	v210\HttpRequest as Request_v210,
};

use JeffreyBostoenExtensions\News\{
	v100\HttpResponseGetMessagesForInstance as Response_v100,
	v110\HttpResponseGetMessagesForInstance as Response_v110,
	v200\HttpResponseGetMessagesForInstance as Response_v200,
	v210\HttpResponseGetMessagesForInstance as Response_v210,
};


// iTop internals.
use DBObjectSearch;
use DBObjectSet;

/**
 * Class ServerExtension. Defines custom news server actions.
 */
class ServerExtension extends BaseServerExtension {

	use ServerWorkerTrait;

	/**
	 * @inheritDoc
	 */
	public function GetRank() : int {
		return 50;
	}


	/**
	 * @inheritDoc
	 */
	public function GetSupportedOperations(): array {

		return [
			eOperation::GetMessagesForInstance,
			eOperation::ReportReadStatistics
		];

	}


	/**
	 * @inheritDoc
	 */ 
	public function Process() : void {

		if($this->GetHttpResponse() !== null) {
			// This allows easy subclassing (where the subclass can just get a lower rank).
			Helper::Trace('A response was already created by another extension. Skip.');
			return;
		}
		
		/** @var ServerWorker $oWorker */
		$oWorker = $this->GetWorker();

		// - The news extension has no specific HttpRequest format.
			
			$oRequest = match($oWorker->GetClientApiVersion()) {
				eApiVersion::v1_0_0 => new Request_v100($oWorker),
				eApiVersion::v1_1_0 => new Request_v110($oWorker),
				eApiVersion::v2_0_0 => new Request_v200($oWorker),
				eApiVersion::v2_1_0 => new Request_v210($oWorker),
			};

			$oWorker->SetHttpRequest($oRequest);
		
		// - Read values, validate.

			$oRequest->ReadUserProvidedValues();
            $oRequest->Validate();

		// - Build response.

			$oResponse = null;

			if($oWorker->GetClientOperation() == eOperation::GetMessagesForInstance) {
					
				$oSet = $this->GetThirdPartyNewsMessagesForInstance();

				$oResponse = match($oWorker->GetClientApiVersion()) {
					eApiVersion::v1_0_0 => new Response_v100($oWorker),
					eApiVersion::v1_1_0 => new Response_v110($oWorker),
					eApiVersion::v2_0_0 => new Response_v200($oWorker),
					eApiVersion::v2_1_0 => new Response_v210($oWorker),
				};

				$oResponse->AddMessages($oSet);

			}
			else {

				// Do nothing.

			}

			$oWorker->SetHttpResponse($oResponse);

	}


	/**
	 * Returns all the relevant messages for an instance.
	 *
	 * @return DBObjectSet An object set with ThirdPartyNewsMessage objects.
	 */
	public function GetThirdPartyNewsMessagesForInstance() : DBObjectSet {

		Helper::Trace('Querying messages.');
		
		// Some publications might still be hidden (surprise announcement, promo, limited offer, ...).
		// By default: Only select the messages that are currently published.
		$sOQL = '
			SELECT ThirdPartyNewsMessage 
			WHERE 
				start_date <= NOW() AND 
				(
					ISNULL(end_date) = 1 OR 
					end_date >= NOW()
				)
		';

		$oSetMessages = new DBObjectSet(DBObjectSearch::FromOQL_AllData($sOQL));
		return $oSetMessages;

    }

}
