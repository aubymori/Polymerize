<?php
namespace Polymerize\Model\Account;

use Rehike\i18n\i18n;
use Rehike\ConfigManager\Config;

/**
 * Mutates the account menu.
 */
class AccountMenuModel
{
    private static array $accountMenuOrder2017 = [
        [
            "GOOGLE",
            "CREATOR_STUDIO",
            "SWITCH_ACCOUNTS",
            "EXIT_TO_APP",
        ],
        [
            "THEME_TOGGLE",
            "SETTINGS_CAIRO",
            "HELP",
            "FEEDBACK"
        ],
        [
            "TRANSLATE",
            "LANGUAGE",
            "ADMIN_PANEL_SETTINGS"
        ]
    ];

    private static array $accountMenuOrder2019 = [
        [
            "GOOGLE",
            "MONETIZATION_ON",
            "CREATOR_STUDIO",
            "SWITCH_ACCOUNTS",
            "EXIT_TO_APP",
        ],
        [
            "THEME_TOGGLE",
            "TRANSLATE",
            "SETTINGS_CAIRO",
            "SHIELD_WITH_AVATAR",
            "HELP",
            "FEEDBACK",
            "KEYBOARD"
        ],
        [
            "LANGUAGE",
            "ADMIN_PANEL_SETTINGS"
        ]
    ];

    private static array $accountMenuOrder2020 = [
        [
            "GOOGLE",
            "MONETIZATION_ON",
            "CREATOR_STUDIO",
            "SWITCH_ACCOUNTS",
            "EXIT_TO_APP",
        ],
        [
            "THEME_TOGGLE",
            "TRANSLATE",
            "LANGUAGE",
            "SETTINGS_CAIRO",
            "SHIELD_WITH_AVATAR",
            "HELP",
            "FEEDBACK",
            "KEYBOARD"
        ],
        [
            "ADMIN_PANEL_SETTINGS"
        ]
    ];

    /**
     * Mutates the account menu.
     * 
     * @param array &$sections Reference to the list of account menu sections.
     * @param object &$header  Reference to the activeAccountHeaderRenderer object.
     */
    public static function mutate(array &$sections, ?object &$header)
    {
        $style = Config::getConfigProp("masthead.accountMenuStyle");
        $accountMenuOrder = self::$accountMenuOrder2020;
        switch ($style)
        {
            case "2017":
                $accountMenuOrder = self::$accountMenuOrder2017;
                break;
            case "2019":
                $accountMenuOrder = self::$accountMenuOrder2019;
                break;
        }      
                
        // Re-order sections:
        $newSections = [];
        foreach ($accountMenuOrder as $section)
        {
            $newItems = [];
            foreach ($section as $iconType)
            {
                $item = self::findItemByIconName($sections, $iconType);
                if (!is_null($item))
                    $newItems[] = $item;
            }

            if (count($newItems) > 0)
                $newSections[] = (object)[
                    "multiPageMenuSectionRenderer" => (object)[
                        "items" => $newItems
                    ]
                ];
        }

        $sections = $newSections;

        // Fix settings icon:
        $settingsItem = self::findItemByIconName($sections, "SETTINGS_CAIRO");
        if (isset($settingsItem->compactLinkRenderer->icon->iconType))
        {
            $settingsItem->compactLinkRenderer->icon->iconType = "SETTINGS";
        }

        // Change appearance of last section items:
        $lastSection = $sections[count($sections) - 1];
        foreach ($lastSection->multiPageMenuSectionRenderer->items as &$item)
        {
            $itemInner = null;
            if ($itemInner = @$item->compactLinkRenderer)
            {
                unset($itemInner->icon);
                if ($style != "2020")
                {
                    if (isset($itemInner->title))
                    {
                        $itemInner->title = (object)[
                            "runs" => [
                                (object)[
                                    "text" => @$itemInner->title->simpleText ?? $itemInner->title->runs[0]->text,
                                    "deemphasize" => true
                                ]
                            ]
                        ];
                    }

                    if (isset($itemInner->subtitle))
                    {
                        $itemInner->subtitle = (object)[
                            "runs" => [
                                (object)[
                                    "text" => @$itemInner->subtitle->simpleText ?? $itemInner->subtitle->runs[0]->text,
                                    "deemphasize" => true
                                ]
                            ]
                        ];
                    }
                }
            }
        }

        // Swap channel/manage Google account links:
        if (!is_null($header))
        {
            $accountItem = self::findItemByIconName($sections, "GOOGLE");
            $accountItemInner = null;
            $headerLink = null;
            if (!is_null($accountItem) 
            && ($accountItemInner = @$accountItem->compactLinkRenderer)
            && ($headerLink = @$header->manageAccountTitle->runs[0]))
            {
                $strings = i18n::getNamespace("account_menu");
                $accountItemInner->icon->iconType = "ACCOUNT_BOX";

                $channelEndpoint = $headerLink->navigationEndpoint;
                $accountItemText = $headerLink->text;
                if (!isset($channelEndpoint->channelCreationFormEndpoint))
                    $accountItemText = $strings->get("yourChannel");
                
                $accountItemInner->title = (object)[
                    "simpleText" => $accountItemText
                ];
                $headerLink->text = $strings->get("manageYourGoogleAccount");

                $headerLink->navigationEndpoint = $accountItemInner->navigationEndpoint;
                $accountItemInner->navigationEndpoint = $channelEndpoint;
            }
        }
    }

    /**
     * Finds an account menu item by the icon name.
     */
    private static function &findItemByIconName(array &$sections, string $iconType): ?object
    {
        foreach ($sections as &$section)
        {
            $sectionInner = null;
            if ($sectionInner = @$section->multiPageMenuSectionRenderer)
            {
                foreach ($sectionInner->items as &$item)
                {
                    //var_dump($item);

                    if ($iconType == "THEME_TOGGLE"
                    && isset($item->toggleThemeCompactLinkRenderer))
                    {
                        return $item;
                    }
                    
                    $itemInner = null;
                    if ($itemInner = @$item->compactLinkRenderer)
                    {
                        if (@$itemInner->icon->iconType == $iconType)
                            return $item;
                    }
                }
            }
        }
        // DUH DUH DOYYYYYYYYYYYYY
        $nullref = null;
        return $nullref;
    }
}