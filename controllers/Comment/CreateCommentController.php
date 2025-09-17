<?php
namespace Polymerize\Controller\Comment;

use Rehike\ControllerV2\RequestMetadata;

use Polymerize\Controller\Core\PolymerController;
use Polymerize\Model\Comments\CommentThreadRenderer;
use Polymerize\Model\Common\DisplayNameManager;

class CreateCommentController extends PolymerController
{
    public function onPost(RequestMetadata $request, object &$data): void
    {
        if (is_array($data->actions))
        foreach ($data->actions as &$action)
        {
            if (isset($action->createCommentAction->contents->commentThreadRenderer))
            {
                CommentThreadRenderer::mutate(
                    $action->createCommentAction->contents->commentThreadRenderer,
                    $data->frameworkUpdates
                );

                $displayNameManager = new DisplayNameManager;
                $displayNameManager->applyDisplayNamesToSingleComment(
                    $action->createCommentAction->contents
                );
            }
        }
    }
}