<?php
namespace Polymerize\Model\Guide;

use Rehike\i18n\i18n;
use Rehike\ConfigManager\Config;

/**
 * Mutates the guide to work better with 2021 Polymer and to
 * match the user's preferences.
 */
class GuideModel
{
    private static object $strings;

    private static array $cairoToLegacyIconMap = [
        // Main section
        "TAB_HOME_CAIRO" => "WHAT_TO_WATCH",
        "TAB_SUBSCRIPTIONS_CAIRO" => "SUBSCRIPTIONS",
        // Library section
        "WATCH_HISTORY_CAIRO" => "WATCH_HISTORY",
        "PLAYLISTS_CAIRO" => "PLAYLISTS",
        "MY_VIDEOS_CAIRO" => "MY_VIDEOS",
        "WATCH_LATER_CAIRO" => "WATCH_LATER",
        "LIKES_PLAYLIST_CAIRO" => "LIKES_PLAYLIST",
        "CONTENT_CUT_CAIRO" => "CONTENT_CUT",
        "OFFLINE_DOWNLOAD_CAIRO" => "OFFLINE_DOWNLOAD",
        // End section
        "SETTINGS_CAIRO" => "SETTINGS",
        "FLAG_CAIRO" => "FLAG",
        "HELP_CAIRO" => "HELP",
        "FEEDBACK_CAIRO" => "FEEDBACK"
    ];

    /**
     * Processes the Shorts entry.
     * 
     * @param object &$entry Reference to the Shorts guide entry.
     */
    private static function processShortsEntry(object &$entry): void
    {
        self::$strings = i18n::getNamespace("guide");

        $option = Config::getConfigProp("guide.secondItem");
        switch ($option)
        {
            case "TRENDING":
            case "EXPLORE":
            {
                $strId = strtolower($option);
                $iconId = $option;
                $feedId = strtolower($option);

                unset($entry->serviceEndpoint);
                $entry->icon->iconType = $iconId;
                $entry->formattedTitle = (object)[
                    "simpleText" => self::$strings->get($strId)
                ];
                $entry->accessibility = (object)[
                    "accessibilityData" => (object) [
                        "label" => self::$strings->get($strId)
                    ]
                ];
                $entry->navigationEndpoint = (object)[
                    "browseEndpoint" => (object)[
                        "browseId" => "FE$feedId"
                    ],
                    "commandMetadata" => (object)[
                        "webCommandMetadata" => (object)[
                            "url" => "/feed/$feedId",
                            "webPageType" => "WEB_PAGE_TYPE_BROWSE",
                            "rootVe" => 6827
                        ]
                    ]
                ];
                break;
            }
        }
    }

    /**
     * Converts the "You" entry back into "Library".
     * 
     * @param object &$entry Reference to the "You" guide entry.
     */
    private static function convertYouEntry(object &$entry): void
    {
        self::$strings = i18n::getNamespace("guide");

        $entry->icon->iconType = 
            Config::getConfigProp("guide.oldLibraryIcon")
            ? "FOLDER"
            : "VIDEO_LIBRARY_WHITE";

            $entry->formattedTitle = (object)[
                "simpleText" => self::$strings->get("library")
            ];
            $entry->accessibility = (object)[
                "accessibilityData" => (object) [
                    "label" => self::$strings->get("library")
                ]
            ];

            $entry->navigationEndpoint = (object)[
                "browseEndpoint" => (object)[
                    "browseId" => "FElibrary"
                ],
                "commandMetadata" => (object)[
                    "webCommandMetadata" => (object)[
                        "url" => "/feed/library",
                        "webPageType" => "WEB_PAGE_TYPE_BROWSE",
                        "rootVe" => 6827
                    ]
                ]
            ];
    }

    /**
     * Finds the UCID of the currently logged in user from the guide.
     */
    private static function findUcid(array $sections): string|null
    {
        $items = @$sections[0]->guideSectionRenderer->items ?? null;
        if (is_null($items))
            return null;
        foreach ($items as $item)
        {
            $sectionItems = null;
            if ($sectionItems = @$item->guideCollapsibleSectionEntryRenderer->sectionItems)
            foreach ($sectionItems as $sectionItem)
            {
                $icon = @$sectionItem->guideEntryRenderer->icon->iconType ?? null;
                if ($icon == "MY_VIDEOS_CAIRO")
                {
                    $url = @$sectionItem->guideEntryRenderer->navigationEndpoint->urlEndpoint->url ?? null;
                    if (!is_null($url))
                    {
                        return substr($url, 35, strlen($url) - 42);
                    }
                }
            }
        }
        return null;
    }

