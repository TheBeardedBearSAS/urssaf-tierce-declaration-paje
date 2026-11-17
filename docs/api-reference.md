# URSSAF "API Tierce Déclaration PAJE" — technical reference

This document is the single source of truth used by the package for enums, validation rules and wire fixtures.
It summarises the official specification:

- Swagger contract: **v1.5.1** (Swagger 2.0; `docs/openapi/td-paje-prd.json` and `docs/openapi/td-paje-sb.json`)
- Technical documentation PDF: **v1.5.2** (2025-12-17), file name `…_v1.5.2.pdf`
  (`docs/reference/ACOSS-Documentation-API-PAJE_v1.5.2.pdf`, 83 pages)
- Catalogue page: https://portailapi.urssaf.fr/fr/catalogue-api/prd/td-paje
- PDF: https://cedito-medias.urssaf.fr/medias/PortailAPI/files/documentationAPIs/PAJE/ACOSS-Documentation-API-PAJE_v1.5.2.pdf

The package constant `SpecVersion::CURRENT` must equal `1.5.2`. Rule shared by the URSSAF packages: `SpecVersion` is
the version in the **file name of the published PDF** (the most detailed source), falling back to the Swagger version
when the PDF is not versioned. Here the PDF (1.5.2) is newer than the Swagger (1.5.1): the PDF revision 1.5.2 only
adds blocking / non-blocking messages to PDF §4.4 and has no wire impact. Field names, codes and wire values below are
reproduced verbatim from the specification (they are French by design); everything else in the package is English.

> Status: specification only. Nothing below is implemented yet; see [roadmap.md](roadmap.md).

Vocabulary: *PE* = *particulier employeur* (private employer); *SP* = *salarié particulier* (private-employed worker);
*AMA* = *assistante maternelle agréée* (licensed childminder); *GED* = *garde d'enfants à domicile* (home childcare);
*PAS* = *prélèvement à la source* (withholding at source, art. 204A CGI); *CMG* = *complément de libre choix du mode de
garde*; *ODPF* = *organisme débiteur des prestations familiales* (Caf/MSA).

> **Source and rights.** The specification, the field names, the codes and the official JSON examples quoted here come
> from the URSSAF documents listed above and remain URSSAF's property. They are reproduced only so the package can
> interoperate with the API, and they are not covered by this package's MIT license. In case of discrepancy, the
> official URSSAF documentation prevails. The official documents themselves are not versioned: download them into
> `docs/reference/`, a local git-ignored folder. This package is not affiliated with URSSAF.

---

## 1. General

| Topic | Value |
|---|---|
| Purpose | On behalf of a private employer: estimate contributions for a Pajemploi activity, declare the salary paid to an AMA or a GED to the collection bodies, and apply the withholding at source (art. 204A CGI). The platform acts as *tiers déclarant* (third-party declarant). |
| Access | Restricted offer: access request validated by ACOSS (PDF §2.1); catalogue subscription form. Credentials (client id / client secret) issued after approval. A platform needs a **mandate** from each employer (§2.11). |
| Security | OAuth2 **client credentials** (Swagger `securityDefinitions.securityHeader`, flow `application`) |
| Token endpoint | `POST https://api.urssaf.fr/api/oauth/v1/token` (form-urlencoded: `grant_type=client_credentials`, `client_id`, `client_secret`, `scope`) |
| Scope | `tiersdecl.paje` ("Acces a l'API Tierce Declaration PAJE") |
| Production base URL | `https://api.urssaf.fr/tiersdecl/v1/paje` |
| Sandbox base URL | `https://api-edi.urssaf.fr/tiersdecl/v1/paje` (Swagger `td-paje-sb.json`) |
| Content type | `application/json` (request and response) |
| Headers | Only the `Authorization: Bearer` header is part of the contract. No `x-request-id`-style header is declared (the PDF curl examples carry Postman headers only; they are not part of the contract). |
| HTTP errors | 400 (validation / functional), 401, 403, 500, 503 (Swagger, all operations except mandates), 429 (quota, PDF §2.5), 512 (security, PDF only) |

Package mapping (already in place, do not duplicate):

- `Infrastructure\Urssaf\Environment`: `Sandbox` → `https://api-edi.urssaf.fr`, `Production` → `https://api.urssaf.fr`
  (host only, the sandbox host must stay overridable by configuration).
- `Infrastructure\Urssaf\UrssafEndpoints`: `TOKEN_PATH = /api/oauth/v1/token`, `TOKEN_SCOPE = tiersdecl.paje`,
  `API_BASE_PATH = /tiersdecl/v1/paje`.

Production and sandbox contracts are **identical** except for the Swagger `host` (`api.urssaf.fr` vs
`api-edi.urssaf.fr`); there is no behavioural difference in the contract (verified by a structural diff of both files).

Common data conventions:

- Dates: `yyyy-MM-dd` (Swagger `format: date`), no time part, in query strings and bodies.
- Amounts (`mnt*`): JSON numbers in euros, 2 decimals (`multipleOf: 0.01`), typically `maximum: 9999.99`.
- Names (`nomEnfant`, `prenomEnfant`, `nomNaissance`, …): upper case, no accents, pattern `[A-Z'\-\s]*`, 30 chars max.
  The official examples use `L'HOMME` and `JEAN-MICHEL`.
- `numeroPajeEmployeur` / `numeroPajeSalarie` / `numeroPaje`: **14-character** alphanumeric string (the "pseudo-siret",
  e.g. `Y1869005780009`, `Y2926822480004`: one letter followed by 13 digits *inferred from the examples*).
- NIR (`nirSalarie`): 15 characters.

### 1.1 Quotas (HTTP 429 when exceeded)

Two levels (PDF §2.5): a global quota per API and a quota per API client. Values are **not published**.

### 1.2 Known limitations (official)

- PDF §1.2: **no multi-platform**. An employer cannot mandate several platforms at the same time (error
  `COMPTE_DEJA_MANDATE`, §2.11).
- PDF §1.2, operation 023: matching of an employee on `nomNaissance`, `prenom`, `villeNaissance` is **strict**; an
  evolution is under analysis.
- PDF §3.9 / §3.11 (changelog 1.5.1): AMA (pre-)declarations take **one child only**; the child linked to the
  contract end must be the declared child or omitted.
- PDF §4.4.1: declarative space for an employment period opens on the **25th of the employment month**
  (`ER_API_DECLA_0000`); declarations before `[PERIODE1_MONO]` (start of the mono-child declaration, example
  "Décembre 2025") are no longer possible (`ER_API_DECLA_00072`).
- PDF §4.4.1: when the computed hourly rate `mntSalaireNetMensuel / nbHeures` is **≥ 100 €**, the API answers HTTP 400
  with code `ERREUR_TECHNIQUE` (point not specified in the functional specification).
- PDF §3.9 / §3.10: the pre-declaration is subject to the same opening constraints as a declaration.

### 1.3 Error payload

Business and validation errors are an **array** of `Erreur` objects (HTTP 400), technical errors (HTTP 500) too
(Swagger: `array` of `Erreur`), **except the mandate operations** (§2.11, §2.12) whose 400 and 500 schemas are a single
`Erreur` object. The PDF shows a single object for each case. The client must therefore accept both forms.

```json
{ "code": "PARAM_INVALIDE", "message": "Un des paramètres de votre demande est invalide", "description": "Le paramètre dateNaissance est invalide" }
```

`Erreur`: `code` (string, e.g. `ER_API_020_NB_0000`), `message` (string), `description` (string). All optional in the
Swagger.

Technical error (500): `{"code":"ERR_TECHNIQUE","message":"Erreur technique","description":"Une erreur système est survenue"}`
(PDF code `ERR_TECHNIQUE`; §4.4.1 text names the 400 variant `ERREUR_TECHNIQUE`, see §5).

