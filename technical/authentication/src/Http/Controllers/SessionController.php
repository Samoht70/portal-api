<?php

namespace Technical\Authentication\Http\Controllers;

use Functional\Users\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * The authenticated user's own session surface, guarded by the stateless
 * `auth:api` token guard.
 */
class SessionController extends Controller
{
    public function me(#[CurrentUser] User $user): JsonResponse
    {
        $user->permissions = $user->getPermissionsViaRoles()->pluck('name');
        $user->unsetRelations();

        return response()->json($user);
    }

    public function logout(#[CurrentUser] User $user): Response
    {
        // Revoke the presented access token — stateless, no session to flush.
        $user->token()->revoke();

        return response()->noContent();
    }
}
