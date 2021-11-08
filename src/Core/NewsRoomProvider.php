<?php

/**
 * @copyright   Copyright (C) 2019-2020 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2020-11-04 15:45:48
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
	 * Class JBNewsroomProvider
	 *
	 * Note: This is greatly inspired by the itop-hub-connector module.
	 */
	class JBNewsroomProvider extends NewsroomProviderBase {
		
		/**
		 * @inheritDoc
		 */
		public function GetTTL() {
			// Update every hour
			return 60 * 60;
		}

		/**
		 * @inheritDoc
		 */
		public function IsApplicable(User $oUser = null) {
			
			// @todo review rights here!
			if(utils::GetCurrentModuleSetting('enabled', false) == false) {
				return false;
			}
			elseif($oUser !== null) {
				return UserRights::IsAdministrator($oUser);
			}
			else {
				return false;
			}

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
		 * Note: Placeholders are only used in the news' URL
		 */
		public function GetPlaceholders() {
			$aPlaceholders = array();

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
