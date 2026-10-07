---
description: Draft a feature spec for founder approval
argument-hint: <feature idea or roadmap item>
---
Draft a feature spec for: $ARGUMENTS

1. Read `.ai/guidelines/getsorted-project.md`, `docs/roadmap.md`, and the product docs relevant to this feature (PRD, user journeys, job lifecycle, money flow, matching, scoping) plus `docs/security/security-baseline.md`.
2. Find the next spec number in `docs/specs/` (create the folder if needed) and copy `docs/templates/feature-spec.md` to `docs/specs/NNN-short-name.md`.
3. Fill every section. Acceptance criteria must be numbered, testable "Given/When/Then" statements. Include empty, loading and error states for screens. List policies (who can see/do what) and PII involved.
4. Keep scope small enough for one PR (or split into sub-specs and say so).
5. List anything that needs a founder decision under "Open questions", referencing `docs/product/open-questions.md` numbers where they apply. Do not invent answers for product, money, security or privacy questions.
6. Do not write any code. End by summarising the spec in plain language (5 bullet points max) and asking the founder to approve or change it.
