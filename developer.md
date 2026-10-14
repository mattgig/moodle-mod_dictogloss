# Dictogloss — Developer guide

Architecture, data model, tests and conventions for `mod_dictogloss` (Moodle 5.x).

---

## 1. Requirements and tooling

- **Moodle 5.0+** (`version.php`: `requires = 2025041400`, `supported = [500, 503]`).
- PHP 8.1+ with the usual Moodle requirements.
- Front-end assets are built with the standard Moodle toolchain:
  - AMD modules live in `amd/src/` (ES modules) and are compiled to `amd/build/*.min.js`.
  - Rebuild after changing `amd/src/`:

    ```bash
    npx grunt amd
    ```

- Lint/validate with `moodle-plugin-ci` (`phpcs`, `phpmd`, `phpdoc`, `grunt`, `phpunit`,
  `behat`, `mustache`, `validate`).

---

## 2. Directory layout

```
mod/dictogloss/
├── activity root (entry scripts)
│   ├── view.php            Learner/teacher activity page (stages, comparison, grading)
│   ├── report.php          Teacher monitor + word-cloud moderation
│   ├── results.php         Results overview + bulk actions + export
│   ├── flow.php            Live controls (on-the-spot switches, random teams)
│   ├── grade.php           Single-user grade view
│   └── index.php           Course-level activity list
├── classes/
│   ├── activity.php        Aggregate root (lazy domain services)
│   ├── external/           AJAX external functions (see below)
│   ├── local/              Domain services and request handlers
│   ├── output/renderer.php Component renderer
│   ├── diff/word_diff.php  Tokenising, similarity and word-level diff
│   ├── overview/sentence_clusterer.php  Sentence alignment/clustering
│   ├── event/              Event classes
│   ├── form/               moodleform subclasses
│   ├── completion/custom_completion.php  Activity custom-completion rule(s)
│   ├── table/results_table.php  table_sql for the results export/table
│   ├── privacy/provider.php
│   └── navigation/views/secondary.php  Secondary navigation view
├── templates/*.mustache    11 Mustache templates
├── amd/src/ + amd/build/   AMD modules
├── backup/moodle2/         Backup/restore tasks and steps
├── db/                     access.php, install.xml, upgrade.php
├── lang/{en,de,es,fr,zh_cn}/
├── tests/                  PHPUnit + tests/behat/
├── lib.php, mod_form.php, settings.php, styles.css, version.php
```

---

## 3. Architecture

The module follows a **thin entry point → domain service → output layer** split.

### Aggregate root — `classes/activity.php`

`\mod_dictogloss\activity` wraps a course module (`$cm`), course (`$course`) and instance record
(`$instance`) and lazily builds the domain services:

```php
$activity = new activity($cm, $course, $instance);

$activity->scope();        // scope_resolver
$activity->state();        // state_service
$activity->submissions();  // submission_service
$activity->grader();       // grader
$activity->random();       // random_service
$activity->wordcloud();    // wordcloud_service
$activity->peers();        // peer_service
$activity->overview();     // overview_service
```

It also exposes convenience accessors (`get_cm()`, `get_course()`, `get_instance()`,
`get_context()`, `can_submit()`, `has_audio()`, `get_audio_files()`, `threshold()`,
`is_wordcloud_random()`, `is_peers_random()`) and the status/reveal/grading constants.

### Domain services — `classes/local/`

| Class | Responsibility |
| --- | --- |
| `scope_resolver` | Groups/groupings scoping, group filter options, group ids/names. |
| `state_service` | Reveal state, comparison, plain-text source, replay and reconstruction-open flags (`FLAGS`). |
| `submission_service` | Submission lifecycle: create/get, keywords, reconstruction, follow-up, submit/finalise/reopen/delete, grading, completion and events. |
| `grader` | Automatic similarity calculation and gradebook push. |
| `random_service` | Random partner generation/lookup and re-shuffling. |
| `wordcloud_service` | Keyword aggregation and moderation. |
| `peer_service` | Peer scope/enable checks, pull gating and shared reconstructions. |
| `overview_service` | Monitor rows, contributor list, results statistics, variants overview. |
| `status` | Status labels/helpers. |
| `nav_tabs` | Single source of truth for the module navigation tabs. |

### Request handlers — `classes/local/`

Entry scripts stay thin and delegate to small handlers:

| Handler | Used by |
| --- | --- |
| `view_actions` | `view.php` (reopen, reconstruction-ready). |
| `report_actions` | `report.php` (moderation, deletion, reopen). |
| `results_actions` | `results.php` (bulk delete/reopen). |
| `live_controls` | `flow.php` (live switches, re-shuffle). |
| `autosave_handler` | `external\save_field` (field autosave, gated by the current state). |
| `peers_handler` | `external\get_peers` (build the group-reconstructions panel). |

### Group scoping and access control

