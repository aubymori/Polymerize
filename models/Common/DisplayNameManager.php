<?php
namespace Polymerize\Model\Common;

use Rehike\Network;

use Rehike\ConfigManager\Config;
use Rehike\Async\Promise;
use function Rehike\Async\async;

/**
 * Manager for display names.
 * 
 * @author aubymori <aubyomori@gmail.com>
 * @author Isabella Lulamoon <kawapure@gmail.com>
 * @author The Rehike Maintainers
 */
class DisplayNameManager
{
    // Allowed characters source: https://support.google.com/youtube/answer/11585688
    private const HANDLE_REGEX = "/@[0-9A-Za-z-_\.·]+/";

    protected array $remoteData = [];
    
    protected ?object $displayNameMap = null;
    
     /**
     * Supplies a display name map to the comment bakery.
     * 
     * This may be supplied from a custom continuation.
     */
    public function supplyDisplayNameMap(object $displayNameMap): void
    {
        $this->displayNameMap = $displayNameMap;
    }
    
    /**
     * Get the display name of a commenter.
     */
    public function getDisplayName(string $ucid): ?string
    {
        if (DisplayNameCacheManager::has($ucid))
        {
            return DisplayNameCacheManager::get($ucid);
        }
        else if (!is_null($this->displayNameMap) && isset($this->displayNameMap->{$ucid}))
        {
            return $this->displayNameMap->{$ucid};
        }
        else if (isset($this->remoteData[$ucid]))
        {
            return $this->remoteData[$ucid];
        }
        
        return null;
    }

    /**
     * Gets UCIDs needed for channel handles.
     * 
     * PIECE OF SHIT PIECE OF SHIT PIECE OF SHIT
     * BAD FUNCTION VERY SLOW
     */
    private function getUcidsForHandles(array &$channelIds): Promise/*<void>*/
    {
        return async(function() use (&$channelIds)
        {
            foreach ($channelIds as $key => $value)
            if (substr($value, 0, 1) == "@")
            {
                $dataApiResponse = yield Network::dataApiRequest(
                    action: "channels",
                    params: [
                        "part" => "id",
                        "forHandle" => $value
                    ]
                );
                $data = $dataApiResponse->getJson();
                if ($data && isset($data->items[0]->id))
                {
                    $ucid = $data->items[0]->id;
                }
                else
                {
                    $response = yield Network::innertubeRequest(
                        action: "navigation/resolve_url",
                        body: [
                            "url" => "https://www.youtube.com/$value"
                        ]
                    );
                    $data = $response->getJson();

                    $ucid = @$data->endpoint->browseEndpoint->browseId ?? null;
                    // For some handles, resolve_url returns a classic channel
                    // URL (e.g. https://www.youtube.com/jawed). For every case
                    // that this happens, you can just make another resolve_url
                    // request, and it will actually give you the UCID of the
                    // channel.
                    if (is_null($ucid))
                    {
                        $response2 = yield Network::innertubeRequest(
                            action: "navigation/resolve_url",
                            body: [
                                "url" => $data->endpoint->urlEndpoint->url
                            ]
                        );
                        $data2 = $response2->getJson();
                        $ucid = @$data2->endpoint->browseEndpoint->browseId ?? null;
                    }
                }

                $channelIds[$value] = $ucid;
                unset($channelIds[$key]);
            }
        });
    }
    
    /**
     * Ensures that data is available.
     */
    public function ensureDataAvailable(array $channelIds): Promise/*<void>*/
    {
        return async(function() use ($channelIds) { 
            $remoteCids = $this->filterKnownChannelIds($channelIds);
            
            // Ensure that we need to make a request in the first place. If we
            // already have all UCIDs populated, then we just avoid making any
            // request at all.
            if (empty($remoteCids))
            {
                return new Promise(fn($r) => $r());
            }

            yield $this->getUcidsForHandles($remoteCids);
            
            // Try to request from data API:
            // temporarily disabled bc buggy
            $dataApiResponse = null;//yield $this->requestFromDataApi($remoteCids);
            
            if (!$dataApiResponse)
            {
                $embedMap = yield $this->requestFromSubscribeButtonEmbed($remoteCids);
                
                foreach ($embedMap as $key => $value)
                {
                    $this->remoteData[$key] = $value;
                }
            }
        });
    }

