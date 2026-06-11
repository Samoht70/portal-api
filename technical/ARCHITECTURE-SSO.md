# SSO Authentication Architecture

This is the **mother / identity-provider** application. It authenticates its own
users (email + password with email verification, Microsoft / Azure AD, and
Fortify two-factor) and acts as an **OAuth2 + OpenID Connect authorization
server** so external **child applications** can sign users in against it.

Everything is **fully stateless**: there is no session guard for application
access. Access tokens are RSA-signed Passport JWTs that any resource server
validates offline against the JWKS endpoint.

---

## 1. Layer decomposition (OSDD)

Two technical layers, one acyclic dependency edge:

```
functional/users ──▶ technical/authentication ──▶ technical/oauth-server
                          (identity)                 (OAuth2/OIDC server)
```

| Layer | Responsibility | Key packages |
|---|---|---|
| `technical/oauth-server` | OAuth2/OIDC authorization server for child apps: clients, scopes, `/oauth/authorize`, `/oauth/token`, JWKS, OIDC discovery. Owns the stateless `auth.php` guards + `passport.php`. | `laravel/passport`, `jeremy379/laravel-openid-connect` |
| `technical/authentication` | First-party identity: credential login, email verification, 2FA (Fortify), Microsoft login, first-party token issuance. | `laravel/fortify`, `laravel/socialite`, `socialiteproviders/microsoft` |
| `functional/users` | Owns the `User` model + `users` table (incl. the verification / 2FA columns). | — |

`technical/authentication` depends on `technical/oauth-server` because it issues
first-party Passport tokens. `functional/users` depends on
`technical/authentication` because the `User` model composes `HasApiTokens`
(Passport) and `TwoFactorAuthenticatable` (Fortify) — this formalizes a
dependency that used to be implicit (the model previously imported `JwtUser`).

---

## 2. The three flows

### A — First-party stateless login (mother app's own front-end)

```
POST /api/auth/login {email, password}
  ├─ bad credentials      → 401 {error: invalid_credentials}
  ├─ email not verified   → 403 {error: email_unverified}   (verification re-sent)
  ├─ 2FA enabled          → 200 {two_factor: true, pending_token, expires_in}
  └─ otherwise            → 200 {access_token, token_type, expires_in}

POST /api/auth/two-factor-challenge {pending_token, code | recovery_code}
  → 200 {access_token, ...}   |   401 {error: invalid_two_factor_code|invalid_pending_token}
```

- **Credentials** are verified through the `users` provider directly
  (`AttemptCredentials`) — no session is created.
- **Email verification** is enforced before any token is issued; `User`
  implements `MustVerifyEmail`.
