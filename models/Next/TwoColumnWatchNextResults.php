<?php
namespace Polymerize\Model\Next;

use Rehike\i18n\i18n;
use Rehike\ConfigManager\Config;

use Polymerize\Util\ParsingUtils;
use Polymerize\Model\Common\ButtonRenderer;
use Polymerize\Model\Common\ToggleButtonRenderer;
use Polymerize\Model\Common\SubscribeButtonRenderer;
use Polymerize\Model\Common\MetadataBadgeRenderer;
use Polymerize\Model\Browse\BrowseModel;
use Polymerize\Model\Common\DisplayNameManager;
use Rehike\Async\Promise;

class TwoColumnWatchNextResults
{
    private const REDIRECT_URL_REGEX = "/(?<=\?q=|&q=)(?=(.*?)&|$)/";

    private static array $incompatibleWatchActionIcons = [
        "SPARK"
    ];

    private static array $extraWatchActionIcons = [
        "MONEY_HEART",
        "CONTENT_CUT",
        "OUTLINE_BAG"
    ];

    private static object $strings;
    private static object $rydData;
    private static ?object $primaryInfo = null;
    private static ?object $secondaryInfo = null;
    private static bool $signedIn = false;

    public static function mutate(object &$data, object $rydData, bool $signedIn): void
    {
        self::$strings = i18n::getNamespace("next");
        self::$rydData = $rydData;
        self::$signedIn = $signedIn;

        foreach ($data->results->results->contents as &$result)
        {
            $resultInner = null;
            if ($resultInner = @$result->videoPrimaryInfoRenderer)
            {
                self::$primaryInfo = $resultInner;
            }
            else if ($resultInner = @$result->videoSecondaryInfoRenderer)
            {
                self::$secondaryInfo = $resultInner;
            }
        }

        if (!is_null(self::$primaryInfo))
        {
            self::mutatePrimaryInfo(self::$primaryInfo);
        }

        if (!is_null(self::$secondaryInfo))
        {
            self::mutateSecondaryInfo(self::$secondaryInfo);
        }

        $secondaryResults = @$data->secondaryResults->secondaryResults->results ?? null;
        if (!is_null($secondaryResults))
        {
            $sidebarStyle = Config::getConfigProp("watch.sidebarStyle");
            $videoList = null;
            if (isset($secondaryResults[0]->relatedChipCloudRenderer))
            {
                $videoList = &$secondaryResults[1]->itemSectionRenderer->contents;
            }
            else
            {
                $videoList = &$secondaryResults;
            }

            BrowseModel::mutateItems($videoList, "compact");

            if ($sidebarStyle == "COMPACT_AUTOPLAY")
            {
                $autoplayId = @$data->autoplay->autoplay->sets[0]->autoplayVideo->watchEndpoint->videoId ?? null;
                if (!is_null($autoplayId))
                {
                    $autoplayVideo = null;
                    foreach ($videoList as $i => $video)
                    {
                        if (@$video->compactVideoRenderer->videoId == $autoplayId)
                        {
                            $autoplayVideo = $video;
                            array_splice($videoList, $i, 1);
                        }
                    }

                    if (!is_null($autoplayVideo))
                    {
                        $autoplayRenderer = (object)[
                            "compactAutoplayRenderer" => (object)[
                                "contents" => [ $autoplayVideo ],
                                "infoIcon" => (object)[
                                    "iconType" => "INFO"
                                ],
                                "infoText" => (object)[
                                    "simpleText" => self::$strings->get("autoplayInfoText")
                                ],
                                "title" => (object)[
                                    "simpleText" => self::$strings->get("autoplayTitle")
                                ],
                                "toggleDescription" => (object)[
                                    "simpleText" => self::$strings->get("autoplayToggleDescription")
                                ]
                            ]
                        ];

                        array_splice($videoList, 0, 0, [$autoplayRenderer]);
                    }
                }
            }

            for ($i = 0; $i < count($videoList); $i++)
            {
                if (isset($videoList[$i]->channelRenderer))
                {
                    array_splice($videoList, $i, 1);
                    $i--;
                }
            }

            if ($sidebarStyle != "DEFAULT")
            {
                $data->secondaryResults->secondaryResults->results = $videoList;
                // Fix for continuations
                $data->secondaryResults->secondaryResults->targetId = "watch-next-feed";
            }
        }
        

        if (!is_null(self::$primaryInfo) && !is_null(self::$secondaryInfo))
        {
            $oldInfoLayout = Config::getConfigProp("watch.oldInfoLayout");
            if ($oldInfoLayout || Config::getConfigProp("appearance.subCountOnSubButton"))
            {
                $subscribeButton = null;
                if ($subscribeButton = @self::$secondaryInfo->subscribeButton->subscribeButtonRenderer)
                {
                    $subscriberCount = null;
                    if ($subscriberCount = @self::$secondaryInfo->owner->videoOwnerRenderer->subscriberCountText->simpleText)
                    {
                        SubscribeButtonRenderer::addSubscriberCount($subscribeButton, $subscriberCount);
                    }
                }
            }

            if ($oldInfoLayout)
            {
                unset(self::$secondaryInfo->owner->videoOwnerRenderer->subscriberCountText);

                $dateText = null;
                if ($dateText = @self::$primaryInfo->dateText->simpleText)
                {
                    $isUnlistedOrPrivate = false;
                    if (isset(self::$primaryInfo->badges))
                    foreach (self::$primaryInfo->badges as $badge)
                    {
                        $iconType = @$badge->metadataBadgeRenderer->icon->iconType ?? null;
                        if ($iconType == "PRIVACY_UNLISTED" || $iconType == "PRIVACY_PRIVATE")
                        {
                            $isUnlistedOrPrivate = true;
                            break;
                        }
                    }

                    if (!preg_match(self::$strings->get("nonPublishCheck"), $dateText))
                    {
                        $dateText = self::$strings->format(
                            $isUnlistedOrPrivate ? "uploadedOn" : "publishedOn",
                            $dateText
                        );
                        // Allowing this to go through on videos that should display "Published on"
                        // will replace it with a relative date text, which is bad.
                        unset(self::$primaryInfo->updatedMetadataEndpoint);
                    }

                    self::$secondaryInfo->dateText = (object)[
                        "simpleText" => $dateText
                    ];
                    unset(self::$primaryInfo->dateText);
                }
            }
        }
    }