**Non-blocking errors** (`erreursNonBloquantes`, array of `Erreur`) are returned with **HTTP 200** inside the result
(operations 023, 040, 041, 045, 046, 050, 051). Their codes end with `_NB_…` (catalogue in §3.6). A 200 response is
therefore not necessarily clean.

Security errors (401 token missing/invalid, 403 refused, 512): body not documented.

---

## 2. Operations

Recommended flow (PDF, order inferred from the operation numbering and the error codes):

1. **Mandate** (§2.11): the platform notifies Pajemploi that it holds a mandate from the employer. Without a mandate,
   employer-scoped operations fail (`MANDAT_INEXISTANT` appears in the PDF example of 040, §2.5; the PDF does not state
   which operations require it, see §5).
2. **Check the employer** (§2.1) and the **employee** (§2.2), **associate** the employee to the employer (§2.4),
   **check the children** (§2.3).
3. **Estimate** (§2.5, §2.6): stateless simulation, no employer context needed.
4. **Pre-declare** (§2.7, §2.8): dry-run of the declaration, returns the computed amounts (employer share,
   contributions, PAS) and nothing is recorded ("simuler à blanc").
5. **Declare** (§2.9, §2.10): same input as the pre-declaration, **records** the declaration and returns the amounts,
   the *volet social* reference (`referenceDocumentaire`) and the value date (`dateVirement`).
6. **Cancel the mandate** (§2.12) when the employer stops using the platform.

There is no operation to read, amend or cancel a declaration. A repeated declaration is rejected with
`ER_API_050_0070` (AMA) / `ER_API_DECLA_00020` (GED), so declaration is **not idempotent from the client's point of
view**: never retry a declare blindly after a timeout (see §4).

Employer-scoped operations share two parameters:

| Field | In | Type | Req. | Rules |
|---|---|---|---|---|
| `numeroPajeEmployeur` | path | string | yes | Pajemploi employer number ("Pseudo-siret"), alphanumeric, exactly 14 characters (`minLength` = `maxLength` = 14) |
| `dtNaissanceEmployeur` | query | string (date) | yes | Employer birth date, `yyyy-MM-dd`. A mismatch with the number gives `ER_API_020_0000` |

### 2.1 Check an employer account — `GET /employeurs/{numeroPajeEmployeur}/verifier`

`operationId`: `verificationPePaje`. Summary: "API PAJE020 - Verifier un compte Pajemploi pour un particulier employeur".

Checks that the employer identified by number and birth date has a **valid** Pajemploi account: active online account
and valid bank details.

Parameters: `numeroPajeEmployeur` (path) and `dtNaissanceEmployeur` (query), see the table above. No body.

Example: `GET https://api.urssaf.fr/tiersdecl/v1/paje/employeurs/Y1869005780009/verifier?dtNaissanceEmployeur=1975-02-23`

Response 200: `OutputVerificationPe`

| Field | Type | Rules |
|---|---|---|
| `employeurAutoriseOk` | boolean | PE registered and valid on the Pajemploi site; all employer data required for the declaration is present (valid bank details) |
| `etatCompteEnLigne` | string | Online account state: `Actif`, `Absent`, `Inactif`, `Coordonnées bancaires invalides` (Swagger/PDF describe the values in prose; the exact wire spelling of the last one is not published) |

Business errors (HTTP 400): `PARAM_INVALIDE`, `ER_API_020_0000` (birth date does not match the number),
`ER_API_020_0010` (no employer for this number). Technical: 500 `ERR_TECHNIQUE`; 401, 403, 503 without body.

Fixtures: `verify_employer_response.json`, `error_param_invalide.json`, `error_birth_date_mismatch.json`,
`error_employer_not_found.json`, `error_technical.json`.

### 2.2 Check an employee account — `POST /salaries/verifier`

`operationId`: `verificationSpPaje`. Summary: "API PAJE021 - Verifier un compte Pajemploi pour un salarie particulier".

Searches the Pajemploi employee repository for the individual. If found, the Pajemploi number and the childcare modes
(AMA / GED) are returned. The account is valid if the employee's online account is active.

The POST carries the search data in the **query string** and, optionally, in the body. Priority of the identification
criteria (PDF §3.2.2): **1** `numeroPajeSalarie`, **2** `nirSalarie`, **3** body `inputIdentificationSalarie`. At least
one of the three must be provided; the lower-priority criteria are mandatory only when the higher ones are absent.

Query parameters:

| Field | Type | Req. | Rules |
|---|---|---|---|
| `numeroPajeSalarie` | string | conditional | Pajemploi employee number, 14 alphanumeric characters. Mandatory without NIR and without administrative data |
| `nirSalarie` | string | conditional | NIR, 15 characters. Mandatory without Pajemploi number and without administrative data |
| `dtNaissanceSalarie` | string (date) | no | Employee birth date, `yyyy-MM-dd`. Swagger: "obligatoire en l'absence du numero Pajemploi et de NIR" but declared `required: false`; PDF: facultatif (changelog 1.2) |

Body (optional, Swagger `required: false`): `InputIdentificationSalarie` (shared with §2.4)

| Field | Type | Req. | Rules |
|---|---|---|---|
| `cdCivilite` | string | PDF: yes / Swagger: no | 1 char: `1` = M., `2` = Mme |
| `nomNaissance` | string | PDF: yes / Swagger: no | max 30, pattern `[A-Z'\-\s]*` |
| `nomUsage` | string | no | max 30, pattern `[A-Z'\-\s]*` |
| `prenom` | string | PDF: yes / Swagger: no | max 30, pattern `[A-Z'\-\s]*` |
| `dtNaissanceSalarie` | string (date) | PDF: yes / Swagger: no | `yyyy-MM-dd`, e.g. `1980-01-30` |
| `codePaysNaissance` | string | conditional | INSEE country code, **3 chars max** (e.g. FRANCE = `100`) |
| `departementNaissance` | string | if born in France | 3 chars max (e.g. `972`, `07`) |
| `villeNaissance` | string | PDF: yes / Swagger: no | max 30, pattern `[a-zA-ZáÁàÀâÂäÄéÉèÈëËêÊìÌíÍïÏîÎñÑòÒóÓôÔöÖùÙúÚüÜûÛçÇýÝÿŸŒœÆæ'\-\s]*` (accents allowed, unlike the other names; the Swagger pattern is mangled, see §5) |

Official call: `POST …/salaries/verifier?nirSalarie=178010701005265&dtNaissanceSalarie=1979-01-01` with body `{}`.

Response 200: `OutputVerificationSp`

| Field | Type | Rules |
|---|---|---|
| `numeroPajeSalarie` | string | Pajemploi employee number (the official example returns the placeholder `string`) |
| `indcAma` | boolean | the employee may work as an AMA |
| `indcGed` | boolean | the employee may work as a GED |
| `etatCompteEnLigne` | string | `Actif`, `Absent`, `Inactif` |

Business errors: `PARAM_INVALIDE` (400), `ERR_TECHNIQUE` (500); no other code is documented for this operation.
Fixtures: `verify_employee_request.json` (the official `{}` body), `verify_employee_response.json`,
`error_param_invalide.json`.

### 2.3 Check the entitled children of an employer — `POST /employeurs/{numeroPajeEmployeur}/enfants/verifier`

`operationId`: `verificationEnfantsPe`. Summary: "API PAJE022 - Verification des enfants ouvrant droit d'un particulier
employeur Pajemploi".

From a list of children, checks that they exist in the Pajemploi system as *enfants ouvrant droit* of the employer.

Parameters: `numeroPajeEmployeur` (path), `dtNaissanceEmployeur` (query), both required.

Body (required): `InputVerificationEnfantPe`

| Field | Type | Req. | Rules |
|---|---|---|---|
| `inputEnfantPe` | array of `InputEnfant` | yes | Children to check. Size limits not published |

