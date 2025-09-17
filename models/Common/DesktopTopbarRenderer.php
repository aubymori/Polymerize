<?php
namespace Polymerize\Model\Common;

use Rehike\i18n\i18n;
use Rehike\ConfigManager\Config;

/**
 * Mutates the desktopTopbarRenderer to work better with 2021 Polymer and to
 * match the user's preferences.
 */
class DesktopTopbarRenderer
{
    /**
     * Mutates a desktopTopbarRenderer object.
     * 
     * @param object &$topbar Reference to the desktopTopbarRenderer object to mutate.
     */
    public static function mutate(object &$topbar)
    {
        $strings = i18n::getNamespace("topbar");

        foreach ($topbar->topbarButtons as &$button)
        {
            $buttonInner = null;
            // Notifications button
            if ($buttonInner = @$button->notificationTopbarButtonRenderer)
            {
                // This was changed to "NOTIFICATIONS_CAIRO". Revert it so the icon actually
                // shows up.
                $buttonInner->icon->iconType = "NOTIFICATIONS";

                // Google changed the notifications menu from requesting notification/get_notification_menu
                // to requesting browse with browse ID FEnotifications_inbox, and with it, changed the signal service
                // name which makes old Polymer go crazy and request service_ajax. Luckily, the older endpoint still exists.
                // If it ever ceases to exist, we can implement it on Polymerize as a proxy to FEnotifications_inbox.
                $buttonInner->menuRequest->signalServiceEndpoint->signal = "GET_NOTIFICATIONS_MENU";
            }
            // Creation button:
            else if ($buttonInner = @$button->buttonRenderer)
            {
                // Don't mess with Sign in button
                if (@$buttonInner->targetId == "topbar-signin")
                    continue;

                // Legacy upload button:
                if (Config::getConfigProp("masthead.legacyUploadButton"))
                {
                    // Revert icon
                    $buttonInner->icon->iconType = "UPLOAD";

                    // Revert text
                    unset($buttonInner->text);
                    $buttonInner->tooltip = $strings->get("upload");
                    $buttonInner->accessibility = (object)[
                        "accessibilityData" => (object)[
                            "label" => $strings->get("upload")
                        ]
                    ];

                    // Remove menu and replace with /upload endpoint:
                    unset($buttonInner->command);
                    $buttonInner->navigationEndpoint = (object)[
                        "commandMetadata" => (object)[
                            "webCommandMetadata" => (object)[
                                "rootVe" => 83769,
                                "url" => "/upload",
                                "webPageType" => "WEB_PAGE_TYPE_UNKNOWN"
                            ]
                        ],
                        "uploadEndpoint" => (object)[
                            "hack" => true
                        ]
                    ];
                }
                else
                {
                    // Revert icon
                    $buttonInner->icon->iconType = "VIDEO_CALL";

                    // Move text to tooltip
                    $buttonInner->tooltip = $buttonInner->text->runs[0]->text;
                    $buttonInner->accessibility = (object)[
                        "accessibilityData" => (object)[
                            "label" => $buttonInner->text->runs[0]->text
                        ]
                    ];
                    unset($buttonInner->text);
                }
            }
        }
    }
}