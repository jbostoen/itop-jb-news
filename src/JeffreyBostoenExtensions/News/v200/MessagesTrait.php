<?php

/**
 * @copyright   Copyright (c) 2019-2026 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.260110
 */

namespace JeffreyBostoenExtensions\News\v200;

use JeffreyBostoenExtensions\ServerCommunication\Helper;

// iTop internals.
use DBObjectSet;

// iTop classes.
use ThirdPartyNewsMessage;

// Generic.
use stdclass;

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
     * @var object|null $icons The icon library. Key = "ref_md5", value = an Icon. */
    public ?object $icons;


	/**
	 * Sets the messages.
	 * 
	 * @param DBObjectSet $oSet
	 */
    public function AddMessages(DBObjectSet $oSet) : void {
        
		$oSet->Rewind();

		$oIconLib = new stdClass();

		/** @var ThirdPartyNewsMessage $oObj */
		while($oObj = $oSet->Fetch()) {

			$oMessage = Message::FromThirdPartyNewsMessage($oObj);
			$this->messages[] = $oMessage;

			$oIcon = $oMessage->GetIcon();

			if($oIcon !== null) {
				
				$sIconRef = $oIcon->GetRef();
				$oIconLib->$sIconRef = $oIcon;

			}


		}

		$this->icons = $oIconLib;


	}

}