    private static function mutatePrimaryInfo(object &$data): void
    {
        $videoActions = null;
        if ($videoActions = @$data->videoActions->menuRenderer)
        {
            if (isset($videoActions->topLevelButtons[0]->segmentedLikeDislikeButtonViewModel))
            {
                self::convertLikeDislikeButtons($videoActions->topLevelButtons);
            }
            
            foreach ($videoActions->topLevelButtons as &$button)
            {
                if (isset($button->buttonViewModel))
                {
                    $button = ButtonRenderer::fromViewModel($button);
                }
            }

            $extraButtons = Config::getConfigProp("watch.extraActionButtons");
            foreach ($videoActions->flexibleItems as &$item)
            {
                $btn = $item->menuFlexibleItemRenderer->topLevelButton;
                if (isset($btn->buttonViewModel)
                && !in_array($btn->buttonViewModel->iconName, self::$incompatibleWatchActionIcons)
                && ($extraButtons || !in_array(@$btn->buttonViewModel->iconName, self::$extraWatchActionIcons)))
                {
                    $videoActions->topLevelButtons[] = ButtonRenderer::fromViewModel($btn);
                }
            }
            unset($videoActions->flexibleItems);

            // Move save from menu to top level buttons
            foreach ($videoActions->items as $i => $item)
            {
                if (@$item->menuServiceItemRenderer->icon->iconType == "PLAYLIST_ADD")
                {
                    $menuItem = $item->menuServiceItemRenderer;
                    $videoActions->topLevelButtons[] = (object)[
                        "buttonRenderer" => (object)[
                            "size" => "SIZE_DEFAULT",
                            "style" => "STYLE_DEFAULT",
                            "icon" => (object) [ "iconType" => "PLAYLIST_ADD" ],
                            "serviceEndpoint" => $menuItem->serviceEndpoint,
                            "text" => $menuItem->text,
                            "tooltip" => $menuItem->text->runs[0]->text,
                            "accessibility" => (object)[
                                "label" => $menuItem->text->runs[0]->text
                            ],
                            "accessibilityData" => (object)[
                                "accessibilityData" => (object)[
                                    "label" => $menuItem->text->runs[0]->text
                                ]
                            ]
                        ]
                    ];

                    array_splice($videoActions->items, $i, 1);
                    break;
                }
            }
        }
    }

