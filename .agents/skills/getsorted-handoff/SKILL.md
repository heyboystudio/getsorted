---
name: getsorted-handoff
description: Save or resume Get Sorted development work when switching between Claude and Codex, including unfinished changes, approvals and verification results.
---

Read `docs/engineering/agent-workflow.md` from the project root.

For a handoff, inspect the actual branch and working diff, then update `docs/engineering/handoff.md` and the active spec's Progress section. Record what is complete, what remains, approvals and their source, checks actually run and the next action. Do not change application code or commit merely to create a handoff.

For a resume, read those notes and the active spec, then verify the checkout, branch and working diff. Continue the authorized task without repeating established approvals. If notes are absent or stale, reconstruct the state from git and the spec; do not guess that previous checks passed.
