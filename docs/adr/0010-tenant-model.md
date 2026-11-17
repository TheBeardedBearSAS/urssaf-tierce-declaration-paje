# ADR 0010 — Tenant model

Status: Accepted (2026-11-17). Relates to [ADR 0000](0000-reference-architecture.md).

## Context

The reference is multi-tenant: `TenantId` on every aggregate, `CredentialsProvider::forTenant()`. The PAJE API forbids
two platforms holding the mandate of one employer at the same time (`COMPTE_DEJA_MANDATE`, §1.2).

## Decision

- A tenant is one set of OAuth2 credentials of a third-party declarant platform. Employers, mandates and declarations
  belong to a tenant; the same Pajemploi employer number may exist under two tenants (URSSAF enforces the single
  mandate, the package does not enforce it across tenants).
- Token cache key: hash of environment, base URI and client id (reference behaviour).

## Consequences

- Commands take `--tenant`; the bundle declares tenants under `credentials`.