Teacher-facing pages and actions honour the activity group mode:
- `activity::can_access_user($userid)` checks that the target is enrolled (or has a contribution)
  and, in separate-groups mode without `moodle/site:accessallgroups`, that the viewer shares a
  group with them. It is called from `view.php`, `view_actions`, `report_actions`,
  `results_actions` and `grade.php`.
- `scope_resolver::get_visible_userids()` returns `null` (unrestricted) or the list of user ids in
  the teacher's allowed groups (`[]` fails closed). `report.php` and `results.php` pass it into the
  listing queries, and `get_group_filter_options()` only offers the allowed groups.
- `wordcloud_service::save_moderation()` is scoped in `report_actions` to the words currently
  visible to the teacher, so a restricted teacher cannot moderate another group's words.

Stage gates are enforced server-side in `autosave_handler` and in the `view.php` form handling:
`keywords` are frozen once reconstruction starts, `reconstruction` once it is submitted (or while
it is held), and `followup` only after submission.

### External functions (AJAX)

The browser talks to the server through Moodle external functions (`db/services.php` +
`classes/external/`), called from AMD via `core/ajax` — there are no standalone endpoint
scripts and no sesskey in the URL. `core/ajax` supplies the sesskey and serialises errors.

| Function | Class | Type | Purpose |
| --- | --- | --- | --- |
| `mod_dictogloss_save_field` | `external\save_field` | write | Autosave one field (`keywords`/`reconstruction`/`followup`/`played`). |
| `mod_dictogloss_get_wordcloud` | `external\get_wordcloud` | read | Return the re-rendered word cloud section. |
| `mod_dictogloss_get_peers` | `external\get_peers` | read | Return the group-reconstructions panel. |

Each function validates parameters, checks the module context and `mod/dictogloss:submit`, and
delegates to the same domain handler used elsewhere. `get_wordcloud`/`get_peers` currently return
rendered HTML; moving them to structured data plus client-side rendering would make them reusable
by the mobile app.

### Output layer

- **One component renderer**: `classes/output/renderer.php`
  (`\mod_dictogloss\output\renderer`). All markup is produced through `render_from_template()`
  or `render_*()` helper methods.
- **Mustache templates** in `templates/`: `stage_nav`, `wordcloud_section`, `comparison`,
  `monitor`, `variants_overview`, `peers`, `audio_player`, `contribution_nav`,
  `teacher_controls`, `results_summary`, `live_controls`.
- **`styles.css`** uses Bootstrap CSS custom properties only (no hard-coded colours) and
  namespaced selectors.

### AMD modules (`amd/src/`)

| Module | Purpose |
| --- | --- |
| `notebook` | Debounced autosave for the notes/reconstruction/follow-up text areas. |
| `wordcloud` | Refresh the word cloud section without a page reload. |
| `peers` | Pull the group reconstructions, tab switching, "Add" buttons, copy guard. |
| `confirm` | Confirmation dialogs for destructive actions. |
| `colour` | Toggle the colour-coded rewrite view. |
| `variants` | Focus/behaviour helpers for the variants overview. |
| `formdeps` | Show/hide the Random assignment section based on the settings form. |

---

## 4. Data model

| Table | Purpose | Key indexes |
| --- | --- | --- |
| `dictogloss` | One row per activity instance (settings + live flags). | PK |
| `dictogloss_submissions` | One row per learner (workflow status, texts, grade, timing). | unique (`dictogloss`, `userid`) |
| `dictogloss_keywords` | Normalised keyword tokens per submission (for aggregation). | (`dictogloss`, `groupid`, `word`), (`submissionid`), (`userid`) |
| `dictogloss_wordoverrides` | Teacher moderation of aggregated cloud entries. | unique (`dictogloss`, `word`) |
| `dictogloss_randompartners` | Random partner assignments (per feature/user). | unique (`dictogloss`, `feature`, `userid`, `partnerid`) |

Schema changes go in `db/install.xml` **and** `db/upgrade.php` (with a new `upgrade_mod_savepoint`
and a matching `$plugin->version` bump). Never rename a column in place without an upgrade step.

### Constants (`activity`)

```php
STATUS_NOTSTARTED = 0   STATUS_KEYWORDS = 1   STATUS_RECONSTRUCTION = 2
STATUS_SUBMITTED  = 3   STATUS_FINALISED = 4

REVEAL_ONSUBMIT = 1     REVEAL_MANUAL = 2
GRADE_ON_SUBMIT = 1     GRADE_AFTER_RELEASE = 0

RANDOM_FEATURE_WORDCLOUD = 'wordcloud'
RANDOM_FEATURE_PEERS     = 'peers'
```

---

## 5. Similarity, diffing and clustering

- `classes/diff/word_diff.php`
  - `tokenize()`, `sentence_tokens()`, `token_set()`, `split_sentences()`
  - `jaccard()` – Jaccard token overlap (0..1)
  - `compare()` – Dice coefficient over the word-level longest common subsequence, plus
    per-token alignment (`source_tokens` / `student_tokens`, statuses `matched` / `missing` /
    `extra`).
