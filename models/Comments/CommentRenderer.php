<?php
namespace Polymerize\Model\Comments;

use Rehike\i18n\i18n;
use Rehike\ConfigManager\Config;
use Polymerize\Model\Util\ViewModelUtils;
use Polymerize\Util\ParsingUtils;

class CommentRenderer
{
    /**
     * Reconstruct a comment view model from mutation entities in response data.
     */
    public static function reconstructCommentViewModel(object $frameworkUpdates): object
    {
        // We're only given a mutation set, so we have to regenerate the comment renderer
        // from this data.
        $commentViewModel = (object)[];
        
        foreach ($frameworkUpdates->entityBatchUpdate->mutations as $mutation)
        {
            $entityKey = $mutation->entityKey;
            
            if (isset($mutation->payload->commentEntityPayload))
            {
                $commentViewModel->commentKey = $entityKey;
            }
            else if (isset($mutation->payload->engagementToolbarStateEntityPayload))
            {
                $commentViewModel->toolbarStateKey = $entityKey;
            }
            else if (isset($mutation->payload->engagementToolbarSurfaceEntityPayload))
            {
                $commentViewModel->toolbarSurfaceKey = $entityKey;
            }
            else if (isset($mutation->payload->commentSurfaceEntityPayload))
            {
                $commentViewModel->commentSurfaceKey = $entityKey;
            }
            else if (isset($mutation->payload->commentPinnedStateEntityPayload))
            {
                // I have no idea where this is exposed in CommentViewModel
            }
        }
        
        return (object)[
            "commentViewModel" => $commentViewModel
        ];
    }

