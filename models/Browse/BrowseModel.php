<?php
namespace Polymerize\Model\Browse;

use Polymerize\Model\Common\LockupViewModel;
use Polymerize\Model\Common\SubscribeButtonRenderer;
use Rehike\ConfigManager\Config;
use Rehike\i18n\i18n;

/**
 * Implements a generic browse model that aims to modify nearly all browse pages.
 */
class BrowseModel
{
    /**
     * @return bool Whether or not to keep the item.
     */
    public static function mutateSingleItem(?object &$item, string $lockupType = ""): bool
    {
        if (is_null($item))
            return true;

        foreach ($item as $name => &$itemInner)
        switch ($name)
        {
            // Modern ad renderer, which leaves gaps in rich grids.
            case "adSlotRenderer":
            // YouTube Premium ad
            case "statementBannerRenderer":
            // Channel shorts shelf
            case "reelShelfRenderer":
                return false;
            case "lockupViewModel":
                $item = LockupViewModel::toLegacyRenderer($item, $lockupType);
                // Cursed hack to get the new inner item
                self::mutateLockup($item->{array_key_first((array)$item)});
                return true;
            case "richGridRenderer":
                if (isset($itemInner->header))
                    self::mutateSingleItem($itemInner->header, $lockupType);
                return self::mutateItems($itemInner->contents, $lockupType);
                return true;
            case "richItemRenderer":
                // Odd case: For some reason YouTube puts clips on the library page in gridVideoRenderer
                // inside a rich item which 2021 Polymer doesn't support. Just rename the renderer.
                if (isset($itemInner->content->gridVideoRenderer))
                {
                    $itemInner->content->videoRenderer = $itemInner->content->gridVideoRenderer;
                    unset($itemInner->content->gridVideoRenerer);
                }
                // fall-thru
            case "richSectionRenderer":
                return self::mutateSingleItem($itemInner->content, "");
            case "richShelfRenderer":
                // YouTube Playables shelf
                if (isset($itemInner->contents[0]->richItemRenderer->content->miniGameCardViewModel))
                    return false;

                // Shorts shelves
                if (@$itemInner->icon->iconType == "YOUTUBE_SHORTS_BRAND_24")
                    return false;

                // fall-thru
            case "sectionListRenderer":
                // Remove bad continuation
                if (isset($itemInner->contents[0]->itemSectionRenderer->contents[0]->playlistVideoListRenderer))
                    unset($itemInner->contents[1]);
                // fall-thru
            case "itemSectionRenderer":
                // Remove duplicate sort menu on playlist pages
                if (isset($itemInner->contents[0]->playlistVideoListRenderer))
                    unset($itemInner->header);
                return self::mutateItems($itemInner->contents, $lockupType);
            case "shelfRenderer":
                if (isset($itemInner->content))
                    return self::mutateSingleItem($itemInner->content, $lockupType);
            case "horizontalListRenderer":
            case "gridRenderer":
                if (isset($itemInner->items))
                    return self::mutateItems($itemInner->items, "grid");
            case "expandedShelfContentsRenderer":
                return self::mutateItems($itemInner->items, "list");
            case "horizontalCardListRenderer":
                return self::mutateItems($itemInner->cards, "grid");
            case "backstagePostThreadRenderer":
                return self::mutateSingleItem($itemInner->post);
            case "backstagePostRenderer":
                if (isset($itemInner->backstageAttachment))
                    self::mutateSingleItem($itemInner->backstageAttachment);
                return true;
            case "feedEntryRenderer":
                $item = $itemInner->item;
                return self::mutateSingleItem($item);
            case "videoRenderer":
            case "videoCardRenderer":
            case "gridVideoRenderer":
            case "compactVideoRenderer":
                self::mutateLockup($itemInner);
                self::mutateVideo($itemInner);
                return true;
            case "gridChannelRenderer":
            case "channelRenderer":
                self::mutateLockup($itemInner);
                if ($name == "channelRenderer")
                    self::mutateChannel($itemInner);
                return true;
            case "playlistRenderer":
            case "gridPlaylistRenderer":
            case "compactPlaylistRenderer":
                self::mutateLockup($itemInner);
                self::mutatePlaylist($itemInner);
                return true;
            case "playlistVideoListRenderer":
            {
                $items = &$itemInner->contents;
                $continuation = @$items[array_key_last($items)]->continuationItemRenderer ?? null;
                
                if (!is_null($continuation))
                {
                    $endpoint = $continuation->continuationEndpoint;
                    if (is_array(@$endpoint->commandExecutorCommand->commands))
                    foreach ($endpoint->commandExecutorCommand->commands as $command)
                    {
                        if (isset($command->continuationCommand))
                        {
                            $endpoint->continuationCommand = $command->continuationCommand;
                            $endpoint->commandMetadata = @$command->commandMetadata ?? null;
                            unset($endpoint->commandExecutorCommand);
                            break;
                        }
                    }
                }
                return true;
            }
        }
        return true;
    }

