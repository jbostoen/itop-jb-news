<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.251212
 */

namespace JeffreyBostoenExtensions\ServerCommunication;

use JeffreyBostoenExtensions\ServerCommunication\Base\{
	HttpRequest,
	HttpResponse
};

// Generic.
use Exception;
use SodiumException;

// iTop internals.
use DBObjectSearch;
use DBObjectSet;
use KeyValueStore;
use MetaModel;
use stdClass;
use UserRights;

/**
 * Trait ServerWorkerTrait. Adds server worker handling to a class.
 */
trait ServerWorkerTrait {

    /**
     * @var ServerWorker|null The server worker handling this request.
     */
    private ServerWorker|null $oWorker;


	/**
	 * Sets the server worker.
	 *
	 * @param ServerWorker|null $oWorker
	 * @return void
	 */
	public function SetWorker(ServerWorker|null $oWorker) : void {
		$this->oWorker = $oWorker;
	}

	/**
	 * Returns the server worker.
	 *
	 * @return ServerWorker
	 */
	public function GetWorker() : ServerWorker {
		return $this->oWorker;
	}


	/**
	 * Returns the HTTP request (once set).  
	 * A shortcut, for a frequent operation.
	 * 
	 * @return HttpRequest|null
	 */
	public function GetHttpRequest() : HttpRequest|null {
		return $this->oWorker->GetHttpRequest();
	}

	/**
	 * Returns the HTTP response (once set).  
	 * A shortcut, for a frequent operation.
	 *
	 * @return HttpResponse|null
	 */
	public function GetHttpResponse() : HttpResponse|null {
		return $this->oWorker->GetHttpResponse();
	}

	/**
	 * Returns the payload.
	 *
	 * @return stdClass|null
	 */
	public function GetPayload() : stdClass|null {
		return $this->oWorker->GetPayload();
	}

}


/**
 * Enum eApiVersion. Defines the API versions that have been in use so far.
 */
enum eApiVersion : string {

	case v1_0_0 = '1.0'; // Deprecated.
	case v1_1_0 = '1.1.0';
	case v2_0_0 = '2.0.0'; // As of 1st of July, 2025.
	case v2_1_0 = '2.1.0'; // As of 14th of December, 2025.
	
}


/**
 * Enum eCryptographyLibrary. Defines the cryptography libraries supported by this extension.
 */
enum eCryptographyLibrary : string {

	case Sodium = 'sodium';
	case None = 'none';

}


/**
 * Enum eOperation. Defines the operations that can be performed by the extension.
 */
enum eOperation : string {

	case GetMessagesForInstance = 'get_messages_for_instance';
	case ReportReadStatistics = 'report_read_statistics';

}


/**
 * Enum eOperationMode. The operation mode.
 */
enum eOperationMode : string {
	case Cron = 'cron';
	case Mitm = 'mitm';
}


/**
 * Enum eUserOperation. Defines the operations that can be performed by the extension and are requested/triggered by a user.
 */
enum eUserOperation : string {

	case FetchMessages = 'fetch';
	case GetAllMessages = 'get_all_messages';
	case MarkAllAsRead = 'mark_all_as_read';
	case MarkMessageAsRead = 'mark_message_as_read';
	case PostMessagesToInstance = 'post_messages_to_instance';
	case Redirect = 'redirect';
	case ViewAll = 'view_all';

}


/**
 * Enum eToken. Defines the token types.
 */
enum eToken : string {

	case ClientToken = 'client_token';
	case NewClientToken = 'new_client_token';

}


/**
 * Class Helper. Contains a lot of functions to assist in various requests.
 */
abstract class Helper {

	/** @var int CLIENT_TOKEN_BYTES Number of random bytes for the client token. */
	const CLIENT_TOKEN_BYTES = 100;
	
	/** @var string MODULE_CODE The name of this extension. */
	/** @todo Alter this. */
	const MODULE_CODE = 'jb-news';
	
	/** @var string DEFAULT_APP_NAME The default app name. */
	const DEFAULT_APP_NAME = 'Unknown';
	
