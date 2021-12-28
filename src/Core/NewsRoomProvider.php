<?php

/**
 * @copyright   Copyright (c) 2019-2021 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2.7.211212
 *
 * Definition of NewsRoomProvider.
 */

namespace jb_itop_extensions\NewsClient;

// iTop internals
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
			return (Int)utils::GetCurrentModuleSetting('ttl', 3600);
		}

		/**
		 * @inheritDoc
		 */
		public function IsApplicable(User $oUser = null) {
			
			// @todo review rights here!
			switch(true) {
				case (utils::GetCurrentModuleSetting('enabled', false) == false): // Not enabled
				case (utils::GetCurrentModuleSetting('client', false) == false): // Not acting as a client
				case ($oUser === null): // No user (not sure when this happens)
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
		 * Note: Placeholders are only used in the news URL
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
				.'&exec_module='.utils::GetCurrentModuleName()
				.'&exec_page=index.php'
				.'&exec_env='.MetaModel::GetEnvironment()
				.'&operation='.$sOperation
				.'&version=1.0';
		}
		
	}
		
}
