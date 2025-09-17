<?php
namespace Polymerize\Controller;

use Rehike\ControllerV2\RequestMetadata;

use Polymerize\Controller\Core\PolymerController;

class PlayerController extends PolymerController
{
    /**
     * Nukes ads. I hate Google for adding ads to the EMBED player.
     */
    public function onPost(RequestMetadata $request, object &$data): void
    {
        unset($data->playerAds);
        unset($data->adPlacements);
        unset($data->adSlots);
    }
}