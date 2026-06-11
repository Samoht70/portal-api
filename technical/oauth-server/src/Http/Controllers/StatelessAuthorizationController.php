<?php

namespace Technical\OauthServer\Http\Controllers;

use Functional\Users\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Laravel\Passport\Bridge\User as UserEntity;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Response as PsrResponse;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Stateless, token-guarded replacement for Passport's session-based
 * authorization endpoint.
 *
 * Flow:
 *  1. A child app redirects the browser to GET /oauth/authorize. As a top-level
 *     navigation it carries no bearer token, so `prompt` bounces it to the SSO
 *     front-end (which holds the user's first-party token), preserving the
 *     original OAuth query.
 *  2. The front-end reads the consent details via `show` (auth:api).
 *  3. On approval it calls `authorize` (auth:api), which mints the authorization
 *     code and returns the redirect URL back to the child app's redirect_uri.
 *
 * No session, no StatefulGuard — the user is identified from the bearer token.
 */
class StatelessAuthorizationController extends Controller
{
    public function __construct(
        private readonly AuthorizationServer $server,
        private readonly ClientRepository $clients,
    ) {}

    public function prompt(Request $request): RedirectResponse
    {
        $target = rtrim((string) config('app.frontend_url'), '/').'/authorize';

        return redirect()->away($target.'?'.$request->getQueryString());
    }

    public function show(ServerRequestInterface $psrRequest): JsonResponse
    {
        try {
            $authRequest = $this->server->validateAuthorizationRequest($psrRequest);
        } catch (OAuthServerException $exception) {
            return $this->error($exception);
        }

        $client = $this->clients->find($authRequest->getClient()->getIdentifier());

        $scopeIds = array_map(
            fn ($scope): string => $scope->getIdentifier(),
            $authRequest->getScopes(),
        );

        return response()->json([
            'client' => [
                'id' => $client->getKey(),
                'name' => $client->name,
            ],
            'scopes' => Passport::scopesFor($scopeIds),
            'request' => $psrRequest->getQueryParams(),
        ]);
    }

    public function authorize(ServerRequestInterface $psrRequest, Request $request): JsonResponse
    {
        try {
            $authRequest = $this->server->validateAuthorizationRequest($psrRequest);

            /** @var User $user */
            $user = $request->user();
            $authRequest->setUser(new UserEntity($user->getAuthIdentifier()));
            $authRequest->setAuthorizationApproved($request->boolean('approved', true));

            $psrResponse = $this->server->completeAuthorizationRequest($authRequest, new PsrResponse);
        } catch (OAuthServerException $exception) {
            return $this->error($exception);
        }

        // completeAuthorizationRequest returns a 302 carrying the child app's
        // redirect_uri with the authorization code (or an error) in the query.
        return response()->json([
            'redirect_url' => $psrResponse->getHeaderLine('Location'),
        ]);
    }

    private function error(OAuthServerException $exception): JsonResponse
    {
        return response()->json([
            'error' => $exception->getErrorType(),
            'message' => $exception->getMessage(),
        ], $exception->getHttpStatusCode());
    }
}
