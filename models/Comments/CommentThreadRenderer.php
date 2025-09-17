<?php
namespace Polymerize\Model\Comments;

use Rehike\i18n\i18n;
use Rehike\ConfigManager\Config;

class CommentThreadRenderer
{
    public static function mutate(object &$thread, object $frameworkUpdates): void
    {
        if (isset($thread->commentViewModel))
        {
            $thread->comment = CommentRenderer::fromViewModel($thread->commentViewModel, $frameworkUpdates);
            unset($thread->commentViewModel);
        }

        $replies = null;
        if ($replies = @$thread->replies->commentRepliesRenderer)
        {
            $strings = i18n::getNamespace("comments");
            $creatorText = @$replies->viewRepliesCreatorThumbnail->accessibility->accessibilityData->label ?? null;
            $replyCount = (int)preg_replace("/[^0-9]+/", "", @$replies->viewReplies->buttonRenderer->text->runs[0]->text ?? "0");
            
            $viewString = "viewReplies" . (is_null($creatorText) ? "" : "Creator") . ($replyCount <= 1 ? "Singular" : "Plural");
            $hideString = "hideReplies" . ($replyCount <= 1 ? "Singular" : "Plural");

            $replies->viewReplies->buttonRenderer->text = (object)[
                "simpleText" => $strings->format(
                    $viewString,
                    ($viewString == "viewRepliesCreatorSingular") ? $creatorText : $replyCount,
                    $creatorText ?? ""
                )
            ];
            $replies->hideReplies->buttonRenderer->text = (object)[
                "simpleText" => $strings->format($hideString, $replyCount)
            ];

            $oldIcon = Config::getConfigProp("comments.oldRepliesArrow");
            $replies->viewReplies->buttonRenderer->icon = (object)[
                "iconType" => $oldIcon ? "EXPAND_MORE" : "ARROW_DROP_DOWN"
            ];
            $replies->viewReplies->buttonRenderer->iconPosition = "BUTTON_ICON_POSITION_TYPE_RIGHT_OF_TEXT";
            $replies->hideReplies->buttonRenderer->icon = (object)[
                "iconType" => $oldIcon ? "EXPAND_LESS" :  "ARROW_DROP_UP"
            ];
            $replies->hideReplies->buttonRenderer->iconPosition = "BUTTON_ICON_POSITION_TYPE_RIGHT_OF_TEXT";

            if (is_array(@$replies->teaserContents))
            foreach ($replies->teaserContents as &$teaser)
            {
                if (isset($teaser->commentViewModel))
                {
                    $teaser = CommentRenderer::fromViewModel($teaser, $frameworkUpdates);
                }
            }
        }
    }
}