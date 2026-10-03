# Phase 0 — local tool preflight

Checked 2026-10-03 in `/home/andymichaels/Downloads/Sortd/sortd`.

Status: step 1 is incomplete. No Laravel application or dependencies have been installed.

| Requirement | Observed result |
|---|---|
| PHP 8.4 | `php` not found on PATH |
| Composer | `composer` not found on PATH |
| Node LTS | Node v18.19.1 and npm 9.2.0 found; target LTS still needs verification and provisioning |
| Docker / Compose | `docker` not found on PATH; container runtime not verified |
| PostgreSQL 17 + PostGIS locally | `psql` not found on PATH; database and extension not verified |
| Git | Git 2.43.0 available |
| GitHub PR tooling | GitHub CLI 2.102.0 installed in `~/.local/bin/gh`; official release archive SHA-256 verified |
| GitHub authentication | Browser login verified as `heyboystudio`; `gh auth status` confirms keyring storage; Git credential helper configured |
| Repository | Founder supplied private `https://github.com/heyboystudio/sortd`; initially empty; local Git initialized with the supplied documents as the baseline |

Missing commands do not establish whether services or tools exist elsewhere on the machine.

## Required local action

Installing Docker needs the workstation administrator password (`sudo -n true` reports that a password is required). Enter it only in your own terminal, never in chat.

From the project directory run:

```bash
sudo bash scripts/install-docker-ubuntu.sh
```

The script follows [Docker's official apt installation instructions](https://docs.docker.com/engine/install/ubuntu/), using Zorin's Ubuntu `noble` base. Docker does not officially support Ubuntu derivatives. It adds Docker's signing key and apt source, installs Engine and Compose, starts Docker, and runs its `hello-world` check. It stops for conflicting packages rather than removing them. It does not change user groups or grant passwordless administrator access. Docker user access still needs configuration afterward.

Validation so far: Bash syntax and non-root refusal checked; installation and container checks have not run. PHP, Composer, Node LTS, and PostgreSQL/PostGIS provisioning remain pending.

## Resume plan

1. Finish Docker installation locally and verify daemon access.
2. Provision PHP 8.4, Composer, and Node 24 LTS after checking official distributions. Node 24 is identified as LTS by the [official download page](https://nodejs.org/en/download).
3. Start a local PostgreSQL 17 + PostGIS container and verify its server version and extension using a local-only connection.
4. Record successful checks and complete roadmap step 1 in its PR before starting the Laravel scaffold. Keep that PR in draft until these checks pass.

`composer check` is unavailable at this stage: Composer and the Laravel application are absent. No application tests or dependency audits have run.
