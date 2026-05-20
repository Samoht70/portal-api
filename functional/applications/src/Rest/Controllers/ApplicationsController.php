<?php

namespace Functional\Applications\Rest\Controllers;

use Functional\Applications\Rest\Resources\ApplicationResource;
use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Resource;

class ApplicationsController extends Controller
{
    /**
     * The resource the controller corresponds to.
     *
     * @var class-string<Resource>
     */
    public static $resource = ApplicationResource::class;
}
