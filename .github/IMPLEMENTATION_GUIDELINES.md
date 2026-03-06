# Implementation Guidelines for "delete-folder-with-options"

Purpose
- Provide a short, actionable guideline the agent will follow when implementing the "delete folder with options" feature.
- Centralize default choices, branch/commit conventions, and links to repository instructions so the implementation remains consistent.

Defaults for this work
- Conflict strategy (default): `suffix` (append `-1`, `-2`, ... on name collisions).
- Physical deletion behavior: asynchronous in production using Symfony Messenger (ENV: `ASYNC_FILE_DELETION=true`).

Workflow (TDD & Commit conventions)
1. Create a dedicated branch: `git checkout -b feat/delete-folder-with-options`.
2. Work in small atomic steps: for each change: write a failing test (RED), implement minimal code to pass (GREEN), refactor if needed, run tests, commit.
3. Commit messages must follow `.github/CONVENTION_DE_COMMIT.md` (emoji + type(scope): message). Examples:
   - `✅ test(FolderDeletion): add red test for delete recursive`
   - `✨ feat(FolderDeletion): implement deleteContents flag in FolderProcessor`
4. Do not push or open PRs without owner's instruction; the owner prefers to open PRs but can delegate if asked.

Branch/PR policy
- Work on feature branch; keep commits logical and small.
- Rebase or squash as needed before merge; do not merge to `main` without review.

Testing
- Use PHPUnit for unit and integration tests. Add tests covering:
  - unauthorized access (ownership check)
  - delete recursive (files and folders removed)
  - preserve contents → files moved to "Uploads" and name-conflict handling
  - failure/rollback (simulate storage deletion failure)
- Run `vendor/bin/phpunit` locally; ensure tests pass before committing final changes.

Async deletion details
- Implement physical file deletion via Messenger worker when `ASYNC_FILE_DELETION=true`.
- The FolderDeletionService should queue deletion jobs for files; tests may stub the message bus.

Secrets & env
- Never commit secrets. Use `.env` for generic defaults only and `.env.local` for sensitive values.

Where to update progress
- Update `.github/avancement.md` with a short line when a task is complete.
- The high-level plan is in `.github/plan.md` and the session plan (copy) in the Copilot session state.

Helpful links (repository files)
- .github/copilot-instructions.md
- .github/CONVENTION_DE_COMMIT.md
- .github/plan.md
- .github/avancement.md
- src/State/FolderProcessor.php
- src/Repository/FolderRepository.php
- src/Service/DefaultFolderService.php

Notes for the agent
- Refer to these guidelines before each commit to avoid drift.
- Ask clarifying questions via `ask_user` tool if any behavior is ambiguous.

Created-by: Copilot CLI
