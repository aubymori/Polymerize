<?php
namespace Polymerize\Model\Browse;

use Polymerize\Model\Common\ButtonRenderer;
use Polymerize\Util\ParsingUtils;
use Rehike\i18n\i18n;

class ChannelModel
{
    private static ?object $strings = null;

    private static function ensureStrings(): void
    {
        if (is_null(self::$strings))
            self::$strings = i18n::getNamespace("channel");
    }

    /**
     * Add the about tab to the list of channel tabs.
     * 
     * @param array   &$tabs      The list of tabs
     * @param string  $baseUrl    The channel's base URL (e.g. "/@aubymori").
     * @param string  $ucid       The channel's UCID.
     * @param ?object $aboutData  The data for the about tab, if selected.
     */
    public static function addAboutTab(array &$tabs, string $baseUrl, string $ucid, ?object $aboutData): void
    {
        self::ensureStrings();

        $aboutIndex = array_key_last($tabs);
        if (!isset($tabs[$aboutIndex]->expandableTabRenderer))
        {
            $aboutIndex++;
        }
        
        $aboutTab = (object)[
            "title" => self::$strings->get("tabAbout"),
            "endpoint" => (object)[
                "browseEndpoint" => (object)[
                    "browseId" => $ucid,
                    "canonicalBaseUrl" => $baseUrl,
                    // {
                    //     "2": "about",
                    //     "23": 0,
                    //     "50": {},
                    //     "110": {
                    //         "1": {
                    //             "6": {}
                    //         },
                    //         "9": {}
                    //     }
                    // }
                    "params" => "EgVhYm91dLgBAJIDAPIGBgoCMgBKAA%3D%3D"
                ],
                "commandMetadata" => (object)[
                    "webCommandMetadata" => (object)[
                        "apiUrl" => "/youtubei/v1/browse",
                        "rootVe" => 3611,
                        "url" => "$baseUrl/about",
                        "webPageType" => "WEB_PAGE_TYPE_CHANNEL"
                    ]
                ]
            ]
        ];

        if (!is_null($aboutData))
        {
            // Select tab
            foreach ($tabs as &$tab)
            {
                if (@$tab->tabRenderer->selected)
                {
                    unset($tab->tabRenderer->selected);
                    unset($tab->tabRenderer->content);

                    if (!isset($tab->tabRenderer->endpoint))
                    {
                        // For single-tabbed channels, YouTube removes the endpoint from the tab.
                        // This is a problem since we need to add back about tab.
                        $tab->tabRenderer->endpoint = (object)[
                            "browseEndpoint" => (object)[
                                "browseId" => $ucid,
                                "canonicalBaseUrl" => $baseUrl,
                                "params" => "EghmZWF0dXJlZPIGBAoCMgA%3D"
                            ],
                            "commandMetadata" => (object)[
                                "webCommandMetadata" => (object)[
                                    "apiUrl" => "/youtubei/v1/browse",
                                    "rootVe" => 3611,
                                    "url" => $baseUrl,
                                    "webPageType" => "WEB_PAGE_TYPE_CHANNEL"
                                ]
                            ]
                        ];
                    }
                }
            }
            $aboutTab->selected = true;

            // Convert data
            $about = (object)[];
            $metadata = $aboutData->metadata->aboutChannelViewModel;

            $about->channelId = $ucid;

            if (isset($metadata->description))
            {
                $about->descriptionLabel = ParsingUtils::attributedStringToFormattedString($metadata->descriptionLabel);
                $about->description = (object)[
                    "runs" => [
                        (object)[
                            "text" => $metadata->description
                        ]
                    ]
                ];
            }

            $about->statsLabel = (object)[
                "runs" => [
                    (object)[
                        "text" => self::$strings->get("aboutStatsLabel")
                    ]
                ]
            ];
            $about->joinedDateText = ParsingUtils::attributedStringToFormattedString($metadata->joinedDateText);
            if (isset($metadata->viewCountText))
                $about->viewCountText = (object)[
                    "simpleText" => $metadata->viewCountText
                ];

            if (isset($metadata->country))
            {
                $about->countryLabel = (object)[
                    "runs" => [
                        (object)[
                            "text" => self::$strings->get("aboutCountryLabel"),
                            "deemphasize" => true
                        ]
                    ]
                ];
                $about->country = (object)[
                    "simpleText" => $metadata->country
                ];
            }

            if (isset($metadata->signInForBusinessEmail) || isset($metadata->onBusinessEmailRevealClickCommand))
            {
                $about->businessEmailLabel = (object)[
                    "runs" => [
                        (object)[
                            "text" => self::$strings->get("aboutBusinessEmailLabel")
                        ]
                    ]
                ];

                if (isset($metadata->onBusinessEmailRevealClickCommand))
                {
                    $about->onBusinessEmailRevealClickCommand = $metadata->onBusinessEmailRevealClickCommand->innertubeCommand;
                    $about->businessEmailButton = ButtonRenderer::fromViewModel($metadata->businessEmailRevealButton);
                    $about->bypassBusinessEmailCaptcha = $metadata->bypassBusinessEmailCaptcha;
                    if (isset($metadata->businessEmail))
                        $about->businessEmail = $metadata->businessEmail;
                    if (isset($metadata->businessEmailSubmitCaptchaLabel))
                        $about->businessEmailRevealSubmitButtonLabel = (object)[
                            "simpleText" => $metadata->businessEmailSubmitCaptchaLabel
                        ];
                }
                else
                {
                    $about->signInForBusinessEmail
                        = ParsingUtils::attributedStringToFormattedString($metadata->signInForBusinessEmail, true);
                }
            }

            if (isset($about->country) || isset($about->businessEmailLabel))
            {
                $about->detailsLabel = (object)[
                    "runs" => [
                        (object)[
                            "text" => self::$strings->get("aboutDetailsLabel")
                        ]
                    ]
                ];
            }

            if (isset($metadata->customLinksLabel))
                $about->primaryLinksLabel = ParsingUtils::attributedStringToFormattedString($metadata->customLinksLabel);

            if (isset($metadata->links))
            {
                $about->primaryLinks = [];
                foreach ($metadata->links as $link)
                {
                    $linkInner = $link->channelExternalLinkViewModel;
                    $about->primaryLinks[] = (object)[
                        "icon" => (object)[
                            "thumbnails" => $linkInner->favicon->sources
                        ],
                        "navigationEndpoint" => $linkInner->link->commandRuns[0]->onTap->innertubeCommand,
                        "title" => (object)[
                            "simpleText" => $linkInner->title->content
                        ]
                    ];
                }
            }

            foreach ([
                "flaggingButton",
                "shareChannel"
            ] as $buttonName)
            {
                if (isset($aboutData->{$buttonName}))
                {
                    if (!isset($about->actionButtons))
                        $about->actionButtons = [];

                    $button = $aboutData->{$buttonName};
                    $button->buttonRenderer->tooltip = $button->buttonRenderer->text->runs[0]->text;
                    unset($button->buttonRenderer->text);
                    $about->actionButtons[] = $button;
                }
            }

            $aboutTab->content = (object)[
                "sectionListRenderer" => (object)[
                    "contents" => [
                        (object)[
                            "itemSectionRenderer" => (object)[
                                "contents" => [
                                    (object)[
                                        "channelAboutFullMetadataRenderer" => $about
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];
        }

        array_splice($tabs, $aboutIndex, 0, [(object)[
            "tabRenderer" => $aboutTab
        ]]);
    }
}