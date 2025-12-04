<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250909
 */
 
@include_once('../approot.inc.php');
@include_once('../../approot.inc.php');
@include_once('../../../approot.inc.php');

require_once(APPROOT.'/application/application.inc.php');
require_once APPROOT.'/application/startup.inc.php';

// This one isn't autoloaded yet.
require_once(APPROOT.'/application/loginwebpage.class.inc.php');

use JeffreyBostoenExtensions\News\{
    eApiVersion,
	Helper,
	Server
};


try {
	
	$oServer = new Server();
	$oServer->ProcessIncomingRequest();
	

}
catch(Exception $oException) {
	
	// Note: Transform to cope with XSS attacks
	echo htmlentities($oException->GetMessage(), ENT_QUOTES, 'utf-8');

	Helper::Trace($oException->GetMessage());
	IssueLog::Error($oException->getMessage() . "\nDebug trace:\n" . $oException->getTraceAsString());

	// Set HTTP response code to 500 Internal Server Error.
	// Newer versions of the news extension will reject the response if HTTP status != 200.
	http_response_code(500); // Internal server error

	// No output?
	
}