- `classes/overview/sentence_clusterer.php`
  - Aligns reconstructed sentences to the original (best Jaccard match above a floor) and
    clusters similar options using the tunable threshold. Falls back to positional alignment
    when there is no source text.

---

## 6. Events

Defined in `classes/event/`:

`course_module_viewed`, `course_module_instance_list_viewed`, `peers_viewed`,
`words_moderated`, and the submission events extending `submission_base`
(`submission_created`, `submission_updated`, `submission_finalised`, `submission_reopened`,
`submission_deleted`, `submission_graded`, `grade_overridden`, `keywords_saved`).

`submission_base` sets `objecttable = 'dictogloss_submissions'` and implements
`get_objectid_mapping()` / `get_other_mapping()` for log restore.

---

## 7. Capabilities (`db/access.php`)

| Capability | Type | Default archetypes |
| --- | --- | --- |
| `mod/dictogloss:addinstance` | write | editingteacher, manager |
| `mod/dictogloss:view` | read | guest, student, teacher, editingteacher, manager |
| `mod/dictogloss:submit` | write | student |
| `mod/dictogloss:grade` | write | teacher, editingteacher, manager |
| `mod/dictogloss:manage` | write | teacher, editingteacher, manager |
| `mod/dictogloss:viewreports` | read | teacher, editingteacher, manager |

Destructive report/result actions require `manage`; merely viewing reports requires
`viewreports`.

---

## 8. Backup / restore

`backup/moodle2/` contains the standard four files. Field lists in
`backup_dictogloss_stepslib.php` must match the schema; the restore steps guard optional/legacy
fields and default the `NOTNULL` columns. When adding a column, add it to the backup element list
too (`tests` can assert this).

---

## 9. Privacy

`classes/privacy/provider.php` implements the metadata, userlist and plugin providers. It covers
`dictogloss_submissions`, `dictogloss_keywords`, `dictogloss_wordoverrides` (including
`moderatedby`) and `dictogloss_randompartners` (both sides of a pairing) for context discovery,
export and deletion.

---

## 10. Tests

- **PHPUnit** (`tests/`)
  - `lib_test.php` – integration tests for the workflow, word cloud, random assignment, etc.
  - `diff_test.php` – pure unit tests for `word_diff` and the clusterer.
  - `services_test.php` – reveal modes, plain-source conversion, grader edge cases,
    finalise/reopen/delete, visible grade, clusterer "extra" bucket.
  - `privacy/provider_test.php` – context discovery, export and deletion.
  - `generator/lib.php` – the data generator (`create_module('dictogloss', [...])`).
- **Behat** (`tests/behat/`)
  - `behat_mod_dictogloss.php` – the `the following dictogloss activities exist` step.
  - `dictogloss.feature` – end-to-end scenarios (notes → reconstruct → reveal; manual reveal;
    held reconstruction; teacher opens it live).

Run from the Moodle root (after initialising the test environment with
`php admin/tool/phpunit/cli/init.php`):

```bash
# PHPUnit
vendor/bin/phpunit --testsuite mod_dictogloss_testsuite

# Behat
vendor/bin/behat --config behat.yml --tags @mod_dictogloss
```

---

## 11. Conventions

- GPL header, `@package mod_dictogloss`, `@copyright`, `@license` on every file.
- `defined('MOODLE_INTERNAL') || die();` in every non-entry file (entry scripts omit it).
- No closing `?>` tag; LF line endings.
- Namespaced classes; prefer `\core\context\module` / `\core\context\course`.
- Parameterised SQL only (`$DB` API with named/`?` params); never concatenate user input.
- Escape output (`s()`, `format_string()`, `format_text()`, escaped Mustache `{{ }}`).
- State-changing actions validate capability **and** `require_sesskey()`; destructive actions
  require a write capability and POST.
- Prefer targeted column updates (`update_record()` with a partial object / `set_field()`) over
  writing whole rows, to avoid lost updates.
- Keep `get_string()` in the output/entry layer; domain services take raw values.

### Adding a setting end-to-end

1. Add the column to `db/install.xml` and an `upgrade_mod_savepoint` step to `db/upgrade.php`.
2. Add the form element in `mod_form.php` (and `hideIf` dependencies if needed).
3. Add the string to **all** language files (keep key parity).
4. Read it via `$activity->get_instance()->yourfield` in the relevant service.
5. Add it to `backup_dictogloss_stepslib.php` and the generator defaults in
   `tests/generator/lib.php`.
6. Bump `$plugin->version`.

### Adding an event

Create a class in `classes/event/` (extend `submission_base` for submission events, or
`core\event\base`), add its string, trigger it from the relevant service, and provide
`get_objectid_mapping()` where applicable.
