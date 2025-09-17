<?php
namespace Polymerize\Controller\Notification;

use Rehike\ControllerV2\RequestMetadata;

use Polymerize\Controller\Core\PolymerController;
use Polymerize\Model\Comments\CommentThreadRenderer;
use Polymerize\Model\Comments\CommentRenderer;
use Polymerize\Model\Common\DisplayNameManager;

class GetNotificationMenuController extends PolymerController
{
    public function onPost(RequestMetadata $request, object &$data): void
    {
        $dataInner = null;
        if ($dataInner = @$data->actions[0]->getMultiPageMenuAction->menu->multiPageMenuRenderer)
        {
            foreach ($dataInner->sections as &$section)
            {
                if (is_array(@$section->itemSectionRenderer->contents))
                {
                    foreach ($section->itemSectionRenderer->contents as &$content)
                    {
                        if (isset($content->commentThreadRenderer))
                        {
                            CommentThreadRenderer::mutate(
                                $content->commentThreadRenderer,
                                $data->frameworkUpdates
                            );
                        }
                    }

                    $displayNameManager = new DisplayNameManager;
                    $displayNameManager->applyDisplayNamesToComments($section->itemSectionRenderer->contents);
                }
            }
        }
        else if ($dataInner = @$data->actions)
        {
            foreach ($dataInner as &$action)
            {
                $actionInner = null;
                if (($actionInner = @$action->appendContinuationItemsAction)
                || ($actionInner = @$action->reloadContinuationItemsCommand))
                {
                    if (is_array(@$actionInner->continuationItems))
                    {
                        foreach ($actionInner->continuationItems as &$item)
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
                            $actionInner->continuationItems
                        );
                    }
                }
            }
        }
    }
}