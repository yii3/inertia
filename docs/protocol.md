# Inertia v3 protocol notes

## Requests

The service recognizes `X-Inertia: true` and `X-Inertia: 1`. Partial reloads target the current component with
`X-Inertia-Partial-Component` and use:

- `X-Inertia-Partial-Data` for included dot paths.
- `X-Inertia-Partial-Except` for excluded dot paths.
- `X-Inertia-Reset` to suppress merge metadata for reset props.
- `X-Inertia-Except-Once-Props` for values already cached by the client.
- `X-Inertia-Version` for asset-version negotiation.

`errors` and native `Prop::always()` paths survive partial filtering. Native `Prop::defer()` and `Prop::optional()`
callbacks are not evaluated on the initial request. An only- or except-based partial reload may select an optional
prop. Page, shared, and version closures are invoked without arguments; request-dependent values must be resolved or
captured explicitly by application code.

## Responses

Inertia requests receive JSON plus `X-Inertia: true`. Initial browser visits receive the common HTML root view rendered
through the configured Yii `WebView`. Every response varies on `X-Inertia`, while preserving existing `Vary` tokens.
Inertia PUT, PATCH, and DELETE redirects with status 302 are normalized to 303 by the protocol core; status 301 remains
unchanged.

A mismatched version on an Inertia GET returns status 409, the current absolute URI in `X-Inertia-Location`, and the
current version in `X-Inertia-Version`.
`Inertia::location()` uses the same 409 header response for an Inertia request and a normal 302 `Location` response for
other requests.

An Inertia redirect whose `Location` contains a fragment is converted to an empty 409 response with an absolute
URL in `X-Inertia-Redirect`. This conversion is skipped for requests with `Purpose: prefetch`. Unlike
`X-Inertia-Location`, this header starts a fresh Inertia GET instead of a hard window visit.

## Initial page data

The shared root view follows the external Inertia v3 page-data format:

```html
<script data-page="app" type="application/json">{...}</script>
<div id="app"></div>
```

The script precedes the root element. JSON is encoded with hexadecimal escaping for tags, quotes, ampersands, and
apostrophes so prop content cannot terminate the script element.

## Page metadata

The base payload contains `component`, `props`, `url`, and `version`. Empty optional metadata is omitted. Supported
metadata keys are `flash`, `clearHistory`, `preserveFragment`, `encryptHistory`, `deferredProps`, `rescuedProps`,
`sharedProps`, `mergeProps`, `prependProps`, `deepMergeProps`, `matchPropsOn`, `scrollProps`, and `onceProps`.

`sharedProps` lists the top-level keys registered as shared props. The protocol-owned `errors` prop is not reported as
application-shared data. Page props replace shared props
with the same top-level key rather than recursively retaining nested shared fields. Empty validation errors are encoded
as `{}` and `X-Inertia-Error-Bag` scopes non-empty errors. Session flash values appear only in the top-level `flash`
field.

Merge metadata follows the v3 client schema:

- root append paths appear in `mergeProps`.
- root prepend paths appear in `prependProps`.
- nested append and prepend strategies emit the complete prop path.
- match strategies are a string list such as `users.data.id`.
- deep merges appear only in `deepMergeProps`.

Dot-notated configured prop keys are expanded by the core. Component names, prop keys, callback results, JSON values,
and redirect targets use the core validation rules and fail explicitly when invalid.

Native once behavior composes with deferred, optional, and merge props. A loaded once value may be omitted while its
`onceProps` metadata remains in the response. An explicitly selected partial prop ignores the loaded-once header and
is resolved again; `fresh()` also forces resolution. Expiration timestamps use epoch milliseconds. A missing
configured version remains nullable through `getVersion()` but is normalized to an empty string in the core page
payload.

## JSON forms and CSRF

Inertia v3 can submit forms as `application/json`. Yii's `Yiisoft\Request\Body\RequestBodyParser` parses these bodies
before CSRF validation. Applications should configure additional media types directly on that middleware when needed.

`CsrfTokenCookieMiddleware` writes Yii's masked token to a readable `XSRF-TOKEN` cookie. The configured Yii
`CsrfTokenMiddleware` accepts the corresponding `X-XSRF-TOKEN` request header. The cookie is `SameSite=Lax`, has no
`HttpOnly` flag, and automatically uses `Secure` for HTTPS requests unless configuration overrides it.