    /**
     * Mutates a list of guide sections.
     * 
     * @param array &$sections Reference to the list of guide sections.
     * @param bool  $loggedIn  Is the user logged in?
     */
    public static function mutate(array &$sections, bool $loggedIn): void
    {
        self::$strings = i18n::getNamespace("guide");
        $insertMyChannelItem = Config::getConfigProp("guide.myChannelItem");

        $exploreSectionIndex = -1;

        foreach ($sections as $i => &$section)
        {
            $sectionInner = null;
            if ($sectionInner = @$section->guideSectionRenderer)
            {
                $myChannelIndex = -1;
                $ucid = null;

                if (@$sectionInner->items[0]->guideEntryRenderer->icon->iconType == "SHOPPING_BAG_CAIRO")
                {
                    $exploreSectionIndex = $i;
                    continue;
                }

                if (is_array(@$sectionInner->items))
                foreach ($sectionInner->items as $j => &$item)
                {
                    $itemInner = null;
                    if ($itemInner = @$item->guideEntryRenderer)
                    {
                        $icon = @$itemInner->icon->iconType ?? null;
                        if (isset(self::$cairoToLegacyIconMap[$icon]))
                        {
                            $itemInner->icon->iconType = self::$cairoToLegacyIconMap[$icon];
                        }
                        else if ($icon == "TAB_SHORTS_CAIRO")
                        {
                            self::processShortsEntry($itemInner);
                        }
                        else if ($icon == "ACCOUNT_CIRCLE_CAIRO")
                        {
                            self::convertYouEntry($itemInner);
                        }

                        if ($insertMyChannelItem && $icon == "TAB_HOME_CAIRO")
                        {
                            $ucid = self::findUcid($sections);
                            if (!is_null($ucid))
                            {
                                $myChannelIndex = $j + 1;
                            }
                        }
                    }
                    else if ($itemInner = @$item->guideCollapsibleSectionEntryRenderer)
                    {
                        if ($headerEntryInner = @$itemInner->headerEntry->guideEntryRenderer)
                        {
                            self::convertYouEntry($headerEntryInner);
                        }
                        
                        if (is_array(@$itemInner->sectionItems))
                        foreach ($itemInner->sectionItems as &$sectionItem)
                        {
                            $sectionItemInner = null;
                            if ($sectionItemInner = @$sectionItem->guideEntryRenderer)
                            {
                                $icon = @$sectionItemInner->icon->iconType ?? null;
                                if (isset(self::$cairoToLegacyIconMap[$icon]))
                                {
                                    $sectionItemInner->icon->iconType = self::$cairoToLegacyIconMap[$icon];
                                }
                            }
                            else if ($sectionItemInner = @$sectionItem->guideDownloadsEntryRenderer)
                            {
                                $entry = $sectionItemInner->entryRenderer->guideEntryRenderer;
                                $icon = @$entry->icon->iconType ?? null;
                                if (isset(self::$cairoToLegacyIconMap[$icon]))
                                {
                                    $entry->icon->iconType = self::$cairoToLegacyIconMap[$icon];
                                }
                            }
                        }
                    }
                }

                // Using array_splice inside a foreach loop mauls everything.
                if ($myChannelIndex != -1 && !is_null($ucid))
                {
                    $entry = (object)[
                        "guideEntryRenderer" => (object)[
                            "accessibility" => (object)[
                                "accessibilityData" => (object)[
                                    "label" => self::$strings->get("myChannel")
                                ]
                            ],
                            "formattedTitle" => (object)[
                                "simpleText" => self::$strings->get("myChannel")
                            ],
                            "icon" => (object)[
                                "iconType" => "ACCOUNT_CIRCLE"
                            ],
                            "navigationEndpoint" => (object)[
                                "commandMetadata" => (object)[
                                    "webCommandMetadata" => (object)[
                                        "url" => "/channel/$ucid",
                                        "webPageType" => "WEB_PAGE_TYPE_CHANNEL",
                                        "rootVe" => 6827,
                                        "apiUrl" => "/youtubei/v1/browse"
                                    ]
                                ],
                                "browseEndpoint" => (object)[
                                    "browseId" => $ucid
                                ]
                            ],
                            "isPrimary" => true
                        ]
                    ];
                    array_splice($sectionInner->items, $myChannelIndex, 0, [$entry]);
                }
            }
            // Fix subscriptions expand/collapse icon
            // Also fix the title for it
            else if ($sectionInner = @$section->guideSubscriptionsSectionRenderer)
            {
                $subsItem = @$sectionInner->items[0]->guideCollapsibleSectionEntryRenderer->headerEntry->guideEntryRenderer ?? null;
                if (!is_null($subsItem))
                {
                    $sectionInner->formattedTitle = $subsItem->formattedTitle;
                    array_splice($sectionInner->items, 0, 1);
                }

                if (is_array(@$sectionInner->items))
                foreach ($sectionInner->items as $i => &$item)
                {
                    $itemInner = null;
                    if ($itemInner = @$item->guideCollapsibleEntryRenderer)
                    {
                        $itemInner->collapserItem->guideEntryRenderer->icon->iconType = "COLLAPSE";
                        $itemInner->expanderItem->guideEntryRenderer->icon->iconType = "EXPAND";

                        $lastItem = $itemInner->expandableItems[count($itemInner->expandableItems) - 1];
                        if (@$lastItem->guideEntryRenderer->icon->iconType == "VIEW_LIST_CAIRO")
                            $lastItem->guideEntryRenderer->icon->iconType = "VIEW_LIST";
                    }
                }
            }
        }

        if ($exploreSectionIndex != -1)
        {
            if ($loggedIn)
            {
                array_splice($sections, $exploreSectionIndex, 1);
            }
            else
            {
                // Build the hardcoded signed out "Best of YouTube" section that used to show up in
                // the place of Explore.
                $section = &$sections[$exploreSectionIndex]->guideSectionRenderer;
                $section->formattedTitle = (object)[
                    "simpleText" => self::$strings->get("bestOfYouTube.title")
                ];
                $section->items = [
                    self::bestOfYouTubeEntry("music", "music", "UC-9-kyTW8ZkZNDHQJ6FgpwQ"),
                    self::bestOfYouTubeEntry("sports", "sports", "UCEgdi0XIXXZ-qJOFPf4JSKw"),
                    self::bestOfYouTubeEntry("gaming", "gaming", "UCOpNcN46UbXVtpKMrmU4Abg", "/gaming"),
                    self::bestOfYouTubeEntry("moviesTv", "movies_tv", "UClgRkhTL3_hImCAmdLfDE4g"),
                    self::bestOfYouTubeEntry("news", "news", "UCYfdidRxbB8Qhf0Nx7ioOYw"),
                    self::bestOfYouTubeEntry("live", "live", "UC4R8DWoMoI7CAwX8_LjQHig"),
                    self::bestOfYouTubeEntry("spotlight", "spotlight", "UCBR8-60-B28hp2BmDPdntcQ"),
                    self::bestOfYouTubeEntry("threeSixty", "360", "UCzuqhhs6NWbgTzMuM09WKDQ")
                ];
            }
        }
    }