    public static function fromViewModel(object &$viewModel, object $frameworkUpdates): ?object
    {
        if (isset($viewModel->commentThreadRenderer))
            return $viewModel;

        $viewModelInner = null;
        if (!($viewModelInner = @$viewModel->commentViewModel))
            return null;

        $entities = ViewModelUtils::findEntities([
            "comment" => $viewModelInner->commentKey,
            "commentSurface" => $viewModelInner->commentSurfaceKey,
            "toolbarState" => $viewModelInner->toolbarStateKey,
            "toolbarSurface" => $viewModelInner->toolbarSurfaceKey
        ], $frameworkUpdates);

        $comment = @$entities->comment->commentEntityPayload ?? null;
        $commentSurface = @$entities->commentSurface->commentSurfaceEntityPayload ?? null;
        $toolbarState = @$entities->toolbarState->engagementToolbarStateEntityPayload ?? null;
        $toolbarSurface = @$entities->toolbarSurface->engagementToolbarSurfaceEntityPayload ?? null;
        if (is_null($comment)
        || is_null($commentSurface)
        || is_null($toolbarState)
        || is_null($toolbarSurface))
            return null;

        $strings = i18n::getNamespace("comments");
        $result = (object)[];

        $likeState = str_replace("TOOLBAR_LIKE_STATE_", "", $toolbarState->likeState);

        $result->commentId = $comment->properties->commentId;
        $result->contentText = ParsingUtils::attributedStringToFormattedString($comment->properties->content);
        $result->publishedTimeText = (object)[
            "runs" => [
                (object)[
                    "text" => $comment->properties->publishedTime,
                    "navigationEndpoint" => $commentSurface->publishedTimeCommand->innertubeCommand
                ]
            ]
        ];
        $result->voteCount = (object)[
            "accessibility" => (object)[
                "accessibilityData" => (object)[
                    "label" => $comment->toolbar->likeCountA11y
                ]
            ],
            "simpleText" => ($likeState == "LIKED")
                ? $comment->toolbar->likeCountLiked
                : $comment->toolbar->likeCountNotliked
        ];

        // Fixes frontend hiding of vote count element
        if (trim($result->voteCount->simpleText) == "")
        {
            $result->voteCount->simpleText = "0";
        }

        $result->voteStatus = $likeState;
        $result->isLiked = ($likeState == "LIKED");

        $result->authorThumbnail = (object)[
            "accessibility" => (object)[
                "accessibilityData" => (object)[
                    "label" => $comment->avatar->accessibilityText
                ]
            ],
            "thumbnails" => $comment->avatar->image->sources
        ];
        $result->authorText = (object)[
            "simpleText" => $comment->author->displayName
        ];
        
        if (isset($comment->author->channelPageEndpoint->innertubeCommand))
        {
            $result->authorEndpoint = $comment->author->channelPageEndpoint->innertubeCommand;

            /* For comment parsing by the display name manager */
            if (!isset($result->authorEndpoint->browseEndpoint))
            {
                $result->authorEndpoint->browseEndpoint = (object)[
                    "browseId" => $comment->author->channelId
                ];
            }
        }
        else
        {
            $channelId = $comment->author->channelId;
            
            $result->authorEndpoint = (object)[
                "browseEndpoint" => (object)[
                    "browseId" => $channelId
                ],
                "commandMetadata" => (object)[
                    "webCommandMetadata" => (object)[
                        "url" => "/channel/$channelId"
                    ]
                ]
            ];
        }
        
        $result->authorIsChannelOwner = $comment->author->isCreator;

        if ($comment->author->isCreator
        || $comment->author->isVerified
        || $comment->author->isArtist)
        {
            $authorCommentBadge = (object)[
                "authorText" => $result->authorText,
                "authorEndpoint" => $result->authorEndpoint
            ];
            if ($comment->author->isVerified || $comment->author->isArtist)
            {
                $verifiedIcon = (!$comment->author->isCreator && Config::getConfigProp("comments.fixVerifiedIcon"))
                    ? "CHECK_CIRCLE_THICK" : "CHECK";
                $authorCommentBadge->icon = (object)[
                    "iconType" => $comment->author->isArtist ? "MUSIC_NOTE" : $verifiedIcon
                ];
            }

            if ($comment->author->isCreator)
            {
                $authorCommentBadge->color = (object)[
                    "basicColorPaletteData" => (object)[
                        "backgroundColor" => 4287137928,
                        "foregroundTitleColor" => 4294967295
                    ]
                ];
            }

            $result->authorCommentBadge = (object)[
                "authorCommentBadgeRenderer" => $authorCommentBadge
            ];
        }

        if (isset($viewModelInner->pinnedText))
        {
            $result->pinnedCommentBadge = (object)[
                "pinnedCommentBadgeRenderer" => (object)[
                    "label" => (object)[
                        "simpleText" => $viewModelInner->pinnedText
                    ],
                    "icon" => (object)[
                        "iconType" => "KEEP"
                    ]
                ]
            ];
        }

        if (isset($viewModelInner->linkedCommentText))
        {
            $result->linkedCommentBadge = (object)[
                "metadataBadgeRenderer" => (object)[
                    "label" => $viewModelInner->linkedCommentText,
                    "style" => "BADGE_STYLE_TYPE_SIMPLE"
                ]
            ];
        }

        $result->expandButton = (object)[
            "buttonRenderer" => (object)[
                "style" => "STYLE_TEXT",
                "size" => "SIZE_DEFAULT",
                "text" => (object)[
                    "simpleText" => $strings->get("readMore")
                ],
                "accessibility" => (object)[
                    "label" => $strings->get("readMore")
                ]
            ]
        ];

        $result->collapseButton = (object)[
            "buttonRenderer" => (object)[
                "style" => "STYLE_TEXT",
                "size" => "SIZE_DEFAULT",
                "text" => (object)[
                    "simpleText" => $strings->get("showLess")
                ],
                "accessibility" => (object)[
                    "label" => $strings->get("showLess")
                ]
            ]
        ];

        if (isset($toolbarSurface->menuCommand->innertubeCommand->menuEndpoint->menu))
        {
            $result->actionMenu = $toolbarSurface->menuCommand->innertubeCommand->menuEndpoint->menu;
        }

        $actionButtons = (object)[
            "style" =>
                Config::getConfigProp("comments.leftReplyButton")
                    ? "COMMENT_ACTION_BUTTON_STYLE_TYPE_DEFAULT"
                    : "COMMENT_ACTION_BUTTON_STYLE_TYPE_DESKTOP_TOOLBAR" 
        ];

        $paused = isset($toolbarSurface->commentDisabledActionCommand->innertubeCommand);

        $actionButtons->likeButton = (object)[
            "toggleButtonRenderer" => (object)[
                "style" => (object)[
                    "styleType" => "STYLE_TEXT"
                ],
                "size" => (object)[
                    "sizeType" => "SIZE_DEFAULT"
                ],
                "isToggled" => ($likeState == "LIKED"),
                "isDisabled" => false,
                "defaultIcon" => (object)[
                    "iconType" => "LIKE"
                ],
                "defaultTooltip" => $comment->toolbar->likeInactiveTooltip,
                "toggledTooltip" => $comment->toolbar->likeActiveTooltip,
                "toggledStyle" => (object)[
                    "styleType" => $paused ? "STYLE_TEXT" : "STYLE_DEFAULT_ACTIVE"
                ],
                "accessibilityData" => (object)[
                    "accessibilityData" => (object)[
                        "label" => $comment->toolbar->likeButtonA11y
                    ]
                ],
                "toggledAccessibilityData" => (object)[
                    "accessibilityData" => (object)[
                        "label" => $comment->toolbar->likeActiveTooltip
                    ]
                ]
            ]
        ];

        $actionButtons->dislikeButton = (object)[
            "toggleButtonRenderer" => (object)[
                "style" => (object)[
                    "styleType" => "STYLE_TEXT"
                ],
                "size" => (object)[
                    "sizeType" => "SIZE_DEFAULT"
                ],
                "isToggled" => ($likeState == "DISLIKED"),
                "isDisabled" => false,
                "defaultIcon" => (object)[
                    "iconType" => "DISLIKE"
                ],
                "defaultTooltip" => $comment->toolbar->dislikeInactiveTooltip,
                "toggledTooltip" => $comment->toolbar->dislikeActiveTooltip,
                "toggledStyle" => (object)[
                    "styleType" => $paused ? "STYLE_TEXT" : "STYLE_DEFAULT_ACTIVE"
                ],
                "accessibilityData" => (object)[
                    "accessibilityData" => (object)[
                        "label" => $comment->toolbar->dislikeInactiveTooltip
                    ]
                ],
                "toggledAccessibilityData" => (object)[
                    "accessibilityData" => (object)[
                        "label" => $comment->toolbar->dislikeActiveTooltip
                    ]
                ]
            ]
        ];

        $setLikeEndpoint = function (object &$data, bool $toggled, object $endpoint)
        {
            if ($toggled && !isset($endpoint->performCommentActionEndpoint))
                return;

            $member = $toggled
                ? "toggledServiceEndpoint"
                : (isset($endpoint->performCommentActionEndpoint)
                    ? "defaultServiceEndpoint"
                    : "defaultNavigationEndpoint");

            $data->toggleButtonRenderer->{$member} = $endpoint;
        };

        $signedIn = !isset($toolbarSurface->prepareAccountCommand->innertubeCommand);
        

        if ($signedIn && !$paused)
        {
            $setLikeEndpoint($actionButtons->likeButton,    false,      $toolbarSurface->likeCommand->innertubeCommand);
            $setLikeEndpoint($actionButtons->likeButton,    true,     $toolbarSurface->unlikeCommand->innertubeCommand);
            $setLikeEndpoint($actionButtons->dislikeButton, false,   $toolbarSurface->dislikeCommand->innertubeCommand);
            $setLikeEndpoint($actionButtons->dislikeButton, true,  $toolbarSurface->undislikeCommand->innertubeCommand);
        }
        else if ($paused)
        {
            $setLikeEndpoint($actionButtons->likeButton,    false, $toolbarSurface->commentDisabledActionCommand->innertubeCommand);
            $setLikeEndpoint($actionButtons->dislikeButton, false, $toolbarSurface->commentDisabledActionCommand->innertubeCommand);
        }
        else
        {
            $setLikeEndpoint($actionButtons->likeButton,    false, $toolbarSurface->prepareAccountCommand->innertubeCommand);
            $setLikeEndpoint($actionButtons->dislikeButton, false, $toolbarSurface->prepareAccountCommand->innertubeCommand);
        }

        $isHearted = !in_array($toolbarState->heartState, [
            "TOOLBAR_HEART_STATE_UNHEARTED",
            "TOOLBAR_HEART_STATE_UNHEARTED_EDITABLE"
        ]);
        $isHeartEditable = in_array($toolbarState->heartState, [
            "TOOLBAR_HEART_STATE_UNHEARTED_EDITABLE",
            "TOOLBAR_HEART_STATE_HEARTED_EDITABLE"
        ]);
        if ($isHearted || $isHeartEditable)
        {
            $actionButtons->creatorHeart = (object)[
                "creatorHeartRenderer" => (object)[
                    "creatorThumbnail" => (object)[
                        "thumbnails" => [
                            (object)[
                                "url" => $comment->toolbar->creatorThumbnailUrl,
                                "width" => 88,
                                "height" => 88
                            ]
                        ]
                    ],
                    "unheartedTooltip" => @$comment->toolbar->heartInactiveTooltip ?? null,
                    "heartedTooltip" => $comment->toolbar->heartActiveTooltip,
                    "heartedAccessibility" => (object)[
                        "accessibilityData" => (object)[
                            "label" => $strings->get("heart")
                        ]
                    ],
                    "unheartedAccessibility" => (object)[
                        "accessibilityData" => (object)[
                            "label" => $strings->get("heart")
                        ]
                    ],
                    "heartIcon" => (object)[
                        "iconType" => "FULL_HEART"
                    ],
                    "heartColor" => (object)[
                        "basicColorPaletteData" => (object)[
                            "foregroundTitleColor" => 4294901760
                        ]
                    ],
                    "heartEndpoint" => @$toolbarSurface->heartCommand->innertubeCommand ?? null,
                    "unheartEndpoint" => @$toolbarSurface->unheartCommand->innertubeCommand ?? null,
                    "isEnabled" => $isHeartEditable,
                    "isHearted" => $isHearted,
                ]
            ];
        }

        if (isset($toolbarSurface->replyCommand->innertubeCommand))
        {
            $actionButtons->replyButton = (object)[
                "buttonRenderer" => (object)[
                    "navigationEndpoint" => $paused
                        ? $toolbarSurface->commentDisabledActionCommand->innertubeCommand
                        : $toolbarSurface->replyCommand->innertubeCommand,
                    "text" => (object)[
                        "simpleText" => $strings->get("reply")
                    ],
                    "size" => "SIZE_DEFAULT",
                    "style" => "STYLE_TEXT",
                    "isDisabled" => false
                ]
            ];
        }

        $result->actionButtons = (object)[
            "commentActionButtonsRenderer" => $actionButtons
        ];

        return (object)[
            "commentRenderer" => $result
        ];
    }
}