<?php
/**
 * Polymerize:
 * 
 * A proxy server designed to serve old versions of the
 * "Polymer" YouTube frontend.
 */

//ob_start();
set_include_path($_SERVER["DOCUMENT_ROOT"]);

if (PHP_VERSION_ID < 80000)
{
    include "includes/fatal_templates/php_too_old.php";
    die();
}

// PHP < 8.2 IDE fix
include "includes/polyfill/AllowDynamicProperties.php";

// Include the Composer and Polymerize autoloaders, respectively.
require "vendor/autoload.php";
require "includes/polymerize_autoloader.php";

\Rehike\i18n\i18n::getConfigApi()
    ->setRootDirectory($_SERVER["DOCUMENT_ROOT"] . "/i18n");

\Rehike\ConfigManager\Config::registerConfigDefinitions(
    \Polymerize\ConfigDefinitions::getConfigDefinitions()
);

\Rehike\ConfigManager\Config::loadConfig();

\Polymerize\YtStateManager::init();

\Rehike\Network\NetworkCore::setResolve([
    \Rehike\Util\Nameserver\Nameserver::get("www.youtube.com", "1.1.1.1", 443)->serialize()
]);

require "router.php";

\Rehike\Boot\ShutdownEvents::runAllEvents();