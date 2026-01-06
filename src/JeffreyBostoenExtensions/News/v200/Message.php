<?php

/**
 * @copyright   Copyright (c) 2019-2026 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.260106
 */

namespace JeffreyBostoenExtensions\News\v200;

use JeffreyBostoenExtensions\News\Base\Message as Base;

// iTop internals.
use ThirdPartyNewsMessage;


/**
 * Class Message. Represents an outgoing message to a client. (API v2.0.0)
 */
class Message extends Base {

    /**
     * @var string $oql The OQL that specifies which users should see this message.
     */
    public string $oql = 'SELECT User';

    
    /**
     * Converts a ThirdPartyNewsMessage to a Message object.
     *
     * @param ThirdPartyNewsMessage $oMessage
     * @return Message
     */
    public static function FromThirdPartyNewsMessage(ThirdPartyNewsMessage $oMessage) : Message {

        /** @var Message $oMsg */
        $oMsg = parent::FromThirdPartyNewsMessage($oMessage);
        
        $oMsg->icon = $oMsg->GetIcon()->GetRef();

        return $oMsg;

    }
    

}

