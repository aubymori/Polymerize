<?php
namespace Polymerize\Controller;

use Rehike\ControllerV2\Router;

Router::redirect([
    "/shorts/(*)" => function($request)
    {
        if (isset($request->path[1]))
            return "/watch?v=" . $request->path[1];
        else
            return "/watch";
    },
]);

Router::get([
    "/polymerize/static/**" => Polymerize\StaticRouter::class
]);

Router::funnel([
    "/signin_passive",
    "/signin"
]);

Router::route([
    // InnerTube Action                       Page URI                                  Controller class
    // =================================================================================================
    [ "guide",                                null,                             GuideController::class ],
    [ "browse",                               null,                            BrowseController::class ],
    [ "next",                                 "/watch",                          NextController::class ],
    [ "search",                               "/results",                      SearchController::class ],
    [ "player",                               null,                            PlayerController::class ],
    [ "account/account_menu",                 null,               Account\AccountMenuController::class ],
    [ "subscription/subscribe",               null,            Subscription\SubscribeController::class ],
    [ "comment/get_comment_replies",          null,         Comment\GetCommentRepliesController::class ],
    [ "comment/create_comment",               null,             Comment\CreateCommentController::class ],
    [ "comment/create_comment_reply",         null,        Comment\CreateCommentReplyController::class ],
    [ "comment/update_comment",               null,             Comment\UpdateCommentController::class ],
    [ "comment/update_comment_reply",         null,        Comment\UpdateCommentReplyController::class ],
    [ "comment/perform_comment_action",       null,      Comment\PerformCommentActionController::class ],
    [ "share/get_share_panel",                null,               Share\GetSharePanelController::class ],
    [ "notification/get_notification_menu",   null,  Notification\GetNotificationMenuController::class ],
]);