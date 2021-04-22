<?php

namespace App\Http\Controllers;

use App\Services\Admin\SitemapGenerator;
use Common\Core\BaseController;
use Illuminate\Http\JsonResponse;

class SitemapController extends BaseController
{
    /**
     * @return JsonResponse
     */
    public function generate()
    {
        app(SitemapGenerator::class)->generate();
        return $this->success([]);
    }
}
