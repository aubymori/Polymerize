<?php
namespace Polymerize;

use Rehike\ConfigManager\Config;

/**
 * Builds the YtCfg.
 */
class YtCfg
{
    private const CSP_NONCE = "tZ6lf2cgpM1Lr7NaCwIpuw";

    private static array $webPlayerContextConfigs = [
        "WEB_PLAYER_CONTEXT_CONFIG_ID_KEVLAR_WATCH" => [
            "transparentBackground" => true,
            "showMiniplayerButton" => true,
            "externalFullscreen" => true,
            "showMiniplayerUiWhenMinimized" => true,
            "rootElementId" => "movie_player",
            "eventLabel" => "detailpage",
            "playerStyle" => "desktop-polymer",
            "csiPageType" => "watch",
            "enableCsiLogging" => true,
            "allowWoffleManagement" => true
        ],
        "WEB_PLAYER_CONTEXT_CONFIG_ID_KEVLAR_CHANNEL_TRAILER" => [
            "rootElementId" => "c4-player",
            "eventLabel" => "profilepage",
            "playerStyle" => "desktop-polymer",
            "enableCsiLogging" => true,
            "csiPageType" => "channels"
        ],
        "WEB_PLAYER_CONTEXT_CONFIG_ID_KEVLAR_PLAYLIST_OVERVIEW" => [
            "rootElementId" => "c4-player",
            "eventLabel" => "playlistoverview",
            "playerStyle" => "desktop-polymer",
            "disableSharing" => true,
            "hideInfo" => true,
            "disableWatchLater" => true,
            "enableCsiLogging" => true,
            "csiPageType" => "playlist_overview"
        ],
        "WEB_PLAYER_CONTEXT_CONFIG_ID_KEVLAR_VERTICAL_LANDING_PAGE_PROMO" => [
            "rootElementId" => "ytd-default-promo-panel-renderer-inline-playback-renderer",
            "eventLabel" => "detailpage",
            "playerStyle" => "desktop-polymer",
            "controlsType" => 0,
            "disableRelatedVideos" => true,
            "hideInfo" => true,
            "startMuted" => true,
            "enableMutedAutoplay" => true,
            "csiPageType" => "watch",
            "enableCsiLogging" => true,
        ],
        "WEB_PLAYER_CONTEXT_CONFIG_ID_KEVLAR_SPONSORSHIPS_OFFER" => [
            "rootElementId" => "ytd-sponsorships-offer-with-video-renderer",
            "eventLabel" => "sponsorshipsoffer",
            "playerStyle" => "desktop-polymer",
            "disableRelatedVideos" => true,
            "annotationsLoadPolicy" => 3,
            "disableFullscreen" => true,
        ],
        "WEB_PLAYER_CONTEXT_CONFIG_ID_KEVLAR_SHORTS" => [
            "rootElementId" => "shorts-player",
            "playerStyle" => "desktop-polymer",
            "controlsType" => 0,
            "disableKeyboardControls" => true,
            "disableRelatedVideos" => true,
            "annotationsLoadPolicy" => 3,
            "hideInfo" => true,
            "disableFullscreen" => true,
            "enableCsiLogging" => true,
            "storeUserVolume" => true,
            "disableSeek" => true,
            "disablePaidContentOverlay" => true
        ]
    ];

    public static function decodePlayerFlags(string $file): array|null
    {
        $playerFlags = file_get_contents($_SERVER["DOCUMENT_ROOT"] . "/" . $file);
        $playerFlags = preg_replace("/\r?\n/", "&", $playerFlags);
        $split = explode("&", $playerFlags);
        $result = [];
        foreach ($split as $flag)
        {
            if (false === strpos($flag, "="))
                continue;
            // comment lines
            if (substr($flag, 0, 1) == "#")
                continue;
            $split2 = explode("=", $flag, 2);
            $result[$split2[0]] = $split2[1];
        }
        return $result;
    }

    public static function encodePlayerFlags(array $playerFlags): string
    {
        $flags = [];
        foreach ($playerFlags as $key => $value)
        {
            $flags[] = "$key=$value";
        }
        return implode("&", $flags);
    }

