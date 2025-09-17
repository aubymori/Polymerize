<?php
namespace Polymerize;

use Polymerize\YtApp;

use Rehike\TemplateManager;

/**
 * Manages the global app state for Rehike during boot.
 * 
 * @author Taniko Yamamoto <kirasicecreamm@gmail.com>
 * @author The Rehike Maintainers
 */
final class YtStateManager
{
    /**
     * Initialise and get the global state.
     */
    public static function init(): YtApp
    {
        $yt = new YtApp();

        self::bindToEverything($yt);

        return $yt;
    }

    /**
     * Bind the global state to everything that needs it.
     */
    protected static function bindToEverything(YtApp $yt): void
    {
        TemplateManager::registerGlobalState($yt);
    }
}