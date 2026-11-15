# URSSAF "API Tierce Déclaration PAJE" for PHP

`thebeardedbear/urssaf-tierce-declaration-paje` — client, domain model and Symfony integration for the URSSAF API that
lets a platform, on behalf of a private employer, estimate the contributions of a Pajemploi activity, declare the
salary paid to a licensed childminder (*assistante maternelle agréée*) or a home childcare worker (*garde d'enfants à
domicile*), and apply the withholding at source.

Namespace: `TheBeardedBear\UrssafTierceDeclarationPaje`.

> [!IMPORTANT]
> **Unofficial package.** This project is an independent open-source implementation of a public API. It is not
> developed, endorsed, certified or supported by URSSAF, and it is not affiliated with URSSAF in any way. "URSSAF" is
> used only to name the API this package talks to. Using this package does not replace the URSSAF habilitation
> process; the URSSAF documentation remains the authority on the API, and you remain responsible for the data your
> application sends to URSSAF.

> 🚧 **Skeleton — not implemented yet.** This repository currently contains the tooling and the specification only.
> The implementation follows [docs/roadmap.md](docs/roadmap.md).

## Scope

| Operation | Endpoint |
|---|---|
| Check an employer account (PAJE020) | `GET /employeurs/{numeroPajeEmployeur}/verifier` |
| Check an employee account (PAJE021) | `POST /salaries/verifier` |
| Check the entitled children (PAJE022) | `POST /employeurs/{numeroPajeEmployeur}/enfants/verifier` |
| Associate an employee to an employer (PAJE023) | `POST /employeurs/{numeroPajeEmployeur}/salarie/associer` |
| Register a mandate (PAJE030) | `POST /mandats` |
| Cancel a mandate (PAJE031) | `DELETE /mandats` |
| Estimate contributions, childminder (PAJE040) | `POST /ama/estimer` |
| Estimate contributions, home childcare (PAJE041) | `POST /ged/estimer` |
| Pre-declare a childminder salary (PAJE045) | `POST /employeurs/{numeroPajeEmployeur}/ama/predeclarer` |
| Pre-declare a home childcare salary (PAJE046) | `POST /employeurs/{numeroPajeEmployeur}/ged/predeclarer` |
| Declare a childminder salary (PAJE050) | `POST /employeurs/{numeroPajeEmployeur}/ama/declarer` |
| Declare a home childcare salary (PAJE051) | `POST /employeurs/{numeroPajeEmployeur}/ged/declarer` |

OAuth2 client credentials, scope `tiersdecl.paje`, base URL `https://api.urssaf.fr/tiersdecl/v1/paje` (sandbox:
`https://api-edi.urssaf.fr/tiersdecl/v1/paje`).

## Documentation

- [docs/api-reference.md](docs/api-reference.md) — technical reference (single source of truth for the package).
- [docs/roadmap.md](docs/roadmap.md) — planned architecture and order of work.
- [docs/openapi/](docs/openapi/) — official OpenAPI contract(s) from the URSSAF API portal.
- `docs/reference/` — official URSSAF documentation: not versioned (URSSAF documents), download it from the links in [docs/api-reference.md](docs/api-reference.md) into this local, git-ignored folder.
- Catalogue page: https://portailapi.urssaf.fr/fr/catalogue-api/prd/td-paje

## Requirements

PHP 8.2+. Development only needs Docker: every `make` target runs in a container.

## Development

```sh
make install     # build the image, install dependencies
make ci          # full quality gate: cs, rector, phpstan, mago, deptrac, composer checks, guard, coverage, infection
make test        # unit + functional tests
make help        # every target
```

`make ci PHP_VERSION=8.4` runs the gate on another PHP version.

## Versioning

Semantic Versioning. `SpecVersion::CURRENT` holds the version of the official URSSAF documentation implemented.

## Contributing and security

Contributions are welcome: see [CONTRIBUTING.md](CONTRIBUTING.md). Report vulnerabilities privately as described in
[SECURITY.md](SECURITY.md), never in a public issue.

## License

MIT. See [LICENSE](LICENSE). The software is provided "as is", without warranty of any kind.

The API specification summarised in [docs/api-reference.md](docs/api-reference.md) belongs to URSSAF; see the sources
listed there.
