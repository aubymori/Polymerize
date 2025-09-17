<?php
namespace Polymerize\Model\Common;

class ToggleButtonRenderer
{
    public static function fromViewModel(object $viewModel, ?object $context = null, string $commandMemberName = "serviceEndpoint"): ?object
    {
        if (isset($viewModel->toggleButtonRenderer))
            return $viewModel;

        $viewModelInner = null;
        if (!($viewModelInner = @$viewModel->toggleButtonViewModel))
            return null;

        $button = (object)[];

        $defaultButton = ButtonRenderer::fromViewModel($viewModelInner->defaultButtonViewModel, $commandMemberName);
        $toggledButton = ButtonRenderer::fromViewModel($viewModelInner->toggledButtonViewModel, $commandMemberName);

        // Property maps:
        $propertyMaps = [
            "defaultButton" => [
                // These should be the same between the two:
                "isDisabled" => "isDisabled",
                "trackingParams" => "trackingParams",
                
                "accessibility" => "accessibility",
                "text" => "defaultText",
                "size" => "size",
                "accessibilityData" => "accessibilityData",
                "tooltip" => "defaultTooltip",
                "icon" => "defaultIcon",
                "navigationEndpoint" => "defaultNavigationEndpoint",
                "serviceEndpoint" => "defaultServiceEndpoint",
            ],
            "toggledButton" => [
                "text" => "toggledText",
                "size" => "toggledSize",
                "accessibilityData" => "toggledAccessibilityData",
                "tooltip" => "toggledTooltip",
                "icon" => "toggledIcon",
                "navigationEndpoint" => "toggledNavigationEndpoint",
                "serviceEndpoint" => "toggledServiceEndpoint"
            ]
        ];

        foreach ($propertyMaps as $var => $defs)
        {
            foreach ($defs as $origName => $destName)
            {
                if (isset($$var->buttonRenderer->{$origName}))
                {
                    $button->{$destName} = $$var->buttonRenderer->{$origName};
                }
            }
        }

        if (isset($defaultButton->style))
            $button->style = (object)[
                "styleType" => $defaultButton->style
            ];

        if (isset($toggledButton->style))
            $button->toggledStyle = (object)[
                "styleType" => $toggledButton->style
            ];

        if (@$viewModelInner->isTogglingDisabled)
            $button->isDisabled = true;

        if (isset($viewModelInner->identifier))
            $button->targetId = $viewModelInner->identifier;

        if (isset($context->defaultStyle))
        {
            $button->style = (object)[
                "styleType" => $context->defaultStyle
            ];
        }

        if (isset($context->toggledStyle))
        {
            $button->toggledStyle = (object)[
                "styleType" => $context->toggledStyle
            ];
        }

        if (isset($context->id))
        {
            $button->toggleButtonSupportedData = (object)[
                "toggleButtonIdData" => (object)[
                    "id" => $context->id
                ]
            ];
        }

        if (isset($context->isToggled))
        {
            $button->isToggled = $context->isToggled;
        }

        return (object)[
            "toggleButtonRenderer" => $button
        ];
    }
}