    private static function bestOfYouTubeEntry(string $stringId, string $iconName, string $ucid, ?string $urlOverride = null)
    {
        return (object)[
            "guideEntryRenderer" => (object)[
                "formattedTitle" => (object)[
                    "simpleText" => self::$strings->get("bestOfYouTube.$stringId")
                ],
                "accessibility" => (object)[
                    "accessibilityData" => (object)[
                        "label" => self::$strings->get("bestOfYouTube.$stringId")
                    ]
                ],
                "thumbnail" => (object)[
                    "thumbnails" => [
                        (object)[
                            "url" => "/polymerize/static/img/best_of_youtube/{$iconName}.jpg"
                        ]
                    ]
                ],
                "entryData" => (object)[
                    "guideEntryData" => (object)[
                        "guideEntryId" => $ucid
                    ]
                ],
                "navigationEndpoint" => (object)[
                    "commandMetadata" => (object)[
                        "webCommandMetadata" => (object)[
                            "url" => $urlOverride ?? "/channel/$ucid",
                            "webPageType" => "WEB_PAGE_TYPE_CHANNEL",
                            "rootVe" => 6827,
                            "apiUrl" => "/youtubei/v1/browse"
                        ]
                    ],
                    "browseEndpoint" => (object)[
                        "browseId" => $ucid
                    ]
                ],
                "presentationStyle" => "GUIDE_ENTRY_PRESENTATION_STYLE_NONE"
            ]
        ];
    }
}