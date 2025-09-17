<?php
namespace Polymerize\Model\Common;

use Rehike\i18n\i18n;
use Polymerize\Util\ParsingUtils;

class LockupViewModel
{
    public static function toLegacyRenderer(object &$viewModel, string $type = ""): ?object
    {
        $viewModelInner = null;
        if (!($viewModelInner = @$viewModel->lockupViewModel))
            return $viewModel;

        $propName = match($viewModelInner->contentType)
        {
            "LOCKUP_CONTENT_TYPE_PLAYLIST" => "playlistRenderer",
            "LOCKUP_CONTENT_TYPE_VIDEO" => "videoRenderer",
            default => "FIXME"
        };
        if ($type != "")
            $propName = $type . ucfirst($propName);

        $result = (object)[];
        
        switch ($viewModelInner->contentType)
        {
            case "LOCKUP_CONTENT_TYPE_VIDEO":
                self::convertVideo($viewModelInner, $result);
                break;
            case "LOCKUP_CONTENT_TYPE_PLAYLIST":
                self::convertPlaylist($viewModelInner, $result, $propName);
                break;
        }

        return (object)[
            $propName => $result
        ];
    }

    private static function convertVideo(object &$viewModel, object &$out): void
    {
        $metadata = $viewModel->metadata->lockupMetadataViewModel;
        $out->title = (object)[
            "simpleText" => $metadata->title->content
        ];
        $out->videoId = $viewModel->contentId;

        $thumbnail = $viewModel->contentImage->thumbnailViewModel;
        $out->thumbnail = (object)[
            "thumbnails" => $thumbnail->image->sources
        ];

        if (isset($metadata->image))
        {
            $image = $metadata->image->decoratedAvatarViewModel;
            $avatar = $image->avatar->avatarViewModel;
            $out->owner = (object)[
                "acccessibility" => (object)[
                    "accessibilityData" => (object)[
                        "label" => $image->a11yLabel
                    ]
                ],
                "thumbnail" => (object)[
                    "thumbnails" => $avatar->image->sources
                ],
                "navigationEndpoint" => $image->rendererContext->commandContext->onTap->innertubeCommand
            ];
        }

        if (is_array(@$thumbnail->overlays))
        {
            $out->thumbnailOverlays = [];
            foreach ($thumbnail->overlays as $overlay)
            foreach ($overlay as $name => $content)
            {
                switch ($name)
                {
                    case "animatedThumbnailOverlayViewModel":
                        $out->richThumbnail = (object)[
                            "movingThumbnailRenderer" => (object)[
                                "enableHoveredLogging" => true,
                                "enableOverlay" => true,
                                "movingThumbnailDetails" => (object)[
                                    "logAsMovingThumbnail" => true,
                                    "thumbnails" => $content->thumbnail->sources
                                ]
                            ]
                        ];
                        break;
                    case "thumbnailOverlayBadgeViewModel":
                        foreach ($content->thumbnailBadges as $tbadge)
                        {
                            $tcontent = $tbadge->thumbnailBadgeViewModel;
                            switch ($tcontent->badgeStyle)
                            {
                                case "THUMBNAIL_OVERLAY_BADGE_STYLE_LIVE":
                                    $strings = i18n::getNamespace("misc");
                                    $text = $tcontent->text;
                                    $text = ($text == $strings->get("liveBadgeTextMatch"))
                                        ? $strings->get("liveBadgeText")
                                        : $text;
                                    if (!isset($out->badges))
                                        $out->badges = [];
                                    $out->badges[] = (object)[
                                        "metadataBadgeRenderer" => (object)[
                                            "label" => $text,
                                            "style" => "BADGE_STYLE_TYPE_LIVE_NOW"
                                        ]
                                    ];
                                    break;
                                default:
                                    if (!isset($tcontent->text))
                                        break;
                                    
                                    $out->thumbnailOverlays[] = (object)[
                                        "thumbnailOverlayTimeStatusRenderer" => (object)[
                                            "style" => "DEFAULT",
                                            "text" => (object)[
                                                "accessibility" => (object)[
                                                    "accessibilityData" => (object)[
                                                        "label" => @$tcontent->rendererContext->accessibilityContext->label ?? ""
                                                    ]
                                                ],
                                                "simpleText" => $tcontent->text
                                            ]
                                        ]
                                    ];
                                    $out->thumbnailOverlays[] = (object)[
                                        "thumbnailOverlayNowPlayingRenderer" => (object)[
                                            "text" => (object)[
                                                "runs" => [
                                                    (object)[
                                                        "text" => $tcontent->animatedText
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ];
                                    break;
                            }
                            
                        }
                        break;
                    case "thumbnailHoverOverlayToggleActionsViewModel":
                        foreach ($content->buttons as $button)
                        {
                            $convertedButton = ToggleButtonRenderer::fromViewModel($button);
                            $convertedButton = $convertedButton->toggleButtonRenderer;
                            $memberMap = [
                                "defaultIcon" => "untoggledIcon",
                                "defaultServiceEndpoint" => "untoggledServiceEndpoint",
                                "accessibilityData" => "untoggledAccessibility",
                                "toggledAccessibilityData" => "toggledAccessibility",
                            ];
                            foreach ($memberMap as $name => $newName)
                            {
                                if (isset($convertedButton->{$name}))
                                {
                                    $convertedButton->{$newName} = $convertedButton->{$name};
                                    unset($convertedButton->{$name});
                                }
                            }

                            $convertedButton->untoggledTooltip = $convertedButton->accessibility->label;
                            $convertedButton->toggledTooltip = $convertedButton->toggledAccessibility->accessibilityData->label;

                            $out->thumbnailOverlays[] = (object)[
                                "thumbnailOverlayToggleButtonRenderer" => $convertedButton
                            ];
                        }
                        break;
                    case "thumbnailBottomOverlayViewModel":
                    {
                        $out->thumbnailOverlays[] = (object)[
                            "thumbnailOverlayResumePlaybackRenderer" => (object)[
                                "percentDurationWatched" => $content->progressBar->thumbnailOverlayProgressBarViewModel->startPercent
                            ]
                        ];
                        
                        $badge = @$content->badges[0]->thumbnailBadgeViewModel ?? null;
                        if (!is_null($badge))
                        {
                            $out->thumbnailOverlays[] = (object)[
                                "thumbnailOverlayTimeStatusRenderer" => (object)[
                                    "style" => "DEFAULT",
                                    "text" => (object)[
                                        "accessibility" => (object)[
                                            "accessibilityData" => (object)[
                                                "label" => @$badge->rendererContext->accessibilityContext->label ?? ""
                                            ]
                                        ],
                                        "simpleText" => $badge->text
                                    ]
                                ]
                            ];
                            $out->thumbnailOverlays[] = (object)[
                                "thumbnailOverlayNowPlayingRenderer" => (object)[
                                    "text" => (object)[
                                        "runs" => [
                                            (object)[
                                                "text" => $badge->animatedText
                                            ]
                                        ]
                                    ]
                                ]
                            ];
                        }
                        break;
                    }
                }
            }
        }
    
        $metadataRows = $metadata->metadata->contentMetadataViewModel->metadataRows;
        foreach ($metadataRows as $rowId => $contents)
        {
            if (isset($contents->metadataParts))
            foreach ($contents->metadataParts as $partId => $part)
            {
                $hasBadges = isset($metadataRows[array_key_last($metadataRows)]->badges);
                $rowCount = count($metadataRows);
                // Author text.
                if ($rowId == 0 &&
                ((!$hasBadges && $rowCount == 2) || ($hasBadges && $rowCount == 3)))
                {
                    $bylineText = ParsingUtils::attributedStringToFormattedString($part->text);
                    $out->shortBylineText = $bylineText;
                    $out->longBylineText = $bylineText;

                    // Just fucking kill me now.
                    $badgeIcon = @$part->text->attachmentRuns[0]->element->type->imageType->image->sources[0]->clientResource->imageName ?? null;
                    $badgeIcon = match ($badgeIcon)
                    {
                        "CHECK_CIRCLE_FILLED" => "CHECK_CIRCLE_THICK",
                        "AUDIO_BADGE" => "MUSIC_NOTE",
                        default => null
                    };
                    if (!is_null($badgeIcon))
                    {
                        $out->ownerBadges = [
                            (object)[
                                "metadataBadgeRenderer" => (object)[
                                    "icon" => (object)[
                                        "iconType" => $badgeIcon
                                    ],
                                    "style" => "BADGE_STYLE_TYPE_VERIFIED"
                                ]
                            ]
                        ];
                    }
                }
                // Below byline (view count and date)
                else if (($rowCount == 1)
                || (!$hasBadges && $rowCount == 2 && $rowId == 1)
                || ($hasBadges && $rowCount == 3 && $rowId == 1))
                {
                    $memberName = match ($partId)
                    {
                        0 => "shortViewCountText",
                        1 => "publishedTimeText",
                        default => null
                    };
                    if (!is_null($memberName))
                    {
                        $out->{$memberName} = ParsingUtils::attributedStringToFormattedString($part->text);
                    }
                }
            }
            
            if (isset($contents->badges))
            foreach ($contents->badges as $badge)
            {
                if (!isset($out->badges))
                    $out->badges = [];

                $badgeInner = $badge->badgeViewModel;
                $out->badges[] = (object)[
                    "metadataBadgeRenderer" => (object)[
                        "label" => $badgeInner->badgeText,
                        "style" => match ($badgeInner->badgeStyle)
                        {
                            "BADGE_COMMERCE" => "BADGE_STYLE_TYPE_YPC",
                            default => "BADGE_STYLE_TYPE_SIMPLE"
                        }
                    ]
                ];
            }
        }

        // WHY WHY WHY WHY WHY WHY WHY WHY WHY WHY WHY WHY WHY WHY
        $menu = null;
        if ($menu = @$metadata->menuButton->buttonViewModel->onTap->innertubeCommand->showSheetCommand->panelLoadingStrategy
            ->inlineContent->sheetViewModel->content)
        {
            $out->menu = MenuRenderer::fromViewModel($menu);
        }

        $out->navigationEndpoint = $viewModel->rendererContext->commandContext->onTap->innertubeCommand;
    }

    private static function convertPlaylist(object &$viewModel, object &$out, string &$propName): void
    {
        $metadata = $viewModel->metadata->lockupMetadataViewModel;
        $thumbnail = $viewModel->contentImage->collectionThumbnailViewModel->primaryThumbnail->thumbnailViewModel;

        $out->title = (object)[
            "simpleText" => $metadata->title->content
        ];
        $out->playlistId = $viewModel->contentId;
        $out->thumbnail = (object)[
            "thumbnails" => $thumbnail->image->sources
        ];
        $out->thumbnailRenderer = (object)[
            "playlistVideoThumbnailRenderer" => (object)[
                "thumbnail" => (object)[
                    "thumbnails" => $thumbnail->image->sources
                ]
            ]
        ];
        $out->navigationEndpoint = $viewModel->rendererContext->commandContext->onTap->innertubeCommand;
        
        $strings = i18n::getNamespace("regex");
        $out->thumbnailOverlays = [];
        $isMix = false;
        foreach ($thumbnail->overlays as $overlay)
        {
            if (isset($overlay->thumbnailOverlayBadgeViewModel))
            {
                $videoCountText = $overlay->thumbnailOverlayBadgeViewModel->thumbnailBadges[0]->thumbnailBadgeViewModel->text;
                $videoCountText = preg_replace($strings->get("videoCountIsolator"), "", $videoCountText);
                if ($videoCountText == $strings->get("mixMatch"))
                {
                    $videoCountText = "50+";
                    $isMix = true;
                    $propName = str_replace("Playlist", "Radio", $propName);
                    $propName = str_replace("playlist", "radio", $propName);
                }

                $out->thumbnailOverlays[] = (object)[
                    "thumbnailOverlaySidePanelRenderer" => (object)[
                        "text" => (object)[
                            "simpleText" => $videoCountText
                        ],
                        "icon" => (object)[
                            "iconType" => $isMix ? "MIX" : "PLAYLISTS"
                        ]
                    ]
                ];
            }
            else if (isset($overlay->thumbnailHoverOverlayViewModel))
            {
                $text = $overlay->thumbnailHoverOverlayViewModel->text->content;
                $out->thumbnailOverlays[] = (object)[
                    "thumbnailOverlayHoverTextRenderer" => (object)[
                        "text" => (object)[
                            "simpleText" => $text
                        ],
                        "icon" => (object)[
                            "iconType" => "PLAY_ALL"
                        ]
                    ]
                ];
            }
        }

        $metadataRows = $metadata->metadata->contentMetadataViewModel->metadataRows;
        foreach ($metadataRows as $rowId => $contents)
        {
            if (isset($contents->metadataParts))
            {
                $parts = $contents->metadataParts;
                
                if (isset($parts[0]->text->commandRuns[0]->onTap->innertubeCommand))
                {
                    $endpoint = $parts[0]->text->commandRuns[0]->onTap->innertubeCommand;
                    
                    if (isset($endpoint->commandMetadata->webCommandMetadata->webPageType))
                    {
                        $webPageType = $endpoint->commandMetadata->webCommandMetadata->webPageType;
                        
                        if ($webPageType == "WEB_PAGE_TYPE_CHANNEL" && $rowId == 0 && !isset($out->shortBylineText))
                        {
                            // This is almost certainly a link to the channel of the creator of the playlist.
                            $bylineText = ParsingUtils::attributedStringToFormattedString($parts[0]->text);
                            $out->shortBylineText = $bylineText;
                            $out->longBylineText = $bylineText;

                            // Just fucking kill me now.
                            $badgeIcon = @$parts[0]->text->attachmentRuns[0]->element->type->imageType->image->sources[0]->clientResource->imageName ?? null;
                            $badgeIcon = match ($badgeIcon)
                            {
                                "CHECK_CIRCLE_FILLED" => "CHECK_CIRCLE_THICK",
                                "AUDIO_BADGE" => "MUSIC_NOTE",
                                default => null
                            };
                            if (!is_null($badgeIcon))
                            {
                                $out->ownerBadges = [
                                    (object)[
                                        "metadataBadgeRenderer" => (object)[
                                            "icon" => (object)[
                                                "iconType" => $badgeIcon
                                            ],
                                            "style" => "BADGE_STYLE_TYPE_VERIFIED"
                                        ]
                                    ]
                                ];
                            }
                        }
                        else if ($webPageType == "WEB_PAGE_TYPE_PLAYLIST" && !isset($out->viewPlaylistText))
                        {
                            // View full playlist link
                            $out->viewPlaylistText = ParsingUtils::attributedStringToFormattedString($parts[0]->text);
                        }
                        else if ($webPageType == "WEB_PAGE_TYPE_WATCH")
                        {
                            // Playlist contents preview.
                            if (!isset($out->videos))
                            {
                                $out->videos = [];
                            }
                            
                            // The title has the time embedded. Dirty hack to isolate it:
                            $titleParts = explode(" · ", ParsingUtils::getText($parts[0]->text));
                            $lengthText = $titleParts[count($titleParts) - 1];
                            array_pop($titleParts);
                            $title = implode(" · ", $titleParts);
                            
                            $out->videos[] = (object)[
                                "childVideoRenderer" => (object)[
                                    "title" => (object)[
                                        "simpleText" => $title
                                    ],
                                    "navigationEndpoint" => $endpoint,
                                    "lengthText" => (object)[
                                        "simpleText" => $lengthText
                                    ]
                                ]
                            ];
                        }
                    }
                }
                else if (preg_match($strings->get("playlistUpdatedMatch"), $parts[0]->text->content))
                {
                    $out->publishedTimeText = (object)[
                        "simpleText" => $parts[0]->text->content
                    ];
                }
                else if ($isMix)
                {
                    switch ($rowId)
                    {
                        case 0:
                            $out->shortBylineText = ParsingUtils::attributedStringToFormattedString($parts[0]->text);
                            break;
                        case 1:
                            $out->publishedTimeText = (object)[
                                "simpleText" => $parts[0]->text->content
                            ];
                    }
                }
            }
        }

        // WHY WHY WHY WHY WHY WHY WHY WHY WHY WHY WHY WHY WHY WHY
        $menu = null;
        if ($menu = @$metadata->menuButton->buttonViewModel->onTap->innertubeCommand->showSheetCommand->panelLoadingStrategy
            ->inlineContent->sheetViewModel->content)
        {
            $out->menu = MenuRenderer::fromViewModel($menu);
        }
    }
}