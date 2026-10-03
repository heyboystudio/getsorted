# How we build (founder + Claude Code)

The founder decides **what** and checks the result; Claude Code writes the code, tests and docs. This workflow replaces the code reviews and stand-ups a team would have.

## The loop for every feature

1. **Spec** — `/spec <feature>` creates `docs/specs/NNN-feature.md` from the template: goal, user story, acceptance criteria, screens, data changes, out of scope, open questions. Founder reads and approves it (or edits).
2. **Plan** — Claude Code uses plan mode to propose the files, migrations, Actions, tests and risks. Founder approves.
3. **Branch** — `feat/NNN-short-name`.
4. **Test first** — write failing tests from the acceptance criteria.
5. **Build** — smallest change that passes; follow `docs/engineering/conventions.md`.
6. **Check** — `composer check` (Pint, Larastan, Pest, audits) must pass locally.
7. **Review** — run the `code-reviewer` and `security-reviewer` agents; fix what they find.
8. **Docs** — update data-model.md, decisions.md, the spec's status, and the changelog in the same PR.
9. **PR** — description lists what changed, how to try it, screenshots for UI, and anything the founder must decide.
10. **Preview** — founder clicks through the staging preview and approves; Claude merges after CI is green.

`/build-feature <spec>` runs steps 2–9 in order and stops for approval at step 2 and before merging.

## Definition of done

- [ ] Acceptance criteria all met and each covered by a test
- [ ] CI green: Pint, Larastan, Pest, `composer audit`, `npm audit`
- [ ] Policies cover every new model/action; authorisation tests exist
- [ ] No secrets, no `dd()`, no TODOs without an issue link
- [ ] Mobile layout checked at 360 px for any UI change
- [ ] Docs updated (data model, decisions, spec status)
- [ ] Reviewer agents run and findings resolved
- [ ] Founder approved the preview

## Session hygiene for Claude Code

- Start each session with: "Read CLAUDE.md, docs/roadmap.md and the spec we're working on."
- One feature per session where possible; `/clear` between unrelated tasks.
- If context gets long, ask Claude to write progress notes into the spec's "Progress" section before clearing.
- Never let Claude run commands against staging or production databases. Deployments happen through CI only.

## Commands the founder will use

| Command | What it does |
|---|---|
| `/spec <idea>` | Draft a feature spec for approval |
| `/build-feature <spec file>` | Plan → test → build → review → PR |
| `/review` | Run code + security review on the current branch |
| `/adr <decision>` | Add a decisions-log entry |
| `/phase-check` | Report progress against the roadmap and what's next |
