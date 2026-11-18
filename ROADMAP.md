# Roadmap

Intended direction for the next twelve months (November 2026 to November 2027). It is a statement of intent, not a
commitment; it is reviewed at each minor release. Proposals are welcome in GitHub issues. The implementation plan of the
first release (domain, gateway, bridges) is kept in [docs/roadmap.md](docs/roadmap.md).

## Planned

- **Settle the sandbox open points.** Run the live contract suite (`make test-contract`) against the URSSAF sandbox,
  settle the open points of [docs/api-reference.md](docs/api-reference.md) (retry safety of the mandate and association
  operations, error code suffixes, required fields) and release the matching changes, with upgrade notes in the
  [changelog](CHANGELOG.md).
- **Follow the URSSAF API.** Track each new version of the URSSAF *API Tierce Déclaration PAJE* (OpenAPI contract and
  technical documentation) and release the matching changes.
- **Release 1.0.** Stabilise the public API (domain model, gateway, Symfony bundle, Doctrine integration), then release
  `1.0.0`. From 1.0 on, breaking changes only happen in major versions, as required by
  [Semantic Versioning](https://semver.org/).
- **Keep up with the platform.** Support new PHP versions (8.5 and later) and the new Symfony and Doctrine versions
  as they are released, and drop the versions that reach their end of life in a minor release, announced in the
  changelog.
- **Keep the quality and security bar.** 100 % coverage and 100 % MSI, at least 96 % branch coverage, PHPStan at level
  max, signed releases, and the OpenSSF Best Practices criteria.

## Not planned

- Other URSSAF APIs: each one gets its own package (see *Other URSSAF packages* in the [README](README.md)).
- Official bridges for other frameworks than Symfony: the core works without a framework (see
  [docs/standalone.md](docs/standalone.md)).
- Sending a declaration automatically after an unknown outcome: the API has no idempotency key and no read operation for
  a declaration, so the package never produces an automatic duplicate (see [ADR 0003](docs/adr/0003-retry-and-unknown-outcome.md)).
