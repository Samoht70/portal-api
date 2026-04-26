<?php

namespace Technical\Authentication\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Technical\Authentication\Http\Requests\LoginRequest;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        if (!$token = auth()->attempt($credentials)) {
            throw new UnauthorizedHttpException('Basic', 'invalid-credentials');
        }

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60
        ]);
    }

    public function logout(): Response
    {
        auth()->logout();

        return response()->noContent();
    }

    public function refresh(): JsonResponse
    {
        return response()->json([
            'access_token' => auth()->refresh(),
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60
        ]);
    }

    public function me(): JsonResponse
    {
        $currentUser = auth()->user();

        $currentUser->permissions = $currentUser->getPermissionsViaRoles()->pluck('name');
        $currentUser->unsetRelations();

        return response()->json($currentUser);
    }
}
