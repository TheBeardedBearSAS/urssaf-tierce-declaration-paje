
## Operations 020 to 023, 030, 031 (identity and mandates)

Only the official request of 023 needs a `_valid` variant: its placeholders are refused by the domain value objects
(lengths, patterns, `numVoie` >= 1), so the positive request test is built on the variant. The official responses of
020, 021, 022 and 023 are mapped as they are (their only dates are real dates; `numeroPajeSalarie` stays the raw
placeholder `"string"`, which the mapper does not validate). The negative tests (date placeholder refused, ADR 0001)
are written inline.

### `associate_employee_request_valid.json` (from `associate_employee_request.json`)

Same keys, same order, same values where the official value is already valid (`dtDebutAgrement`, `dtNaissanceSalarie`
twice, `cdCivilite`). Replaced placeholders. The values are either taken from the api-reference examples or **obviously fictitious
placeholders (`DURAND`, `RESIDENCE LES LILAS`, `DE LA PAIX`, `LE BOURG`, `B`, `12`), used only for fields that have no
official example (usage name, address lines); they are not personal data** and describe no real person or address:

| Field | Official | Variant | Why |
|---|---|---|---|
| `inputAdresseSalarie.codeCommune` | `string` | `94022` | exactly 5 characters; INSEE code of the api-reference example (CHOISY LE ROI) |
| `inputAdresseSalarie.codePostal` | `string` | `94600` | exactly 5 characters; postal code of that municipality |
| `inputAdresseSalarie.complementLocalisation` | `string` | `RESIDENCE LES LILAS` | pattern `[A-Z'\-\s]*`, 32 characters at most (fictitious) |
| `inputAdresseSalarie.lettreVoie` | `string` | `B` | 1 character (fictitious) |
| `inputAdresseSalarie.libelleVoie` | `string` | `DE LA PAIX` | 21 characters at most (fictitious) |
| `inputAdresseSalarie.lieuDit` | `string` | `LE BOURG` | pattern `[A-Z'\-\s]*`, 32 characters at most (fictitious) |
| `inputAdresseSalarie.numVoie` | `0` | `12` | 1 to 9999 (fictitious) |
| `inputAdresseSalarie.typeVoie` | `string` | `R` | 1 to 3 characters of `[A-Z'\-\s]` (documented code) |
| `inputIdentificationSalarie.codePaysNaissance` | `string` | `100` | 3 characters at most; FRANCE in the api-reference |
| `inputIdentificationSalarie.departementNaissance` | `string` | `972` | 3 characters at most; official example of the api-reference |
| `inputIdentificationSalarie.nomNaissance` | `string` | `L'HOMME` | upper-case name; official example of the api-reference |
| `inputIdentificationSalarie.nomUsage` | `string` | `DURAND` | upper-case name (fictitious) |
| `inputIdentificationSalarie.prenom` | `string` | `JEAN-MICHEL` | upper-case name; official example of the api-reference |
| `inputIdentificationSalarie.villeNaissance` | `string` | `CHOISY LE ROI` | pattern of the PDF, 30 characters at most; the municipality of the api-reference example |
| `nirSalarie` | `string` | `178010701005265` | 15 characters; the NIR of the official 021 call in the api-reference |
| `numeroPajeSalarie` | `string` | `Y2926822480004` | 14 characters; the Pajemploi number of the official mandate example (api-reference §2.11), reused as a fictitious employee number |
| `typeActiviteSalarie` | `string` | `A` | 1 character (`A` = AMA) |

The official file is a flattened Swagger example: `numeroPajeSalarie` is a query parameter of 023, not a field of the
body `InputCreationSalarie`. The tests split it accordingly.

## Estimates, pre-declarations and declarations (040, 041, 045, 046, 050, 051)

`estimate_ama_request`, `estimate_ged_request` and `estimate_response` are valid as they are. The others have a
`_valid` variant:

| File | Replacement | Why |
|---|---|---|
| `predeclare_ama_request_valid`, `predeclare_ged_request_valid`, `declare_ged_request_valid` | `inputDeclCommun.nbHeures` 0 becomes 100 | the minimum is 1 |
| same three files | `inputDeclCommun.mntSalaireNetMensuel` 0 becomes 500 | the contract requires a value above 0 (500 is the amount of the official declaration example) |
| `predeclare_*_request_valid`, `declare_*_request_valid` | `inputSp`: `nirSalarie: "string"` removed, `numeroPajeSalarie: "string"` becomes `"Y2926822480004"` (the 14-character Pajemploi number of the official mandate example, api-reference §2.11, reused as a fictitious employee number) | an employee is identified by its number or by its NIR, never both (ADR 0004); the NIR variant is built in the tests |
| `predeclare_ged_request_valid`, `declare_ged_request_valid` | `finDeContrat.mntIndRupture` becomes `mntIndLicenciement` | `InputFinDeContratGed` has no `mntIndRupture` (api-reference §5) |
| `predeclare_response_valid`, `declare_response_valid` | `declaration.dtPrelevementPrevisionnelle` `"string"` becomes `"2020-01-01"`, `declaration.dtVersementPrevisionnelle` `"string"` becomes `"2020-01-31"` | strict `Y-m-d` dates (ADR 0001); the value is only an example date |

`referenceDocumentaire: "string"` of `declare_response` is a valid string and is kept.