`InputEnfant` (reused by operations 045, 046, 050, 051):

| Field | Type | Req. | Rules |
|---|---|---|---|
| `dtNaissanceEnfant` | string (date) | yes | `yyyy-MM-dd`, e.g. `2015-01-12` |
| `nomEnfant` | string | yes | max 30, pattern `[A-Z'\-\s]*`, e.g. `L'HOMME` |
| `prenomEnfant` | string | yes | max 30, pattern `[A-Z'\-\s]*`, e.g. `JEAN-MICHEL` |

Response 200: `OutputVerificationEnfantPe`

| Field | Type | Rules |
|---|---|---|
| `verificationEnfantPe` | array of `VerificationEnfantPe` | one item per requested child |

`VerificationEnfantPe`: `inputEnfantPe` (`InputEnfant`, the searched child) and `reponseStatutEnfant` (integer, see §3.1).

Errors: `PARAM_INVALIDE` (400), `ERR_TECHNIQUE` (500). Non-blocking message `ER_API_022_NB_0000` (unknown child) is
catalogued in PDF §4.4.2 but this response has no `erreursNonBloquantes` field (see §5).
Fixtures: `verify_children_request.json`, `verify_children_response.json`.

### 2.4 Associate an employee to an employer — `POST /employeurs/{numeroPajeEmployeur}/salarie/associer`

`operationId`: `associerSalarie`. Summary: "API PAJE023 - Associer un salarie a un particulier employeur".

Associates an employee to an employer. The employee is first **matched** against the repository:

1. if `numeroPajeSalarie` or `nirSalarie` is provided, reconciliation on the existing employee with that number;
2. otherwise reconciliation on `[nomNaissance, prenom, nomUsage, dateNaissance, villeNaissance, codePaysNaissance]`
   (the birth date is ignored when absent; the match is strict, see §1.2).

If the employee is unknown **and** the creation data was supplied in the body, the employee is **created** and
associated. The association is required before declaring (`ER_API_DECLA_00100` on 050).

Parameters:

| Field | In | Type | Req. | Rules |
|---|---|---|---|---|
| `numeroPajeEmployeur` | path | string | yes | see above |
| `dtNaissanceEmployeur` | query | string (date) | yes | see above |
| `numeroPajeSalarie` | query | string | conditional | 14 alphanumeric characters; mandatory without NIR and without creation data; to be filled first when known |
| `nirSalarie` | query | string | conditional | 15 characters; mandatory without Pajemploi number and without administrative data; priority 2 |
| `dtNaissanceSalarie` | query | string (date) | Swagger: yes / PDF: "Facultatif" | `yyyy-MM-dd`. If given, must be consistent with the date attached to the number or NIR. The PDF header marks it `*` but its text says optional (changelog 1.2); the Swagger declares `required: true` (see §5) |

Body (Swagger `required: false`): `InputCreationSalarie`. **Technical note (PDF):** when the employee is already known and
identified through the query parameters, the body must **not be empty**: send `{}`. The body is mandatory for a creation.

| Field | Type | Req. | Rules |
|---|---|---|---|
| `typeActiviteSalarie` | string | PDF: yes (creation) / Swagger: no | 1 char: `A` = AMA, `G` = GED (§3.2) |
| `dtNaissanceSalarie` | string (date) | creation | `yyyy-MM-dd`; "Obligatoire (permet de vérifier la cohérence des données)" |
| `nirSalarie` | string | no | 15 chars; "fortement recommandé" |
| `dtDebutAgrement` | string (date) | AMA only | Start date of the current licence (*agrément*), `yyyy-MM-dd`. Mandatory for type `A`, **empty** for type `G` (Swagger title is a copy-paste "Date de naissance") |
| `inputAdresseSalarie` | `InputAdresse` | creation | at least one of `libelleVoie`, `complementLocalisation`, `lieuDit` |
| `inputIdentificationSalarie` | `InputIdentificationSalarie` | creation | see §2.2 |

`InputAdresse` (required: `codeCommune`, `codePostal`):

| Field | Type | Req. | Rules |
|---|---|---|---|
| `numVoie` | integer (int32) | no | 1–9999 ("numérique sur 4 digits") |
| `lettreVoie` | string | no | 1 char, pattern `[A-Z'\-\s]*`; values in §3.3 |
| `typeVoie` | string | no | 1–3 chars, pattern `[A-Z'\-\s]*`; values in §3.4 |
| `libelleVoie` | string | required if `numVoie` is set | max 21 |
| `complementLocalisation` | string | no | max 32, pattern `[A-Z'\-\s]*` |
| `lieuDit` | string | no | max 32, pattern `[A-Z'\-\s]*` |
| `codeCommune` | string | yes | INSEE municipality code, exactly 5 chars (e.g. `94022` = CHOISY LE ROI) |
| `codePostal` | string | yes | exactly 5 digits |

Response 200: `OutputAssociationEmployeurSalarie`

| Field | Type | Rules |
|---|---|---|
| `salarieCreeOk` | boolean | the employee was created in the Pajemploi system |
| `salarieAssocieOk` | boolean | the employee was associated to the employer |
| `salarieDejaAssocieOK` | boolean | an active association already existed (note the upper-case `OK`) |
| `outputVerificationSp` | `OutputVerificationSp` | see §2.2 |
| `erreursNonBloquantes` | array of `Erreur` | e.g. `ER_API_023_NB_0000` (already associated and active), agreement-date warnings, §3.6 |

Errors (400): `PARAM_INVALIDE`, `PE_INEXISTANT` (the PDF code has a trailing space, `"PE_INEXISTANT "`, an extraction
artefact: trimmed here, see §5). 500 `ERR_TECHNIQUE`.
Fixtures: `associate_employee_request.json` (the official example, placeholder values `string` kept), `associate_employee_response.json`,
`error_pe_inexistant.json`, `error_param_invalide.json`.

### 2.5 Estimate contributions, childminder — `POST /ama/estimer`

`operationId`: `estimerCotisationsAma`. Summary: "API PAJE040 - Estimer les montants des cotisations pour une activite
d'assistante maternelle".

Provides the contribution amounts for an AMA, **without any Pajemploi account context** (no employer number, no
birth date). Evaluates the contributions remaining at the employer's expense after the Caf/MSA coverage under the
CMG of the PAJE.

Body (required): `InputEstimationAma` (= `InputEstimation` + AMA fields; required: `mntSalaireNetMensuel`,
`nbEnfantsGardes`, `nbJoursActivite`)

| Field | Type | Req. | Rules |
|---|---|---|---|
| `cdPostalEmployeur` | string | no | exactly 5 chars, **default `75000`** |
| `cdPostalSalarie` | string | no | exactly 5 chars, **default `75000`** |
| `mntSalaireNetMensuel` | number (double) | yes | declared monthly net salary, ≤ 9999.99, multiple of 0.01 |
| `nbEnfantsGardes` | integer | yes | 1–5 |
| `nbJoursActivite` | integer | yes | 1–31 |
| `nbJoursCongesPayes` | number (double) | no | 0–99, multiple of 0.5 |
| `nbHeuresMajorees` | integer | no | 0–999 |
| `nbHeuresComplementaires` | integer | no | 0–999 (the official description wrongly reads "Nombre de jours d'activité") |

Response 200: `OutputEstimation` (shared with §2.6)

| Field | Type | Rules |
|---|---|---|
| `mntCotiPriseEnChargeOdpf` | number (double) | contributions covered by the ODPF (Caf/MSA). For a CMG employer and an AMA activity always > 0 |
| `mntCotiEmplAcharge` | number (double) | contributions remaining at the employer's expense. For a CMG employer and an AMA activity always 0 |
| `mntExoEmployeur` | number (double) | flat-rate deduction corresponding to employer exemptions. For a CMG employer and an AMA activity always 0 |
| `erreursNonBloquantes` | array of `Erreur` | e.g. `ER_API_041_NB_0010`, §3.6 |

