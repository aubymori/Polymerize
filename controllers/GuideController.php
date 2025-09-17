<?php
namespace Polymerize\Controller;

use Rehike\ControllerV2\RequestMetadata;

use Polymerize\Controller\Core\PolymerController;
use Polymerize\Model\Guide\GuideModel;

class GuideController extends PolymerController
{
    public function onPost(RequestMetadata $request, object &$data): void
    {
        if (isset($data->items))
        {
            GuideModel::mutate($data->items, $this->isLoggedIn());
        }
    }
}