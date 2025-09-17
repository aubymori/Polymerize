<?php
namespace Polymerize\Controller\Core;

use Polymerize\{
    YtApp,
    YtCfg,
    Util\PrefUtils
};

use Polymerize\Model\Common\DesktopTopbarRenderer;

use Rehike\{
    SimpleFunnel,
    SimpleFunnelResponse,
    TemplateManager,
    Network,
    ConfigManager\Config,
    i18n\i18n,
    Async\Promise
};

use Rehike\ControllerV2\{
    IController,
    IGetController,
    IPostController,
    BaseController,
    RequestMetadata
};

class PolymerController extends BaseController implements IGetController, IPostController
{
    /**
     * Stores information used to render Polymer templates with Twig.
     */
    protected ?YtApp $yt = null;

    /**
     * SimpleFunnel response.
     */
    protected ?SimpleFunnelResponse $response = null;

    /**
     * If this is false during a GET request, the HTML will not be rendered.
     */
    protected bool $doRender = true;

    /**
     * Constructor function so that other controllers, namely the browse
     * controller, can call other controllers if necessary without redoing
     * the request.
     */
    public function __construct(?SimpleFunnelResponse $r = null)
    {
        $this->response = $r;
    }

    public function getYtApp(): YtApp
    {
        return $this->yt;
    }

    private function init(object &$data): void
    {
        foreach (SimpleFunnel::responseHeadersToHttp($this->response->headers) as $httpHeader)
        {
            header($httpHeader, false);
        }
        http_response_code($this->response->status);

        $hl = $this->getHl();
        if (!is_null($hl) && is_dir($_SERVER["DOCUMENT_ROOT"] . "/i18n/" . $hl))
        {
            i18n::getConfigApi()
                ->setCurrentLanguageId($hl);
        }

        if ($topbar = @$data->topbar->desktopTopbarRenderer)
        {
            DesktopTopbarRenderer::mutate($topbar);
        }
    }

    private function getResponse(): Promise
    {
        if (!is_null($this->response))
        {
            return new Promise(function($r) { $r($this->response); });
        }
        else
        {
            return SimpleFunnel::funnelCurrentPage();
        }
    }

    private static function generateSapisidHash(): ?string
    {
        if (!isset($_COOKIE["SAPISID"]))
            return null;

        $sapisid = $_COOKIE["SAPISID"];
        $time = time();
        $sha1 = sha1("{$time} {$sapisid} https://www.youtube.com");
        return "SAPISIDHASH {$time}_{$sha1}";
    }

    public function get(): void
    {
        $this->yt = YtApp::getInstance();
        $this->getResponse()->then(function(SimpleFunnelResponse $r)
        {
            $this->response = $r;
            // Don't even bother with non-HTML content.
            if (false === strpos(strtolower($this->response->getHeader("Content-Type")), "text/html"))
            {
                $this->response->output();
                return;
            }
            $this->yt->ytInitialData = $this->extractInitialData();
            // If we couldn't find the initial data, just assume it's not polymer and output.
            if (is_null($this->yt->ytInitialData))
            {
                $this->response->output();
                return;
            }

            $ytcfg = $this->extractYtCfg();
            $this->yt->ytcfg = YtCfg::build($ytcfg);

            Network::setInnertubeContext($this->yt->ytcfg->INNERTUBE_CONTEXT);
            Network::setInnertubeHeaders([
                "X-Youtube-Client-Name" => @$this->yt->ytcfg->INNERTUBE_CONTEXT_CLIENT_NAME ?? null,
                "X-Youtube-Client-Version" => @$this->yt->ytcfg->INNERTUBE_CONTEXT_CLIENT_VERSION ?? null,
                "X-Goog-AuthUser" => @$this->yt->ytcfg->SESSION_INDEX ?? null,
                "X-Goog-Visitor-Id" => @$this->yt->ytcfg->VISITOR_DATA ?? null,
                "Authorization" => self::generateSapisidHash()
            ]);

            $this->yt->ytCommand = $this->extractCommand();
            $this->yt->ytUrl = $_SERVER["REQUEST_URI"];

            $this->yt->skeleton = "none";
            $this->yt->config = Config::loadConfig();

            $strings = i18n::getNamespace("core")->getAllTemplates();
            $this->yt->messages = $strings->msg;
            $this->yt->footerStrings = $strings->footer;
            $this->yt->theme = PrefUtils::getTheme();

            $this->init($this->yt->ytInitialData);

            $this->onGet($this->getRequest(), $this->yt->ytInitialData);

            Network::run();

            if ($this->doRender)
                echo TemplateManager::render([], "core");
        });
    }

