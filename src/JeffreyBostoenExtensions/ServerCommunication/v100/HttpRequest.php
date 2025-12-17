<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.251212
 */

namespace JeffreyBostoenExtensions\ServerCommunication\v100;

use JeffreyBostoenExtensions\News\eOperation;
use JeffreyBostoenExtensions\ServerCommunication\Base\HttpRequest as Base;
use JeffreyBostoenExtensions\ServerCommunication\eCryptographyLibrary;
use JeffreyBostoenExtensions\ServerCommunication\eOperationMode;
use JeffreyBostoenExtensions\ServerCommunication\ServerWorker;
// iTop internals.
use utils;

/**
* Class HttpRequest. A standard HTTP request.
*/
class HttpRequest extends Base {
    
    /**
     * @var string $encryption_library The encryption library of the iTop instance. ('Sodium' or 'none')
     * */
    public string $encryption_library;

    /**
     * @inheritDoc
     * 
     * Sets everything based on the info provided in parameters by the connected client.  
     * Purely for legacy purposes.
     *
     * @return HttpRequestPayload
     */

    public function __construct(?ServerWorker $oWorker = null) {

        parent::__construct($oWorker);

        // Unlike API 1.1.0 onwards, API 1.0.0 does not have a separate payload object.
        $oRequest = new HttpRequest();
        $oRequest->api_version = utils::ReadParam('api_version', '', false, 'raw_data');
        $oRequest->operation = utils::ReadParam('operation', '', false, 'raw_data');
        $oRequest->instance_hash = utils::ReadParam('instance_hash', '', false, 'raw_data');
        $oRequest->instance_hash2 = utils::ReadParam('instance_hash2', '', false, 'raw_data');
        $oRequest->db_uid = utils::ReadParam('db_uid', '', false, 'raw_data');
        $oRequest->env = utils::ReadParam('env', 'production', false, 'raw_data');
        $oRequest->app_name = utils::ReadParam('app_name', '', false, 'raw_data');
        $oRequest->app_version = utils::ReadParam('app_version', '', false, 'raw_data');
        $oRequest->encryption_library = utils::ReadParam('encryption_library', '', false, 'raw_data');
        
        return $oRequest;


    }

    
    /**
     * @inheritDoc
     */
    public function GetCryptoLib() : ?eCryptographyLibrary {

        return eCryptographyLibrary::tryFrom(strtolower($this->encryption_library));

    }

    
    /**
     * @inheritDoc
     */
    public function GetOperationMode(): ?eOperationMode {

        return (isset($_POST['operation']) ? eOperationMode::Cron : eOperationMode::Mitm);

    }


}
