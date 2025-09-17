<?php
namespace Polymerize\Model\Util;

use Polymerize\Model\Common\LockupViewModel;

class LockupConverter
{
    public static function convertLockups(array &$lockups, string $type = "")
    {
        foreach ($lockups as &$lockup)
        foreach ($lockup as $name => &$content)
        {
            switch ($name)
            {
                case "lockupViewModel":
                    $lockup = LockupViewModel::toLegacyRenderer($lockup, $type);
                    break;
            }
        }
    }
}