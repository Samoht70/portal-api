<?php

namespace Functional\Organizations\Rest\Controllers;

use Functional\Organizations\Rest\Resources\ClientResource;
use Lomkit\Rest\Http\Resource;
use Technical\Rest\Controller;

class ClientsController extends Controller
{
    /**
     * The resource the controller corresponds to.
     *
     * @var class-string<resource>
     */
    public static $resource = ClientResource::class;
}
