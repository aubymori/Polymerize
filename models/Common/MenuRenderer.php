<?php
namespace Polymerize\Model\Common;

use Polymerize\Util\ParsingUtils;

class MenuRenderer
{
    /**
     * Converts a listViewModel to a menuRenderer.
     */
    public static function fromViewModel(object $viewModel, bool $isPopup = false): ?object
    {
        $viewModelInner = null;
        if (!($viewModelInner = @$viewModel->listViewModel))
            return null;

        $result = (object)[];

        $result->items = [];
        foreach ($viewModelInner->listItems as $item)
        {
            $itemInner = null;
            if (!($itemInner = @$item->listItemViewModel))
                continue;

            $newItem = (object)[];

            $newItem->text = ParsingUtils::attributedStringToFormattedString($itemInner->title);
            
            $iconType = @$itemInner->leadingImage->sources[0]->clientResource->imageName ?? null;
            if (!is_null($iconType))
                $newItem->icon = (object)[
                    "iconType" => $iconType
                ];

            $command = @$itemInner->rendererContext->commandContext->onTap->innertubeCommand ?? null;
            if (!is_null($command))
                $newItem->serviceEndpoint = $command;

            $result->items[] = (object)[
                "menuServiceItemRenderer" => $newItem
            ];
        }

        return (object)[
            ($isPopup ? "menuPopupRenderer" : "menuRenderer") => $result
        ];
    }
}