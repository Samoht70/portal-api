<?php

namespace Technical\OauthServer\Http\Controllers;

use Functional\Users\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Technical\OauthServer\Actions\RegisterChildClient;

/**
 * First-party administration of the OAuth clients that represent child
 * applications. Clients are owned by the administrator who registers them and
 * read back through the non-deprecated `oauthApps()` relationship. This is a
 * custom (non-CRUD) management surface, so it stays a regular controller rather
 * than a lomkit REST resource.
 */
class OAuthClientController extends Controller
{
    public function index(#[CurrentUser] User $user): JsonResponse
    {
        return response()->json($user->oauthApps()->get());
    }

    public function store(Request $request, #[CurrentUser] User $user, RegisterChildClient $registerChildClient): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'redirect_uris' => ['required', 'array', 'min:1'],
            'redirect_uris.*' => ['required', 'url'],
        ]);

        $client = $registerChildClient->handle(
            $user,
            $validated['name'],
            $validated['redirect_uris'],
        );

        return response()->json($client, SymfonyResponse::HTTP_CREATED);
    }

    public function destroy(#[CurrentUser] User $user, string $client): Response
    {
        $model = $user->oauthApps()->find($client);

        if ($model === null) {
            throw new NotFoundHttpException;
        }

        $model->delete();

        return response()->noContent();
    }
}