    public static function build(object $baseYtCfg): object
    {
        $ytcfg = json_decode(file_get_contents($_SERVER["DOCUMENT_ROOT"] . "/data/ytcfg_base.json"));
        $kevlarWatchCfg = $baseYtCfg->WEB_PLAYER_CONTEXT_CONFIGS->WEB_PLAYER_CONTEXT_CONFIG_ID_KEVLAR_WATCH;
        $jsUrl = $kevlarWatchCfg->jsUrl;
        $cssUrl = $kevlarWatchCfg->cssUrl;

        $ytcfg->PLAYER_JS_URL = $jsUrl;
        $ytcfg->PLAYER_CSS_URL = $cssUrl;

        // Experiment flags, grabs from user overrides too
        $userExpFlags = @json_decode(file_get_contents($_SERVER["DOCUMENT_ROOT"] . "/expflags_overrides.json"));
        if (!is_null($userExpFlags))
        foreach ($userExpFlags as $key => $value)
        {
            $ytcfg->EXPERIMENT_FLAGS->{$key} = $value;
        }

        // Option-specific overrides:
        $ytcfg->EXPERIMENT_FLAGS->kevlar_system_icons = !Config::getConfigProp("appearance.oldIcons");
        if (Config::getConfigProp("watch.oldInfoLayout"))
            $ytcfg->EXPERIMENT_FLAGS->no_sub_count_on_sub_button = false;

        // Player flags, grabs from user overrides too
        $basePlayerFlags = self::decodePlayerFlags("data/player_flags_base.txt");
        $userPlayerFlags = self::decodePlayerFlags("player_flags_overrides.txt");
        if (!is_null($userPlayerFlags))
        foreach ($userPlayerFlags as $key => $value)
        {
            $basePlayerFlags[$key] = $value;
        }

        // Option-specific overrides:
        if (Config::getConfigProp("watch.sidebarStyle") == "COMPACT_AUTOPLAY")
        {
            $basePlayerFlags["web_player_move_autonav_toggle"] = "false";
        }

        $playerFlags = self::encodePlayerFlags($basePlayerFlags);

        // ??? what the hell is this
        $playerIds = file_get_contents($_SERVER["DOCUMENT_ROOT"] . "/data/player_ids_base.txt");
        $playerIds = preg_replace("/\r?\n/", ",", $playerIds);

        $playerConfigs = self::$webPlayerContextConfigs;
        foreach ($playerConfigs as $name => &$data)
        {
            $data["contextId"] = $name;
            $data["jsUrl"] = $jsUrl;
            $data["cssUrl"] = $cssUrl;

            $data["serializedExperimentIds"] = $playerIds;
            $data["serializedExperimentFlags"] = $playerFlags;

            $data["cspNonce"] = self::CSP_NONCE;

            foreach ([
                "contentRegion",
                "hl",
                "hostLanguage",
                "innertubeApiKey",
                "innertubeApiVersion",
                "innertubeContextClientVersion",
                "device",
                "authorizedUserIndex",
                "datasyncId"
            ] as $prop)
            {
                @$data[$prop] = @$kevlarWatchCfg->{$prop};
            }
        }

        $ytcfg->WEB_PLAYER_CONTEXT_CONFIGS = $playerConfigs;

        foreach ([
            "HL",
            "GL",
            "HTML_DIR",
            "HTML_LANG",
            "GAPI_LOCALE",
            "GAPI_HINT_PARAMS",
            "INNERTUBE_API_KEY",
            "INNERTUBE_API_VERSION",
            "INNERTUBE_CLIENT_NAME",
            "INNERTUBE_CLIENT_VERSION",
            "INNERTUBE_CONTEXT_CLIENT_NAME",
            "INNERTUBE_CONTEXT_CLIENT_VERSION",
            "INNERTUBE_CONTEXT_HL",
            "INNERTUBE_CONTEXT_GL",
            "INNERTUBE_CONTEXT",
            "LOGGED_IN",
            "PAGE_BUILD_LABEL",
            "SESSION_INDEX",
            "SIGNIN_URL",
            "DELEGATED_SESSION_ID",
            "VISITOR_DATA",
            "DATASYNC_ID",
            "XSRF_TOKEN",
            "TIME_CREATED_MS",
            "SERIALIZED_CLIENT_CONFIG_DATA",
            "DEVICE",
            "WORKER_SERIALIZATION_URL",
            "EVENT_ID",
            "BATCH_CLIENT_COUNTER",
            "VALID_SESSION_TEMPDATA_DOMAINS",
            "FEXP_EXPERIMENTS",
            "LATEST_ECATCHER_SERVICE_TRACKING_PARAMS",
            "LOGIN_INFO",
            "CLIENT_PROTOCOL",
            "CLIENT_TRANSPORT",
            "DCLKSTAT",
            "USER_SESSION_ID",
            "STS",
            "RECAPTCHA_V3_SITEKEY",
        ] as $prop)
        {
            if (isset($baseYtCfg->{$prop}))
                $ytcfg->{$prop} = $baseYtCfg->{$prop};
        }

        return $ytcfg;
    }
}