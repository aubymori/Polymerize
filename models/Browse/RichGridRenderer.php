<?php
namespace Polymerize\Model\Browse;

class RichGridRenderer
{
    // Converts a bunch of richItemRenderers to bare items.
    public static function convertItems(array $items): array
    {
        $result = [];

        foreach ($items as $item)
        {
            if (isset($item->continuationItemRenderer))
                $result[] = $item;

            if (!isset($item->richItemRenderer))
                continue;

            $itemName = null;
            $itemContent  = null;
            foreach ($item->richItemRenderer->content as $name => $content)
            {
                $itemName = $name;
                $itemContent = $content;
                break;
            }

            if ($itemName == "feedNudgeRenderer")
                continue;

            $itemName = match ($itemName)
            {
                "videoRenderer"    => "gridVideoRenderer",
                "playlistRenderer" => "gridPlaylistRenderer",
                "radioRenderer"    => "gridRadioRenderer",
                default            => $itemName
            };

            $result[] = (object)[
                $itemName => $itemContent
            ];
        }

        return $result;
    }
}