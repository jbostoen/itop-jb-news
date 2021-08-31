<?php
/**
 * Copyright (c) 2015 - 2019 Molkobain.
 *
 * This file is part of licensed extension.
 *
 * Use of this extension is bound by the license you purchased. A license grants you a non-exclusive and non-transferable right to use and incorporate the item in your personal or commercial projects. There are several licenses available (see https://www.molkobain.com/usage-licenses/ for more informations)
 */

namespace jb_itop_extensions\NewsRoomProvider;

// iTop classes
use \NewsroomProviderBase;
use \User;
use \UserRights;
use \utils;

// Protection for iTop older than 2.6.0 when the extension is packaged with another.
if(class_exists('NewsroomProviderBase')) {
	/**
	 * Class MolkobainNewsroomProvider
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
			if($oUser !== null)
			{
				$aPlaceholders['%user_login%'] = $oUser->Get('login');
				$aPlaceholders['%user_hash%'] = self::GetUserHash();
			}

			$oContact = UserRights::GetContactObject();
			if($oContact !== null)
			{
				$aPlaceholders['%contact_firstname%'] = $oContact->Get('first_name');
				$aPlaceholders['%contact_lastname%'] = $oContact->Get('name');
				$aPlaceholders['%contact_email%'] = $oContact->Get('email');
				$aPlaceholders['%contact_organization%'] = $oContact->Get('org_id_friendlyname');
				$aPlaceholders['%contact_location%'] = $oContact->Get('location_id_friendlyname');
			}
			else
			{
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
			
			$sApp = defined('ITOP_APPLICATION') ? ITOP_APPLICATION : 'unknown';
			$sVersion = defined('ITOP_VERSION') ? ITOP_VERSION : 'unknown';
			
			return 'http://127.0.0.1:8181/test-newsroom/news.php?'
				.'&operation='.$sOperation
				.'&version=1.0'
				.'&user='.urlencode(self::GetUserHash())
				.'&instance='.urlencode(self::GetInstanceHash())
				.'&app-name='.urlencode($sApp)
				.'&app-version='.urlencode($sVersion);
		}
		
		/**
		 * Returns hash of user
		 */
		private function GetUserHash() {
		
			$sUserId = UserRights::GetUserId();
			$sUserHash = hash('fnv1a64', $sUserId);
			
		}
		
		/**
		 * Returns hash of instance
		 */
		private function GetInstanceHash() {
		
			// Note: not retrieving DB UUID for now as it is not of any use for now.
			$sITopUUID = (string) trim(@file_get_contents(APPROOT . 'data/instance.txt'), "{} \n");

			// Prepare a unique hash to identify users and instances across all iTops in order to be able for them 
			// to tell which news they have already read.
			$sInstanceId = hash('fnv1a64', $sITopUUID);
			
		}
	}
}
