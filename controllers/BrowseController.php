<?php
namespace Polymerize\Controller;

use Rehike\ControllerV2\RequestMetadata;

use Polymerize\Controller\Core\PolymerController;
use Polymerize\Model\Comments\CommentThreadRenderer;
use Polymerize\Model\Comments\CommentRenderer;
use Polymerize\Model\Common\DisplayNameManager;
use Polymerize\Model\Browse\BrowseModel;
use Polymerize\Model\Browse\C4TabbedHeaderRenderer;
use Polymerize\Model\Browse\ChannelModel;
use Polymerize\Model\Browse\RichGridRenderer;
use Rehike\ConfigManager\Config;

/**
 * Due to channel URLs being so versatile, this is the DEFAULT controller for GET,
 * every page URL not specifically routed will be handled here.
 * 
 * Make sure it acts accordingly.
 */
class BrowseController extends PolymerController
{
    private function mutateData(object &$data): void
    {
        $dataInner = null;
        if ($dataInner = @$data->contents->twoColumnBrowseResultsRenderer)
        {
            if (is_array(@$dataInner->tabs))
            foreach ($dataInner->tabs as &$tab)
            {
                $contentInner =
                    @$tab->tabRenderer->content
                    ?? null;

                if (!is_null($contentInner))
                {
                    BrowseModel::mutateSingleItem($contentInner);
                }
            }
        }
        else if ($dataInner = @$data->onResponseReceivedActions)
        {
            if (is_array($dataInner))
            foreach ($dataInner as &$action)
            {
                $actionInner = null;
                if ($actionInner = @$action->appendContinuationItemsAction)
                {
                    BrowseModel::mutateItems($actionInner->continuationItems);
                }
            }
        }

        $headerInner = null;
        if (($headerInner = @$data->header->pageHeaderRenderer->content)
        // Some pages (History) have a header on modern Polymer but no visible tabs.
        // The c4TabbedHeaderRenderer looks incorrect in this case.
        && isset($data->contents->twoColumnBrowseResultsRenderer->tabs[0]->tabRenderer->title))
        {
            $data->header = C4TabbedHeaderRenderer::fromViewModel(
                $headerInner,
                $this->isLoggedIn(),
                @$data->frameworkUpdates ?? null
            );
        }

        $route = $this->getServiceTrackingParam("GFEEDBACK", "route");
        if (substr($route, 0, 8) == "channel.")
        {
            $ucid = $this->getServiceTrackingParam("GFEEDBACK", "browse_id");
            if (isset($data->contents->twoColumnBrowseResultsRenderer->tabs))
            {
                $aboutData = null;
                if ($route == "channel.about")
                {
                    $aboutData = @$data->onResponseReceivedEndpoints[0]->showEngagementPanelEndpoint->engagementPanel
                        ->engagementPanelSectionListRenderer->content->sectionListRenderer->contents[0]->itemSectionRenderer
                        ->contents[0]->aboutChannelRenderer;
                }
                
                $baseUrl = @$data->metadata->channelMetadataRenderer->vanityChannelUrl
                    ?? "/channel/$ucid";
                $baseUrl = str_replace("http://www.youtube.com", "", $baseUrl);

                ChannelModel::addAboutTab(
                    $data->contents->twoColumnBrowseResultsRenderer->tabs,
                    $baseUrl,
                    $ucid,
                    $aboutData
                );
            }
        }

        $feedId = $this->getServiceTrackingParam("GFEEDBACK", "browse_id");
        if (Config::getConfigProp("general.homeStyle") == "NON_RICH" && $feedId == "FEwhat_to_watch")
        {
            $content = null;
            if ($content = @$data->contents->twoColumnBrowseResultsRenderer->tabs[0]->tabRenderer->content)
            {
                if (isset($content->richGridRenderer->contents))
                {
                    $newItems = RichGridRenderer::convertItems($content->richGridRenderer->contents);
                    unset($content->richGridRenderer);
                    $content->sectionListRenderer = (object)[
                        "contents" => [
                            (object) [
                                "itemSectionRenderer" => (object)[
                                    "contents" => [
                                        (object)[
                                            "shelfRenderer" => (object)[
                                                "content" => (object)[
                                                    "gridRenderer" => (object)[
                                                        "items" => $newItems,
                                                        "targetId" => "browse-feedFEwhat_to_watch"
                                                    ]
                                                ],
                                                "title" => $data->header->feedTabbedHeaderRenderer->title
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ];
                }
            }
            else if ($content = @$data->onResponseReceivedActions[0]->appendContinuationItemsAction)
            {
                $content->continuationItems = RichGridRenderer::convertItems($content->continuationItems);
            }
        }
        else if ($feedId == "FEsubscriptions")
        {
            // Fix up grid subscriptions
            if ($content = @$data->contents->twoColumnBrowseResultsRenderer->tabs[0]->tabRenderer->content)
            {
                if (isset($content->richGridRenderer->contents))
                {
                    $newItems = RichGridRenderer::convertItems($content->richGridRenderer->contents);
                    $header = $content->richGridRenderer->contents[0]->richSectionRenderer->content->shelfRenderer;
                    unset($content->richGridRenderer);

                    $content->sectionListRenderer = (object)[
                        "contents" => [
                            (object) [
                                "itemSectionRenderer" => (object)[
                                    "contents" => [
                                        (object)[
                                            "shelfRenderer" => (object)[
                                                "content" => (object)[
                                                    "gridRenderer" => (object)[
                                                        "items" => $newItems,
                                                        "targetId" => "browse-feedFEsubscriptions"
                                                    ]
                                                ],
                                                "title" => $header->title,
                                                "subscribeButton" => $header->subscribeButton,
                                                "menu" => $header->menu
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ];
                }   
            }
            else if ($content = @$data->onResponseReceivedActions[0]->appendContinuationItemsAction)
            {
                // Check to not mess up list continuations
                if (isset($content->continuationItems[0]->richItemRenderer))
                    $content->continuationItems = RichGridRenderer::convertItems($content->continuationItems);
            }
        }
    }

    public function onGet(RequestMetadata $request, object &$data): void
    {
        $this->yt->ytPageType = "browse";
        $feedId = $this->getServiceTrackingParam("GFEEDBACK", "browse_id");
        if ($feedId == "FEwhat_to_watch")
        {
            $this->yt->skeleton = "home";
        }

        // Channel live URl gives us a watch page
        // TODO: why in the FUCK does polymer js not remove the watch skeleton here?
        if (isset($this->yt->ytCommand->watchEndpoint))
        {
            $this->doRender = false;
            $nextController = new NextController($this->response);
            $nextController->initializeController($request);
            $nextController->get();
            return;
        }
        
        $this->mutateData($data);
    }

    public function onPost(RequestMetadata $request, object &$data): void
    {
        // POST-specific data:
        if (is_array(@$data->onResponseReceivedEndpoints))
        foreach ($data->onResponseReceivedEndpoints as &$endpoint)
        {
            $endpointInner = null;
            if (($endpointInner = @$endpoint->appendContinuationItemsAction)
            || ($endpointInner = @$endpoint->reloadContinuationItemsCommand))
            {
                if ((@$endpointInner->targetId == "comments-section"
                || substr(@$endpointInner->targetId, 0, 21) == "comment-replies-item-")
                && is_array(@$endpointInner->continuationItems))
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

        $this->mutateData($data);
    }
}