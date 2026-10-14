# Dictogloss (`mod_dictogloss`)

A Moodle 5.x activity module implementing the collaborative **Dictogloss** language-learning
method. Learners listen to a short text, note key words, reconstruct the text (individually or
within a group), and then compare their reconstruction against the original.

> This is the top-level README. For task-oriented guidance see **[teacher.md](teacher.md)**;
> for architecture and contribution notes see **[developer.md](developer.md)**.

## The method

1. **Introduction** – the teacher explains the activity.
2. **Preparation** – a short text and optional audio recording.
3. **First listening** – learners listen and take keyword notes.
4. **Second listening** – learners extend their notes.
5. **Reconstruction** – learners rebuild the text from their own (or their group's) notes.
6. **Comparison** – the reconstruction is compared with the original text.
7. **Follow-up (optional)** – learners rewrite the text in their own words.

## Features

- **Teacher-configured content**: original text, instructions and optional audio recording.
- **Staged student experience** (Listen → Keyword notes → Reconstruction → Comparison →
  Rewriting) with keyword notes and a reconstruction editor.
- **Word cloud** built from keyword contributions, scoped to the learner's group, grouping, own
  keywords, or a random team. Own words are highlighted, and an accessible list fallback is
  provided. When the activity is restricted to a **Grouping** (common module settings), only
  groups in that grouping are used.
- **Teacher monitor** showing every contribution at any stage, including draft work.
- **Group reconstructions**: learners can pull peers' reconstructions (scoped to group, grouping,
  class or random partners) and compare them with their own. Sentence cards and full texts are
  collapsible.
- **Random assignment** for the word cloud and/or peer sharing, drawing partners from the whole
  class, a grouping or a specific group, with a configurable team size and manual re-shuffle. An
  optional shared pool lets both features use the same source.
- **Reconstruction variants overview** that aligns reconstructed sentences to the original and
  clusters similar options (word-overlap / Jaccard, tunable threshold).
- **Word cloud moderation**: hide, rename or delete misspelled or nonsensical keyword entries,
  individually or in bulk.
- **Live controls** (on-the-spot): open the reconstruction, reveal the original text, allow
  listening again, and open the group reconstructions — applied to all learners immediately.
- **Comparison view**: server-rendered, side-by-side word-level diff with a text **and**
  colour legend.
- **Simple grading**: an automatic similarity-based grade pushed to the gradebook, which the
  teacher can override. Grades are hidden until the comparison is released by default.
- **Results page** with summary tiles, group/team filtering and spreadsheet export.
- Privacy API, full backup/restore, events, completion and course-reset support.
- Translations for **English, German, Spanish, French and Simplified Chinese**.

## Installation

1. Copy this directory to `mod/dictogloss` under your Moodle code root (in Moodle 5.x with the
   new directory layout this is `public/mod/dictogloss`).
2. Visit **Site administration → Notifications** to install/upgrade the plugin.
3. Add a **Dictogloss** activity to a course.

## Requirements

- Moodle 5.0 or later (`version.php`: `requires = 2025041400`, `supported = [500, 503]`).
- No third-party JavaScript libraries; the word cloud is rendered server-side.

## Documentation

| Document | Audience |
| --- | --- |
| [teacher.md](teacher.md) | Teachers: creating, running and grading a dictogloss. |
| [developer.md](developer.md) | Developers: architecture, data model, tests and conventions. |

## License

GNU GPL v3 or later.
