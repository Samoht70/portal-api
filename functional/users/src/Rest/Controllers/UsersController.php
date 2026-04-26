<?php

namespace Functional\Users\Rest\Controllers;

use Functional\Users\Rest\Resources\UserResource;
use Lomkit\Rest\Http\Resource;
use Technical\Rest\Controller;

class UsersController extends Controller
{
    /**
     * The resource the controller corresponds to.
     *
     * @var class-string<resource>
     */
    public static $resource = UserResource::class;
}
