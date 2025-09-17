<?php
namespace Polymerize\Controller\Account;

use Rehike\ControllerV2\RequestMetadata;

use Polymerize\Controller\Core\PolymerController;
use Polymerize\Model\Account\AccountMenuModel;

class AccountMenuController extends PolymerController
{
    public function onPost(RequestMetadata $request, object &$data): void
    {
        $dataInner = null;
        if ($dataInner = @$data->actions[0]->openPopupAction->popup->multiPageMenuRenderer)
        {
            $header = @$dataInner->header->activeAccountHeaderRenderer ?? null;
            AccountMenuModel::mutate(
                $dataInner->sections,
                $header
            );
        }
    }
}