<?php
namespace Polymerize\Controller\Comment;

use Rehike\ControllerV2\RequestMetadata;

use Polymerize\Controller\Core\PolymerController;
use Polymerize\Model\Comments\CommentThreadRenderer;
use Polymerize\Model\Common\DisplayNameManager;

class PerformCommentActionController extends PolymerController
{
    public function onPost(RequestMetadata $request, object &$data): void
    {
        if (is_array(@$data->actions))
        foreach ($data->actions as &$action)
        {
            $actionInner = null;
            if (($actionInner = @$action->pinCommentAction)
            || ($actionInner = @$action->unpinCommentAction))
            {
                if (isset($actionInner->actionResult->update->commentThreadRenderer))
                {
                    CommentThreadRenderer::mutate(
                        $actionInner->actionResult->update->commentThreadRenderer,
                        $data->frameworkUpdates
                    );

                    $displayNameManager = new DisplayNameManager;
                    $displayNameManager->applyDisplayNamesToSingleComment(
                        $actionInner->actionResult->update
                    );
                }
            }
        }
    }
}