<?php

/**
 * @copyright   Copyright (c) 2019-2022 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2.7.221223
 *
 */

namespace jb_itop_extensions\NewsProvider;

// Generic
use \Exception;

// iTop internals
use \DBObjectSearch;
use \DBObjectSet;
use \Dict;
use \MetaModel;
use \User;
use \UserRights;
use \utils;
use \WebPage;

// iTop classes
use \ThirdPartyNewsRoomMessage;
use \ThirdPartyNewsRoomMessageTranslation;
use \ThirdPartyMessageReadStatus;

// Custom classes
use \jb_itop_extensions\NewsProvider\NewsRoomWebPage;

/**
 * Class NewsRoomHelper. Contains a lot of functions to assist in AJAX requests.
 */
class NewsRoomHelper {
	
	/** @var \String MODULE_CODE */
	const MODULE_CODE = 'jb-news';

	/** @var \String DEFAULT_API_VERSION */
	const DEFAULT_API_VERSION = '1.1.0';
	
	/** @var \String DEFAULT_APP_NAME */
	const DEFAULT_APP_NAME = 'unknown';
	
	/** @var \String DEFAULT_APP_VERSION */
	const DEFAULT_APP_VERSION = 'unknown';

	/**
	 * Returns all messages that have been published until now (not those planned for further publication) and are applicable to a certain user.
	 * Returned as an array instead of DBObjectSet due to sorting (priority, start_date).
	 *
	 * @return \DBObjectSet Filtered set of messages
	 *
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \MySQLException
	 * @throws \OQLException
	 */
	public static function GetAllMessagesForUser() {
		
		$oSearch = DBObjectSearch::FromOQL('SELECT ThirdPartyNewsRoomMessage WHERE start_date <= NOW() AND (ISNULL(end_date) OR end_date >= NOW())');
		$oSearch->AllowAllData();
		$oSet = new DBObjectSet($oSearch, [
			'priority' => false, // Higher priority first
			'start_date' => false, // Most recent publication first
		]);
		
		$oReturnSet = DBObjectSet::FromScratch('ThirdPartyNewsRoomMessage');
		
		while($oMessage = $oSet->Fetch()) {
		
			if(static::MessageIsApplicable($oMessage) == true) {
				$oReturnSet->AddObject($oMessage);
			}
			
		}
		
		// Apparently it's necessary to rewind
		$oSet->Rewind();
		return $oSet;;
		
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
		$sMessageIconAttCode = 'icon';

		$aSearchParams = ['user_id' => $oUser->GetKey()];
		$oSearch = DBObjectSearch::FromOQL('SELECT ThirdPartyNewsRoomMessage AS M WHERE M.id NOT IN (SELECT ThirdPartyNewsRoomMessage AS M2 JOIN ThirdPartyMessageReadStatus AS RS ON RS.message_id = M2.id WHERE RS.user_id = :user_id) AND M.start_date <= NOW() AND (ISNULL(M.end_date) OR M.end_date >= NOW())', $aSearchParams);
		$oSearch->AllowAllData();
		$oSet = new DBObjectSet($oSearch);
		$oSet->SetLimit(50); // Limit messages count to avoid server crash

		$aMessages = [];
		
		while($oMessage = $oSet->Fetch()) {
			
			if(static::MessageIsApplicable($oMessage) == false) {	
				// Current user should not see this message
				continue;
			}
			
			// Prepare icon URL
			/** @var \ormDocument $oIcon */
			$oIcon = $oMessage->Get($sMessageIconAttCode);
			if(is_object($oIcon) && !$oIcon->IsEmpty()) {
				$sIconUrl = $oIcon->GetDisplayURL('ThirdPartyNewsRoomMessage', $oMessage->GetKey(), $sMessageIconAttCode);
			}
			else {
				$sIconUrl = MetaModel::GetAttributeDef('ThirdPartyNewsRoomMessage', $sMessageIconAttCode)->Get('default_image');
			}

			// Prepare url redirection
			$sUrl = utils::GetAbsoluteUrlExecPage().'?exec_module='.static::MODULE_CODE.'&exec_page=index.php&operation=redirect&message_id='.$oMessage->GetKey().'&user='.$oUser->GetKey();

			$oTranslation = static::GetTranslationForUser($oMessage);
			
			if($oTranslation !== null) {

				$aMessages[] = array(
					'id' => $oMessage->GetKey(),
					'text' => '# '.$oTranslation->Get('title').PHP_EOL.PHP_EOL.$oTranslation->Get('text'), // Prepare header (MarkDown) + add regular text/description.
					'url' => $sUrl, // Mind that this is used to show the messages in the newsroom. Routing is applied to take care of "read date" before redirecting.
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
		
		// Get previously unread messages
		$aSearchParams = ['user_id' => UserRights::GetUserId()];
		$oSearch = DBObjectSearch::FromOQL('SELECT ThirdPartyNewsRoomMessage AS M WHERE M.id NOT IN (SELECT ThirdPartyNewsRoomMessage AS M2 JOIN ThirdPartyMessageReadStatus AS RS ON RS.message_id = M2.id WHERE RS.user_id = :user_id) AND M.start_date <= NOW() AND (ISNULL(M.end_date) OR M.end_date >= NOW())', $aSearchParams);
		$oSearch->AllowAllData();
		$oSetMessages = new DBObjectSet($oSearch);
		
		$iCount = 0;
		
		while($oMessage = $oSetMessages->Fetch()) {
			
			
			try {
				
				$oReadStatus = MetaModel::NewObject('ThirdPartyMessageReadStatus', [
					'message_id' => $oMessage->GetKey(),
					'user_id' => UserRights::GetUserId(),
					'read_date' => date('Y-m-d H:i:s')
				]);
			
				$oReadStatus->DBInsert();
			
				$iCount += 1;
				
			}
			catch(Exception $e) {
				// Probably too late / duplicate
			}
			
		}

		return $iCount;
	}

	/**
	 * Marks the message of $iMessageId ID as read for $oUser.
	 * Returns true if the message could be marked as read, false otherwise (already read for example).
	 *
	 * @param \Integer $iMessageId
	 *
	 * @return \Boolean
	 *
	 * @return bool
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \DeleteException
	 * @throws \MySQLException
	 * @throws \OQLException
	 */
	public static function MarkMessageAsReadForUser($iMessageId) {

		$bRet = false;

		try {
			
			$oReadStatus = MetaModel::NewObject('ThirdPartyMessageReadStatus', [
				'message_id' => $iMessageId,
				'user_id' => UserRights::GetUserId(),
				'read_date' => date('Y-m-d H:i:s')
			]);
		
			$oReadStatus->DBInsert();
		
			$bRet = true;
			
		}
		catch(Exception $e) {
			// Probably too late / duplicate
		}
		
		return $bRet;
		
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
		
		$sMessageIconAttCode = 'icon';

		// Retrieve messages
		$aJsonMessages = [];
		$oSetMessages = static::GetAllMessagesForUser();
		
		while($oMessage = $oSetMessages->Fetch()) {
			
			// Prepare icon URL
			/** @var \ormDocument $oIcon */
			$oIcon = $oMessage->Get($sMessageIconAttCode);
			if(is_object($oIcon) && !$oIcon->IsEmpty()) {
				$sIconUrl = $oIcon->GetDisplayURL('ThirdPartyNewsRoomMessage', $oMessage->GetKey(), $sMessageIconAttCode);
			}
			else {
				$sIconUrl = MetaModel::GetAttributeDef('ThirdPartyNewsRoomMessage', $sMessageIconAttCode)->Get('default_image');
			}
			
			$oTranslation = static::GetTranslationForUser($oMessage);

			if($oTranslation !== null) {
				
				$aJsonMessages[] = [
					'start_date' => $oMessage->Get('start_date'),
					'title' => $oTranslation->Get('title'),
					'text' => $oTranslation->Get('text'),
					'url' => $oTranslation->Get('url'), // Leave this URL intact, it's shown in an overview.
					'icon' => $sIconUrl
				];
			
			}
			
		}

		// Add style
		$oPage->add_saas('env-'.utils::GetCurrentEnvironment().'/'.static::MODULE_CODE.'/css/default.scss');
		$sLabel = Dict::S('UI:News:AllMessages');
		$sMoreInfo = Dict::S('UI:News:MoreInfo');
		
		// Add libraries
		$oPage->add_linked_script(utils::GetAbsoluteUrlAppRoot().'js/jquery.min.js');
		$oPage->add_linked_script(utils::GetAbsoluteUrlAppRoot().'js/showdown.min.js');

		// Build markup
		$oPage->add(
			<<<HTML
<div class="newsroom-all-messages">
	<h2>{$sLabel}</h2>
	<div class="newsroom-messages">
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
				
				$('.newsroom-messages').append(
					'<div class="newsroom-message" data-url="' + msg.url + '">' +
					'		<div class="newsroom-m-icon">' +
					'			<img src="' + msg.icon + '" alt="Message icon" />' +
					'		</div>' +
					'		<div class="newsroom-m-content">' +
					'			<div class="newsroom-m-title">' + sTitle + '</div>' +
					'			<div class="newsroom-m-text">' + sText + '</div>' +
					'			<div class="newsroom-m-date"><p>' + msg.start_date + '</p></div>' +
					'			' + (msg.url == '' ? '' : '<hr/> <p><a href="' + msg.url + '" target="_BLANK">{$sMoreInfo}</a></p>') +
					'		</div>' +
					'</div>'
				);
				
			});

			
JS
		);
		
		
		
	}

	/**
	 * Gets translation for current user. Mind that variables/placeholders also get replaced here.
	 *
	 * @param \ThirdPartyNewsRoomMessage $oMessage Third party newsroom message
	 *
	 * @return \ThirdPartyNewsRoomMessageTranslation
	 */
	public static function GetTranslationForUser($oMessage) {
		
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
		
		// Replace variables/placeholders
		
			$aContextArgs = [];
			$oUser = UserRights::GetUserObject();
			$oContact = UserRights::GetContactObject();
		
			// Defensive programming. Not sure if there are cases where $oUser or $oContact would be null here.
			if($oUser !== null) {
				$aContextArgs = array_merge($aContextArgs, $oUser->ToArgs('current_user'));
			}
			
			if($oContact !== null) {
				$aContextArgs = array_merge($aContextArgs, $oContact->ToArgs('current_contact'));
			}
			
			// Prepare
			foreach(['title', 'text', 'url'] as $sAttCode) {
				
				$this->Set($sAttCode, MetaModel::ApplyParams($this->Get($sAttCode), $aContextArgs));
			
			}
		
		return $oTranslation;
		
	}
	
	/**
	 * Checks whether current user falls under the target OQL.
	 *
	 * @param \ThirdPartyNewsRoomMessage $oMessage Third party newsroom message
	 * @param \User $oUser Optional user
	 *
	 * @return \Boolean
	 */
	public static function MessageIsApplicable(ThirdPartyNewsRoomMessage $oMessage, User $oUser = null) {
		
		$sOQL = trim($oMessage->Get('oql'));
		$oUser = $oUser ?? UserRights::GetUserObject();
		
		$sOQLRestricted = MetaModel::GetModuleSetting(static::MODULE_CODE, 'oql_target_users', 'SELECT User');
		$oFilterRestricted = DBObjectSearch::FromOQL($sOQLRestricted);
		$sClassRestricted = MetaModel::GetRootClass($oFilterRestricted->GetClass());
		
		if($oFilterRestricted->GetClass() != 'User') {
			throw new Exception('The OQL for "oql_target_users" should return user objects.');
		}
		
		// Don't make it too complicated: just use the "oql" value of ThirdPartyNewsRoomMessage; and add a condition to make sure the user ID is also in the subquery.
		
		// Shortcut: no OQL = everyone
		if($sOQL == '') {
			
			return true;
			
		}
		
		try {
			
			$oFilterUsers = DBObjectSearch::FromOQL($sOQL);
			$sOQLClass = $oFilterUsers->GetClass();
			
			if(MetaModel::GetRootClass($sOQLClass) != 'User') {
				
				// This should never be the case.
				throw new Exception('The OQL for a ThirdPartyNewsRoomMessage should return user objects.');
				
			}
			
			// Add condition ('AND')
			$oFilterUsers->AddCondition('id', $oUser->GetKey(), '=');
			
			$oSetUsers = new DBObjectSet($oFilterUsers);
			
			// Matching user
			return ($oSetUsers->Count() == 1);
			
		}
		catch(Exception $e) {
			
			// Invalid OQL?
			
		}
		
		$sTargetProfiles = $oMessage->Get('target_profiles');
		$sTargetProfiles = preg_replace('/[\s]{1,},[\s]{1,}/', '', $sTargetProfiles);
		$aTargetProfiles = explode(',', $sTargetProfiles);
		$aUserProfiles = UserRights::ListProfiles($oUser);
		$aOverlap = array_intersect($aTargetProfiles, $aUserProfiles);
		
		return ($oMessage->Get('target_profiles') == '' || count($aOverlap) > 0);
		
	}
	

}