    /**
     * Populate $dataApiData with channel data.
     * 
     * @param string[] $cids  List of channel IDs to get display names for.
     * 
     * @return Promise<bool> True on success, false on failure
     */
    public function requestFromDataApi(array $cids): Promise/*<bool>*/
    {
        return async(function() use ($cids) {
            $response = yield Network::dataApiRequest(
                action: "channels",
                params: [
                    "part" => "id,snippet",
                    "id" => implode(",", $cids)
                ]
            );
            $data = $response->getJson();
            
            if (!$data)
            {
                return false;
            }
            
            $dataApiData = [];

            if (isset($data->items))
            {
                foreach ($data->items as $item)
                {
                    $dataApiData += [
                        $item->id => $item->snippet
                    ];
                }
            }
            else
            {
                return false;
            }
            
            foreach ($dataApiData as $ucid => $data)
            {
                foreach (array_keys($cids) as $akey)
                {
                    if ($cids[$akey] == $ucid)
                    {
                        var_dump($cids[$akey], $ucid, $data->title);
                        if (is_string($akey))
                            $handle = $akey;

                        $key = isset($handle) ? $handle : $ucid;
                        $this->remoteData[$key] = $data->title;
                        
                        DisplayNameCacheManager::insert(
                            ucid: $key,
                            displayName: $data->title
                        );
                    }                    
                }
            }
            return true;
        });
    }
    
    /**
     * Get the title of the channel from the subscribe button embed page.
     * 
     * This is a very lightweight page to request when the data API is unavailable.
     * 
     * @return Promise<string[]>
     */
    public function requestFromSubscribeButtonEmbed(array $cids): Promise/*<array>*/
    {
        return async(function() use ($cids) {
            $requestPromises = [];
            
            foreach ($cids as $key => $channelId)
            {
                $keyToUse = is_string($key) ? $key : $channelId;
                $requestPromises[$keyToUse] = Network::urlRequest(
                    "https://www.youtube.com/subscribe_embed?channelid=$channelId&layout=full"
                );
            }
            
            $responses = yield Promise::all($requestPromises);
            
            $out = [];
            
            foreach ($responses as $ucid => $response)
            {
                $responseText = $response->getText();
                
                $split = explode("class=\"yt-username\"", $responseText);
                if (!isset($split[1]))
                    continue;
                $rightOfClass = $split[1];
                $rightOfTagEnd = explode(">", $rightOfClass)[1];
                $isolatedDisplayName = explode("<", $rightOfTagEnd)[0];
                $isolatedDisplayName = html_entity_decode($isolatedDisplayName);

                if ($rightOfClass == "")
                {
                    var_dump($ucid, $responseText);
                }

                $out[$ucid] = $isolatedDisplayName;
                
                DisplayNameCacheManager::insert(
                    ucid: $ucid,
                    displayName: $isolatedDisplayName
                );
            }
            
            return $out;
        });
    }
    
    public function filterKnownChannelIds(array $cids): array
    {
        return array_filter($cids, fn($item) => 
            !isset($this->displayNameMap?->{$item}) &&
            !DisplayNameCacheManager::has($item)
        );
    }
    
    /**
     * Creates a display name map from remote data.
     */
    public function createDisplayNameMap(): object
    {
        $out = (object)[];
        
        foreach ($this->remoteData as $ucid => $displayName)
        {
            $out->{$ucid} = $displayName;
        }
        
        return $out;
    }

    private static function addUcidsForComment(object &$comment, array &$out)
    {
        if ($a = @$comment->authorEndpoint->browseEndpoint->browseId)
        {
            if (substr($a, 0, 2) == "UC" && !in_array($a, $out))
                $out[] = $a;
        }

        foreach ($comment->contentText->runs as $run)
        {
            if ($a = @$run->navigationEndpoint->browseEndpoint->browseId)
            {
                if (substr($a, 0, 2) == "UC" && !in_array($a, $out))
                    $out[] = $a;
            }
        }

        if ($a = @$comment->pinnedCommentBadge->pinnedCommentBadgeRenderer->label->simpleText)
        {
            $matches = [];
            if (preg_match(self::HANDLE_REGEX, $a, $matches))
            {
                if (!in_array($matches[0], $out))
                    $out[] = $matches[0];
            }
        }

        if ($a = @$comment->actionButtons->commentActionButtonsRenderer->creatorHeart->creatorHeartRenderer->heartedTooltip)
        {
            $matches = [];
            if (preg_match(self::HANDLE_REGEX, $a, $matches))
            {
                if (!in_array($matches[0], $out))
                    $out[] = $matches[0];
            }
        }
    }

    private static function addUcidsForCommentThread(object &$thread, array &$out)
    {
        if ($a = @$thread->comment->commentRenderer)
        {
            self::addUcidsForComment($a, $out);
        }

        if (isset($thread->replies->commentRepliesRenderer->teaserContents))
        foreach ($thread->replies->commentRepliesRenderer->teaserContents as $teaser)
        {
            if ($a = @$teaser->commentRenderer)
            {
                self::addUcidsForComment($a, $out);
            }
        }
    }

