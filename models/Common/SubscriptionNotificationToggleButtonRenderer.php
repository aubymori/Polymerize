<?php
namespace Polymerize\Model\Common;

use Rehike\i18n\i18n;

/**
 * Mutates the subscriptionNotificationToggleButtonRenderer to match 2021 Polymer.
 */
class SubscriptionNotificationToggleButtonRenderer
{
    /**
     * Mutates a subscriptionNotificationToggleButtonRenderer object.
     * 
     * @param object &$topbar Reference to the subscriptionNotificationToggleButtonRenderer  object to mutate.
     */
    public static function mutate(object &$data): void
    {
        /* Remove the "Unsubscribe" menu item from the notification button. */
        $popupItems = null;
        if ($popupItems = @$data->command->commandExecutorCommand->commands[0]->openPopupAction->popup->menuPopupRenderer->items)
        {
            foreach ($popupItems as $i => $item)
            {
                if (@$item->menuServiceItemRenderer->icon->iconType == "PERSON_MINUS")
                {
                    // PHP references are weak AF.
                    array_splice(
                        $data->command->commandExecutorCommand->commands[0]->openPopupAction->popup->menuPopupRenderer->items,
                        $i, 1
                    );
                }
            }
        }
    }
}