Errors: `PARAM_INVALIDE` (400); the PDF example for `MANDAT_INEXISTANT` appears here although the operation is stated
to be context-free (see §5); 500 `ERR_TECHNIQUE`.
Fixtures: `estimate_ama_request.json`, `estimate_response.json`, `error_mandat_inexistant.json`.

### 2.6 Estimate contributions, home childcare — `POST /ged/estimer`

`operationId`: `estimerCotisationsGed`. Summary: "API PAJE041 - Estimer les montants des cotisations pour une activite de
garde d'enfant a domicile".

Same purpose as §2.5 for a GED activity, without account context.

Body (required): `InputEstimationGed` (= `InputEstimation` + GED fields; required: `mntSalaireNetMensuel`,
`cdModeCalcul`, `complementActiviteOk`, `indcEnfantPlusJeuneAgarder`, `nbHeures`)

| Field | Type | Req. | Rules |
|---|---|---|---|
| `cdPostalEmployeur` | string | Swagger: no / PDF: `*` | exactly 5 chars, default `75000` |
| `cdPostalSalarie` | string | no | exactly 5 chars, default `75000` |
| `mntSalaireNetMensuel` | number (double) | yes | ≤ 9999.99, multiple of 0.01 |
| `cdModeCalcul` | string | yes | 1 char `R`/`F`/`S` (§3.2). For DOM GED: `R` real, `F` flat-rate, `S` specific; non-DOM and default: `R` |
| `nbHeures` | integer | yes | 1–1000 ("travaillées < 1000") |
| `nbHrSupMajA25` | integer | no | 0–35, hours overtime at 25 %; "renseigné que si nbHeures>0" |
| `nbHrSupMajA50` | integer | no | 0–40, hours overtime at 50 %; "renseigné que si nbHeuresSup1>0" (sic) |
| `indcEnfantPlusJeuneAgarder` | string | yes | 1 char: `1` child 0–3 years without activity supplement, `2` child 3–6 years, `3` child over 6 (§3.2) |
| `complementActiviteOk` | boolean | yes | the employer receives an activity supplement |

Response 200: `OutputEstimation` (§2.5). Errors: `PARAM_INVALIDE` (400), `ERR_TECHNIQUE` (500).
Non-blocking: `ER_API_041_NB_0010` (child over 6 years), `ER_API_041_NB_0020` (inconsistent calculation mode: the
simulation then runs in real mode `R`).
Fixtures: `estimate_ged_request.json`, `estimate_response.json`.

### 2.7 Pre-declare a childminder salary — `POST /employeurs/{numeroPajeEmployeur}/ama/predeclarer`

`operationId`: `predeclarerSalaireAma`. Summary: "API PAJE045 - Pre-declarer, via un tiers, le salaire d'une assistante
maternelle".

Dry-run ("pré-déclarer") of the salary paid by an employer to an AMA for a period and **for one child**. Same opening
constraints as a declaration. Returns the computed amounts: employer share of the salary, employer contributions, PAS.
Nothing is recorded.

Parameters: `numeroPajeEmployeur` (path), `dtNaissanceEmployeur` (query), both required.

Body (required): `InputDeclarationAma` (shared with §2.9). Required: `nbJoursActivite` (Swagger). The Swagger does not
mark `inputDeclCommun`, `inputSp` or `inputDeclEnfant` as required, but `inputDeclCommun` carries required fields and
`inputSp` the identification: treat them as required (see §5).

| Field | Type | Req. | Rules |
|---|---|---|---|
| `inputDeclCommun` | `InputDeclCommun` | yes (in practice) | common period / salary data, below |
| `inputDeclEnfant` | array of `InputEnfantAma` | yes (in practice) | **a single child** per declaration (`ER_API_DECLA_00040` otherwise) |
| `inputSp` | `InputSp` | yes (in practice) | employee identification, below |
| `finDeContrat` | `InputFinDeContratAma` | no | contract end indemnities |
| `nbJoursActivite` | integer | yes | 1–99 in the contract; description "Champ sur 2 caractères supérieur à 0 et inférieur ou égal à 31" (Swagger format `uint8`) |
| `nbHeuresMajorees` | integer | no | 0–999 |
| `nbHeuresComplementaires` | integer | no | 0–999 (description wrongly reads "Nombre de jours d'activité") |
| `mntIndemnitesEntretien` | number | no | 0–9999.99, multiple of 0.01 |
| `mntIndemnitesRepas` | number | no | ≤ 9999.99, multiple of 0.01 |

`InputDeclCommun` (shared by all four declaration operations; required: `dtDebutPeriode`, `dtFinPeriode`,
`dtPaiementSalaire`, `mntSalaireNetMensuel`, `nbHeures`):

| Field | Type | Req. | Rules |
|---|---|---|---|
| `dtDebutPeriode` | string (date) | yes | start of the service period, `yyyy-MM-dd` |
| `dtFinPeriode` | string (date) | yes | end of the service period |
| `dtPaiementSalaire` | string (date) | yes | payment date of the service |
| `nbHeures` | integer | yes | hours **including** paid-leave absence hours and specific hours; minimum 1. Swagger: no maximum; PDF: maximum 1000, "valeur entière positive < 1000" |
| `nbJoursCongesPayes` | number (double) | no | 0–99, multiple of 0.5 ("par tranche de demi-journée") |
| `mntSalaireHoraireNet` | number (double) | no | net hourly rate of one normal hour, ≤ 9999.99; required for AMA when major / complementary hours are given (`ER_API_050_0015`) |
| `nbHeuresSpecifiques` | integer | no | 0–1000; must not exceed the sum of normal + major + complementary hours (AMA, `ER_API_050_0030`) |
| `mntAcompteSalarie` | number (double) | no | advance paid to the employee, ≤ 9999.99 |
| `mntSalaireNetMensuel` | number (double) | yes | monthly net salary to pay, in euros, **> 0**, ≤ 9999.99 |
| `mntIndemnitesKilometriques` | number (double) | no | mileage allowance, ≤ 9999.99 |

`InputSp` (employee identification; no field is individually required, one identification is needed):

| Field | Type | Req. | Rules |
|---|---|---|---|
| `numeroPajeSalarie` | string | conditional | 14 alphanumeric chars; mandatory without NIR and administrative data |
| `nirSalarie` | string | conditional | 15 chars; mandatory without Pajemploi number and administrative data |
| `dtNaissanceSalarie` | string (date) | no | `yyyy-MM-dd`; if given must match the employee found (`ER_API_021_0010`) |

`InputEnfantAma` = `InputEnfant` (§2.3) +

| Field | Type | Req. | Rules |
|---|---|---|---|
| `joursAccueilHandicap` | integer (int32) | no | days of care when the child is disabled, ill or "inadapté". If the child is disabled, must be **equal** to `nbJoursActivite` (`ER_API_050_0051`) |
| `joursAccueil24Consecutive` | integer (int32) | no | days with care of more than 24 consecutive hours. Must **not exceed** `nbJoursActivite` (`ER_API_050_0050`) |

`InputFinDeContratAma` (all optional, amounts ≤ 9999.99, multiple of 0.01):

| Field | Meaning |
|---|---|
| `mntIndDepartRetraite` | retirement indemnity |
| `mntIndCcp` | paid-leave compensation (*ICCP*) |
| `mntIndPreavis` | indemnity in lieu of notice |
| `mntIndPrecarite` | precarity indemnity |
| `mntIndRupture` | contract termination indemnity |
| `enfants` | array of `InputEnfantAma`: **no longer to be provided** (PDF 1.5.1); if given, must be identical to the declared child (`ER_API_050_0060`) |

