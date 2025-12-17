<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.251212
 */

namespace JeffreyBostoenExtensions\News\Base;

/**
 * Class Translation. Represents a translation of a message. (API v1.0.0)
 */
class Translation {

    /**
     * @var string $language The language of the message.
     */
    public string $language;

    /**
     * @var string $title The title of the message.
     */
    public string $title;

    /**
     * @var string $text The text of the message.
     */
    public string $text;

    /**
     * @var string $url The related URL.
     */
    public string $url;

}
