<?php
namespace Polymerize\Controller;

use Rehike\Network;
use Rehike\Async\Promise;

use Rehike\ControllerV2\RequestMetadata;
use Rehike\ConfigManager\Config;

use Polymerize\Controller\Core\PolymerController;
use Polymerize\Model\Browse\BrowseModel;
use Polymerize\Model\Next\TwoColumnWatchNextResults;
use Polymerize\Model\Comments\CommentRenderer;
use Polymerize\Model\Comments\CommentThreadRenderer;
use Polymerize\Model\Common\DisplayNameManager;
use Polymerize\Model\Util\LockupConverter;

class NextController extends PolymerController
{
    private function mutateData(object &$data): void
    {
        $dataInner = null;
        if ($dataInner = @$data->contents->twoColumnWatchNextResults)
        {
            if (Config::getConfigProp("watch.useRyd"))
            {
                $rydPromise = Network::urlRequest(
                    "https://returnyoutubedislikeapi.com/votes?videoId="
                    . $data->currentVideoEndpoint->watchEndpoint->videoId
                );
            }
            else
            {
                $rydPromise = new Promise(fn($r) => $r(null));
            }
            $rydPromise->then(function ($rydResponse) use ($dataInner)
            {
                $rydData = (object)[];
                try
                {
                    $rydData = $rydResponse?->getJson() ?? (object)[];
                } catch (\Throwable $e) {}
                TwoColumnWatchNextResults::mutate($dataInner, $rydData, $this->isLoggedIn());
            });
        }
        else if ($dataInner = @$data->onResponseReceivedEndpoints)
        {
            foreach ($dataInner as &$endpoint)
            {
                $endpointInner = null;
                if (($endpointInner = @$endpoint->appendContinuationItemsAction)
                || ($endpointInner = @$endpoint->reloadContinuationItemsCommand))
                {
                    if (@$endpointInner->targetId == "watch-next-feed")
                    {
                        if (is_array(@$endpointInner->continuationItems))
                        {
                            BrowseModel::mutateItems($endpointInner->continuationItems, "compact");
                        }
                    }
                    else
                    {
                        if (is_array(@$endpointInner->continuationItems))
                        {
                            foreach ($endpointInner->continuationItems as &$item)
                            {
                                $itemInner = null;
                                if ($itemInner = @$item->commentThreadRenderer)
                                {
                                    CommentThreadRenderer::mutate($itemInner, $data->frameworkUpdates);
                                }
                                else if ($itemInner = @$item->commentViewModel)
                                {
                                    $item = CommentRenderer::fromViewModel($item, $data->frameworkUpdates);
                                }
                            }

                            $displayNameManager = new DisplayNameManager;
                            $displayNameManager->applyDisplayNamesToComments(
                                $endpointInner->continuationItems
                            );
                        }
                    }
                }
            }
        }
    }

    public function onGet(RequestMetadata $request, object &$data): void
    {
        $this->yt->ytPageType = "watch";
        $this->yt->skeleton = "watch";
        $this->yt->ytInitialPlayerResponse = $this->extractInitialPlayerResponse();
        unset($this->yt->ytInitialPlayerResponse->playerAds);
        unset($this->yt->ytInitialPlayerResponse->adPlacements);
        unset($this->yt->ytInitialPlayerResponse->adSlots);
        $this->mutateData($data);
    }

    public function onPost(RequestMetadata $request, object &$data): void
    {
        $this->mutateData($data);
    }

    private function extractInitialPlayerResponse(): object|null
    {
        return self::extractJsonData(
            $this->response->getText(),
            ">var ytInitialPlayerResponse = ",
            ";var"
        );
    }
}