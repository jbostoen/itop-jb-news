<?php

/**
 * @copyright   Copyright (c) 2019-2022 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2.7.220113
 *
 */

namespace jb_itop_extensions\NewsClient;

// iTop internals
use \DBObjectSearch;
use \DBObjectSet;
use \Dict;
use \Exception;
use \MetaModel;
use \User;
use \UserRights;
use \utils;
use \WebPage;

// iTop classes
use \ThirdPartyNewsRoomMessage;
use \ThirdPartyNewsRoomMessageTranslation;
use \ThirdPartyUnreadMessageToUser;

// Custom classes
use \jb_itop_extensions\NewsClient\NewsRoomWebPage;

/**
 * Class NewsRoomHelper. Contains a lot of functions to assist in AJAX requests.
 */
class NewsRoomHelper {
	
	/** @var \String MODULE_CODE */
	const MODULE_CODE = 'jb-news';

	/** @var \String DEFAULT_API_VERSION */
	const DEFAULT_API_VERSION = '1.0';
	
	/** @var \String DEFAULT_APP_NAME */
	const DEFAULT_APP_NAME = 'unknown';
	
	/** @var \String DEFAULT_APP_VERSION */
	const DEFAULT_APP_VERSION = 'unknown';

	/**
	 * Returns all published messages until now (not those planned for further publication) for this user
	 *
	 * @return \ThirdPartyNewsRoomMessage[] Set of messages
	 *
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \MySQLException
	 * @throws \OQLException
	 */
	protected static function GetAllMessages() {
		
		$oSearch = DBObjectSearch::FromOQL('SELECT ThirdPartyNewsRoomMessage WHERE start_date <= NOW() AND (ISNULL(end_date) OR end_date >= NOW())');
		$oSet = new DBObjectSet($oSearch);
		
		$aMessages = [];
		while($oMessage = $oSet->Fetch()) {
			
			if(static::MessageIsApplicable($oMessage) == true) {
				$aMessages[] = $oMessage;
			}
			
		}
		
		// Sort
		usort($aMessages, function ($oMessage1, $oMessage2) {
			
			$iSort = $oMessage1->Get('priority') <=> $oMessage2->Get('priority');
			
			return ($iSort == 0 ? strtotime($oMessage1->Get('start_date') <=> $oMessage2->Get('start_date')) : $iSort);
			
		});

		return $aMessages;
		
	}

	/**
	 * Returns an array of ThirdPartyNewsRoomMessage data prepared for the webservice.
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
		$sMessageClass = 'ThirdPartyNewsRoomMessage';
		$sMessageIconAttCode = 'icon';

		$aSearchParams = array('user_id' => $oUser->GetKey());
		$oSearch = DBObjectSearch::FromOQL('SELECT M FROM '.$sMessageClass.' AS M JOIN ThirdPartyUnreadMessageToUser AS LUM ON LUM.message_id = M.id WHERE LUM.user_id = :user_id AND M.start_date <= NOW() AND (ISNULL(M.end_date) OR M.end_date >= NOW())', $aSearchParams);
		$oSet = new DBObjectSet($oSearch);
		$oSet->SetLimit(50); // Limit messages count to avoid server crash

		$aMessages = [];
		$aProfiles = UserRights::ListProfiles();
		
		while($oMessage = $oSet->Fetch()) {
			
			if(static::MessageIsApplicable($oMessage) == false) {	
				// Current user should not see this message
				continue;
			}
			
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
			
			if($oTranslation !== null) {

				$aMessages[] = array(
					'id' => $oMessage->GetKey(),
					'text' => '# '.$oTranslation->Get('title').PHP_EOL.PHP_EOL.$oTranslation->Get('text'),
					'url' => $oTranslation->Get('url'),
					'start_date' => $oMessage->Get('start_date'),
					'priority' => $oMessage->Get('priority'),
					'image' => $sIconUrl,
				);
				
			}
			
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
	 * Makes a newsroom web page displaying all messages
	 *
	 * @param \NewsRoomWebPage $oPage
	 *
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \MySQLException
	 * @throws \OQLException
	 * @throws \Exception
	 */
	public static function MakeAllMessagesPage(NewsRoomWebPage &$oPage) {
		
		$sMessageClass = 'ThirdPartyNewsRoomMessage';
		$sMessageIconAttCode = 'icon';

		// Retrieve messages
		$aJsonMessages = [];
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

			if($oTranslation !== null) {
				
				$aJsonMessages[] = [
					'url' => $oTranslation->Get('url'),
					'icon' => $sIconUrl,
					'start_date' => $oMessage->Get('start_date'),
					'title' => $oTranslation->Get('title'),
					'text' => $oTranslation->Get('text')
				];
			
			}
			
		}

		// Add style
		$oPage->add_saas('env-'.utils::GetCurrentEnvironment().'/'.static::MODULE_CODE.'/css/default.scss');
		$sLabel = Dict::S('UI:News:AllMessages');
		
