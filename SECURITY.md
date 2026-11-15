# Security policy

## Supported versions

Only the latest release of the latest minor version receives security fixes. While the package is in `0.x`, that is
the latest `0.y.z`.

## Reporting a vulnerability

**Do not open a public issue.** Report vulnerabilities privately through GitHub:
[Report a vulnerability](https://github.com/TheBeardedBearSAS/urssaf-tierce-declaration-paje/security/advisories/new)
(tab *Security* > *Advisories* > *Report a vulnerability*).

Please include:

- the affected version(s);
- a description of the issue and its impact (for example: credentials or personal data exposed, incorrect data sent
  to or read from URSSAF, a bypassed validation);
- the steps to reproduce, ideally a minimal test case.

Never include real credentials (`client_id` / `client_secret`), access tokens, or the real data of people or companies
(identity, tax or social security numbers, SIRET, IBAN...) in a report. Use fictitious values.

You will get an acknowledgement within 5 working days. The fix is then prepared privately and released, and the
advisory is published with credit to the reporter unless you ask otherwise.

## Scope

In scope: the code of this package (HTTP client, OAuth2 token handling, validation, wire encoding and decoding,
Symfony and Doctrine bridges).

Out of scope: the URSSAF platform itself (report those issues to URSSAF), and vulnerabilities in dependencies that
this package does not make exploitable (report them upstream).