Response 200: `OutputPreDeclaration`

| Field | Type | Rules |
|---|---|---|
| `declaration` | `DeclarationCommun` | computed amounts, below |
| `indcDeclPajemploiPlus` | boolean | "declaration en Pajemploi +" indicator, default `false` (in the Swagger only) |
| `dateVirement` | string (date) | transfer date (in the Swagger only) |
| `verificationEnfantPe` | array of `VerificationEnfantPe` | entitlement status of the child (§2.3) |
| `erreursNonBloquantes` | array of `Erreur` | §3.6 |

`DeclarationCommun` (all fields optional numbers unless noted; shared with §2.9, §2.10):

| Field | Type | Meaning |
|---|---|---|
| `mntCotisations` | number | total contributions |
| `mntCotiPriseEnChargeOdpf` | number | contributions covered by the ODPF (Caf/MSA). CMG employer + AMA: always > 0 |
| `mntCotiEmplAcharge` | number | contributions at the employer's expense. CMG employer + AMA: always 0 |
| `mntSalaireNetImposable` | number | taxable net salary |
| `txPas` | number | employee withholding rate applied (PAS) |
| `mntImpotPas` | number | tax withheld |
| `mntSalaireNetApayerApresPas` | number | net salary payable after PAS |
| `mntEmplCmgRemuneration` | number | CMG remuneration amount |
| `mntEmplAverserSalarie` | number | amount the employer must pay the employee |
| `mntAverserParPaje` | number | amount to be paid **to** the employer by the Pajemploi national centre |
| `mntApreleverParPaje` | number | amount to be debited **from** the employer by the Pajemploi national centre |
| `dtPrelevementPrevisionnelle` | string (date) | planned debit date |
| `dtVersementPrevisionnelle` | string (date) | planned payment date |

The official example response uses the placeholder `"string"` for the two dates (kept in the fixture; the contract
type is `date`).

Business errors (HTTP 400, PDF §3.9 and §4.4.1): `PARAM_INVALIDE`, `ER_API_DECLA_00071`, `ER_API_050_0050`,
`ER_API_050_0051`, `ER_API_DECLA_00040`, `ER_API_050_0060`, `ER_API_050_0070`, `ER_API_050_0080`; the full list is §3.6.
Fixtures: `predeclare_ama_request.json`, `predeclare_response.json`, `error_period_invalid_00071.json`,
`error_24h_days_exceeded_0050.json`, `error_handicap_days_invalid_0051.json`, `error_one_child_max_00040.json`,
`error_contract_end_child_0060.json`, `error_duplicate_declaration_0070.json`, `error_childcare_hours_exceeded_0080.json`.

### 2.8 Pre-declare a home childcare salary — `POST /employeurs/{numeroPajeEmployeur}/ged/predeclarer`

`operationId`: `predeclarerSalaireGed`. Summary: "API PAJE046 - Pre-declarer, via un tiers, le salaire pour une garde a
domicile".

"Simulate blank" the declaration of a salary paid to an employee for a period and a GED activity. Same opening
constraints as a declaration, same computed amounts as §2.7. Nothing is recorded.

Parameters: `numeroPajeEmployeur` (path), `dtNaissanceEmployeur` (query), both required.

Body (required): `InputDeclarationGed` (shared with §2.10). Required: `cdModeCalcul`.

| Field | Type | Req. | Rules |
|---|---|---|---|
| `cdModeCalcul` | string | yes | 1 char `R`/`F`/`S` (§3.2); the PDF documents an enum of 3 values, the Swagger does not |
| `inputDeclCommun` | `InputDeclCommun` | yes (in practice) | §2.7 |
| `inputDeclEnfant` | array of `InputEnfant` | yes (in practice) | children (§2.3); no maximum published for GED |
| `inputSp` | `InputSp` | yes (in practice) | §2.7 |
| `finDeContrat` | `InputFinDeContratGed` | no | below |
| `mntFraisTransport` | number | no | transport costs, ≤ 9999.99, multiple of 0.01 |
| `nbHrSupMajA25` | integer | no | 0–35, overtime hours at 25 % |
| `nbHrSupMajA50` | integer | no | 0–40, overtime hours at 50 % |

`InputFinDeContratGed` (all optional, ≤ 9999.99, multiple of 0.01): `mntIndDepartRetraite`, `mntIndCcp`, `mntIndPreavis`,
`mntIndPrecarite`, `mntIndLicenciement` (dismissal indemnity). **Discrepancy:** the PDF GED examples send
`mntIndRupture`, which is not in `InputFinDeContratGed` (see §5).

Response 200: `OutputPreDeclaration` (§2.7). Errors: `PARAM_INVALIDE`, `ER_API_DECLA_00071` (the GED variant of the
description adds "Les déclarations antérieures à [PERIODE1_MONO] ne sont plus possibles"), `ER_API_DECLA_00020`, §3.6.
Fixtures: `predeclare_ged_request.json`, `predeclare_response.json`, `error_period_invalid_00071.json`,
`error_period_invalid_00072.json`.

### 2.9 Declare a childminder salary — `POST /employeurs/{numeroPajeEmployeur}/ama/declarer`

`operationId`: `declarerSalaireAma`. Summary: "API PAJE050 - Declarer, via un tiers, le salaire d'une assistante maternelle".

Declares the salary paid to an AMA for a period and one child. **Records** the declaration. Returns the computed
amounts (employer share of the salary and contributions, PAS), the employer's debit bank account (PDF text: **not**
present in the response schema) and the debit date computed when the declaration is validated.

Parameters and body: identical to §2.7 (`InputDeclarationAma`). Official example: employee with `joursAccueilHandicap` 10,
`mntSalaireNetMensuel` 500, `nbHeures` 100, `nbJoursActivite` 10.

Response 200: `OutputDeclaration`

| Field | Type | Rules |
|---|---|---|
| `referenceDocumentaire` | string | reference of the *volet social* |
| `declaration` | `DeclarationCommun` | §2.7 |
| `indcDeclPajemploiPlus` | boolean | "Pajemploi +" indicator, default `false` |
| `dateVirement` | string (date) | transfer date |
| `verificationEnfantPe` | array of `VerificationEnfantPe` | §2.3 |
| `erreursNonBloquantes` | array of `Erreur` | §3.6 |

Business errors (HTTP 400): same as §2.7 plus `ER_API_DECLA_00072`, `ER_API_DECLA_00100` (employee not associated), see
§3.6. The PDF §3.11 output lists `indcDeclPajemploiPlus` and `dateVirement` but §3.12, §3.9 and §3.10 do not (Swagger
has them for all four operations).
Fixtures: `declare_ama_request.json` (see the note in §5 on the malformed official example), `declare_response.json`,
`error_period_invalid_00072.json` and the AMA errors of §2.7.

### 2.10 Declare a home childcare salary — `POST /employeurs/{numeroPajeEmployeur}/ged/declarer`

`operationId`: `declarerSalaireGed`. Summary: "API PAJE051 - Declarer, via un tiers, le salaire d'une garde d'enfant a domicile".

Declares the salary paid for a GED activity for a period. **Records** the declaration. Same parameters and body as
§2.8 (`InputDeclarationGed`), same response as §2.9 (`OutputDeclaration`).

A given employee can have only **one volet social per month** in GED (`ER_API_DECLA_00020`, also on 046).
Business errors: see §3.6 (GED-specific `ER_API_051_*`).
Fixtures: `declare_ged_request.json`, `declare_response.json`, `error_period_invalid_00072.json`.

### 2.11 Register a mandate — `POST /mandats`

`operationId`: `enregistrerMandatPaje`. Summary: "API PAJE030 - Enregistrement de mandat".

