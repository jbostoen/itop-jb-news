<?php

/**
 * @copyright   Copyright (c) 2019-2024 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2.7.240527
 *
 * Definition of NewsRoomProvider.
 */

namespace jb_itop_extensions\NewsProvider;

// iTop internals
use \DBObjectSearch;
use \DBObjectSet;
use \MetaModel;
use \UserRights;
use \utils;

// iTop classes
use \NewsroomProviderBase;
use \User;

// Protection for iTop older than 2.6.0 when the extension is packaged with another.

if(class_exists('NewsroomProviderBase')) {
	
	/**
	 * Class NewsRoomProvider
	 *
	 * Note: This is greatly inspired by the itop-hub-connector module.
	 */
	class NewsRoomProvider extends NewsroomProviderBase {
		
		/**
		 * @inheritDoc
		 */
		public function GetTTL() {
			
			// Update every hour
			return (Int)MetaModel::GetModuleSetting(NewsRoomHelper::MODULE_CODE, 'ttl', 3600);
			
		}

		/**
		 * @inheritDoc
		 */
		public function IsApplicable(User $oUser = null) {
			
			// What's the use case of $oUser being null?
			if($oUser === null) {
				return false;
			}
			
			// The iTop admin can specify a more restrictive query to determine for who the newsroom messages should be enabled.
			$sOQL = MetaModel::GetModuleSetting(NewsRoomHelper::MODULE_CODE, 'oql_target_users', 'SELECT User');
			$oFilterUsers = DBObjectSearch::FromOQL($sOQL);
			$oFilterUsers->AllowAllData();
			if(MetaModel::GetRootClass($oFilterUsers->GetClass()) != 'User') {
				$sOQL = 'SELECT User';
				$oFilterUsers = new DBObjectSearch($sOQL);
			}
			$oFilterUsers->AddCondition('id', $oUser->GetKey(), '=');
			$oSetUsers = new DBObjectSet($oFilterUsers);
			
			
			// @todo review rights here!
			switch(true) {
				
				case (MetaModel::GetModuleSetting(NewsRoomHelper::MODULE_CODE, 'enabled', false) == false): // Not enabled
				case (MetaModel::GetModuleSetting(NewsRoomHelper::MODULE_CODE, 'client', false) == false): // Not acting as a client
				case ($oSetUsers->Count() != 1):
					return false;
					
			}
			
			// All other cases
			return true;

		}

		/**
		 * @inheritDoc
		 */
		public function GetLabel() {
			return 'Jeffrey Bostoen';
		}

		/**
		 * @inheritDoc
		 */
		public function GetMarkAllAsReadURL() {
			return $this->MakeUrl('mark_all_as_read');
		}

		/**
		 * @inheritDoc
		 */
		public function GetFetchURL() {
			return $this->MakeUrl('fetch');
		}

		/**
		 * @inheritDoc
		 */
		public function GetViewAllURL() {
			return $this->MakeUrl('view_all');
		}

		/**
		 * @inheritDoc
		 *
		 * Note: Placeholders are only used in the news URL.
		 */
		public function GetPlaceholders() {
			
			$aPlaceholders = [];

			$oUser = UserRights::GetUserObject();
			if($oUser !== null) {
				$aPlaceholders['%user_login%'] = $oUser->Get('login');
			}

			$oContact = UserRights::GetContactObject();
			if($oContact !== null) {
				$aPlaceholders['%contact_firstname%'] = $oContact->Get('first_name');
				$aPlaceholders['%contact_lastname%'] = $oContact->Get('name');
				$aPlaceholders['%contact_email%'] = $oContact->Get('email');
				$aPlaceholders['%contact_organization%'] = $oContact->Get('org_id_friendlyname');
				$aPlaceholders['%contact_location%'] = $oContact->Get('location_id_friendlyname');
			}
			else {
				$aPlaceholders['%contact_firstname%'] = '';
				$aPlaceholders['%contact_lastname%'] = '';
				$aPlaceholders['%contact_email%'] = '';
				$aPlaceholders['%contact_organization%'] = '';
				$aPlaceholders['%contact_location%'] = '';
			}

			return $aPlaceholders;
		}

		/**
		 * @inheritDoc
		 */
		public function GetPreferencesUrl() {
			return null;
		}

		/**
		 * Returns an URL to the news editor for the $sOperation and current user
		 *
		 * @param string $sOperation
		 *
		 * @return string
		 */
		private function MakeUrl($sOperation) {
			
			return utils::GetAbsoluteUrlExecPage().'?'
				.'&exec_module='.NewsRoomHelper::MODULE_CODE
				.'&exec_page=index.php'
				.'&exec_env='.MetaModel::GetEnvironment()
				.'&operation='.$sOperation
				.'&version=1.0';
		}
		
	}
		
}
