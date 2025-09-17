<?php
namespace Polymerize\Controller\Subscription;

use Rehike\ControllerV2\RequestMetadata;

use Polymerize\Controller\Core\PolymerController;
use Polymerize\Model\Common\SubscriptionNotificationToggleButtonRenderer;

class SubscribeController extends PolymerController
{
    public function onPost(RequestMetadata $request, object &$data): void
    {
        $notificationButton = null;
        if ($notificationButton = @$data->newNotificationButton->subscriptionNotificationToggleButtonRenderer)
        {
            SubscriptionNotificationToggleButtonRenderer::mutate($notificationButton);
        }
    }
}