	/** @var string DEFAULT_APP_VERSION The default app version.*/
	const DEFAULT_APP_VERSION = 'Unknown';

	
	/** @var string|null $sTraceId Unique ID of this run. */
	private static $sTraceId = null;

    
	/**
	 * Returns the trace ID.
	 *
	 * @return string
	 */
	public static function GetTraceId() : string {

		if(static::$sTraceId == null) {
				
			static::$sTraceId = bin2hex(random_bytes(10));
			
		}

		return static::$sTraceId;

	}

	
	/**
	 * Trace function used for debugging.
	 *
	 * @param string $sMessage The message.
	 * @param mixed ...$args
	 *
	 * @return string
	 */
	public static function Trace($sMessage, ...$args) : string {

		$sMessage = call_user_func_array('sprintf', func_get_args());

		// Store somewhere?		
		if(MetaModel::GetModuleSetting(static::MODULE_CODE, 'trace_log', false) == true) {
			
			$sTraceFileName = sprintf(APPROOT.'/log/trace_servercommunication_%1$s.log', date('Ymd'));

			try {
				
				
				
				// Not looking to create an error here 
				file_put_contents($sTraceFileName, sprintf('%1$s | %2$s | %3$s'.PHP_EOL,
					date('Y-m-d H:i:s'),
					static::GetTraceId(),
					$sMessage,
				), FILE_APPEND | LOCK_EX);

			}
			catch(Exception $e) {
				// Don't do anything
			}
			
		}

		return $sMessage;

    }


	/**
	 * Returns hash (fnv1a64) of user.
	 */
	public static function GetUserHash() {
	
		$sUserId = UserRights::GetUserId();
		$sUserHash = hash('fnv1a64', $sUserId);
		return $sUserHash;
		
	}

	/**
	 * Returns UID of instance.
	 * 
	 * @return string
	 */
	public static function GetInstanceUID() {
	
		return (string) trim(@file_get_contents(APPROOT . 'data/instance.txt'), "{} \n");
		
	}

	/**
	 * Returns a hash (sha256) of the instance. (based on data/instance.txt).  
	 * 
	 * This identifier should remain stable, but can be shared across different iTop environments.   
	 * 
	 * In the wild, it has also been seen that this file is copied for another deployment.
	 * For example, certain organizations may end up having a "test" environment that is a copy of their "production".
	 * It has also occurred that this file gets modified or deleted.
	 */
	public static function GetInstanceHash() {
	
		$sUid = static::GetInstanceUID();
		$sInstanceId = hash('sha256', $sUid);
		
		return $sInstanceId;
		
	}
	
	/**
	 * Returns a hash (fnv1a64) of the instance. (based on data/instance.txt).  
	 * 
	 * This identifier should remain stable, but can be shared across different iTop environments.   
	 * 
	 * In the wild, it has also been seen that this file is copied for another deployment.
	 * For example, certain organizations may end up having a "test" environment that is a copy of their "production".
	 * It has also occurred that this file gets modified or deleted.
	 */
	public static function GetInstanceHash2() {
	
		// Note: not retrieving DB UUID for now as it is not of any use for now.
		$sITopUUID = (string) trim(@file_get_contents(APPROOT . 'data/instance.txt'), "{} \n");
		$sInstanceId = hash('fnv1a64', $sITopUUID);
		
		return $sInstanceId;
	
	
	}
	
	/**
	 * Returns UID of database.
	 * 
	 * This identifier should remain stable.
	 * In the wild, it has also been seen that a database gets restored (copied). 
	 * For example, certain organizations may end up having a "test" environment that is a copy of their "production".
	 */
	public static function GetDatabaseUID() {
		
		$oFilter = DBObjectSearch::FromOQL_AllData('SELECT DBProperty WHERE name = "database_uuid"');
		$oSet = new DBObjectSet($oFilter);
		$oDBProperty = $oSet->Fetch();
		
		if($oDBProperty !== null) {
			
			return $oDBProperty->Get('value');
			
		}
		
		return '';
		
	}

