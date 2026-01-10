<?php

/**
 * @copyright   Copyright (c) 2019-2026 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.260110
 */

namespace JeffreyBostoenExtensions\News\Base;

use JeffreyBostoenExtensions\ServerCommunication\Helper;

// iTop internals.
use MetaModel;

// iTop classes.
use ThirdPartyNewsMessage;

/**
 * Class Icon. Represents an icon.
 */
class Icon {

    /**
     * @var string $data The icon data (base64 encoded).
     */
    public string $data;

    /**
     * @var string $mimetype The icon mime type.
     */
    public string $mimetype;

    /**
     * @var string $filename The icon file name.
     * */
    public string $filename;

    /**
     * Returns an Icon object (or null) from a ThirdPartyNewsMessage object.
     *
     * @param ThirdPartyNewsMessage $oMsg
     * @return Icon|null
     */
    public static function FromThirdPartyNewsMessage(ThirdPartyNewsMessage $oMsg) : Icon|null {

        // Icon should be mandatory!
        if($oMsg->Get('icon') === null) {
            Helper::Trace('Message ID %1$s has no icon defined!', $oMsg->GetKey());
            return null;
        }

        $oAttDef = MetaModel::GetAttributeDef($oMsg::class, 'icon');
        
        /** @var array|null $aIcon Null or array with keys data, mimetype, filename (and downloads_count) */
        $aIcon = $oAttDef->GetForJSON($oMsg->Get('icon'));

        $oIcon = new Icon();
        $oIcon->mimetype = $aIcon['mimetype'];
        $oIcon->filename = $aIcon['filename'];
        $oIcon->data = $aIcon['data'];

        return $oIcon;


        
    }

    
    /**
     * Generates a reference for the current object.
     *
     * @return string
     */
    public function GetRef() : string {
        
        return 'ref_'.md5(json_encode($this));

    }


}
