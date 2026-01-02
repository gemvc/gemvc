<?php
namespace App\Controller;

use Gemvc\Core\Controller;
use Gemvc\Http\Request;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Response;
use Gemvc\Http\HtmlResponse;
use Gemvc\Core\Documentation;
use App\Controller\DeveloperController;
use Gemvc\Helper\ProjectHelper;

class IndexController extends Controller
{
    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    /**
     * Get server status or dev info
     * 
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        if(!ProjectHelper::isDevEnvironment()){
            return Response::success('server running',1,'server running');
        }
        return new DeveloperController($this->request)->devInfo();
    }

    /**
     * Get API documentation
     * 
     * @return HtmlResponse
     */
    public function document(): HtmlResponse
    {
        $doc = new Documentation();
        return $doc->htmlResponse();
    }

    /**
     * SPA Entry Point
     * 
     * @return HtmlResponse
     */
    public function developer(): HtmlResponse
    {
        if(!ProjectHelper::isDevEnvironment()){
            return new HtmlResponse('Page not found', 404);
        }
        $devController = new DeveloperController($this->request);
        return $devController->app();
    }



    /**
     * Get Logo Data
     * 
     * @return JsonResponse
     */
    public function logo(): JsonResponse
    {
        return new DeveloperController($this->request)->logo();
    }

    /**
     * Get Favicon
     * 
     * @return HtmlResponse
     */
    public function favicon(): HtmlResponse
    {
        $faviconPath = __DIR__ . '/../../vendor/gemvc/library/src/startup/common/system_pages/favicon.ico';
        
        if (file_exists($faviconPath)) {
            $faviconContent = file_get_contents($faviconPath);
            if ($faviconContent !== false) {
                $headers = [
                    'Content-Type' => 'image/x-icon',
                    'Cache-Control' => 'public, max-age=31536000'
                ];
                return new HtmlResponse($faviconContent, 200, $headers);
            }
        }
        
        return new HtmlResponse('', 404);
    }
}
