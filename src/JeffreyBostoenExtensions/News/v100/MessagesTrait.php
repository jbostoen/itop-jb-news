<?php

/**
 * @copyright   Copyright (c) 2019-2026 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.260106
 */

namespace JeffreyBostoenExtensions\News\v100;

// iTop internals.
use DBObjectSet;

// iTop classes.
use ThirdPartyNewsMessage;

/**
 * Trait MessagesTrait. Contains logic for messages.
 */
trait MessagesTrait {

    /**
     * @var Message[] $messages The messages to be sent to the client.
     */
    public array $messages = [];

    /**
     * @var string|null $signature The signature of the response.
     */
    public ?string $signature;

	/**
	 * Sets the messages.
	 * 
	 * @param DBObjectSet $oSet
	 */
    public function AddMessages(DBObjectSet $oSet) : void {
        
		$oSet->Rewind();

		// The structure of the "message" changed over time.
		// Each version namespace must have its own Message class.
		// Get the full class name and return everything before the last backslash
        $sNamespace = substr(get_class($this), 0, strrpos(get_class($this), '\\'));
		$sMsgClass = $sNamespace.'\\Message';

		/** @var ThirdPartyNewsMessage $oMessage */
		while($oMessage = $oSet->Fetch()) {
			$this->messages[] = $sMsgClass::FromThirdPartyNewsMessage($oMessage);
		}

	}

}


