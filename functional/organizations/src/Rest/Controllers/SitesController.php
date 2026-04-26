<?php

namespace Functional\Organizations\Rest\Controllers;

use Functional\Organizations\Rest\Resources\SiteResource;
use Lomkit\Rest\Http\Resource;
use Technical\Rest\Controller;

class SitesController extends Controller
{
    /**
     * The resource the controller corresponds to.
     *
     * @var class-string<resource>
     */
    public static $resource = SiteResource::class;
}
