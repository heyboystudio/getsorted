# Working with Claude and Codex

The founder switches between Claude and Codex when credits run out. Both agents use the same specs, decisions, branch and acceptance criteria. Chat history is not shared; repository notes are the handoff.

## Starting or resuming

- Read this guide, `.ai/guidelines/sortd-project.md`, `.ai/guidelines/sortd-domain.md`, the relevant spec and its Progress section, and `docs/engineering/handoff.md` if present.
- Inspect `git status --short`, the current branch, `git worktree list` and the relevant diff before editing. Confirm that the handoff describes this checkout and branch. If not, inspect the matching worktree; do not switch or reset a dirty checkout.
- Preserve unfinished changes, including untracked files. Resume the existing approved task rather than starting a replacement implementation. Do not stash, discard, commit or merge another agent's work merely to clean the checkout.
- Recorded founder approvals and decisions remain valid when switching agents. Do not ask for approval again solely because the agent changed. Ask about missing decisions or materially changed scope.
- Recent explicit founder decisions and approved specs can supersede older PRD or roadmap text. Use the decisions log to establish that; ask when product, payment, privacy or security requirements remain unresolved. Do not use a stale “Phase 0” heading as the current task.
- Use `PRODUCT.md` for the homepage's approved branding and positioning when present. It does not automatically rename the rest of the product.

## Shared workflow

Follow `docs/engineering/workflow.md` and the command definitions in `.claude/commands/`. In Codex, the founder can ask in ordinary language: “draft a spec”, “build this approved spec”, “review this branch”, “record this decision”, or “check our progress”. Read the matching command file and carry out its workflow; Claude's slash-command syntax is not required.

Project skills are in `.agents/skills/` for Codex and `.claude/skills/` for Claude. Use the relevant framework/testing skill. For Laravel Boost changes, edit `.ai/guidelines/` as the source and regenerate the managed sections when PHP is available. Keep this guide linked above those generated sections in both `AGENTS.md` and `CLAUDE.md`.

For reviews, use the criteria in `.claude/agents/spec-checker.md`, `code-reviewer.md` and `security-reviewer.md`. Use delegated reviewers when available and authorized; otherwise perform the checks directly and report that independent agent review was not performed. Include committed, unstaged and untracked changes in the review scope as appropriate. Never describe a direct review as an independent review.

## Switching agents

When asked to hand off or when stopping with unfinished work, update `docs/engineering/handoff.md` and the active spec's Progress section. Include the date, agent, checkout, branch, spec, completed work, remaining work, founder decisions/approvals with their source, and the next concrete action. Record commands actually run and their outcomes; distinguish passed, failed and not run. Include uncommitted files and any known blockers. Keep notes free of secrets and personal data.

If credits expire before notes are updated, the next agent must reconstruct the state from the spec, git history and working diff before continuing. Handoff notes are evidence to verify, not proof that tests passed.

## Local tools

The existing `.codex/config.toml` launches Laravel Boost with `php artisan boost:mcp`. It requires PHP and installed Composer dependencies in the project. If unavailable, report the tool limitation and consult installed package source or official framework documentation rather than pretending Boost ran.

Run the project's quality gate for application changes and report actual results. Documentation/instruction-only setup can be checked with diff and skill validation; it does not establish that the app passes its quality gate. Never read `.env` files or print secrets, and never run commands against staging or production databases.