    public function post(): void
    {
        $this->yt = YtApp::getInstance();
        $this->getResponse()->then(function(SimpleFunnelResponse $r)
        {
            $this->response = $r;

            // Don't even bother with non-JSON content.
            if (false === strpos(strtolower($this->response->getHeader("Content-Type")), "application/json"))
            {
                $this->response->output();
                return;
            }

            $data = null;
            try
            {
                $data = json_decode($this->response->getText());
            }
            catch (\Throwable $e)
            {
                $this->response->output();
                return;
            }

            $request = $this->getRequest();
            $requestData = $request->getPostBody("application/json");
            if (is_null($requestData))
            {
                $this->response->output();
                return;
            }

            Network::setInnertubeContext($requestData->context);
            $headers = $request->getHeaders();
            Network::setInnertubeHeaders([
                "X-Youtube-Client-Name" => @$headers["x-youtube-client-name"] ?? null,
                "X-Youtube-Client-Version" => @$headers["x-youtube-client-version"] ?? null,
                "X-Goog-AuthUser" => @$headers["x-goog-authuser"] ?? null,
                "X-Goog-Visitor-Id" => @$headers["x-goog-visitor-id"] ?? null,
                "Authorization" => @$headers["authorization"] ?? null
            ]);

            $this->init($data);

            $this->onPost($request, $data);

            Network::run();
            
            echo json_encode($data);
        });
    }

    /**
     * Gets the current InnerTube language.
     */
    public function getHl(): ?string
    {
        // If a page request, get from ytcfg data.
        if ($_SERVER["REQUEST_METHOD"] == "GET")
        {
            return @$this->yt->ytcfg->HL ?? null;
        }
        // If an InnerTube request, get from the context sent.
        else
        {
            $json = @json_decode(file_get_contents("php://input"));
            if (!is_null($json))
            {
                return @$json->context->client->hl ?? null;
            }
            return null;
        }
    }

    private function getData(): ?object
    {
        $data = null;
        if ($_SERVER["REQUEST_METHOD"] == "GET")
        {
            $data = $this->yt->ytInitialData;
        }
        else
        {
            try
            {
                $data = json_decode($this->response->getText());
            }
            catch (\Throwable $e)
            {  }
        }
        return $data;
    }

    /**
     * Queries the response context to determine whether the user is logged in.
     */
    public function isLoggedIn(): bool
    {
        $data = $this->getData();
        return isset($data->responseContext->mainAppWebResponseContext->loggedOut)
            ? !$data->responseContext->mainAppWebResponseContext->loggedOut
            : false;
    }

    public function getServiceTrackingParam(string $serviceName, string $paramName): ?string
    {
        $data = $this->getData();
        if (is_null($data))
            return null;
        if (is_array(@$data->responseContext->serviceTrackingParams))
        foreach ($data->responseContext->serviceTrackingParams as $service)
        if ($service->service == $serviceName)
        foreach ($service->params as $param)
        if ($param->key == $paramName)
        {
            return $param->value;
        }
        return null;
    }

    /**
     * This method is overloaded by other controllers to perform data transformations
     * and other tasks.
     */
    public function onGet(RequestMetadata $request, object &$data): void
    {

    }

    /**
     * This method is overloaded by other controllers to perform data transformations
     * and other tasks.
     */
    public function onPost(RequestMetadata $request, object &$data): void
    {

    }

    protected static function extractData(string $input, string $prefix, string $suffix): ?string
    {
        $start = strpos($input, $prefix);
        if (false === $start)
            return null;
        $input = substr($input, $start + strlen($prefix));

        $end = strpos($input, $suffix);
        if (false === $end)
            return null;
        $result = substr($input, 0, $end);
        return $result;
    }

    protected function extractJsonData(string $input, string $prefix, string $suffix): ?object
    {
        $extracted = self::extractData($input, $prefix, $suffix);
        try
        {
            return json_decode($extracted);
        } catch (\Throwable $e) { echo "what"; var_dump($extracted); return null; }
    }

    private function extractYtCfg(): ?object
    {
        return self::extractJsonData(
            $this->response->getText(),
            "\nytcfg.set(",
            ");"
        );
    }

    private function extractInitialData(): ?object
    {
        return self::extractJsonData(
            $this->response->getText(),
            ">var ytInitialData = ",
            ";</script>",
            true
        );
    }

    private function extractCommand(): ?object
    {
        return self::extractJsonData(
            $this->response->getText(),
            "window['ytCommand'] = ",
            ";"
        );
    }
}