<?php
namespace Polymerize\Model\Common;

use Polymerize\Model\Util\ViewModelUtils;
use Rehike\i18n\i18n;

/**
 * Mutates the subscribeButtonRenderer to match 2021 Polymer
 * and earlier.
 */
class SubscribeButtonRenderer
{
    /**
     * Mutates a subscribeButtonRenderer object.
     * 
     * @param object &$data Reference to the subscribeButtonRenderer object to mutate.
     */
    public static function mutate(object &$data): void
    {
        /* Remove the "Unsubscribe" menu item from the notification button. */
        $notificationButton = null;
        if ($notificationButton = @$data->notificationPreferenceButton->subscriptionNotificationToggleButtonRenderer)
        {
            SubscriptionNotificationToggleButtonRenderer::mutate($notificationButton);
        }

        if (!isset($data->subscribedButtonText))
        {
            $strings = i18n::getNamespace("misc");  
            $data->subscribedButtonText = (object)[
                "runs" => [
                    (object)[
                        "text" => $strings->get("subscribedButtonText")
                    ]
                ]
            ];
        }
    }

    /**
     * Converts a subscribeButtonViewModel to a subscribeButtonRenderer.
     */
    public static function fromViewModel(object &$viewModel, object $frameworkUpdates): object
    {
        $viewModelInner = null;
        if (!($viewModelInner = @$viewModel->subscribeButtonViewModel))
            return $viewModel;

        $result = (object)[];

        $entities = ViewModelUtils::findEntities([
            "state" => @$viewModelInner->stateEntityStoreKey ?? null,
            "notificationState" => @$viewModelInner->notificationStateEntityStoreKeys->subsNotificationStateKey ?? null
        ], $frameworkUpdates);
        $state = @$entities->state->subscriptionStateEntity ?? null;
        $notificationState = @$entities->notificationState->subscriptionNotificationStateEntity ?? null;
        if (is_null($state) || is_null($notificationState))
        {
            return $viewModel;
        }

        $result->subscribed = $state->subscribed;
        $result->enabled = true;

        $result->unsubscribedButtonText = (object)[
            "runs" => [
                (object)[
                    "text" => $viewModelInner->subscribeButtonContent->buttonText
                ]
            ]
        ];
        $result->subscribedButtonText = (object)[
            "runs" => [
                (object)[
                    "text" => $viewModelInner->unsubscribeButtonContent->buttonText
                ]
            ]
        ];

        $result->subscribeAccessibility = (object)[
            "accessibilityData" => (object)[
                "label" => $viewModelInner->subscribeButtonContent->accessibilityText
            ]
        ];
        $result->unsubscribeAccessibility = (object)[
            "accessibilityData" => (object)[
                "label" => $viewModelInner->unsubscribeButtonContent->accessibilityText
            ]
        ];
        
        $result->onSubscribeEndpoints = [
            $viewModelInner->subscribeButtonContent->onTapCommand->innertubeCommand
        ];
        // :PepeMods:
        if (is_array(@$viewModelInner->onShowSubscriptionOptions->innertubeCommand->showSheetCommand->panelLoadingStrategy
            ->inlineContent->sheetViewModel->content->listViewModel->listItems))
        {
            foreach ($viewModelInner->onShowSubscriptionOptions->innertubeCommand->showSheetCommand->panelLoadingStrategy
                ->inlineContent->sheetViewModel->content->listViewModel->listItems as $item)
            {
                if (@$item->listItemViewModel->leadingImage->sources[0]->clientResource->imageName == "PERSON_MINUS")
                {
                    $result->onUnsubscribeEndpoints = [
                        $item->listItemViewModel->rendererContext->commandContext->onTap->innertubeCommand
                    ];
                }
            }

            $menu = MenuRenderer::fromViewModel($viewModelInner->onShowSubscriptionOptions->innertubeCommand->showSheetCommand->panelLoadingStrategy
                ->inlineContent->sheetViewModel->content, true);
            $buildNotificationState = function(int $stateId, string $iconType, string $accessibilityText): object
            {
                return (object)[
                    "stateId" => $stateId,
                    "nextStateId" => $stateId,
                    "state" => (object)[
                        "buttonRenderer" => (object)[
                            "size" => "SIZE_DEFAULT",
                            "style" => "STYLE_TEXT",
                            "isDisabled" => false,
                            "accessibility" => (object)[
                                "label" => $accessibilityText
                            ],
                            "icon" => (object)[
                                "iconType" => $iconType
                            ]
                        ]
                    ]
                ];
            };

            
            $result->notificationPreferenceButton = (object)[
                "subscriptionNotificationToggleButtonRenderer" => (object)[
                    "currentStateId" => match ($notificationState->state) {
                        "SUBSCRIPTION_NOTIFICATION_STATE_OFF" => 0,
                        "SUBSCRIPTION_NOTIFICATION_STATE_ALL" => 2,
                        "SUBSCRIPTION_NOTIFICATION_STATE_OCCASIONAL" => 3,
                        default => 0
                    },
                    "states" => [
                        $buildNotificationState(2, "NOTIFICATIONS_ACTIVE", $viewModelInner->bellAccessibilityData->allLabel),
                        $buildNotificationState(3, "NOTIFICATIONS_NONE", $viewModelInner->bellAccessibilityData->occasionalLabel),
                        $buildNotificationState(0, "NOTIFICATIONS_OFF", $viewModelInner->bellAccessibilityData->offLabel),
                    ],
                    "targetId" => "notification-bell",
                    // ???
                    "secondaryIcon" => (object)[
                        "iconType" => "EXPAND_MORE"
                    ],
                    "command" => (object)[
                        "commandExecutorCommand" => (object)[
                            "commands" => [
                                (object)[
                                    "openPopupAction" => (object)[
                                        "popup" => $menu,
                                        "popupType" => "DROPDOWN"
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];
            SubscriptionNotificationToggleButtonRenderer::mutate($result->notificationPreferenceButton->subscriptionNotificationToggleButtonRenderer);
        }



        return (object)[
            "subscribeButtonRenderer" => $result
        ];
    }

    /**
     * Adds subscriber count text to a subscribe button.
     * Also supports the logged out stub button (buttonRenderer with STYLE_DESTRUCTIVE style)
     * 
     * @param object &$data     Reference to the subscribeButtonRenderer or buttonRenderer object.
     * @param string $countText The untruncated subscriber count text.
     */
    public static function addSubscriberCount(object &$data, string $countText): void
    {
        $strings = i18n::getNamespace("regex");
        $truncatedText = preg_replace($strings->get("subscriberCountIsolator"), "", $countText);

        foreach ([
            "unsubscribedButtonText",
            "unsubscribeButtonText",
            "subscribedButtonText",
            "text"
        ] as $prop)
        {
            $textObj = null;
            if (!($textObj = @$data->{$prop}))
                continue;

            if (isset($textObj->simpleText))
            {
                $textObj->runs = [
                    (object)[
                        "text" => $textObj->simpleText
                    ]
                ];
                unset($textObj->simpleText);
            }

            $textObj->runs[0]->text .= " ";
            $textObj->runs[1] = (object)[
                "deemphasize" => true,
                "text" => $truncatedText
            ];
        }
    }
}