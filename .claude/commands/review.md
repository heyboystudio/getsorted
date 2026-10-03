---
description: Run spec, code and security review on the current branch
---
Review the current branch against `main`.

1. Get the diff (`git diff main...HEAD`) and identify the spec it implements (from the branch name or commit messages).
2. Run the `spec-checker`, `code-reviewer` and `security-reviewer` agents in parallel on the diff.
3. Merge their findings into one list grouped as **Must fix**, **Should fix**, **Nice to have**, each with file:line and a one-line reason.
4. Run `composer check` and report the result.
5. Summarise for the founder in plain language: is this safe to merge, and if not, what's blocking it. Do not change code unless asked.
