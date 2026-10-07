# freeunit4drupal

freeunit4drupal is a planned local development environment for Drupal that needs no Docker.
It is built on the FreeUnit application server (https://github.com/freeunitorg/freeunit),
a community fork of NGINX Unit that runs PHP in its own worker processes.

Status: research and plan, 2026-10-07. There is no code yet.

## What it would be

- One Go binary that runs one FreeUnit instance per user for all Drupal projects.
- Matched PHP 8.3, 8.4 and 8.5 builds, so the CLI and the web server use the same PHP.
- Each project at `<project>.localhost`, with HTTPS from a local certificate authority.
- MariaDB or MySQL from the host, or SQLite, and mail capture with Mailpit.
- Live import of `.ddev/` configuration, so a team can use it beside DDEV.
- Linux and macOS (Apple Silicon) first. Windows users run the Linux build inside WSL2
  until FreeUnit runs natively on Windows.

The recommendation is a conditional go. The conditions are in section 1 of docs/plan.md.

## Layout

- `docs/research-report.md`: the research report. It answers whether to build the tool,
  what it should be, how and in what order, and what FreeUnit must change first.
  Section 6 resolves the places where the research notes disagree.
- `docs/plan.md`: the project plan. It contains the decisions D1 to D18, the architecture,
  the Phase 0 experiments E1 to E17, the milestones M0 to M11, the FreeUnit changes,
  the contribution rules, the risks and the open decisions for the maintainer.
- `research/*.md`: the five research notes of 2026-10-07, with their sources.
  - `research/ddev-incumbent.md`: DDEV, the incumbent.
  - `research/competitors-and-native-precedents.md`: other local tools, living and dead.
  - `research/drupal-developer-requirements.md`: what Drupal 10, 11 and 12 need, and how
    a new tool gets adopted.
  - `research/freeunit-capabilities.md`: what FreeUnit 1.37 can and cannot do.
  - `research/tool-architecture-options.md`: the design options and their evidence.

## How to read it

1. Read the first paragraph of docs/research-report.md for the answer.
2. Read sections 1 and 2 of docs/plan.md for the recommendation, the goals and the users.
3. Read section 3 of docs/plan.md for the decisions, and section 5 for the experiments
   that can still change them.
4. Go to research/ for the sources behind any claim. Every claim there carries a URL,
   a date, or a code anchor at a named commit.

## Rules for contributions

The full rules are in section 8 of docs/plan.md. In short:

- Write plain technical English: short sentences, one idea each, exact names and numbers,
  and full URLs for issues and pull requests.
- Anchor every claim with a URL and the date it was read, or with `path:line@<commit>`.
- Mark each finding VERIFIED, MEASURED or UNKNOWN. Mark unverified claims as unverified.
- Never publish a speed or resource number without its baseline in the same sentence.
  This project sells simplicity, parity with FreeUnit in production, no Docker and
  measured resource use. It never sells speed.
- Publish nothing private: no host names, home-directory paths, credentials or tokens.
- Send FreeUnit problems upstream to https://github.com/freeunitorg/freeunit.
- Change a decision only in docs/plan.md, with the date and the reason.

## License

Apache-2.0. See LICENSE.
