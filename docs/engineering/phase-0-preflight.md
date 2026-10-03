# Phase 0 — local tool preflight

Checked 2026-10-03 on the founder's Zorin 18.1 workstation (Ubuntu noble base).

Status: step 1 tool verification passed. No Laravel application or application dependencies have been installed. Step 1's PR must be approved before proceeding to the scaffold.

| Requirement | Verified result |
|---|---|
| PHP 8.4 | PHP 8.4.26 CLI; curl, mbstring, DOM, zip, pdo_pgsql, intl, bcmath and GD extensions available |
| Composer | 2.10.3, official installer SHA-384 verified |
| Node LTS | Node 24.21.0, npm 11.19.0; official archive SHA-256 verified; `.nvmrc` selects 24 |
| Docker / Compose | Docker client/server 29.8.2; Compose 5.6.0; rootless context and `hello-world` verified |
| PostgreSQL 17 + PostGIS | PostgreSQL 17.11 / PostGIS 3.5.7; extension and geography-distance assertion passed |
| Git | 2.43.0 |
| GitHub PR tooling | GitHub CLI 2.102.0; official release archive SHA-256 verified |
| GitHub authentication | Browser login as `heyboystudio`; keyring storage verified; Git credential helper configured |
| Repository | Private `https://github.com/heyboystudio/sortd`; supplied bootstrap documents form the initial `main` commit |

## Local setup

The founder entered administrator passwords directly in desktop terminals opened for installation. No password or GitHub credential belongs in this repository or chat.

The two workstation installers are scoped to Ubuntu 24.04 and Zorin's noble base:

```bash
sudo bash scripts/install-docker-ubuntu.sh
sudo bash scripts/install-php-ubuntu.sh
```

The Docker installer follows [Docker's signed apt repository instructions](https://docs.docker.com/engine/install/ubuntu/), stops on conflicting packages or differing Docker source files, starts the service, and runs `hello-world`. Zorin is an Ubuntu derivative, which Docker does not officially support. The PHP installer adds [Ondřej Surý's PHP PPA](https://launchpad.net/~ondrej/+archive/ubuntu/php), installs PHP 8.4 CLI/extensions and rootless Docker prerequisites.

Rootless Docker was then configured as the normal user:

```bash
dockerd-rootless-setuptool.sh install --force
docker info --format '{{json .SecurityOptions}}'
```

`--force` permits setup alongside the system service installed by apt. The selected context is `rootless`; no Docker-group membership or passwordless sudo was granted. The user service starts at login. System Docker remains installed separately.

Composer was installed into `~/.local/bin` using the [official verified installer](https://getcomposer.org/download/). Node 24.21.0 was extracted from its [official distribution](https://nodejs.org/dist/v24.21.0/) into `~/.local/share/node-v24.21.0`, with node/npm/npx links in `~/.local/bin`. GitHub CLI is also in `~/.local/bin`. `.bashrc` and `.profile` prepend this directory for future terminals. Existing sessions can use:

```bash
export PATH="$HOME/.local/bin:$PATH"
```

The previously downloaded Herd Lite PHP binary reported 8.4.1 and was not installed. Tool provenance and tradeoffs are recorded in ADR 016.

## Verification

From the project directory:

```bash
bash scripts/check-tools.sh
```

This checks PHP and extensions, Composer, Node 24, npm, Docker and Compose. It refuses remote Docker endpoints, creates a disposable PostgreSQL/PostGIS container with a random password and no network or published ports, verifies PostgreSQL major version 17 and a PostGIS geography-distance query, and removes the container and its anonymous volume on exit. It never loads application environment files or connects to an existing database. The image remains cached for subsequent runs; application database configuration belongs to step 3.

The original Debian image (`17-3.5`) passed the functional check but reported PostgreSQL 17.5 / PostGIS 3.5.2. The final smoke test passed with PostgreSQL 17.11 / PostGIS 3.5.7 using `17-3.5-alpine`, digest `sha256:894f570c0cf0664ed5576a8fd5d5bfb8fb1b19d592885b686c3a88c8bd90c41f`. The script exited successfully and removed the test container and its volume. The tag is intentionally patch-updatable; this digest records the artifact actually checked.

Bash syntax checks passed for all three scripts. Both installers refuse to run without administrator privileges. The smoke test's remote-endpoint guard was checked and refused the remote URL before attempting a connection.

`composer check` and application dependency audits do not exist yet; they are introduced in Phase 0 step 5. No application-test or CI success is claimed for this bootstrap task.
