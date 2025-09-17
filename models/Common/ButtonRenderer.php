<?php
namespace Polymerize\Model\Common;

use Polymerize\Util\ParsingUtils;

class ButtonRenderer
{
    public static function fromViewModel(object $viewModel, string $commandMemberName = "command"): ?object
    {
        if (isset($viewModel->buttonRenderer))
            return $viewModel;

        $viewModelInner = null;
        if (!($viewModelInner = @$viewModel->buttonViewModel))
            return null;

        $button = (object)[];

        if (isset($viewModelInner->trackingParams))
            $button->trackingParams = $viewModelInner->trackingParams;

        if (isset($viewModelInner->title))
            $button->text = (object)[
                "simpleText" => $viewModelInner->title
            ];
        else if (isset($viewModelInner->titleFormatted))
            $button->text = ParsingUtils::attributedStringToFormattedString($viewModelInner->titleFormatted);
            

        if (isset($viewModelInner->tooltip))
            $button->tooltip = $viewModelInner->tooltip;

        if (isset($viewModelInner->iconName))
            $button->icon = (object)[
                "iconType" => $viewModelInner->iconName
            ];

        if (isset($viewModelInner->accessibilityText))
        {
            $button->accessibility = (object)[
                "label" => $viewModelInner->accessibilityText
            ];

            $button->accessibilityData = (object)[
                "accessibilityData" => (object)[
                    "label" => $viewModelInner->accessibilityText
                ]
            ];
        }

        if (isset($viewModelInner->onTap->serialCommand->commands))
            foreach ($viewModelInner->onTap->serialCommand->commands as $command)
            {
                if (isset($command->innertubeCommand))
                    $button->{$commandMemberName} = $command->innertubeCommand;
            }
        else if (isset($viewModelInner->onTap->innertubeCommand))
            $button->{$commandMemberName} = $viewModelInner->onTap->innertubeCommand;

        if (@$viewModelInner->state == "BUTTON_VIEW_MODEL_STATE_DISABLED")
            $button->isDisabled = true;

        $button->style = match (@$viewModelInner->style)
        {
            "BUTTON_VIEW_MODEL_STYLE_MONO" => "STYLE_DEFAULT",
            default => "STYLE_DEFAULT"
        };
        
        $button->size = match (@$viewModelInner->size)
        {
            "BUTTON_VIEW_MODEL_SIZE_DEFAULT" => "SIZE_DEFAULT",
            default => "SIZE_DEFAULT"
        };

        return (object)[
            "buttonRenderer" => $button
        ];
    }
}