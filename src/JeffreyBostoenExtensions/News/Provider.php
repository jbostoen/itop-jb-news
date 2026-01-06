<?php

/**
 * @copyright   Copyright (c) 2019-2026 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.260106
 */

namespace JeffreyBostoenExtensions\News;

// iTop internals.
use MetaModel;
use NewsroomProviderBase;
use UserRights;
use utils;

// iTop classes.
use User;

/**
 * Class Provider
 *
 * Note: Inspired by the itop-hub-connector module.
 */
class Provider extends NewsroomProviderBase {
	
	/**
	 * @inheritDoc
	 */
	public function GetTTL() {
		
		// Update every hour
		return (Int)MetaModel::GetModuleSetting(Helper::MODULE_CODE, 'ttl', 3600);
		
	}

	/**
	 * @inheritDoc
	 */
	public function IsApplicable(?User $oUser = null) {

		// The parent class allows 'null' to be provided as an argument to this method.
		// If there is no user, there is no point for this extension.
		if($oUser === null) {
			return false;
		}
		
		$bTargetedUser = Helper::IsTargetedUser($oUser);
		
		
		// The provider is only applicable if the module is enabled, the user is targeted, and the extension is acting as a client.
		switch(true) {
			
			case (MetaModel::GetModuleSetting(Helper::MODULE_CODE, 'enabled', false) == false): // Not enabled.
			case (MetaModel::GetModuleSetting(Helper::MODULE_CODE, 'client', false) == false): // Not acting as a client.
			case $bTargetedUser == false:
				return false;
				
		}
		
		// All other cases:
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

		return $this->MakeUrl(eUserOperation::MarkAllAsRead);

	}

	/**
	 * @inheritDoc
	 */
	public function GetFetchURL() {

		return $this->MakeUrl(eUserOperation::FetchMessages);

	}

	/**
	 * @inheritDoc
	 */
	public function GetViewAllURL() {

		return $this->MakeUrl(eUserOperation::ViewAll);
		
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
	 * Returns an URL to the news editor for the requested $sOperation.
	 *
	 * @param eUserOperation $eOperation
	 *
	 * @return string
	 */
	private function MakeUrl($eOperation) {
		
		return utils::GetAbsoluteUrlExecPage().'?'
			.'&exec_module='.Helper::MODULE_CODE
			.'&exec_page=index.php'
			.'&exec_env='.MetaModel::GetEnvironment()
			.'&operation='.$eOperation->value
			.'&version=1.0';
	}
	
}
