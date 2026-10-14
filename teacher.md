# Dictogloss — Teacher guide

This guide explains how to create, run, monitor and grade a **Dictogloss** activity in Moodle.

---

## 1. What is a dictogloss?

In a dictogloss, learners hear a short text, note only key words, and then work together (or
alone) to reconstruct the text from those notes. The reconstruction is finally compared with
the original. It develops listening, note-taking and collaborative writing.

The activity moves learners through five stages:

**Listening → Keyword notes → Reconstruction → Comparison → Rewriting** (the last one is
optional).

---

## 2. Creating the activity

Add a **Dictogloss** activity to your course and configure the settings below. The settings are
grouped exactly as they appear in the form.

### General

| Setting | What it does |
| --- | --- |
| **Name** | The name shown in the course and everywhere else. |
| **Description** | Standard Moodle activity description (shown to learners if the module is set to show it). |

### Content

| Setting | What it does |
| --- | --- |
| **Instructions** | Text shown to learners at the top of the activity. Use it to explain the task. |
| **Text to be dictated** | The original text. It is **hidden from learners** until it is revealed (see *How the original text is revealed*). **Required.** |
| **Audio recordings** | Optional. Upload one or more audio files. If you do not, the activity tells learners that you will read the text aloud. |

> The original text is stored as rich text; embedded media is hidden together with the text until
> it is revealed.

### Behaviour

| Setting | Default | What it does |
| --- | --- | --- |
| **Enable keyword note-taking** | Yes | When off, learners skip the notes stage and go straight to reconstruction; the word cloud is disabled. |
| **Word cloud source** | The student's group | Whose keywords are aggregated in the word cloud: *The student's group*, *The student's grouping*, *Only the student's own keywords*, or *Randomly assigned students*. Hidden when note-taking is off. |
| **Listening again is a separate step** | No | If on (and audio is uploaded), after handing in their notes learners can listen again and then start the reconstruction, after which the audio is no longer available. Good for independent work. |
| **Hold the reconstruction until the teacher opens it** | No | If on, learners can review the word cloud but cannot open the reconstruction editor until you open it from the **Live controls**. |
| **Allow listening again during reconstruction** | No | If on, learners can replay the audio while reconstructing. Hidden when the separate listen-again step is used. |
| **Sentence clustering threshold (%)** | 70 | How similar two reconstructed sentences must be to be grouped as the same option in the **variants overview**. The site default is set by an administrator. |
| **How the original text is revealed** | Automatically after the reconstruction is submitted | *Automatically after the reconstruction is submitted* releases the source as soon as a learner hands in a reconstruction; *The teacher reveals it manually* waits for you (see **Live controls**). |
| **Enable follow-up rewrite** | No | Adds the optional Rewriting stage after the comparison. |

### Group reconstructions

Lets learners pull **each other's** reconstructions and compare them with their own.

| Setting | Default | What it does |
| --- | --- | --- |
| **Share group reconstructions** | No | Scope: *No*, *The student's group*, *The student's grouping*, *Whole class* or *Randomly assigned peers*. |
| **Which reconstructions to share** | Drafts and submitted | *Drafts and submitted*, *Drafts only*, *Submitted only* or *None*. |
| **Minimum own words before pulling** | 20 | A learner must first write this many words of their own reconstruction before they can pull peers' work. |
| **Wait between pulls (seconds)** | 60 | Cooldown between pulls. |
| **Show student names** | No | If off, peers are shown anonymously. |
| **Allow copying peers' text** | Yes | If off, learners cannot select or copy shared text (best effort), but the **Add** buttons still insert sentences. |

The whole feature is opened on the spot from **Live controls → Open group reconstructions**.

### Random assignment

Only relevant when **Word cloud source** and/or **Share group reconstructions** is set to
*Randomly assigned students / Randomly assigned peers*. Partners are chosen randomly and
independently (two learners need not list each other), and teams may overlap.

| Setting | Default | What it does |
| --- | --- | --- |
| **Use the same random source for both** | No | Makes the word cloud and peer sharing draw from one shared pool, updated by a single **Re-shuffle** button. |
| **Random source** | Whole class | *Whole class*, *A grouping* or *A specific group*. |
| **Grouping / Group** | — | Shown depending on the source chosen. |
| **Students per group** | 3 (cloud) / 2 (peers) | Team size, including the learner. Must be at least 2. |

The generated teams are listed on the **Live controls** page; use **Re-shuffle teams** to
regenerate them (the current assignment is replaced).

### Similarity grading

| Setting | Default | What it does |
| --- | --- | --- |
| **Automatic similarity grading** | Yes | Calculates a score from the similarity between the reconstruction and the original text. You can always override it. |
| **When to show grades** | After the teacher releases the comparison | *After the teacher releases the comparison* hides the grade until the source is revealed; *Immediately after submitting* shows it as soon as the learner hands in. |

### Common module settings and completion

The standard Moodle **Common module settings**, **Grade** and **Completion** sections follow.
- Use **Group mode** / **Grouping** to scope the activity. When a grouping is set, the word
  cloud and peer features only use groups from that grouping.
