<?php
namespace Polymerize\Controller;

use Rehike\ControllerV2\RequestMetadata;

use Polymerize\Controller\Core\PolymerController;
use Polymerize\Model\Browse\BrowseModel;
use Polymerize\Model\Search\SearchSubMenuRenderer;

class SearchController extends PolymerController
{
    private function mutateData(object &$data): void
    {
        $contents = @$data->contents->twoColumnSearchResultsRenderer->primaryContents ?? null;
        $header = @$data->header->searchHeaderRenderer ?? null;
        if (!is_null($contents))
        {
            BrowseModel::mutateSingleItem($contents);

            if (!is_null($header))
            {
                $contents->sectionListRenderer->subMenu = SearchSubMenuRenderer::fromSearchHeaderRenderer($header);
            }
        }
    }

    public function onGet(RequestMetadata $request, object &$data): void
    {
        $this->yt->ytPageType = "search";
        $this->mutateData($data);
    }

    public function onPost(RequestMetadata $request, object &$data): void
    {
        $this->mutateData($data);
    }
}