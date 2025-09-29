<?php
namespace Polymerize\Model\Common;

class MetadataBadgeRenderer
{
    private static array $iconMap = [
        "AUDIO_BADGE" => "OFFICIAL_ARTIST_BADGE"
    ];

    public static function fixIcons(array &$badges)
    {
        foreach ($badges as &$badge)
        {
            $icon = @$badge->metadataBadgeRenderer->icon->iconType ?? null;
            if (isset(self::$iconMap[$icon]))
                $badge->metadataBadgeRenderer->icon->iconType = self::$iconMap[$icon];
        }
    }
}