- **Stateless 2FA**: when 2FA is enabled the login returns a short-lived
  (5 min), encrypted-and-authenticated `pending_token` (`TwoFactorPendingToken`,
  no DB row, no session). The challenge endpoint resolves it, verifies the TOTP
  code (Fortify's provider) or consumes a single-use recovery code, then issues
  the real token via the single `IssueAccessToken` seam.

### B — Microsoft (Azure AD) login

```
GET /api/auth/microsoft/redirect   → {redirect_url}   (Socialite stateless())
GET /api/auth/microsoft/callback    → 200 {access_token|two_factor} | 404 {error: user_not_found}
```

- Socialite runs in `stateless()` mode (no session).
- **No auto-provisioning**: an unknown email is rejected with `user_not_found`;
  the account is never created on the fly (`AuthenticateMicrosoftUser`).
- A matched user with an unverified email is marked verified (Microsoft asserts
  the email). If the user has 2FA enabled, the same pending-token challenge applies.
- The callback returns JSON for an SPA-driven exchange. For a full-page redirect
  flow, redirect to `FRONTEND_URL` with a **one-time exchange code** instead of
  the token (never put a token in a URL).

### C — OAuth2 / OIDC for external child apps

Standard Authorization Code + PKCE handled by Passport + the OIDC layer:

```
GET  /oauth/authorize?response_type=code&client_id=…&redirect_uri=…
                     &code_challenge=…&code_challenge_method=S256&scope=openid …
POST /oauth/token   (grant_type=authorization_code, code, code_verifier, …)
                     → {access_token (JWT), refresh_token, id_token}
GET  /oauth/jwks                         (public keys)
GET  /.well-known/openid-configuration   (OIDC discovery)
```

- Child apps are **public clients with PKCE** registered via
  `RegisterChildClient` / `POST /api/oauth/clients`.
- Child apps validate JWTs **statelessly** against the JWKS — no introspection
  call back to the mother app.

**Stateless authorize endpoint (implemented).** Passport's `AuthorizationController`
depends on a `StatefulGuard` + session by design, so the guard cannot just be
swapped. Instead `OauthServerServiceProvider` calls `Passport::ignoreRoutes()`
and registers stateless replacements (`routes/oauth.php` + `routes/api.php`):

- `GET /oauth/authorize` (the endpoint OIDC discovery advertises) is a thin
  `prompt` action that **redirects the browser to the SSO front-end**
  (`FRONTEND_URL/authorize?…`), preserving the original OAuth query — a top-level
  navigation carries no bearer token, so the user can't be identified here.
- The front-end, holding the user's first-party token, calls the **token-guarded**
  `GET /api/oauth/authorize` (consent details) then `POST /api/oauth/authorize`
  (`StatelessAuthorizationController`): it validates via league's `AuthorizationServer`,
  sets the user from the bearer token, completes the request and returns the
  `redirect_url` (child `redirect_uri` + code).
- `POST /oauth/token` delegates to Passport's `AccessTokenController` unchanged.

`laravel/passport` is removed from package auto-discovery (root `composer.json`
`dont-discover`), so the active provider is the OIDC one whose `AuthorizationServer`
emits `id_token`s for the `openid` scope. Likewise `Fortify::ignoreRoutes()` is
set — Fortify's session-based controllers are replaced by the stateless ones in
`technical/authentication`, while its guard-free Actions (2FA, recovery codes)
are reused. Pin the Passport version and cover the authorize flow with a feature
test, as it overrides default behaviour.

---

## 3. Data model

`functional/users` migration `…_add_email_verification_and_two_factor_to_users_table`
adds `email_verified_at`, `two_factor_secret`, `two_factor_recovery_codes`,
`two_factor_confirmed_at`. **Columns follow their table's owning layer**; the
behaviour (traits/contracts) is contributed by `technical/authentication`. A
technical layer never migrates a functional layer's table.

Passport's `oauth_*` tables are registered by Passport itself.

---

## 4. Guards & authorization

- Default guard is `api` (Passport token guard) — `config/auth.php` in
  `oauth-server`. Protected routes use `auth:api`.
- **spatie permissions** and **lomkit access-control** are unchanged: they
  operate on the token-resolved `User`. `/me` still enriches the user with
  `getPermissionsViaRoles()`.
- The client-admin endpoints are gated by the spatie permissions
  `view|create|delete global oauth_clients` (permissions, not roles) — added to
  the `RolesAndPermissionsSeeder` in `technical/access-control`.

---

## 5. Install / wiring checklist (manual, run after reviewing the scaffold)

```bash
# from the project root
composer update                       # pull passport, fortify, socialite, oidc + the two layers

php artisan passport:install          # or passport:keys — then move the keys into env
#   set PASSPORT_PRIVATE_KEY / PASSPORT_PUBLIC_KEY in .env

php artisan vendor:publish --tag=passport-config
php artisan vendor:publish --provider="Laravel\Fortify\FortifyServiceProvider"
php artisan vendor:publish --tag=oidc-config        # jeremy379/laravel-openid-connect

php artisan migrate                   # users columns + oauth_* + fortify/passport tables

# first-party SPA client (Authorization Code + PKCE) → set PASSPORT_SPA_CLIENT_ID
php artisan passport:client --public

# personal access client — required by first-party login token issuance (createToken)
php artisan passport:client --personal

# Azure AD app registration → MICROSOFT_CLIENT_ID / SECRET / REDIRECT_URI
```

Then implement the two seams flagged in the code:
1. **Authorize route → `auth:api`** (`OauthServerServiceProvider`, §C above).
2. **Front-end redirect for email-verification / Microsoft callback** (set
   `FRONTEND_URL`).

Add the `* global oauth_clients` permissions to the access-control seeder and
write the layer test suites (login, 2FA challenge, Microsoft authenticate-only,
authorize+token round-trip with PKCE, JWKS validation).
