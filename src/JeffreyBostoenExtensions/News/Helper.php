<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.251212
 */

namespace JeffreyBostoenExtensions\News;

use JeffreyBostoenExtensions\ServerCommunication\Page;

// Generic.
use Exception;

// iTop internals.
use DBObjectSearch;
use DBObjectSet;
use Dict;
use MetaModel;
use ormDocument;
use User;
use UserRights;
use utils;

// iTop classes.
use ThirdPartyNewsMessage;
use ThirdPartyNewsMessageTranslation;

/**
 * Class Helper. Contains a lot of functions to assist in various requests.
 */
abstract class Helper {
	
	/** @var string MODULE_CODE The name of this extension. */
	/** @todo Alter this. */
	const MODULE_CODE = 'jb-news';
	

	/**
	 * Returns all messages that have been published so far where the current user is a target.  
	 * Messages that are planned for publication in the future, will be excluded.
	 *
	 * @return DBObjectSet Filtered set of messages
	 *
	 * @throws CoreException
	 * @throws CoreUnexpectedValue
	 * @throws MySQLException
	 * @throws OQLException
	 */
	public static function GetAllMessagesForUser() : DBObjectSet {
		
		// Query the newsroom messages.
		// Only return the ones that where the start_date is in the past,
		// and the end_date is not set or in the future.
		// By default, a regular user would not have access to these objects, so make sure all data is allowed.
		$oSearch = DBObjectSearch::FromOQL_AllData('
			SELECT ThirdPartyNewsMessage 
			WHERE 
				start_date <= NOW() AND 
				(
					ISNULL(end_date) = 1 OR
					end_date >= NOW()
				)
		');

		// Sort by priority and start_date (most recent first).
		$oSet = new DBObjectSet($oSearch, [
			'priority' => true, // Lowest number = highest priority, so list messages ascending.
			'start_date' => false, // Most recent publication first.
		]);
		
		$oFilteredSet = DBObjectSet::FromScratch('ThirdPartyNewsMessage');
		
		/** @var ThirdPartyNewsMessage $oMessage */
		while($oMessage = $oSet->Fetch()) {
		
			if(static::MessageIsApplicable($oMessage) == true) {
				$oFilteredSet->AddObject($oMessage);
			}
			
		}
		
		return $oFilteredSet;
		
	}


	/**
	 * Returns an array of ThirdPartyNewsMessage data, prepared for the webservice.
	 *
	 * @return array
	 *
	 * @throws CoreException
	 * @throws CoreUnexpectedValue
	 * @throws MySQLException
	 * @throws OQLException
	 * @throws Exception
	 */
	public static function GetUnreadMessagesForUser() : array {
		
		$oUser = UserRights::GetUserObject();

		// A regular user might be missing privileges to query the data, so allow all data.
		$oSearch = DBObjectSearch::FromOQL_AllData('
			SELECT ThirdPartyNewsMessage AS M 
			WHERE 
				M.id NOT IN (
					SELECT ThirdPartyNewsMessage AS M2 
					JOIN ThirdPartyMessageUserStatus AS UserStatus ON UserStatus.message_id = M2.id 
					WHERE 
						UserStatus.user_id = :user_id
				) 
				AND M.start_date <= NOW() 
				AND (
					ISNULL(M.end_date) = 1 OR 
					M.end_date >= NOW()
				)
		', [
			'user_id' => $oUser->GetKey()
		]);
		$oSet = new DBObjectSet($oSearch);

		// Limit messages count to avoid server crash (inspired by Combodo, not sure if it's necessary).
		// Either way, a user is unlikely to scroll through that many new messages with great interest anyway.
		$oSet->SetLimit(50);

		$aMessages = [];
		$aApplicableMessageIds = [];
		
		/** @var ThirdPartyNewsMessage $oMessage */
		while($oMessage = $oSet->Fetch()) {
			
			if(static::MessageIsApplicable($oMessage) == false) {	
				// Current user should not see this message
				continue;
			}
			
			$sIconUrl = static::GetIconUrl($oMessage);

			// Prepare URL redirection.
			// When a user clicks this message in the (limited) newsroom, 
			// they will be redirected to a full page showing this specific message.
			$sUrl = utils::GetAbsoluteUrlExecPage().'?'.
				'&exec_module='.static::MODULE_CODE.
				'&exec_page=index.php'.
				'&operation=redirect'.
				'&message_id='.$oMessage->GetKey().
				'&user='.$oUser->GetKey();

			$oTranslation = static::GetTranslationForUser($oMessage);
			
			if($oTranslation !== null) {

				$aMessages[] = array(
					'id' => $oMessage->GetKey(),
					// Prepare header (MarkDown) and add regular text/description.
					'text' => '# '.$oTranslation->Get('title').PHP_EOL.PHP_EOL.$oTranslation->Get('text'),
					// Mind that this is used to show the messages in the newsroom. Routing is applied to take care of "read date" before redirecting.
					'url' => $sUrl,
					'start_date' => $oMessage->Get('start_date'),
					'priority' => $oMessage->Get('priority'),
					'image' => $sIconUrl,
				);
				
			}

			$aApplicableMessageIds[$oMessage->GetKey()] = true;
			
		}

		// The fact that this method is executed,
		// implies the message will be shown to the user.

			if(count($aApplicableMessageIds) > 0) {
				
				$oFilter = DBObjectSearch::FromOQL_AllData('
					SELECT ThirdPartyMessageUserStatus AS UserStatus 
					WHERE 
						UserStatus.user_id = :user_id AND 
						UserStatus.message_id IN (:message_ids)
				');
				$oSet = new DBObjectSet($oFilter, [], [
					'user_id' => $oUser->GetKey(),
					'message_ids' => array_keys($aApplicableMessageIds)
				]);

				// - Update the "last shown" date for all messages that are applicable for the user.

					while($oMessageStatus = $oSet->Fetch()) {
						
						// Update the "last shown" date.
						$oMessageStatus->Set('last_shown_date', date('Y-m-d H:i:s'));
						$oMessageStatus->DBUpdate();

						unset($aApplicableMessageIds[$oMessageStatus->Get('message_id')]);
						
					}

				// - For the messages that weren't seen: Create a new status object.

					foreach(array_keys($aApplicableMessageIds) as $iMessageId) {

						$oMessageStatus = MetaModel::NewObject('ThirdPartyMessageUserStatus', [
							'message_id' => $iMessageId,
							'user_id' => $oUser->GetKey(),
							'first_shown_date' => date('Y-m-d H:i:s'),
							'last_shown_date' => date('Y-m-d H:i:s')
						]);
						$oMessageStatus->DBInsert();

					}

			}

		return $aMessages;
	}


	/**
	 * Marks messages as read for the current user.
	 *
	 * @return int The number of messages that has been successfully marked as read.
	 *
	 * @throws CoreException
	 * @throws CoreUnexpectedValue
	 * @throws DeleteException
	 * @throws MySQLException
	 * @throws OQLException
	 */
	public static function MarkAllMessagesAsReadForUser() : int {

		// @todo Review this. "Mark all" : Really mark all, or just what was shown?

		// - Note: Something weird is going on; but the below OQL can not be a subquery in the next OQL?
			$oFilter = DBObjectSearch::FromOQL_AllData('
			
					SELECT ThirdPartyNewsMessage AS M2 
					JOIN ThirdPartyMessageUserStatus AS UserStatus ON UserStatus.message_id = M2.id 
					WHERE 
						UserStatus.user_id = :user_id
			
			');
			$oSet = new DBObjectSet($oFilter, [], [
				'user_id' => UserRights::GetUserId()
			]);
			$aExcludedIds = [ -1 ];
			while($oMessage = $oSet->Fetch()) {
				$aExcludedIds[] = $oMessage->GetKey();
			}
		
		// Find the messages that were not yet marked as read.
		// Mind that a regular user by default has no access, so make sure to allow all data.
			$oFilter = DBObjectSearch::FromOQL_AllData('
				SELECT ThirdPartyNewsMessage AS M 
				WHERE 
					M.id NOT IN (:excluded_ids)
					AND M.start_date <= NOW() 
					AND (
						ISNULL(M.end_date)  OR 
						M.end_date >= NOW()
					)
			');

			$oSetMessages = new DBObjectSet($oFilter, [], [
				'excluded_ids' => $aExcludedIds
			]);

		$iCount = 0;
		
		Helper::Trace('Mark %1$s messages (previously unread) as read for user ID "%2$s".', $oSetMessages->Count(), UserRights::GetUserId());

		/** @var ThirdPartyNewsMessage $oMessage */
		while($oMessage = $oSetMessages->Fetch()) {
			
			try {
				
				$oUserStatus = MetaModel::NewObject('ThirdPartyMessageUserStatus', [
					'message_id' => $oMessage->GetKey(),
					'user_id' => UserRights::GetUserId(),
					'read_date' => date('Y-m-d H:i:s')
				]);
			
				$oUserStatus->DBInsert();
			
				$iCount += 1;
				
			}
			catch(Exception $e) {
				Helper::Trace($e->getMessage());
			}
			
		}

		Helper::Trace('Marked.');

		return $iCount;
	}


	/**
	 * Marks the specified message as read for the current user.
	 *
	 * @param int $iMessageId The internal ID (iTop) of the message.
	 *
	 * @return bool True if the message was successfully marked as read, false otherwise (e.g. already marked as read before).
	 *
	 * @return bool
	 * @throws CoreException
	 * @throws CoreUnexpectedValue
	 * @throws DeleteException
	 * @throws MySQLException
	 * @throws OQLException
	 */
	public static function MarkMessageAsReadForUser(int $iMessageId) : bool {

		try {
			
			/** @var ThirdPartyMessageUserStatus $oUserStatus */
			$oUserStatus = MetaModel::NewObject('ThirdPartyMessageUserStatus', [
				'message_id' => $iMessageId,
				'user_id' => UserRights::GetUserId(),
				'read_date' => date('Y-m-d H:i:s')
			]);
		
			$oUserStatus->DBInsert();
		
			return true;
			
		}
		catch(Exception $e) {
			// Probably too late / duplicate
		}
		
		return false;
		
	}


	/**
	 * Makes a newsroom web page displaying all messages that are relevent for the current user.
	 *
	 * @param Page $oPage
	 * 
	 * @return void
	 *
	 * @throws CoreException
	 * @throws CoreUnexpectedValue
	 * @throws MySQLException
	 * @throws OQLException
	 * @throws Exception
	 */
	public static function MakeAllMessagesPage(Page $oPage) : void {
		

		// Fetch messages.
		$aJsonMessages = [];
		$oSetMessages = static::GetAllMessagesForUser();
		
		/** @var ThirdPartyNewsMessage $oMessage */
		while($oMessage = $oSetMessages->Fetch()) {
			
			$sIconUrl = static::GetIconUrl($oMessage);
			$oTranslation = static::GetTranslationForUser($oMessage);

			if($oTranslation !== null) {
				
				$aJsonMessages[] = [
					'start_date' => $oMessage->Get('start_date'),
					'title' => $oTranslation->Get('title'),
					'text' => $oTranslation->Get('text'),
					'priority' => $oMessage->Get('priority'),
					'url' => $oTranslation->Get('url'), // Leave this URL intact, it's shown in an overview. (@todo Check the purpose and improve documentation).
					'icon' => $sIconUrl
				];
			
			}
			
		}

		// Add style.
		$oPage->add_saas('env-'.utils::GetCurrentEnvironment().'/'.static::MODULE_CODE.'/css/default.scss');
		$sLabel = Dict::S('UI:News:AllMessages');
		$sMoreInfo = Dict::S('UI:News:MoreInfo');
		
		// Add libraries.
		$oPage->LinkScriptFromAppRoot('js/jquery.min.js');
		$oPage->LinkScriptFromAppRoot('js/showdown.min.js');

		// Some day, this should be changed to only show a specific's new source name and logo.
		$sSourceClass = SourceJeffreyBostoen::class;
		$sSourceName = $sSourceClass::GetThirdPartyName();
		$sSourceLogoSvg = $sSourceClass::GetLogoSVG();

		// Build markup.
		$oPage->add(<<<HTML

<div class="header">
	<div class="header-container">
		<div class="header-logo">
			{$sSourceLogoSvg}
		</div>
		<div class="header-name">
			<h1>{$sSourceName}</h1>
		</div>
	</div>
</div>

<div class="newsroom-all-messages">
	<h2>{$sLabel}</h2>
	<div class="newsroom-messages">
	</div>
</div>

HTML
		);
		
		$sJsonMessages = json_encode($aJsonMessages, JSON_PRETTY_PRINT);

		
		$oPage->add_ready_script(
<<<JS

			let oShownDownConverter = new showdown.Converter();
			const aThirdPartyNewsMessages = {$sJsonMessages}
			
			$.each(aThirdPartyNewsMessages, function(i) {
				
				const msg = aThirdPartyNewsMessages[i];
				const sTitle = oShownDownConverter.makeHtml(msg.title);
				const sText = oShownDownConverter.makeHtml(msg.text);

				const sUrl = msg.url ? `<hr/> <p><a href="\${msg.url}" target="_blank">{$sMoreInfo}</a></p>` : '';
				
				$('.newsroom-messages').append(`
					<div class="newsroom-message" data-url="\${msg.url}" data-priority="\${msg.priority}">
							<div class="newsroom-m-icon">
								<img src="\${msg.icon}" alt="Message icon" />
							</div>
							<div class="newsroom-m-content">
								<div class="newsroom-m-title">\${sTitle}</div>
								<div class="newsroom-m-text">\${sText}</div>
								<div class="newsroom-m-date"><p>\${msg.start_date}</p></div>
								\${sUrl}
							</div>
					</div>`
				);
				
			});

			
JS
		);
		
		
	}

	/**
	 * Gets translation for current user.  
	 * This method also takes care of replacing variables/placeholders.
	 * 
	 * It will fall back to the English version if no translation is available for the user language.  
	 * If English isn't found either, it will return the first available translation.
	 * 
	 * @param ThirdPartyNewsMessage $oMessage Third party newsroom message.
	 *
	 * @return ThirdPartyNewsMessageTranslation|null A translation.
	 */
	public static function GetTranslationForUser(ThirdPartyNewsMessage $oMessage) : ThirdPartyNewsMessageTranslation|null {
		
		/** @var ormLinkSet $oSetTranslations */
		$oSetTranslations = $oMessage->Get('translations_list');
		
		/**
		 * @var ThirdPartyNewsMessageTranslation $oTranslation Third Party Newsroom Message Translation
		 */
		$oTranslation = null;
		
		/** @var ThirdPartyNewsMessageTranslation $oCurrentTranslation */
		while($oCurrentTranslation = $oSetTranslations->Fetch()) {
			
			switch(true) {
				
				// Matches user language.
				case ($oCurrentTranslation->Get('language') == UserRights::GetUserLanguage()):
					$oTranslation = $oCurrentTranslation;
				
					// No need to continue searching.
					break 2;
					
				// Text is empty, but English string has been found.
				case ($oCurrentTranslation->Get('language') == 'EN US'):
				
				// Take first available language if English or user language hasn't been found yet.
				case $oTranslation == null:
					$oTranslation = $oCurrentTranslation;
					break;
					
			}
			
		}
		

		// - Finalize by filling in the placeholders.

			if($oTranslation !== null) {

				// Now that above a translation has been selected: 
				// Replace variables/placeholders (such as the default ones for current contact, current user).
				// Do this for each attribute.
				foreach(['title', 'text', 'url'] as $sAttCode) {
					
					$oTranslation->Set($sAttCode, MetaModel::ApplyParams($oTranslation->Get($sAttCode), []));
				
				}

			}
			else {

				Helper::Trace('Unable to find translation for message ID %1$s.', $oMessage->GetKey());

			}
		
		return $oTranslation;
		
	}
	
	/**
	 * Checks whether the current user falls under the target OQLs to see this message.
	 * 
	 * Filter 1:  
	 * The iTop administrators can set the OQL for the target users in the module settings.  
	 * This determines which users can even see messages at all.
	 * 
	 * Filter 2:   
	 * The news provider can set an OQL per message to target specific users.
	 * 
	 *
	 * @param ThirdPartyNewsMessage $oMessage Third party newsroom message.
	 *
	 * @return bool True if the message is applicable for the current user, false otherwise.
	 */
	public static function MessageIsApplicable(ThirdPartyNewsMessage $oMessage) : bool {

		$sOQL = trim($oMessage->Get('oql'));
		$oUser = UserRights::GetUserObject();

		if($oUser === null) {

			static::Trace('User is null. Cannot check message applicability.');

			// Fail gracefully.
			return false;

		}

		static::Trace('Check if user ID %1$s is allowed to see message ID %2$s.', $oUser->GetKey(), $oMessage->GetKey());
		
		// - Restriction by iTop administrator.
			
			// The OQL for the target users is stored in the module settings.
			// This is how an iTop administrator determines which users can even see messages at all.
			$bIsTargetedUser = static::IsTargetedUser($oUser);

			if(!$bIsTargetedUser) {

				// The current user is not allowed to see any messages.
				static::Trace('The iTop administrator has not allowed the user ID "%s" to see any messages.', $oUser->GetKey());
				return false;

			}
		
		// - Restriction by the news provider (per message).
		
			// Don't make it too complicated: just use the "oql" value of ThirdPartyNewsMessage; and add a condition to make sure the user ID is also in the subquery.
			
			// Shortcut: no OQL = everyone
			if($sOQL == '') {
				
				static::Trace('This message has no OQL set, so it is visible to all users.');
				return true;
				
			}
			
			try {
				
				$oFilterUsers = DBObjectSearch::FromOQL_AllData($sOQL);
				$sOQLClass = $oFilterUsers->GetClass();
				
				if(MetaModel::GetRootClass($sOQLClass) != 'User') {
					
					// News providers should ensure the OQL query returns user objects.
					static::Trace('The OQL for a ThirdPartyNewsMessage must return user objects. Specified OQL: %1$s', $sOQL);
					return false;
					
				}
				
				// Add condition to check if the current user is in the result set.
				$oFilterUsers->AddCondition('id', $oUser->GetKey(), '=');
				$oSetUsers = new DBObjectSet($oFilterUsers);
				
				// If the user belongs to the set, the message should be visible.
				$bVisible = ($oSetUsers->Count() == 1);

				static::Trace('User in message-specific OQL target group: %1$s', $bVisible ? 'yes' : 'no');
				return $bVisible;
				
			}
			catch(Exception $e) {
				
				static::Trace('The OQL for a ThirdPartyNewsMessage must be valid. Specified OQL: %1$s,', $sOQL);
				return false;
				
			}

		// Defensive programming.
			return false;
		
	}
	

	/**
	 * Returns the icon URL for the specified message.
	 *
	 * @param ThirdPartyNewsMessage $oMessage
	 * @return string
	 */
	public static function GetIconUrl(ThirdPartyNewsMessage $oMessage) : string { 
		
		$sAttCodeIcon = 'icon';

		/** @var ormDocument $oIcon */
		$oIcon = $oMessage->Get($sAttCodeIcon);

		if(is_object($oIcon) && !$oIcon->IsEmpty()) {

			// The message comes with a specific icon.
			// @todo Check if a non-privileged user can access this icon.
			return $oIcon->GetDisplayURL('ThirdPartyNewsMessage', $oMessage->GetKey(), $sAttCodeIcon);

		}
		else {

			// Fallback to default icon.
			return MetaModel::GetAttributeDef('ThirdPartyNewsMessage', $sAttCodeIcon)->Get('default_image');

		}

	}


	/**
	 * Checks whether the specified user is allowed to see messages.  
	 * This is determined by the iTop administrator.
	 * 
	 * Note that this does not check whether the user is allowed to see a specific message.
	 *
	 * @param User $oUser
	 * @return bool
	 */
	public static function IsTargetedUser(User $oUser) : bool {
		
		try {
				
			$sOQL = MetaModel::GetModuleSetting(Helper::MODULE_CODE, 'oql_target_users', 'SELECT User');
			$oFilterUsers = DBObjectSearch::FromOQL_AllData($sOQL);
			$oFilterUsers->AllowAllData();

			// Enforce: If the admin specified an invalid OQL query, every user can see messages.
			if(MetaModel::GetRootClass($oFilterUsers->GetClass()) != 'User') {
				throw new Exception('Invalid OQL filter, wrong object class.');
			}

			$oFilterUsers->AddCondition('id', $oUser->GetKey(), '=');
			$oSetUsers = new DBObjectSet($oFilterUsers);

		}
		catch(Exception $e) {

			// Could occur when the admin specified an invalid OQL query.
			$oFilterUsers = DBObjectSearch::FromOQL_AllData('SELECT User');
			$oFilterUsers->AddCondition('id', $oUser->GetKey(), '=');
			$oSetUsers = new DBObjectSet($oFilterUsers);

		}

		return ($oSetUsers->Count() == 1);

	}

}
