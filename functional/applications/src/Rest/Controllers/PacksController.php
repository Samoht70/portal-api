<?php

namespace Functional\Applications\Rest\Controllers;

use Functional\Applications\Rest\Resources\PackResource;
use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Resource;

class PacksController extends Controller
{
    /**
     * The resource the controller corresponds to.
     *
     * @var class-string<Resource>
     */
    public static $resource = PackResource::class;
}
