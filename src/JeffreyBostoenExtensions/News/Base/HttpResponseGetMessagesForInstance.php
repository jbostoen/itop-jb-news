<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250909
 */

namespace JeffreyBostoenExtensions\News\Base;

use JeffreyBostoenExtensions\News\{
    Helper,
    ServerWorker
};

// iTop internals.
use DBObjectSet;
use DBObjectSearch;

// iTop classes.
use ThirdPartyNewsMessage;

/**
* Class HttpResponseGetMessagesForInstance. A base class for HTTP responses for getting messages for an instance.
*/
class HttpResponseGetMessagesForInstance extends HttpResponse {

    /**
     * @var Message[] $messages The messages to be sent to the client.
     */
    public array $messages = [];

    /**
     * @var string|null $signature The signature of the response.
     */
    public ?string $signature;


    /**
     * @inheritDoc
     * 
     * @param ServerWorker $oWorker The worker handling this response.
     */
    public function __construct(?ServerWorker $oWorker) {

        parent::__construct($oWorker);

		// - Get all relevant messages for this instance.

			$oSet = $this->GetThirdPartyNewsMessagesForInstance();

		// - Convert to response messages.

			$this->BuildResponse($oSet);

    }

	
	

	/**
	 * Returns all the relevant messages for an instance.
	 *
	 * @return DBObjectSet An object set with ThirdPartyNewsMessage objects.
	 */
	public function GetThirdPartyNewsMessagesForInstance() : DBObjectSet {

		Helper::Trace('Getting messages');
		
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
		$oWorker = $this->GetWorker();

		foreach($oWorker->GetExtensions() as $oExtension) {
			$oExtension->ProcessMessages($oWorker, $oSetMessages);
		}

		return $oSetMessages;

    }


	/**
	 * Builds the response.
	 * 
	 * This must be handled in subclasses.
	 *
	 * @param DBObjectSet $oSetMessages
	 * @return void
	 */
    public function BuildResponse(DBObjectSet $oSetMessages) : void {
        
		// Must be handled in sub classes.
			
	}

}