    private static function convertLikeDislikeButtons(array &$topLevelButtons): void
    {
        $viewModel = $topLevelButtons[0]->segmentedLikeDislikeButtonViewModel;
        array_splice($topLevelButtons, 0, 1);

        $context = [
            "defaultStyle" => "STYLE_TEXT",
            "toggledStyle" => "STYLE_DEFAULT_ACTIVE"
        ];

        $likeStatus = $viewModel->likeButtonViewModel->likeButtonViewModel->likeStatusEntity->likeStatus;

        $likeButton =
            ToggleButtonRenderer::fromViewModel(
                $viewModel->likeButtonViewModel->likeButtonViewModel->toggleButtonViewModel,
                (object)($context + [ "id" => "TOGGLE_BUTTON_ID_TYPE_LIKE", "isToggled" => $likeStatus == "LIKE" ]),
                self::$signedIn ? "serviceEndpoint" : "navigationEndpoint"
            );
        $dislikeButton =
            ToggleButtonRenderer::fromViewModel(
                $viewModel->dislikeButtonViewModel->dislikeButtonViewModel->toggleButtonViewModel,
                (object)($context + [ "id" => "TOGGLE_BUTTON_ID_TYPE_DISLIKE", "isToggled" => $likeStatus == "DISLIKE" ]),
                self::$signedIn ? "serviceEndpoint" : "navigationEndpoint"
            );

        if (self::$signedIn)
        {
            $likeButton->toggleButtonRenderer->defaultServiceEndpoint = (object)[
                "commandExecutorCommand" => (object)[
                    "commands" => [
                        (object)[
                            "updateToggleButtonStateCommand" => (object)[
                                "toggled" => false,
                                "buttonId" => "TOGGLE_BUTTON_ID_TYPE_DISLIKE"
                            ]
                        ],
                        $likeButton->toggleButtonRenderer->defaultServiceEndpoint
                    ]
                ]
            ];

            $dislikeButton->toggleButtonRenderer->defaultServiceEndpoint = (object)[
                "commandExecutorCommand" => (object)[
                    "commands" => [
                        (object)[
                            "updateToggleButtonStateCommand" => (object)[
                                "toggled" => false,
                                "buttonId" => "TOGGLE_BUTTON_ID_TYPE_LIKE"
                            ]
                        ],
                        $dislikeButton->toggleButtonRenderer->defaultServiceEndpoint
                    ]
                ]
            ];
        }

        if (isset(self::$rydData->dislikes))
        {
            // We extract the like count by replacing all non-numeric characters in the a11y string.
            $likeCountString = preg_replace("/[^0-9]+/", "", $likeButton->toggleButtonRenderer->accessibility->label);
            if (strlen($likeCountString) > 0)
            {
                $likeCount = (int)$likeCountString;
                $dislikeCount = self::$rydData->dislikes;

                $formattedLikes = self::$strings->formatNumber($likeCount);
                $formattedDislikes = self::$strings->formatNumber($dislikeCount);

                $a11yString = self::$strings->format("dislikeA11yLabel", $formattedDislikes);

                $dislikeButton->toggleButtonRenderer->accessibility = (object)[
                    "label" => $a11yString
                ];
                $dislikeButton->toggleButtonRenderer->accessibilityData = (object)[
                    "accessibilityData" => (object)[
                        "label" => $a11yString
                    ]
                ];
                $dislikeButton->toggleButtonRenderer->toggledAccessibilityData = (object)[
                    "accessibilityData" => (object)[
                        "label" => $a11yString
                    ]
                ];

                $effectiveCounts = function(string $status) use ($likeCount, $dislikeCount, $likeStatus)
                {
                    $likes = $likeCount;
                    $dislikes = $dislikeCount;
                    switch ($likeStatus . "_" . $status)
                    {
                        case "INDIFFERENT_LIKE":
                            $likes++;
                            break;
                        case "INDIFFERENT_DISLIKE":
                            $dislikes++;
                            break;
                        case "LIKE_DISLIKE":
                            $dislikes++;
                            // fall-thru
                        case "LIKE_INDIFFERENT":
                            $likes--;
                            break;
                        case "DISLIKE_LIKE":
                            $likes++;
                            // fall-thru
                        case "DISLIKE_INDIFFERENT":
                            $dislikes--;
                            break;
                    }
                    return (object)[
                        "likes" => max($likes, 0),
                        "dislikes" => max($dislikes, 0)
                    ];
                };

                $untoggledDislikeCount = $effectiveCounts("INDIFFERENT")->dislikes;
                $toggledDislikeCount = $effectiveCounts("DISLIKE")->dislikes;

                $dislikeButton->toggleButtonRenderer->defaultText = (object)[
                    "simpleText" => self::$strings->abbreviateNumber($untoggledDislikeCount)
                ];
                $dislikeButton->toggleButtonRenderer->toggledText = (object)[
                    "simpleText" => self::$strings->abbreviateNumber($toggledDislikeCount)
                ];

                $calculateSentiment = function(string $status) use ($effectiveCounts)
                {
                    $counts = $effectiveCounts($status);
                    if ($counts->likes == 0 && $counts->dislikes == 0)
                        return 50;
                    else if ($counts->dislikes == 0)
                        return 100;
                    else
                        return (int)(($counts->likes / ($counts->likes + $counts->dislikes)) * 100);
                };

                self::$primaryInfo->sentimentBar = (object)[
                    "sentimentBarRenderer" => (object)[
                        "likeStatus" => $likeStatus,
                        "percentIfLiked" => $calculateSentiment("LIKE"),
                        "percentIfIndifferent" => $calculateSentiment("INDIFFERENT"),
                        "percentIfDisliked" => $calculateSentiment("DISLIKE"),
                        "tooltip" => "$formattedLikes / $formattedDislikes"
                    ]
                ];
            }
        }

        array_splice($topLevelButtons, 0, 0, [ $likeButton, $dislikeButton ]);
    }

