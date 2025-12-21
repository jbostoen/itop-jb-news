<?php

/**
 * @copyright   Copyright (c) 2019-2022 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2.7.221223
 *
 */

namespace JeffreyBostoenExtensions\News\LocalServer;

use JeffreyBostoenExtensions\ServerCommunication\{
	eApiVersion,
	eOperation,
	Helper,
	ServerWorker,
	ServerWorkerTrait,
	Extensions\ServerExtension as BaseServerExtension,
	Base\HttpResponse,
	v100\HttpRequest as Request_v100,
	v110\HttpRequest as Request_v110,
	v200\HttpRequest as Request_v200,
	v210\HttpRequest as Request_v210,
	v210\HttpResponse as Response_v210,
};

use JeffreyBostoenExtensions\News\{
	v100\HttpResponseGetMessagesForInstance as Response_v100,
	v110\HttpResponseGetMessagesForInstance as Response_v110,
	v200\HttpResponseGetMessagesForInstance as Response_v200,
	v200\Message as Message,
};


// iTop internals.
use DBObjectSearch;
use DBObjectSet;
use ThirdPartyNewsMessage;

// Generic.
use stdClass;

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


		// - Build response.

			if($oWorker->GetClientOperation() == eOperation::GetMessagesForInstance) {
					
				$oResponse = match($oWorker->GetClientApiVersion()) {
					eApiVersion::v1_0_0 => new Response_v100($oWorker),
					eApiVersion::v1_1_0 => new Response_v110($oWorker),
					eApiVersion::v2_0_0 => new Response_v200($oWorker),
					// From here onward:
					default => $oWorker->GetHttpResponse(),
				};

				// Legacy:
				if(version_compare($oWorker->GetClientApiVersion()->value, '2.0.0', '<=')) {

					$oSet = $this->GetThirdPartyNewsMessagesForInstance();
	
					/** @var Response_v100|Response_v110|Response_v200 $oResponse */
					$oResponse->AddMessages($oSet);

				}
				else {

					$this->AddMessages($oResponse);

				}

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

	
	/**
	 * Sets the messages.
	 * 
	 * Note that since splitting server communication and the news bits,
	 * the structure of the messages/icons hasn't changed yet.
	 * 
	 * @param HttpResponse $oResponse
	 */
    public function AddMessages(HttpResponse $oResponse) : void {
        
		$oSet = $this->GetThirdPartyNewsMessagesForInstance();

		// Ensures this is present in the JSON structure.
		$oResponse->messages = [];

		/** @var ThirdPartyNewsMessage $oMessage */
		while($oMessage = $oSet->Fetch()) {
			$oResponse->messages[] = Message::FromThirdPartyNewsMessage($oMessage);
		}
		
		$oIconLib = new stdClass();

		/** @var ThirdPartyNewsMessage $oObj */
		while($oObj = $oSet->Fetch()) {

			$oMessage = Message::FromThirdPartyNewsMessage($oObj);
			$oResponse->messages[] = $oMessage;

			$oIcon = $oMessage->GetIcon();

			if($oIcon !== null) {
				
				$sIconRef = $oIcon->GetRef();
				$oIconLib->$sIconRef = $oIcon;

			}


		}

		$oResponse->icons = $oIconLib;


	}

}