Notifies the Pajemploi system that the platform obtained a *tierce déclaration* mandate from a private employer.
The mandate itself (the legal act and its collection from the employer) is outside the API: the API only registers it.
Only **one** platform can hold the mandate of an employer at a time.

Query parameters (the PDF table says "Path" for `numeroPaje`, an error; the Swagger and the examples use the query):

| Field | Type | Req. | Rules |
|---|---|---|---|
| `numeroPaje` | string | yes | Pajemploi employer number, exactly 14 characters |
| `dateNaissance` | string (date) | yes | employer birth date, `yyyy-MM-dd` (e.g. `1988-01-30`) |

Example: `POST https://api.urssaf.fr/tiersdecl/v1/paje/mandats?numeroPaje=Y2926822480004&dateNaissance=1988-01-30`
(no body).

Response 200: no body ("Mandat ajoute").
Errors (400, body = single `Erreur`): `PARAM_INVALIDE` (the PDF gives two descriptions: "Le paramètre dateNaissance est
invalide" and "La valeur 1990-10-1 est incorrecte"), `ER_API_020_0000` (birth date mismatch), `COMPTE_DEJA_MANDATE`
("Le compte est déjà mandaté": another mandatory body already holds the mandate). 500 `ERR_TECHNIQUE`; 401, 403 (no 503
in the Swagger for the mandate operations).
Fixtures: `error_param_invalide.json`, `error_birth_date_mismatch.json`, `error_compte_deja_mandate.json`,
`error_technical.json`.

### 2.12 Cancel a mandate — `DELETE /mandats`

`operationId`: `annulerMandatPaje`. Summary: "API PAJE031 - Annulation de mandat".

Notifies the Pajemploi system that the mandate obtained by the platform for the employer is cancelled.

Query parameters: `numeroPaje` and `dateNaissance`, identical to §2.11. Example:
`DELETE …/mandats?numeroPaje=Y2926822480004&dateNaissance=1988-01-30`.

Response 200: no body ("Mandat annule"). Errors (400, single `Erreur`): `PARAM_INVALIDE`, `ER_API_020_0000`,
`COMPTE_DEJA_MANDATE` (as documented in the PDF for this operation too, which is surprising for a cancellation, see §5).
500 `ERR_TECHNIQUE`.
Fixtures: same error fixtures as §2.11.

---

## 3. Reference values

### 3.1 `reponseStatutEnfant` (`VerificationEnfantPe`)

| Value | Meaning |
|---|---|
| `0` | Entitled child not found, or not entitled |
| `1` | OK, entitled child found |
| `2` | OK, entitled child found through a partial search |

### 3.2 Coded fields

| Field | Values |
|---|---|
| `cdModeCalcul` (GED) | `R` real mode (default, and the only one outside the DOM), `F` flat-rate mode, `S` specific mode (DOM GED only) |
| `indcEnfantPlusJeuneAgarder` (GED estimate) | `1` child 0–3 years without activity supplement, `2` child 3–6 years, `3` child over 6 years |
| `typeActiviteSalarie` | `A` assistante maternelle, `G` garde d'enfants à domicile |
| `cdCivilite` | `1` M., `2` Mme |
| `etatCompteEnLigne` (employer) | `Actif`, `Absent`, `Inactif`, `Coordonnées bancaires invalides` |
| `etatCompteEnLigne` (employee) | `Actif`, `Absent`, `Inactif` |

### 3.3 Street letters (`lettreVoie`, PDF §4.2)

`A` … `Z` are the letters themselves, with the following equivalents: `B` = B or *Bis*, `C` = C or *Quinquies*,
`Q` = Q or *Quater*, `T` = T or *Ter*.

### 3.4 Street types (`typeVoie`, PDF §4.3)

| Code | Label | Code | Label | Code | Label |
|---|---|---|---|---|---|
| `ALL` | Allée | `IMB` | Immeuble | `QU` | Quartier |
| `AV` | Avenue | `IMP` | Impasse | `RAM` | Rampe |
| `BAT` | Bâtiment | `LOT` | Lotissement | `RES` | Résidence |
| `BD` | Boulevard | `MAI` | Maison | `RTE` | Route |
| `CAN` | Canal | `MTE` | Montée | `R` | Rue |
| `CHS` | Chaussée | `PAS` | Passage | `RUL` | Ruelle |
| `CHE` | Chemin | `PAR` | Parc | `SEN` | Sentier |
| `CI` | Cité | `PAV` | Pavillon | `SQ` | Square |
| `CL` | Clos | `PL` | Place | `VO` | Voie |
| `C` | Cours | `POR` | Porte | | |
| `COG` | Cottage | `Q` | Quai | | |
| `FG` | Faubourg | | | | |
| `HAM` | Hameau | | | | |

(This is the complete PDF list: ALL, AV, BAT, BD, CAN, CHS, CHE, CI, CL, C, COG, FG, HAM, IMB, IMP, LOT, MAI, MTE, PAS,
PAR, PAV, PL, POR, Q, QU, RAM, RES, RTE, R, RUL, SEN, SQ, VO.)

### 3.5 Geographic zone codes (PDF §4.1)

`codeZoneGeographique` accepts: `NATIO` (metropolitan France excluding Bas-Rhin, Haut-Rhin and Moselle), `97100`
(overseas), `ALSMO` (Bas-Rhin, Haut-Rhin, Moselle). **No field of the contract is named `codeZoneGeographique`** (see §5).

### 3.6 Error catalogue

HTTP-level / generic codes (examples in the PDF, HTTP 400 unless noted):

| Code | HTTP | Where | Meaning |
|---|---|---|---|
| `PARAM_INVALIDE` | 400 | all | "Un des paramètres de votre demande est invalide" (description names the parameter) |
| `ER_API_020_0000` | 400 | 020, 030, 031 | birth date and Pajemploi number do not match |
| `ER_API_020_0010` | 400 | 020, 045, 046, 050, 051 | no employer for this number |
| `PE_INEXISTANT` | 400 | 023 | "Employeur inexistant" |
| `MANDAT_INEXISTANT` | 400 | 040 (example) | "Il n'existe aucun mandat pour ce PE et ce partenaire" |
| `COMPTE_DEJA_MANDATE` | 400 | 030, 031 | another mandatory body already holds the mandate |
| `ERR_TECHNIQUE` | 500 | all | technical error |
| `ERREUR_TECHNIQUE` | 400 | 045, 046, 050, 051 | hourly rate ≥ 100 € (PDF §4.4.1 text) |
| — | 401, 403, 512 | all | security errors (body not documented); 503 "Utilisation de l'API interdite" (e.g. maintenance) |

Blocking messages of the declaration operations (PDF §4.4.1; HTTP 400; "N°" = `code`, "Message court" = `message`,
"Description" = `description`). Services: 045 = AMA pre-declaration, 046 = GED pre-declaration, 050 = AMA declaration,
051 = GED declaration.

| Code | Services | Message court | Description |
|---|---|---|---|
| `ER_API_DECLA_0000` | 045, 046, 050, 051 | Déclaration anticipé interdite | Space opens from the 25th of the employment month |
| `ER_API_DECLA_0001` | 045, 046, 050, 051 | La déclaration n'est plus autorisée | Transmission no longer allowed for this employment period |
| `ER_API_DECLA_0002` | 045, 046, 050, 051 | Dépassement du nombre de déclarations autorisées | Limit of declarations with non-certified employees reached for the period |
| `ER_API_DECLA_00020` | 046, 051 | Déclaration existante sur la période | One volet social per employee and per month |
| `ER_API_DECLA_00040` | 045, 050 | Un enfant maximum par déclaration | Only one child can be kept |
| `ER_API_DECLA_00071` | 045, 046, 050, 051 | Période d'emploi invalide | Employment period date incorrect |
| `ER_API_DECLA_00072` | 045, 046, 050, 051 | Période d'emploi invalide | Declarations before `[PERIODE1_MONO]` no longer possible |
| `ER_API_DECLA_00100` | 050 | Salarié non associé à l'employeur | Employee must first be associated to the employer |
| `ER_API_020_0010` | 045, 046, 050, 051 | Aucun employeur trouvé pour ce numéro | Employer id matches no employer |
| `ER_API_021_0010` | 045, 046, 050, 051 | La date de naissance et le salarié trouvé ne correspondent pas | Birth date differs from the one supplied |
| `ER_API_050_0010` | 045, 050 | Aucuns jours d'activités ni de congés payés | Days of activity + paid-leave days must be > 0 |
| `ER_API_050_0015` | 045, 050 | Salaire horaire net obligatoire | Hourly rate required if major or complementary hours are given |
| `ER_API_050_0020` | 045, 050 | Salaire net total déclaré AMA est très élevé | Net total salary very high (issue of `ER_DECL_10008`) |
| `ER_API_050_0021` | 045, 050 | Salaire net total incohérent | Net total cannot be below hourly rate × normal hours |
| `ER_API_050_0022` | 045, 050 | Salaire net trop bas | Net total must be ≥ `{0}` € |
| `ER_API_050_0030` | 045, 046, 050, 051 | Les heures sont incohérentes | Specific hours must be ≤ normal + major + complementary hours. Note: since the CMG-L reform the specific-hours control is removed |
| `ER_API_050_0031` / `ER_API_051_0031` | AMA / GED (rows share one cell in the PDF) | Nombre d'heures normales supérieur à `{nb heures}` heures | Normal hours exceed the allowed maximum |
| `ER_API_050_0050` | 045, 050 | Le nombre de jours de garde de 24 heures consécutives ne peut pas dépasser le nombre de jours d'activité saisi | |
| `ER_API_050_0051` | 045, 050 | Le nombre de jours accueil Handicap invalide | Disabled-child care days must equal activity days |
| `ER_API_050_0060` | 045, 050 | La fin de contrat doit être effectuée pour l'enfant gardé | Contract-end child differs from the kept child |
| `ER_API_050_0070` | 045, 050 | Déclaration existante sur la période | A declaration already exists for this employee, care mode, period and child |
| `ER_API_050_0080` | 045, 050 | Le nombre d'heures de garde d'un même enfant ne doit pas dépasser `[NMX]` heures pour ce mois et cet allocataire | Sum of declared hours exceeds the authorised hours (`NMX`, e.g. 600) |
| `ER_API_051_0000` | 046, 051 | Salaire horaire déclaré GED est très élevé | Hourly rate too high (issue of `ER_DECL_10006`) |
| `ER_API_051_0010` | 046, 051 | Salaire net total déclaré GED est très élevé | Net total too high (issue of `ER_DECL_10007`) |
| `ER_API_051_0020` | 046, 051 | Informations sur les heures supplémentaires incohérentes | Inconsistent 25 % / 50 % overtime hours |
| `ER_API_051_0040` | 046, 051 | Salaire horaire inférieur au smic | Reconstituted net hourly rate must be ≥ `{0}` €, `{0}` deduced from the calculation mode |

The PDF writes the codes broken across lines (`ER_API_DECLA_` / `0000`, `ER_API_050_00` / `10`, …). They are
reassembled here: for the `ER_API_050_00xx` / `ER_API_051_00xx` families the join gives the 4-digit suffixes above
(confirmed by the examples `ER_API_050_0050`, `_0051`, `_0060`, `_0070`, `_0080`). `ER_API_DECLA_0000/0001/0002` have a
4-digit suffix while `ER_API_DECLA_00020/00040/00071/00072/00100` have 5 digits: this is how they are written in the PDF
(extraction `ER_API_DECLA_ 0000`), **to confirm on the sandbox** (see §5). The codes `ER_DECL_10006/10007/10008` are
legacy internal origins, not returned codes.

Non-blocking messages (PDF §4.4.2, returned in `erreursNonBloquantes`, HTTP 200):

| Code | Message court | Description |
|---|---|---|
| `ER_API_020_NB_0000` | Numéro Pajemploi non autorisé pour la tierce déclaration | Employer status invalid: *Absent* (not registered), *Inactif* (online registration not effective), *Bloqué* (no longer entitled to Paje). The official example reads "L'inscription au site internet n'est pas effective" |
| `ER_API_020_NB_0010` | Coordonnées bancaires invalides | One of the employer's bank details is missing |
| `ER_API_021_NB_0000` | Numéro Pajemploi non vérifié | Employee status invalid: *Absent*, *Inactif*, *Bloqué* |
| `ER_API_022_NB_0000` | Enfant inconnu pour cet employeur | No child found for the birth date in the Pajemploi system |
| `ER_API_041_NB_0010` | Enfant ne peut plus bénéficier des aides | Child over 6: no more PAJE benefit |
| `ER_API_041_NB_0020` | Mode de calcul incohérent | Calculation mode invalid (missing, or non-real for a non-DOM employee); the simulation runs in real mode `R` |
| `ER_API_023_NB_0000` | Salarié / Employeur déjà associé et actif | Employee already associated and active |
| `ER_API_023_DATE_AGREMENT_NB_0000` | Merci de vérifier la date de naissance de votre salariée ainsi que la validité de son agrément | Agreement year must be greater than the childminder's birth year + 18 |
| `ER_API_023_DATE_AGREMENT_NB_0010` | La date de fin d'effet d'agrément calculée à partir de la date de l'agrément doit être supérieure à la date du jour | Agreement date must match the latest renewal |
| `ER_AG_NB_00000` | Date agrément incorrect | Check birth date and agreement validity |
| `ER_AG_NB_00010` | Agrément arrive à échéance | Agreement date must match the latest renewal |
| `ER_API_050_NB_0016` | Dépassement du taux horaire maximum | Hourly cost above `[RCHM_MT_AMA]` €: CMG and contribution coverage are capped |
| `ER_API_051_NB_0016` | Dépassement du taux horaire maximum | Hourly cost above `[RCHM_MT_GED]` €: CMG is capped |
| `ER_API_DECLA_NB_0010` | Écart salaire important avec précédentes déclarations | Significant salary gap versus previous declarations |

---

## 4. Package decisions derived from the specification

Architecture decisions (inherited from `urssaf-tiers-prestations` or specific to this package) are recorded in
[docs/adr/](adr/README.md).

1. **One operation, one method.** The 12 operations map to 12 gateway methods; AMA and GED are separate methods and
   separate request types (different required fields, different `finDeContrat`), sharing value objects
   (`DeclarationPeriod`, `Child`, `EmployeeIdentification`, `Money`).
2. **Employer context.** `EmployerReference` = (`PajeEmployerNumber` 14 alphanumeric chars, `BirthDate`). It is a
   *credential-like* pair: never logged in clear. Employer-scoped operations take it as one parameter.
3. **Amounts.** Wire amounts are JSON numbers; the domain uses a decimal-safe `Amount` value object (signed integer
   cents, bounds applied per field), never `float`. Parsing from the wire goes through the shortest round-trip
   representation rounded to the cent; `txPas` is kept as an exact decimal (`Rate`). Serialisation emits 2 decimals
   (ADR 0008).
4. **Dates.** `yyyy-MM-dd` ↔ `CalendarDate` (strict, no time part, no time zone on the wire; ADR 0001). The placeholder
   `"string"` of the official sample responses must not be treated as a valid date by the mapper (rejects with a typed
   error), only the fixtures are loose.
5. **Result with non-blocking errors.** Every result carries `list<NonBlockingMessage>`; the code is preserved raw and
   typed when known (§3.6). HTTP 200 never means "no warning".
6. **Errors.** Parse both an `Erreur` array and a single `Erreur` object. Known codes become an `ApiErrorCode` enum
   with a raw fallback. Trim codes (the PDF shows trailing spaces).
7. **Declaration safety.** `declare*` must never be retried automatically on a timeout or 5xx: the retry could produce
   `ER_API_050_0070` / `ER_API_DECLA_00020` or, worse, a duplicate (ADR 0003: unknown outcome, manual resolution).
   An opt-in policy pre-declares (045 / 046) before declaring and refuses to declare when the pre-declaration is
   rejected (ADR 0008, default off).
8. **Mandate prerequisite.** The package does not manage the legal mandate; it exposes `registerMandate()` /
   `cancelMandate()` only. Which operations strictly require a registered mandate is unknown (§5): an optional local
   guard (off by default) can refuse 045, 046, 050 and 051 without a local mandate (ADR 0009).
9. **Empty body.** The package always sends `{}` for 021 and for 023 when the employee is identified by query
   parameters (PDF technical note), never an empty body.
10. **Typed identification.** `EmployeeIdentification` is a sum type: by Pajemploi number, by NIR, or by administrative
    data (§2.2 priority 1/2/3); the "at least one" rule is enforced locally. The declarations mirror it with `InputSp`.
11. **Local validation.** Only rules stated in the contract are enforced locally (the Swagger schema plus the
    decisions of this section, ADR 0009): lengths (14 / 15 / 5), patterns of names, numeric ranges, `yyyy-MM-dd`;
    PDF-only rules are left to the server. Contradictions (PDF vs Swagger) are validated with the **laxer** bound and the
    server decides (`nbJoursActivite` 1–99 vs ≤ 31, `nbHeures` unbounded vs ≤ 1000).
12. **Personal data.** NIR, birth dates, employer numbers, child names, employee identities are personal data: never
    logged, never put in exception messages.
13. **Sandbox.** A contract test suite (skipped without credentials) on `https://api-edi.urssaf.fr/tiersdecl/v1/paje`, once credentials and a test
    employer / mandate are available.

---

## 5. Open points

- **Swagger 1.5.1 vs PDF 1.5.2**: the PDF is newer; the only PDF 1.5.2 change is new §4.4 messages (changelog). The
  Swagger has no per-code error documentation.
- **Production vs sandbox Swagger**: identical except `host` (`api.urssaf.fr` / `api-edi.urssaf.fr`).
- **Mandate prerequisite** (interim: optional local guard, off by default, ADR 0009): not stated which operations
  need a mandate. `MANDAT_INEXISTANT` appears only as an
  example of 040 (AMA estimate, documented as context-free); no mandate error is documented for 020 to 023 or 045 to 051.
  `COMPTE_DEJA_MANDATE` is also listed for the cancellation (031).
- **Quota values**: not published.
- **Security errors** (401, 403, 512) body: not documented. HTTP 512 is in the PDF only; HTTP 429 in the PDF only;
  HTTP 503 in the Swagger only (and not on the mandate operations).
- **Error body shape**: array (Swagger, operations except mandates) vs single object (PDF examples and Swagger for
  mandates).
- **`ERR_TECHNIQUE` vs `ERREUR_TECHNIQUE`**: the PDF examples use the first; §4.4.1 text mentions the second (HTTP 400
  for hourly rate ≥ 100 €).
- **Error code suffixes** of the `ER_API_DECLA_*` family (4 vs 5 digits) and codes broken across lines in PDF §4.4.1.
- **`etatCompteEnLigne`**: exact wire value for "Coordonnées bancaires invalides" not published (prose only); not an
  enum in the Swagger.
- **Required flags that differ between PDF and Swagger**: `InputIdentificationSalarie` (PDF: `cdCivilite`, `nomNaissance`,
  `prenom`, `dtNaissanceSalarie`, `villeNaissance` mandatory; Swagger: none), `InputCreationSalarie.typeActiviteSalarie`
  (PDF mandatory; Swagger optional), `dtNaissanceSalarie` query of 023 (Swagger required; PDF optional since 1.2),
  `cdPostalEmployeur` of the GED estimate (PDF `*`; Swagger default `75000`), `inputDeclCommun`/`inputSp`/`inputDeclEnfant`
  (not required in Swagger, required in practice).
- **Value ranges that differ**: `nbJoursActivite` of the declarations 1–99 (Swagger and PDF structure) vs "inférieur ou
  égal à 31" (description) and 1–31 in the estimate; `nbHeures` of `InputDeclCommun` has no Swagger maximum, PDF says ≤
  1000 and "< 1000"; Swagger format `uint8` on fields allowed up to 999 (`nbHeuresMajorees`, `nbHeuresComplementaires`),
  PDF `int32`. `mntIndemnitesRepas` has no `minimum` unlike `mntIndemnitesEntretien`.
- **Enums not in Swagger**: `cdModeCalcul` (R/F/S) and `indcEnfantPlusJeuneAgarder` (1/2/3) are free strings of length
  1 in the Swagger; the PDF documents an `Enum` of 3 values.
- **Output fields**: `indcDeclPajemploiPlus` and `dateVirement` in the Swagger for 045, 046, 050, 051; in the PDF only
  in PDF §3.11. The PDF §3.11/§3.12 introductions announce "le compte bancaire de prélèvement de l'employeur", absent from
  the response schema.
- **`InputFinDeContratGed`**: Swagger has `mntIndLicenciement`; the PDF examples send `mntIndRupture` (AMA field).
  Changelog 1.3.2 says `mntIndRupture` and `mntIndLicenciement` were removed from the declaration calls, yet both exist
  in the 1.5.1 Swagger.
- **Malformed PDF example (§3.11)**: the AMA declaration sample closes `finDeContrat` late (the 5 top-level fields end
  up inside it, one `}` missing) and omits `mntIndRupture`. `declare_ama_request.json` closes `finDeContrat` after
  `mntIndPrecarite`; the values are otherwise verbatim.
- **PDF §3.5 / §3.6**: `numeroPaje` is labelled "Path", it is a query parameter.
- **`InputIdentificationSalarie.villeNaissance`** Swagger pattern is mangled (accented letters degraded to repeated
  ASCII); the PDF pattern is the reference.
- **PDF §4.1**: `codeZoneGeographique` is documented but appears in no schema.
- **Examples**: the sample `L'\''HOMME` is a shell escape of `L'HOMME` (fixtures use the latter); official
  samples use `"string"` as placeholder for NIR / dates / numbers; some PDF error codes carry trailing spaces
  (`PE_INEXISTANT `, `MANDAT_INEXISTANT `, `ER_API_050_0051 `), trimmed.
- **`ER_API_DECLA_00071` description** differs between PDF §3.9 (AMA: short text) and §3.10/§3.12 (adds the
  `[PERIODE1_MONO]` sentence); the fixture keeps the short text, `ER_API_DECLA_00072` carries the other.
- **Formats not published**: `numeroPajeEmployeur` regex (only the 14-length), NIR checksum, `etatCompteEnLigne` enum,
  the maximum size of `inputEnfantPe` / `inputDeclEnfant` for GED, the exact value of `NMX`, `RCHM_MT_AMA`,
  `RCHM_MT_GED`, `PERIODE1_MONO`.
- **Retry safety of 023, 030 and 031**: not specified. 023 may create an employee; a replay of 030 / 031 is not
  documented. Treated as never resent by default, configurable per operation (ADR 0003).
- **Response amounts**: no minimum / maximum published for `DeclarationCommun` and `OutputEstimation`; decoded as
  signed amounts with at most 2 decimals (ADR 0008).
- **`txPas` precision**: not published; decoded with at most 4 decimals (ADR 0008).
- **CESU / PAJE / AE**: the three "Tierce déclaration" APIs share `/tiersdecl/v1` and the mandate model; a shared
  library is a later question, out of scope here.