- In a course using **separate groups**, a teacher who does not have
  `moodle/site:accessallgroups` only sees, grades and moderates the contributions of their own
  groups (in the monitor, results, comparison and word-cloud moderation views).
- **Grade → Maximum grade** enables the gradebook item.
- **Completion → Require a handed-in contribution** completes the activity once a learner has
  **handed in** their contribution (not merely submitted the first draft when a follow-up rewrite
  is enabled).

---

## 3. Running the activity

### The learner flow

1. **Listening** – learners play the audio (or you read the text).
2. **Keyword notes** – they type key words and phrases.
3. **Reconstruction** – they rebuild the text. With **Hold the reconstruction** on, this is
   locked until you open it.
4. **Comparison** – once the source is revealed, they see a side-by-side word-level difference
   with a colour **and** text legend (Matched / Missing / Extra).
5. **Rewriting** (optional) – they rewrite the text in their own words and hand it in.

Drafts are autosaved as they type; a normal form submission also works if JavaScript is disabled.

Once learners move on from the listening stage, the recording is locked (unless *Allow listening
again during reconstruction* is on or you open listening live from **Live controls**). This is
enforced on the server, so it also applies if a learner tries to reach the recording again with
the browser's back button or another browser window.

### Live controls

Teachers with the *Manage* capability see a **Live controls** tab (when at least one control
applies). Every switch takes effect **immediately for all learners**:

1. **Allow listening again** – let everyone replay the audio.
2. **Open the reconstruction** – release the reconstruction editor.
3. **Open group reconstructions** – let learners pull each other's work.
4. **Show the original text** – reveal the source for comparison.

The page also shows the **random teams** (if random assignment is used) and a **Re-shuffle**
action.

---

## 4. Monitoring and moderation

**Monitor contributions** (the *Manage* capability) lists every learner and their current
status, keywords, reconstruction, automatic grade and grade. From here you can:

- open an individual contribution to read the full text and grade it;
- **delete** individual contributions;
- **bulk delete** several contributions by selecting the checkboxes.

The monitor has a group filter and a **View contribution** link that opens the learner's page
with previous/next navigation between learners.

Below the monitor, the **reconstruction options** overview aligns reconstructed sentences to the
original text and groups similar sentences as the same option, so you can quickly see which
phrasings the class produced. The **Sentence clustering threshold** (activity setting) controls
how similar two sentences must be to be grouped together.

### Word cloud moderation

When a word cloud is used, keywords are aggregated. From the monitor you can **hide**,
**rename** or **delete** entries (for example, to remove misspellings), individually or in bulk.
Learners see the moderated cloud on refresh.

---

## 5. Grading

- If **Automatic similarity grading** is on, the activity computes a score (0–100% similarity,
  scaled to the maximum grade) each time a reconstruction is submitted.
- A teacher grade **overrides** the automatic grade and is preserved until you change it.
- With **When to show grades → After the teacher releases the comparison**, learners do not see
  the grade until the source is revealed (automatically on submit, or when you reveal it).
- In an individual learner's contribution view you can save a grade (and feedback) or **Save
  grade and next** to move straight to the next learner.

---

## 6. Results

The **Results** tab (the *View reports* capability) shows summary tiles — *Enrolled*,
*In progress*, *Submitted*, *Handed in* and *Average grade* — and a filterable table of all
contributions. The table can be downloaded as a spreadsheet.

You can also **reopen** handed-in contributions (individually or in bulk) so learners can revise
and hand in again.

---

## 7. Recipes

- **Solo reconstruction** – leave *Share group reconstructions* off; use *Hold the reconstruction*
  if you want a whole-class start; reveal the source automatically or manually.
- **Pair/group collaboration** – set *Share group reconstructions* to *The student's group* (or
  *Randomly assigned peers* with a team size of 2), pick which reconstructions to share, and open
  the feature from **Live controls** when the class is ready.
- **Jigsaw / mixed-ability teams** – set *Word cloud source* to *Randomly assigned students* and
  enable *Use the same random source for both* so the cloud and peers use one pool.
- **Exam-style, no collaboration** – turn off note-taking (learners reconstruct directly), leave
  peers off, and reveal the source manually at the end.

---

## 8. Administrator setting

**Site administration → Plugins → Activity modules → Dictogloss** provides the site default for
the **sentence clustering threshold** used by the variants overview.

---

## 9. Troubleshooting

- **“This activity is not available at the moment.”** appears to a learner on the comparison
  block when the source has not been revealed yet — reveal it (or submit a reconstruction if the
  mode is automatic).
- **The word cloud is empty** — keyword note-taking may be off, or nobody has contributed notes
  yet. Use the **Refresh word cloud** button after notes are saved.
- **Learners cannot pull peers' reconstructions** — the feature may be closed, they may not have
  written the minimum number of own words, or the cooldown may still be running; open it from
  **Live controls**.
- **A handed-in contribution needs changing** — use **Reopen for revision** (from the monitor or
  the learner's page).