    private function applyDisplayNamesToSingleCommentInternal(object &$comment)
    {
        if (isset($comment->authorEndpoint->browseEndpoint->browseId))
            $comment->authorText = (object)[
                "simpleText" => $this->getDisplayName($comment->authorEndpoint->browseEndpoint->browseId)
            ];
        if (isset($comment->authorCommentBadge->authorCommentBadgeRenderer))
        {
            $comment->authorCommentBadge->authorCommentBadgeRenderer->authorText
                = $comment->authorText;
        }

        foreach ($comment->contentText->runs as $i => &$run)
        {
            if ($ucid = @$run->navigationEndpoint->browseEndpoint->browseId)
            {
                $mentionDisplayName = $this->getDisplayName($ucid);
                
                /** 
                 * Redo the whole @ string. This also removes the automatic spaces
                 * put around it.
                 */
                if (substr($ucid, 0, 2) == "UC" && !is_null($mentionDisplayName))
                {
                    $run->text = "@" . $mentionDisplayName . "";
                }

                /**
                 * Add a space to the next run if it isn't there. We need to do this
                 * or else some comments will show things like: "@userHi hello".
                 */
                $nextRun = &$comment->contentText->runs[$i + 1];
                if ($nextRun && substr($nextRun->text, 0, 1) != " ")
                {
                    $nextRun->text = " " . $nextRun->text;
                }
            }
        }

        if ($a = @$comment->pinnedCommentBadge->pinnedCommentBadgeRenderer->label->simpleText)
        {
            $matches = [];
            if (preg_match(self::HANDLE_REGEX, $a, $matches))
            {
                $displayName = $this->getDisplayName($matches[0]);
                if ("" != $displayName)
                    $comment->pinnedCommentBadge->pinnedCommentBadgeRenderer->label->simpleText
                        = str_replace($matches[0], $displayName, $a);
            }
        }

        if ($a = @$comment->actionButtons->commentActionButtonsRenderer->creatorHeart->creatorHeartRenderer->heartedTooltip)
        {
            $matches = [];
            if (preg_match(self::HANDLE_REGEX, $a, $matches))
            {
                $displayName = $this->getDisplayName($matches[0]);
                if ("" != $displayName)
                    $comment->actionButtons->commentActionButtonsRenderer->creatorHeart->creatorHeartRenderer->heartedTooltip
                        = str_replace($matches[0], $displayName, $a);
            }
        }
    }

    private function applyDisplayNamesToCommentThreadInternal(object &$thread)
    {
        if ($a = @$thread->comment->commentRenderer)
        {
            $this->applyDisplayNamesToSingleCommentInternal($a);
        }

        if (isset($thread->replies->commentRepliesRenderer->teaserContents))
        foreach ($thread->replies->commentRepliesRenderer->teaserContents as $teaser)
        {
            if ($a = @$teaser->commentRenderer)
            {
                $this->applyDisplayNamesToSingleCommentInternal($a);
            }
        }
    }

    public function applyDisplayNamesToSingleComment(object &$comment): void
    {
        if (!Config::getConfigProp("general.useDisplayNames"))
            return;

        $ucids = [];
        if ($a = @$comment->commentThreadRenderer)
        {
            self::addUcidsForCommentThread($a, $ucids);
        }
        else if ($a = @$comment->commentRenderer)
        {
            self::addUcidsForComment($a, $ucids);
        }

        $this->ensureDataAvailable($ucids)->then(function() use (&$comment)
        {
            if ($a = @$comment->commentThreadRenderer)
            {
                $this->applyDisplayNamesToCommentThreadInternal($a);
            }
            else if ($a = @$comment->commentRenderer)
            {
                $this->applyDisplayNamesToSingleCommentInternal($a);
            }
        });
    }

    public function applyDisplayNamesToComments(array &$comments): void
    {
        if (!Config::getConfigProp("general.useDisplayNames"))
            return;

        $ucids = [];
        foreach ($comments as $comment)
        {
            if ($a = @$comment->commentThreadRenderer)
            {
                self::addUcidsForCommentThread($a, $ucids);
            }
            else if ($a = @$comment->commentRenderer)
            {
                self::addUcidsForComment($a, $ucids);
            }
        }

        $this->ensureDataAvailable($ucids)->then(function() use (&$comments)
        {
            foreach ($comments as &$comment)
            {
                if ($a = @$comment->commentThreadRenderer)
                {
                    $this->applyDisplayNamesToCommentThreadInternal($a);
                }
                else if ($a = @$comment->commentRenderer)
                {
                    $this->applyDisplayNamesToSingleCommentInternal($a);
                }
            }
        });
    }
}