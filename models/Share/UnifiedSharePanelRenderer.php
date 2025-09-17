<?php
namespace Polymerize\Model\Share;

class UnifiedSharePanelRenderer
{
    public static function mutate(object &$data): void
    {
        $targets = @$data->contents[0]->thirdPartyNetworkSection->shareTargetContainer
            ->thirdPartyShareTargetSectionRenderer->shareTargets ?? null;
        if (!is_null($targets))
        foreach ($targets as &$target)
        {
            if (@$target->shareTargetRenderer->serviceName == "TWITTER")
            {
                $target->shareTargetRenderer->title = (object)[
                    "simpleText" => "Twitter"
                ];
            }
        }
    }
}