<?php
namespace Polymerize;

use stdClass;

/** 
 * Defines the state for Polymer templates.
 */
class YtApp extends stdClass
{
    private static YtApp $instance;

    public function __construct()
    {
        self::$instance = $this;
    }

    public static function &getInstance(): YtApp
    {
        return self::$instance;
    }

    /** 
     * Reprsents the top-level JS variable ytInitialData.
     */
    public ?object $ytInitialData = null;

    /**
     * Represents the top-level JS variable ytCommand.
     */
    public ?object $ytCommand = null;

    /**
     * Represents the top-level JS variable ytUrl.
     */
    public ?string $ytUrl = null;

    /**
     * Represents the top-level JS variable ytPageType.
     */
    public ?string $ytPageType = null;

    /**
     * Reprsents the top-level JS variable ytInitialPlayerResponse.
     */
    public ?object $ytInitialPlayerResponse = null;

    /**
     * Represents frontend configuration data stored to the top-level JS
     * variable yt.config_.
     */
    public ?object $ytcfg = null;
    
    /**
     * Represents frontend configuration data stored to the top-level JS
     * variable yt.config_.
     */
    public ?object $originalYtcfg = null;

    /**
     * Represents messages sent to the setMessage function
     */
    public ?object $messages = null;

    /**
     * Represents strings used for the guide footer links.
     */
    public ?object $footerStrings = null;

    /**
     * Represents the type of skeleton to be used. Can be:
     * - "home"
     * - "watch"
     * - "none"
     */
    public string $skeleton = "none";

    /**
     * User interface theme.
     * 
     * @see PrefUtils::getTheme
     */
    public string $theme = "LIGHT";

    /**
     * Represents the Polymerize configuration data.
     */
    public ?object $config = null;
}