    public static function mutateItems(?array &$items, string $lockupType = ""): bool
    {
        if (is_null($items))
            return true;

        $shouldKeep = false;
        for ($i = 0; $i < count($items); $i++)
        {
            if (false == self::mutateSingleItem($items[$i], $lockupType))
            {
                array_splice($items, $i, 1);
                $i--;
            }
            else $shouldKeep = true;
        }
        return $shouldKeep;
    }

    private static function mutateLockup(object &$lockup): void
    {
        if (is_array(@$lockup->ownerBadges))
        foreach ($lockup->ownerBadges as &$badge)
        {
            $badgeInner = null;
            if ($badgeInner = @$badge->metadataBadgeRenderer)
            {
                // Fix artist channel badge icon
                if (@$badgeInner->icon->iconType == "AUDIO_BADGE")
                {
                    $badgeInner->icon->iconType = "OFFICIAL_ARTIST_BADGE";
                }
            }
        }
    }

    private static function mutateVideo(object &$lockup): void
    {
        $strings = i18n::getNamespace("misc");

        if (is_array(@$lockup->badges))
        foreach ($lockup->badges as &$badge)
        {
            $badgeInner = null;
            if ($badgeInner = @$badge->metadataBadgeRenderer)
            {
                // Fix live badge icon and text
                if (@$badgeInner->style == "BADGE_STYLE_TYPE_LIVE_NOW")
                {
                    unset($badgeInner->icon);
                    if (@$badgeInner->label == $strings->get("liveBadgeTextMatch"))
                        $badgeInner->label = $strings->get("liveBadgeText");
                }
            }
        }

        if (is_array(@$lockup->thumbnailOverlays))
        foreach ($lockup->thumbnailOverlays as &$overlay)
        {
            $overlayInner = null;
            if ($overlayInner = @$overlay->thumbnailOverlayTimeStatusRenderer)
            {
                // Replace "SHORTS" tag with length text
                if (@$overlayInner->style == "SHORTS")
                {
                    unset($overlayInner->style);
                    unset($overlayInner->icon);
                    $overlayInner->text = @$lockup->lengthText ?? null;
                }
            }
        }

        // Make Shorts videos go to watch instead
        if (isset($lockup->navigationEndpoint->reelWatchEndpoint))
        {
            $webCommandMetadata = $lockup->navigationEndpoint->commandMetadata->webCommandMetadata;
            $webCommandMetadata->webPageType = "WEB_PAGE_TYPE_WATCH";
            $webCommandMetadata->url = "/watch?v=" . $lockup->videoId;

            $lockup->navigationEndpoint->watchEndpoint = (object)[
                "videoId" => $lockup->videoId
            ];

            unset($lockup->navigationEndpoint->reelWatchEndpoint);
        }

        // Nullify experiment that makes music uploads start a mix
        if (isset($lockup->navigationEndpoint->watchEndpoint->playlistId))
        {
            unset($lockup->navigationEndpoint->watchEndpoint->playlistId);
            $lockup->navigationEndpoint->commandMetadata->webCommandMetadata->url
                = preg_replace("/(\?|&)(list|start_radio)=(.*?)(?=&|$)/", "", $lockup->navigationEndpoint->commandMetadata->webCommandMetadata->url);
        }
    }

    private static function mutateChannel(object &$lockup): void
    {
        $subscribeButton =
            @$lockup->subscribeButton->subscribeButtonRenderer
            ?? @$lockup->subscribeButton->buttonRenderer
            ?? null;
        if (Config::getConfigProp("appearance.subCountOnSubButton") && !is_null($subscribeButton))
        {
            $subCountText = @$lockup->subscriberCountText->simpleText ?? null;
            // nice one dumbass
            if (substr($subCountText, 0, 1) == "@")
            {
                $subCountText = @$lockup->videoCountText->simpleText ?? null;
            }
            if (!is_null($subCountText))
                SubscribeButtonRenderer::addSubscriberCount($subscribeButton, $subCountText);
        }
    }

    private static function mutatePlaylist(object &$lockup): void
    {
        if (is_array(@$lockup->thumbnailOverlays))
        foreach ($lockup->thumbnailOverlays as &$overlay)
        {
            // Move playlist overlay back to the right on legacy renderers
            // (why do they still report these?)
            if (isset($overlay->thumbnailOverlayBottomPanelRenderer))
            {
                $strings = i18n::getNamespace("regex");
                $overlayInner = $overlay->thumbnailOverlayBottomPanelRenderer;
                $overlayInner->text->simpleText = preg_replace(
                    $strings->get("videoCountIsolator"),
                    "",
                    $overlayInner->text->simpleText
                );

                $overlay->thumbnailOverlaySidePanelRenderer = $overlayInner;
                unset($overlay->thumbnailOverlayBottomPanelRenderer);
            }
        }
    }
}