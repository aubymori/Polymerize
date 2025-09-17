<?php
namespace Polymerize\Controller\Comment;

use Rehike\ControllerV2\RequestMetadata;

use Polymerize\Controller\Core\PolymerController;
use Polymerize\Model\Comments\CommentRenderer;
use Polymerize\Model\Common\DisplayNameManager;

class UpdateCommentReplyController extends PolymerController
{
    public function onPost(RequestMetadata $request, object &$data): void
    {
        if (is_array($data->actions))
        foreach ($data->actions as &$action)
        if (isset($action->updateCommentReplyAction->commentId))
        {
            $viewModel = CommentRenderer::reconstructCommentViewModel($data->frameworkUpdates);
            $action->updateCommentReplyAction->contents = CommentRenderer::fromViewModel($viewModel, $data->frameworkUpdates);

            $displayNameManager = new DisplayNameManager;
            $displayNameManager->applyDisplayNamesToSingleComment($action->updateCommentReplyAction->contents);
        }
    }
}