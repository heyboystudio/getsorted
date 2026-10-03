---
description: Plan, test, build, review and open a PR for an approved spec
argument-hint: <path to approved spec, e.g. docs/specs/004-booking-flow.md>
---
Build the feature described in: $ARGUMENTS

Follow `docs/engineering/workflow.md` exactly.

1. **Check** the spec status is "Approved". If not, stop and ask.
2. **Read** the spec, `.ai/guidelines/*.md`, `docs/engineering/conventions.md`, `docs/engineering/testing.md`, `docs/security/security-baseline.md` and the domain docs it references.
3. **Plan** (plan mode): list files to create/change, migrations, Actions, Policies, components/resources, tests mapped to each acceptance criterion, and risks. Ask the founder to approve the plan. Stop until approved.
4. **Branch**: `feat/NNN-short-name` from up-to-date `main`.
5. **Tests first**: write failing Pest tests covering every acceptance criterion, every guard and every authorisation rule. Run them and confirm they fail for the right reason.
6. **Implement** the smallest change that makes them pass, following the conventions.
7. **Quality gate**: run `composer check` until green. Never weaken or skip a test to get green.
8. **Review**: run the `spec-checker`, `code-reviewer` and `security-reviewer` agents on the diff. Fix every "must fix" finding; list "should fix" items in the PR if not done.
9. **Docs**: update `docs/architecture/data-model.md`, `docs/architecture/decisions.md` (if a decision was made) and the spec's status and Progress section.
10. **Commit** with conventional messages, push, and open a PR whose description includes: summary in plain language, how to try it on the preview, screenshots for UI, test summary, and any decisions needed from the founder.
11. Stop. Do not merge until the founder approves.
