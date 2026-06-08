# Working agreement for the Cartel plugin

## How we work — read this first, every session

The user tracks this project with ADHD in mind: juggling multiple in-flight items causes things
to get mixed up and forgotten. Because of that, **work proceeds one confirmed item at a time**:

1. Pick exactly one item (see "Where the plan lives" below for what's next).
2. Do the work / run the checklist for that item only.
3. The user manually confirms it works (usually by clicking through it in the browser).
4. Only then do we tick it off and pick the next one.

Do not start a second item "while we're at it," batch multiple fixes into one pass, or jump
ahead because something looked quick. If you notice something else that needs doing, name it
and add it to the tracker — don't just go fix it.

## Where the plan lives (read these, don't rely on conversation memory)

- **`CARTEL_SPEC.md`** (repo root, one level up from this plugin folder) — the master vision:
  what Cartel is supposed to do and be, end to end. This is the spec to check any "is X in
  scope?" question against.
- **GitHub Project board** — `Halleluyahsalako`'s private project #1
  (`PVT_kwHOAfGBq84BaC-u`, "@Halleluyahsalako's WP Plugins Development"). Holds ~15 epics, each
  with a `[ ]`/`[x]` checklist body cross-referencing spec sections, plus Status/Priority/Size
  fields. **This is the granular source of truth for what's done / in progress / backlog** —
  always check it (and update it) rather than trusting a recap from memory.
- **Smoke tests**: SMOKE-prefixed fixtures, PASS/FAIL counters, `try/finally` cleanup, deleted
  after a 100%-pass run. Smoke-tested ≠ confirmed working — browser QA is still required before
  an item is considered done (see current QA checklist in progress).

## House rules picked up from working sessions

- `dbDelta` migrations only run on `Hal_Cartel_Install::activate()` — re-trigger it after schema
  changes or new columns/tables won't appear in an existing sandbox DB.
- `gh` CLI lives at `C:\Program Files\GitHub CLI\gh.exe` (not on PATH) — invoke via full path
  through the Bash tool.
- This repo has no remote — it's local-only (and the project board is private). Keep it that
  way unless the user explicitly asks to push somewhere.
- Adversarial code review tool: `/code-review ultra` (user-triggered, billed — cannot be
  launched by Claude). Good checkpoint right before calling an epic "done."

## Frontend design direction (agreed, not yet executed)

Current storefront CSS (`public/assets/hal-cartel.css`) is flat/plain (black 1px borders, no
radius, no shadow, no responsive breakpoints) and the user has flagged it as not matching a
"modern SaaS" look. Reference for the target look:
`...SandBox/app/public/wp-content/plugins/hal-vcg-core/inc/hal-checkout.php` — teal `#0F4C5C` +
orange `#dd6b20` palette, `Poppins`/`Inter` type, card layout with `border-radius: 12-16px` and
soft shadows, rounded inputs with focus rings, `max-width` centered wrapper, responsive grid
collapsing at ~680px. This is its own confirmed milestone — don't redesign mid-QA.