<?php
namespace Polymerize\Controller\Comment;

use Rehike\ControllerV2\RequestMetadata;

use Polymerize\Controller\Core\PolymerController;
use Polymerize\Model\Comments\CommentRenderer;
use Polymerize\Model\Common\DisplayNameManager;

class UpdateCommentController extends PolymerController
{
    public function onPost(RequestMetadata $request, object &$data): void
    {
        if (is_array($data->actions))
        foreach ($data->actions as &$action)
        if (isset($action->updateCommentAction->commentId))
        {
            $viewModel = CommentRenderer::reconstructCommentViewModel($data->frameworkUpdates);
            $action->updateCommentAction->contents = CommentRenderer::fromViewModel($viewModel, $data->frameworkUpdates);

            $displayNameManager = new DisplayNameManager;
            $displayNameManager->applyDisplayNamesToSingleComment($action->updateCommentAction->contents);
        }
    }
}