<?php
namespace Polymerize;

use Rehike\ConfigManager\Config;

use Rehike\ConfigManager\Properties\{
    BoolProp,
    EnumProp,
    PropGroup,
    DependentProp,
    StringProp
};

/**
 * Defines Polymerize configuration definitions.
 * 
 * @author aubymori <aubyomori@gmail.com>
 */
class ConfigDefinitions
{
    public static function getConfigDefinitions(): array
    {
        return [
            "appearance" => [
                "oldIcons" => new BoolProp(true),
                "typography" => new BoolProp(false),
                "typographySpacing" => new BoolProp(false),
                "subCountOnSubButton" => new BoolProp(false)
            ],
            "general" => [
                "useDisplayNames" => new BoolProp(true),
                "homeStyle" => new EnumProp("NON_RICH", [
                    "NON_RICH",
                    "DEFAULT"                    
                ])
            ],
            "masthead" => [
                "legacyUploadButton" => new BoolProp(false),
                "accountMenuStyle" => new EnumProp("2020", [
                    "2017",
                    "2019",
                    "2020"
                ]),
            ],
            "guide" => [
                "secondItem" => new EnumProp("TRENDING", [
                    "TRENDING",
                    "EXPLORE"
                ]),
                "myChannelItem" => new BoolProp(false),
                "oldLibraryIcon" => new BoolProp(false),
                "hideDownloads" => new BoolProp(false),
                "hideClips" => new BoolProp(false)
            ],
            "watch" => [
                "sidebarStyle" => new EnumProp("COMPACT_AUTOPLAY", [
                    "COMPACT_AUTOPLAY",
                    "NO_CHIPS",
                    "DEFAULT"
                ]),
                "extraActionButtons" => new BoolProp(false),
                "useRyd" => new BoolProp(true),
                "oldInfoLayout" => new BoolProp(false),
            ],
            "comments" => [
                "leftReplyButton" => new BoolProp(false),
                "oldRepliesArrow" => new BoolProp(false),
                "fixVerifiedIcon" => new BoolProp(false)
            ]
        ];
    }
}