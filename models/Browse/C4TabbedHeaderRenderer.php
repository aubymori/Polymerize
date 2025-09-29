<?php
namespace Polymerize\Model\Browse;

use Polymerize\Model\Common\ButtonRenderer;
use Polymerize\Model\Common\SubscribeButtonRenderer;
use Rehike\ConfigManager\Config;
use Rehike\i18n\i18n;

class C4TabbedHeaderRenderer
{
    private static array $badgeIconMap = [
        "CHECK_CIRCLE_FILLED" => "CHECK_CIRCLE_THICK",
        "AUDIO_BADGE" => "OFFICIAL_ARTIST_BADGE"
    ];

    public static function fromViewModel(object &$viewModel, bool $loggedIn, ?object $frameworkUpdates): object
    {
        $strings = i18n::getNamespace("regex");

        $viewModelInner = null;
        if (!($viewModelInner = @$viewModel->pageHeaderViewModel))
            return $viewModel;

        $result = (object)[];

        $result->title = $viewModelInner->title->dynamicTextViewModel->text->content;

        // SIGH.
        if (is_array(@$viewModelInner->title->dynamicTextViewModel->text->attachmentRuns))
        foreach ($viewModelInner->title->dynamicTextViewModel->text->attachmentRuns as $aruns)
        {
            if ($icon = @$aruns->element->type->imageType->image->sources[0]->clientResource->imageName)
            {
                if (!isset($result->badges))
                    $result->badges = [];

                $result->badges[] = (object)[
                    "metadataBadgeRenderer" => (object)[
                        "icon" => (object)[
                            "iconType" => @self::$badgeIconMap[$icon] ?? "CHECK_CIRCLE_THICK"
                        ],
                        "style" => "BADGE_STYLE_TYPE_VERIFIED",
                        "tooltip" => "Verified"
                    ]
                ];
            }
        }

        // WHY is this different. They both do the same shit. Make an img element with a
        // src attribute. I hate modern InnerTube.
        if (isset($viewModelInner->animatedImage))
        {
            $result->avatar = (object)[
                "thumbnails" => $viewModelInner->animatedImage->contentPreviewImageViewModel->image->sources
            ];
        }
        else
        {
            $result->avatar = (object)[
                "thumbnails" => @$viewModelInner->image->decoratedAvatarViewModel->avatar->avatarViewModel->image->sources ?? []
            ];
        }
        if (isset($viewModelInner->banner))
        {
            $result->banner = (object)[
                "thumbnails" => $viewModelInner->banner->imageBannerViewModel->image->sources
            ];
        }

        if (is_array(@$viewModelInner->metadata->contentMetadataViewModel->metadataRows))
        foreach ($viewModelInner->metadata->contentMetadataViewModel->metadataRows as $row)
        {
            $text = @$row->metadataParts[0]->text->content ?? null;
            if (!is_null($text))
            {
                if (!isset($result->subscriberCountText) && preg_match($strings->get("subscriberCountIsolator"), $text))
                {
                    $result->subscriberCountText = (object)[
                        "simpleText" => $text
                    ];
                }
            }
        }

        if (is_array(@$viewModelInner->actions->flexibleActionsViewModel->actionsRows[0]->actions))
        foreach ($viewModelInner->actions->flexibleActionsViewModel->actionsRows[0]->actions as $action)
        {
            switch (true)
            {
                // Subscribe button
                case isset($action->subscribeButtonViewModel):
                    $subscribeButton = SubscribeButtonRenderer::fromViewModel($action, $frameworkUpdates);
                    if (Config::getConfigProp("appearance.subCountOnSubButton") && isset($result->subscriberCountText))
                    {
                        SubscribeButtonRenderer::addSubscriberCount(
                            $subscribeButton->subscribeButtonRenderer, 
                            $result->subscriberCountText->simpleText
                        );
                    }
                    $result->subscribeButton = $subscribeButton;
                    break;
                // Join button
                case isset($action->buttonViewModel->onTap->innertubeCommand->commandExecutorCommand->commands[0]->ypcGetOffersEndpoint):
                {
                    $button = ButtonRenderer::fromViewModel($action, "command");
                    $button->buttonRenderer->style = "STYLE_SUGGESTIVE";
                    $result->sponsorButton = $button;
                    break;
                }
                // Signed out subscribe button stub
                case (@$action->buttonViewModel->type == "BUTTON_VIEW_MODEL_TYPE_FILLED"):
                {
                    $button = ButtonRenderer::fromViewModel($action, "navigationEndpoint");
                    $button->buttonRenderer->style = "STYLE_DESTRUCTIVE";
                    if (Config::getConfigProp("appearance.subCountOnSubButton") && isset($result->subscriberCountText))
                    {
                        SubscribeButtonRenderer::addSubscriberCount(
                            $button->buttonRenderer, 
                            $result->subscriberCountText->simpleText
                        );
                    }
                    $result->subscribeButton = $button;
                    break;
                }
                // All other buttons (channel edit buttons and signed out join button stub)
                default:
                {
                    if (!isset($action->buttonViewModel))
                        break;

                    if (!isset($result->editChannelButtons))
                        $result->editChannelButtons = [];

                    $button = ButtonRenderer::fromViewModel($action, "navigationEndpoint");
                    // Logged in: channel edit buttons
                    // Logged out: join button stub
                    $button->buttonRenderer->style = 
                        $loggedIn ? "STYLE_PRIMARY" : "STYLE_SUGGESTIVE";
                    $result->editChannelButtons[] = $button;
                    break;
                }
            }
        }

        return (object)[
            "c4TabbedHeaderRenderer" => $result
        ];
    }
}