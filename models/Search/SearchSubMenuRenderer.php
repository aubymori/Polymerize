<?php
namespace Polymerize\Model\Search;

class SearchSubMenuRenderer
{
    public static function fromSearchHeaderRenderer(object $header): object
    {
        $result = (object)[];

        $newButton = $header->searchFilterButton->buttonRenderer;

        $result->button = (object)[
            "toggleButtonRenderer" => (object)[
                "isDisabled" => false,
                "isToggled" => false,
                "accessibility" => (object)[
                    "label" => $newButton->accessibilityData->accessibilityData->label
                ],
                "defaultIcon" => (object)[
                    "iconType" => "TUNE"
                ],
                "defaultText" => $newButton->text,
                "style" => (object)[
                    "styleType" => "STYLE_TEXT"
                ],
                "defaultTooltip" => $newButton->tooltip,
                "toggledStyle" => (object)[
                    "styleType" => "STYLE_DEFAULT_ACTIVE"
                ],
                "toggledTooltip" => $newButton->tooltip
            ]
        ];

        $result->groups = $newButton->command->openPopupAction->popup->searchFilterOptionsDialogRenderer->groups;

        return (object)[
            "searchSubMenuRenderer" => $result
        ];
    }
}