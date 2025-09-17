<?php
namespace Polymerize\Controller\Comment;

use Rehike\ControllerV2\RequestMetadata;

use Polymerize\Controller\Core\PolymerController;
use Polymerize\Model\Comments\CommentRenderer;
use Polymerize\Model\Comments\CommentThreadRenderer;
use Polymerize\Model\Common\DisplayNameManager;

class GetCommentRepliesController extends PolymerController
{
    public function onPost(RequestMetadata $request, object &$data): void
    {
        $dataInner = null;
        if ($dataInner = @$data->onResponseReceivedEndpoints)
        {
            foreach ($dataInner as &$endpoint)
            {
                $endpointInner = null;
                if (($endpointInner = @$endpoint->appendContinuationItemsAction)
                || ($endpointInner = @$endpoint->reloadContinuationItemsCommand))
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