<?php
namespace Polymerize\Controller\Comment;

use Rehike\ControllerV2\RequestMetadata;

use Polymerize\Controller\Core\PolymerController;
use Polymerize\Model\Comments\CommentRenderer;
use Polymerize\Model\Common\DisplayNameManager;

class CreateCommentReplyController extends PolymerController
{
    public function onPost(RequestMetadata $request, object &$data): void
    {
        if (is_array($data->actions))
        foreach ($data->actions as &$action)
        {
            if (isset($action->createCommentReplyAction->contents->commentViewModel))
            {
                $action->createCommentReplyAction->contents = CommentRenderer::fromViewModel(
                    $action->createCommentReplyAction->contents,
                    $data->frameworkUpdates
                );

                $displayNameManager = new DisplayNameManager;
                $displayNameManager->applyDisplayNamesToSingleComment(
                    $action->createCommentReplyAction->contents
                );
            }
        }
    }
}