	/**
	 * Gets the preferred cryptography library.  
	 * 
	 * The idea is to support more cryptography libraries in the future, 
	 * and prefer the one specified by the iTop administrator if supported.
	 *
	 * @return eCryptographyLibrary Sodium, none
	 */
	public static function GetCryptographyLibrary() : eCryptographyLibrary {
		
		// Check if the cryptography library is set in the iTop configuration.
		$sPreferredCryptoLib = strtolower(MetaModel::GetConfig()->GetEncryptionLibrary());
		$eCryptographyLib = eCryptographyLibrary::tryFrom(strtolower($sPreferredCryptoLib));

		// If by now there is no cryptography library, check if Sodium is available as a fallback.
		// For security reasons, encrypted communication with external servers is heavily recommended.
		if($eCryptographyLib === null || $eCryptographyLib == eCryptographyLibrary::None) {
		
			$eCryptographyLib = function_exists('sodium_crypto_box_keypair') ? eCryptographyLibrary::Sodium : eCryptographyLibrary::None;
		
		}
		
		return $eCryptographyLib;
		
	}


	/**
	 * Gets a KeyValueStore object in this namespace.
	 *
	 * @param string $sKeyName
	 * @return KeyValueStore|null
	 */
	public static function GetKeyValueStore(string $sKeyName) : KeyValueStore|null {

		$oFilter = DBObjectSearch::FromOQL_AllData('SELECT KeyValueStore WHERE key_name = :key_name AND namespace = :namespace', [
			'namespace' => Helper::MODULE_CODE,
			'key_name' => $sKeyName
		]);
		$oSet = new DBObjectSet($oFilter);
		$oKeyValue = $oSet->Fetch();
		return $oKeyValue;

	}


	/**
	 * Returns all classes that implement the specified interface.
	 *
	 * @param string $sInterface
	 * @return string[]
	 */
	public static function GetImplementations(string $sInterface) : array {

		$aResult = [];

		foreach (get_declared_classes() as $sClassName) {
			
			$aImplementations = class_implements($sClassName);

			if (in_array($sInterface, $aImplementations)) {
				$aResult[] = $sClassName;
			}

		}

		return $aResult;
	}

	
	/**
	 * Returns Sodium private key.
	 *
	 * @param eCryptographyKeyType $eKeyType Should be one of these: private_key_file_crypto_sign , private_key_file_crypto_box
	 *
	 * @return string
	 *
	 * @throws Exception
	 */
	public static function GetKeySodium(eCryptographyKeyType $eKeyType) : string {
		
		$sKeyType = $eKeyType->value;

		// Get private key.
		$aKeySettings = MetaModel::GetModuleSetting(Helper::MODULE_CODE, 'sodium', []);
		
		if(is_array($aKeySettings) == false || array_key_exists($sKeyType, $aKeySettings) == false) {
			
			Helper::Trace('Missing %1$s key settings.', $sKeyType);
			throw new Exception('Missing '.$sKeyType.' key settings.');

		}
		
		$sKeyFile = $aKeySettings[$sKeyType];
			
		if(file_exists($sKeyFile) == false) {
			
			Helper::Trace('Missing %1$s key file.', $sKeyType);
			throw new Exception('Missing '.$sKeyType.' key file.');
		
		}
			
		$sKey = file_get_contents($sKeyFile);
		
		try {

			$sBinKey = sodium_base642bin($sKey, SODIUM_BASE64_VARIANT_URLSAFE);

		}
		catch(SodiumException $e) {

			Helper::Trace('Failed to use %1$s key to convert base64 to binary key: %2$s', $sKeyType, $e->getMessage());

		}

		return $sBinKey;
		
	}


	/**
	 * Returns the real user IP.
	 * 
	 * Note: This is not used in the base server communication extension.  
	 * However, as it is a common need, it is included here.
	 *
	 * @return string IP address.
	 *
	 */
	public static function GetClientIP() : string {

		$sClientIP = $_SERVER['HTTP_CLIENT_IP']
			?? $_SERVER['HTTP_CF_CONNECTING_IP'] // CloudFlare.
			?? $_SERVER['HTTP_X_FORWARDED']
			?? $_SERVER['HTTP_X_FORWARDED_FOR']
			?? $_SERVER['HTTP_FORWARDED']
			?? $_SERVER['HTTP_FORWARDED_FOR']
			?? $_SERVER['REMOTE_ADDR']
			?? '0.0.0.0';

		// HTTP_X_FORWARDED_FOR sometimes lists multiple IP addresses, comma-separated
		$aAddresses = explode(',', $sClientIP);
		return trim($aAddresses[0]);

	}

}