		// Add libraries
		$oPage->add_linked_script(utils::GetAbsoluteUrlAppRoot().'js/jquery.min.js');
		$oPage->add_linked_script(utils::GetAbsoluteUrlAppRoot().'js/showdown.min.js');

		// Build markup
		$oPage->add(
			<<<HTML
<div class="jbnewsclient-all-messages">
	<h2>{$sLabel}</h2>
	<div class="jbnewsclient-messages">
	</div>
</div>

HTML
		);
		
		$sJsonMessages = json_encode($aJsonMessages);

		
		$oPage->add_ready_script(
<<<JS

			var oShownDownConverter = new showdown.Converter();
			var aThirdPartyNewsRoomMessages = {$sJsonMessages}
			
			$.each(aThirdPartyNewsRoomMessages, function(i) {
				
				var msg = aThirdPartyNewsRoomMessages[i];
				var sTitle = oShownDownConverter.makeHtml(msg.title);
				var sText = oShownDownConverter.makeHtml(msg.text);
				
				$('.jbnewsclient-messages').append(
					'<div class="jbnewsclient-message" data-url="' + msg.url + '">' +
					'		<div class="jbnewsclient-m-icon">' +
					'			<img src="' + msg.icon + '" alt="Message icon" />' +
					'		</div>' +
					'		<div class="jbnewsclient-m-content">' +
					'			<div class="jbnewsclient-m-title">' + sTitle + '</div>' +
					'			<div class="jbnewsclient-m-text">' + sText + '</div>' +
					'			<div class="jbnewsclient-m-date"><p>' + msg.start_date + '</p></div>' +
					'		</div>' +
					'</div>'
				);
				
			});

			$(document.body).on('click', '[data-url]', function(e) {
				document.location.href = $(this).attr('data-url');
			});
			
JS
		);
		
		
		
	}

	/**
	 * Gets translation for current user
	 *
	 * @param \ThirdPartyNewsRoomMessage $oMessage Third party newsroom message
	 *
	 * @return \ThirdPartyNewsRoomMessageTranslation
	 */
	protected static function GetTranslation($oMessage) {
		
		/** @var \ormLinkSet $oSetTranslations */
		$oSetTranslations = $oMessage->Get('translations_list');
		
		/**
		 * @var \ThirdPartyNewsRoomMessageTranslation $oTranslation Third Party Newsroom Message Translation
		 */
		$oTranslation = null;
		
		while($oCurrentTranslation = $oSetTranslations->Fetch()) {
			
			switch(true) {
				
				// Matches user language
				case ($oCurrentTranslation->Get('language') == UserRights::GetUserLanguage()):
					return $oCurrentTranslation;
					
				// Text is empty, but English string has been found
				case ($oCurrentTranslation->Get('language') == 'EN US'):
				
				// Take first available language if English or user language hasn't been found yet
				case $oTranslation == null:
					$oTranslation = $oCurrentTranslation;
					break;
					
			}
			
		}
		
		return $oTranslation;
		
	}
	
	/**
	 * Checks whether current user falls under target profiles scope
	 *
	 * @param \ThirdPartyNewsRoomMessage $oMessage Third party newsroom message
	 * @param \User $oUser Optional user
	 *
	 * @return \Boolean
	 */
	protected static function MessageIsApplicable(ThirdPartyNewsRoomMessage $oMessage, User $oUser = null) {
		
		$sTargetProfiles = $oMessage->Get('target_profiles');
		$sTargetProfiles = preg_replace('/[\s]{1,},[\s]{1,}/', '', $sTargetProfiles);
		$aTargetProfiles = explode(',', $sTargetProfiles);
		$aUserProfiles = UserRights::ListProfiles($oUser);
		$aOverlap = array_intersect($aTargetProfiles, $aUserProfiles);
		
		return ($oMessage->Get('target_profiles') == '' || count($aOverlap) > 0);
		
	}
	
	/**
	 * Creates record to keep track of unread messages for user
	 *
	 * @param \ThirdPartyNewsRoomMessage $oMessage Third party newsroom message
	 *
	 * @return \void
	 */
	public static function GenerateUnreadMessagesForUsers($oMessage) {

		// @todo For now this method is only called when the message is created. There's no track record of (un)read messages. Hence, there's a record for each user, even if it's not the target user.

		// Create a record for each user
		$oUserSearch = DBObjectSearch::FromOQL('SELECT User');
		$oUserSet = new DBObjectSet($oUserSearch);
		$oUserSet->OptimizeColumnLoad(array());

		while($oUser = $oUserSet->Fetch()) {
						
			$oUnreadMessage = MetaModel::NewObject('ThirdPartyUnreadMessageToUser', array(
				'user_id' => $oUser->GetKey(),
				'message_id' => $oMessage->GetKey(),
			));
			$oUnreadMessage->DBInsert();
		}
		
	}

}
