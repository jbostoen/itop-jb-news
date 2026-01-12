<?php

/**
 * @copyright   Copyright (c) 2019-2026 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.260112
 */

namespace JeffreyBostoenExtensions\News\Base;

// iTop internals.
use ThirdPartyNewsMessage;

/**
 * Class Message. Represents an outgoing message to a client.
 */
class Message {
    
    /**
     * @var string $thirdparty_message_id The third party message identifier.
     */
    public string $thirdparty_message_id;

    /**
     * @var string $title The title of the message.
     */
    public string $title;

    /**
     * @var Icon $icon The icon of the message (API v1.0.0).
     */
    public Icon|string $icon;

    /**
     * @var string $start_date The start date of the message.
     */
    public string $start_date;

    /**
     * @var string $end_date The end date of the message.
     */
    public ?string $end_date;

    /**
     * @var string $priority The priority of the message. (1 = Critical, 2 = Urgent, 3 = Important, 4 = Standard)
     */
    public string $priority;

    /**
     * @var Translation[] $translations_list The translations of the message.
     */
    public array $translations_list = [];

    /**
     * @var Icon|null $oIcon The icon of the message.
     */
    private $oIcon = null;

    

    

    /**
     * Returns an array of Translation objects for the given ThirdPartyNewsMessage.
     *
     * @param ThirdPartyNewsMessage $oMessage
     * @return Translation[]
     */
    public function GetTranslations(ThirdPartyNewsMessage $oMessage) : array {

        /** @var ormLinkSet $oSetTranslations Translations of a message */
        $oSetTranslations = $oMessage->Get('translations_list');

        // There should always be translations. But just in case:
        $aTranslations = [];

        while($oTranslation = $oSetTranslations->Fetch()) {

            /** @var Translation $oMsgTranslation */
            $oMsgTranslation = new Translation();
            $oMsgTranslation->language = $oTranslation->Get('language');
            $oMsgTranslation->title = $oTranslation->Get('title');
            $oMsgTranslation->text = $oTranslation->Get('text');
            $oMsgTranslation->url = $oTranslation->Get('url');
            
            $aTranslations[] = $oMsgTranslation;
            
        }

        return $aTranslations;
        
    }

    /**
     * Converts a ThirdPartyNewsMessage to a Message object.
     *
     * @param ThirdPartyNewsMessage $oMessage
     * @return Message
     */
    public static function FromThirdPartyNewsMessage(ThirdPartyNewsMessage $oMessage) : Message {

        $sClass = static::class;

        $oMsg = new $sClass();
        $oMsg->thirdparty_message_id = $oMessage->Get('thirdparty_message_id');
        $oMsg->title = $oMessage->Get('title');
        $oMsg->start_date = $oMessage->Get('start_date');
        $oMsg->end_date = $oMessage->Get('end_date');
        $oMsg->priority = $oMessage->Get('priority');
        $oMsg->translations_list = $oMsg->GetTranslations($oMessage);

        $oIcon = Icon::FromThirdPartyNewsMessage($oMessage);
        $oMsg->SetIcon($oIcon);

        return $oMsg;

    }


    /**
     * Sets the icon.
     *
     * @param Icon|null $oIcon
     * @return void
     */
    public function SetIcon(?Icon $oIcon) : void {

        $this->oIcon = $oIcon;

    }

    /**
     * Gets the icon.
     *
     * @return Icon
     */
    public function GetIcon() : Icon {

        return $this->oIcon;

    }

}

