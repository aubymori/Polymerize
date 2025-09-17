<?php
namespace Polymerize\Controller\Share;

use Rehike\ControllerV2\RequestMetadata;

use Polymerize\Controller\Core\PolymerController;
use Polymerize\Model\Share\UnifiedSharePanelRenderer;

class GetSharePanelController extends PolymerController
{
    public function onPost(RequestMetadata $request, object &$data): void
    {
        if (is_array(@$data->actions))
        foreach ($data->actions as &$action)
        {
            $actionInner = null;
            if ($actionInner = @$action->openPopupAction->popup->unifiedSharePanelRenderer)
            {
                UnifiedSharePanelRenderer::mutate($actionInner);
            }
        }
    }
}