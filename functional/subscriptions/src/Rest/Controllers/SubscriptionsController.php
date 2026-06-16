<?php

namespace Functional\Subscriptions\Rest\Controllers;

use Functional\Subscriptions\Rest\Resources\SubscriptionResource;
use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Resource;

class SubscriptionsController extends Controller
{
    /**
     * The resource the controller corresponds to.
     *
     * @var class-string<resource>
     */
    public static $resource = SubscriptionResource::class;
}
