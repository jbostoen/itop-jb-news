<?php

/**
 * @copyright   Copyright (C) 2019-2020 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2020-11-04 15:45:48
 *
 * Definition of NewsRoomHelper.
 */

namespace jb_itop_extensions\NewsClient\Common\Helper;

use \DBObjectSearch;
use \DBObjectSet;
use \Dict;
use \Exception;
use \MetaModel;
use \User;
use \UserRights;
use \utils;
use \WebPage;

/**
 * Class NewsRoomHelper. 
 */
class NewsRoomHelper {
	
	/** @var \String MODULE_CODE */
	const MODULE_CODE = 'jb-news-client';

	/** @var \String DEFAULT_API_VERSION */
	const DEFAULT_API_VERSION = '1.0';
	
	/** @var \String DEFAULT_APP_NAME */
	const DEFAULT_APP_NAME = 'unknown';
	
	/** @var \String DEFAULT_APP_VERSION */
	const DEFAULT_APP_VERSION = 'unknown';

	/**
	 * Returns all published messages until now (not those planned for further publication)
	 *
	 * @return \ThirdPartyNewsroomMessage[] Set of messages
	 *
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \MySQLException
	 * @throws \OQLException
	 */
	protected static function GetAllMessages() {
		
		$oSearch = DBObjectSearch::FromOQL('SELECT ThirdPartyNewsroomMessage WHERE start_date <= NOW() AND (ISNULL(end_date) OR end_date >= NOW())');
		$oSet = new DBObjectSet($oSearch);

		$aMessages = [];
		while($oMessage = $oSet->Fetch()) {
			$aMessages[] = $oMessage;
		}

		return $aMessages;
		
	}

	/**
	 * Returns an array of ThirdPartyNewsroomMessage data prepared for the webservice.
	 *
	 * @return array
	 *
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \MySQLException
	 * @throws \OQLException
	 * @throws \Exception
	 */
	public static function GetUnreadMessagesForUser() {
		
		$oUser = UserRights::GetUserObject();
		$sMessageClass = 'ThirdPartyNewsroomMessage';
		$sMessageIconAttCode = 'icon';

		$aSearchParams = array('user_id' => $oUser->GetKey());
		$oSearch = DBObjectSearch::FromOQL('SELECT M FROM '.$sMessageClass.' AS M JOIN ThirdPartyUnreadMessageToUser AS LUM ON LUM.message_id = M.id WHERE LUM.user_id = :user_id AND M.start_date <= NOW() AND (ISNULL(M.end_date) OR M.end_date >= NOW())', $aSearchParams);
		$oSet = new DBObjectSet($oSearch);
		$oSet->SetLimit(50); // Limit messages count to avoid server crash

		$aMessages = [];
		while($oMessage = $oSet->Fetch()) {
			
			// Prepare icon URL
			/** @var \ormDocument $oIcon */
			$oIcon = $oMessage->Get($sMessageIconAttCode);
			if(is_object($oIcon) && !$oIcon->IsEmpty()) {
				$sIconUrl = $oIcon->GetDisplayURL($sMessageClass, $oMessage->GetKey(), $sMessageIconAttCode);
			}
			else {
				$sIconUrl = MetaModel::GetAttributeDef($sMessageClass, $sMessageIconAttCode)->Get('default_image');
			}

			// Prepare url redirection
			$sUrl = utils::GetAbsoluteUrlExecPage().'?exec_module='.static::MODULE_CODE.'&exec_page=index.php&operation=redirect&message_id='.$oMessage->GetKey().'&user='.$oUser->GetKey();

			$oTranslation = self::GetTranslation($oMessage);

			$aMessages[] = array(
				'id' => $oMessage->GetKey(),
				'text' => $oTranslation->Get('text'),
				'url' => $oCurrentTranslation->Get('url'),
				'start_date' => $oMessage->Get('start_date'),
				'priority' => $oMessage->Get('priority'),
				'image' => $sIconUrl,
			);
		}

		return $aMessages;
	}

	/**
	 * Marks messages as read for $oUser.
	 *
	 * Note: Only the 50 first messages will be processed.
	 *
	 * @return \Integer Number messages marked as read
	 *
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \DeleteException
	 * @throws \MySQLException
	 * @throws \OQLException
	 */
	public static function MarkAllMessagesAsReadForUser() {
		
		$oUser = UserRights::GetUserObject();
		$aSearchParams = array('user_id' => $oUser->GetKey());
		$oSearch = DBObjectSearch::FromOQL('SELECT ThirdPartyUnreadMessageToUser WHERE user_id = :user_id', $aSearchParams);
		$oSet = new DBObjectSet($oSearch);
		$oSet->SetLimit(50); // Limit messages count to avoid server crash
		$oSet->OptimizeColumnLoad(array());

		$iMessageCount = 0;
		while($oMessage = $oSet->Fetch()) {
			$oMessage->DBDelete();
			$iMessageCount++;
		}

		return $iMessageCount;
	}

