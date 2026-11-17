# ADR 0002 — HTTP client adaptations to the PAJE contract

Status: Accepted (2026-11-17). Relates to [ADR 0000](0000-reference-architecture.md).

## Context

The reference `JsonApiClient` sends `x-request-id` and `recouv.application.id`, builds only GET and POST-with-body
requests and refuses non-JSON bodies. The PAJE contract declares no request header (api-reference §1), uses
`DELETE /mandats`, `POST /mandats` with query parameters only, `POST` with query parameters **and** a JSON body (021,
023), answers 030 / 031 with an empty 200 body, requires a non-empty body `{}` for 021 and for 023 when the employee is
identified by the query (§4 decision 9), and lists 512 as a security error (PDF only).

## Decision

- `Http\ApiRequest` supports `get(op, path, query)`, `post(op, path, query, ?body)` and `delete(op, path, query)`.
  Query strings are encoded with RFC 3986. The 021 and 023 request mappers always produce a body (`{}` when no field is
  set, through `JsonObject`), never an empty one.
- `JsonApiClient` treats an empty 2xx body as "no payload" (`ApiResponse::isEmpty()`); the 030 / 031 mappers require it
  empty or `{}`, every other operation requires a JSON object.
- Headers: `Authorization: Bearer`, `User-Agent` and `Accept: application/json` always; `Content-Type:
  application/json` only when the request has a body. `ApiConfiguration` exposes an optional request-id header (name
  only, value generated per attempt by the `IdGenerator` port) and an optional application-id header (name + value),
  both off by default.
- Status mapping: 2xx decode; 400 `UrssafRequestRejected` (errors parsed, ADR 0005); 401 → invalidate the token and
  replay once (every operation: a 401 proves the call was not processed), then `UrssafAuthenticationFailed`; 403 and
  512 `UrssafAuthenticationFailed`; 429 `UrssafUnavailable` (quota, `Retry-After` seconds when present); 5xx: see ADR
  0003; anything else `UnexpectedUrssafResponse`. Error bodies of 429 / 5xx are parsed leniently and kept on the
  exception (`ERR_TECHNIQUE`).
- On the token endpoint, 400 / 401 / 403 are `UrssafAuthenticationFailed`, every 5xx (512 included) and 429 are
  `UrssafUnavailable` (`whileAuthenticating`, never an unknown outcome).
- The cause of a transport failure is sanitised: exceptions carry an internal `TransportError` holding only the class
  name of the PSR-18 exception, never its message (it usually embeds the URI, hence the query string of 020 / 021 /
  023 / 030 / 031 with NIR, birth dates and employer numbers, ADR 0007).

## Consequences

- The client stays a port-free internal class, as in the reference; only its request shapes and header policy differ.
- If URSSAF asks for correlation headers, enabling them is configuration only.
