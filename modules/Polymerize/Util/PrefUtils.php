<?php
namespace Polymerize\Util;

/**
 * A set of utilities for parsing YouTube's PREF cookie.
 * 
 * @author aubymori <aubymori@gmail.com>
 * @author The Rehike Maintainers
 */
class PrefUtils
{
    /**
     * Parsed PREF cookie.
     */
    private static object $pref;

    public static function __initStatic()
    {
        if (!isset($_COOKIE["PREF"]))
        {
            self::$pref = (object)[];
            return;
        }

        $parsed = (object)[];
        $temp = explode("&", $_COOKIE["PREF"]);
        foreach ($temp as $value)
        {
            $temp2 = explode("=", $value);
            $parsed->{$temp2[0]} = $temp2[1] ?? "";
        }
        self::$pref = $parsed;
    }

    /**
     * Get the user interface theme.
     * 
     * @return string Either "LIGHT", "DARK", or "DEVICE".
     */
    public static function getTheme(): string
    {
        switch (@self::$pref->f6)
        {
            case "80000":
                return "LIGHT";
            case "400":
                return "DARK";
            default:
                return "DEVICE";
        }
    }

    /**
     * Is autoplay enabled?
     */
    public static function autoplayEnabled(): bool
    {
        if (isset(self::$pref->f5) && substr(self::$pref->f5, 0, 1) == "3")
        {
            return false;
        }
        return true;
    }
}