	/**
	 * Marks the message of $iMessageId ID as read for $oUser.
	 * Returns true if the message could be marked as read, false otherwise (already read for example).
	 *
	 * @param int $iMessageId
	 *
	 * @return bool
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \DeleteException
	 * @throws \MySQLException
	 * @throws \OQLException
	 */
	public static function MarkMessageAsReadForUser($iMessageId) {
		
		$oUser = UserRights::GetUserObject();
		$aSearchParams = array('message_id' => $iMessageId, 'user_id' => $oUser->GetKey());
		$oSearch = DBObjectSearch::FromOQL('SELECT ThirdPartyUnreadMessageToUser WHERE message_id = :message_id AND user_id = :user_id', $aSearchParams);
		$oSet = new DBObjectSet($oSearch);

		$oMessage = $oSet->Fetch();
		if($oMessage !== null) {
			$oMessage->DBDelete();
			return true;
		}
		else {
			return false;
		}
	}

	/**
	 * Makes a web page displaying all messages
	 *
	 * @param \WebPage $oPage
	 *
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \MySQLException
	 * @throws \OQLException
	 * @throws \Exception
	 */
	public static function MakeAllMessagesPage(WebPage &$oPage) {
		
		$sMessageClass = 'ThirdPartyNewsroomMessage';
		$sMessageIconAttCode = 'icon';

		// Retrieve messages
		$sMessagesHtml = '';
		$aMessages = static::GetAllMessages();
		foreach($aMessages as $oMessage) {
			
			// Prepare icon URL
			/** @var \ormDocument $oIcon */
			$oIcon = $oMessage->Get($sMessageIconAttCode);
			if(is_object($oIcon) && !$oIcon->IsEmpty()) {
				$sIconUrl = $oIcon->GetDisplayURL($sMessageClass, $oMessage->GetKey(), $sMessageIconAttCode);
			}
			else {
				$sIconUrl = MetaModel::GetAttributeDef($sMessageClass, $sMessageIconAttCode)->Get('default_image');
			}
			
			$oTranslation = self::GetTranslation($oMessage);

			$sMessagesHtml .=
				<<<HTML
<div class="jbnewsclient-message">
			<a href="{$oTranslation->Get('url')}" target="_blank">
				<div class="jbnewsclient-m-icon">
					<img src="{$sIconUrl}" alt="Message icon" />
				</div>
				<div class="jbnewsclient-m-content">
					<div class="jbnewsclient-m-text">{$oTranslation->Get('text')}</div>
					<div class="jbnewsclient-m-date">{$oMessage->Get('start_date')}</div>
				</div>
			</a>
		</div>
HTML
			;
		}

		// Add style
		$oPage->add_saas('env-'.utils::GetCurrentEnvironment().'/'.static::MODULE_CODE.'/css/default.scss');
		$sLabel = Dict::S('UI:News:AllMessages');

		// Build markup
		$oPage->add(
			<<<HTML
<div class="jbnewsclient-all-messages">
	<h2>{$sLabel}</h2>
	<div class="jbnewsclient-messages">
		{$sMessagesHtml}
	</div>
</div>
HTML
		);
	}

	/**
	 * Gets translation for current user
	 *
	 * @param \ThirdPartyNewsroomMessage $oMessage Third party newsroom message
	 *
	 * @return \ThirdPartyNewsroomMessageTranslation
	 */
	protected static function GetTranslation($oMessage) {
		
		$oSetTranslations = $oMessage->Get('translations_list');
		
		$oTranslation = null;
		$iLanguage = 0; // 0: nothing found; 1: English found; 2: localized version found
		
		while($oCurrentTranslation = $oSetTranslations->Fetch()) {
			
			switch(true) {
				
				// Matches user language
				case ($oCurrentTranslation->Get('language') == UserRights::GetUserLanguage()):
					$iLanguage = 2;
					
				// Text is empty, but English string has been found
				case ($oCurrentTranslation->Get('language') == 'EN US'):
					$iLanguage = 1;
				
				// Take anything if English or user language hasn't been found yet
				case $iLanguage == 0:
					
					$oTranslation = $oCurrentTranslation;
					break;
					
			}
			
			if($iLanguage == 2) {
				break;
			}
			
		}
		
		return $oTranslation;
		
	}

}