    private static function mutateSecondaryInfo(object &$data): void
    {
        if (isset($data->attributedDescription))
        {
            $data->description = ParsingUtils::attributedStringToFormattedString($data->attributedDescription);
            if (isset($data->description->runs))
            {
                self::fixDescLinks($data->description->runs);
                unset($data->attributedDescription);
            }
        }

        // Remove engagement panel stuff
        unset($data->showMoreCommand);
        unset($data->showLessCommand);

        $data->showMoreText = (object)[
            "simpleText" => self::$strings->get("showMore")
        ];

        $subscribeButton = null;
        if ($subscribeButton = @$data->subscribeButton->subscribeButtonRenderer)
        {
            SubscribeButtonRenderer::mutate($subscribeButton);
        }

        $owner = null;
        if ($owner = @$data->owner->videoOwnerRenderer)
        {
            // Fix artist channel badges
            if (is_array(@$owner->badges))
            {
                MetadataBadgeRenderer::fixIcons($owner->badges);
            }
        }
    }

    private static function fixDescLinks(array &$runs): void
    {
        $useDisplayNames = Config::getConfigProp("general.useDisplayNames");
        if ($useDisplayNames)
        {
            /* Can YouTube seriously fuck off? Nobody wants to see the user's handle.
               We want the display name. Fucking cunts. */
            $ucids = [];
            foreach ($runs as $run)
            if (isset($run->navigationEndpoint->browseEndpoint->browseId)
            && substr($run->navigationEndpoint->browseEndpoint->browseId, 0, 2) == "UC")
            {
                $ucids[] = $run->navigationEndpoint->browseEndpoint->browseId;
            }

            $displayNameManager = new DisplayNameManager;
            $displayNamesPromise = $displayNameManager->ensureDataAvailable($ucids);
        }
        else
        {
            $displayNamesPromise = new Promise(fn($r) => $r(null));
        }

        $displayNamesPromise->then(function () use (&$runs, &$displayNameManager, $useDisplayNames)
        {
            foreach ($runs as &$run)
        if (isset($run->navigationEndpoint))
        {
            // Video links
            if (isset($run->navigationEndpoint->watchEndpoint)
            &&  !preg_match("/^([0-9]{1,2}(:)?)+$/", $run->text)) // Prevent replacing timestamps
            {
                $run->text = self::truncate(
                    $run->navigationEndpoint->commandMetadata->webCommandMetadata->url,
                    true
                );
            }
            // Channel links
            else if (isset($run->navigationEndpoint->browseEndpoint))
            {
                switch (substr($run->navigationEndpoint->browseEndpoint->browseId, 0, 2))
                {
                    case "UC":
                        if ($useDisplayNames)
                        {
                            $run->text = "@" . $displayNameManager->getDisplayName($run->navigationEndpoint->browseEndpoint->browseId);
                        }
                        else
                        {
                            $count = 1; // This has to be a variable for some reason
                            $run->text = str_replace("\xc2\xa0", "", str_replace("/", "", $run->text, $count));
                            // Add @ if it isn't there
                            if (substr($run->text, 0, 1) != "@")
                            {
                                $run->text = "@" . $run->text;
                            }
                        }
                        break;
                    case "FE":
                        break;
                    default:
                        $run->text = self::truncate(
                            $run->navigationEndpoint->commandMetadata->webCommandMetadata->url,
                            true
                        );
                        break;
                }
            }
            // Other links which have custom styling
            else if (str_contains($run->text, "\xC2\xA0"))
            {
                $url = $run->navigationEndpoint->commandMetadata->webCommandMetadata->url;

                // Some external links (e.g. Twitter) have custom styles applied to them
                // like channel links and such. Just using the regular URL from these
                // results in a redirect link being directly displayed, so if that occurs,
                // we just extract the actual URL from the redirect URL.
                if (str_starts_with($url, "https://www.youtube.com/redirect"))
                {
                    $matches = [];
                    preg_match(self::REDIRECT_URL_REGEX, $url, $matches);
                    if (isset($matches[1]))
                    {
                        $url = urldecode($matches[1]);
                    }
                }

                $run->text = self::truncate($url);
            }
        }
        });
    }

    /**
     * Truncate a string for displaying as a description link.
     */
    private static function truncate(?string $string, bool $prefix = false): ?string
    {
        if (is_null($string))
            return null;
        if ($prefix)
            $string = "https://www.youtube.com" . $string;
        if (mb_strlen($string) <= 37)
        {
            return $string;
        }
        else
        {
            $result = ParsingUtils::mb_substr_ex($string, 0, 37);
            return $result . "...";
        }
    }

    private static function mutatePlaylist(object &$playlist): void
    {

    }
}