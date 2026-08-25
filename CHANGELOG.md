# Changelog

## v2.0.0-MOODLE_500 (2026-09-16)
- Moodle 5 question bank support: session questions now use
  `question_references`, question bank entries and Moodle question versions
  instead of relying only on a fixed `question.id`.
- Existing KUET questions are migrated idempotently to fixed-version
  references. Session history, responses, grades, order and per-question
  settings are preserved.
- Teachers can select supported questions from shared question bank activities
  in the current course and from other banks they are authorised to use. The
  server validates the source bank, question ownership, status, type and
  `useall`/`usemine` permissions independently of the destination activity.
- The session question selector uses Moodle's question bank filters and
  pagination in an AJAX modal, supports multi-selection, and rejects forged,
  draft or unsupported questions atomically.
- Added fixed-version and latest-ready policies. Dynamic references follow the
  newest ready version while a session is editable, require review after a
  version change, and are frozen when the session starts.
- Added a version selector to the KUET question settings page when an entry has
  multiple ready versions. Selecting another version updates the reference and
  effective question transactionally and invalidates version-dependent KUET
  configuration for review.
- Added permission-aware links to edit the source question in Moodle's question
  bank from session setup step 2 and from the question bank selection dialog.
  The editor returns to the KUET session after saving or cancelling.
- Question mutations are serialised and sessions become immutable after they
  start or contain attempt data, preventing concurrent edits from changing an
  active assessment.
- Backup, restore, session duplication and deletion now preserve or clean up
  KUET question references correctly.
- Fixed the question bank modal dependency for Moodle 5 by removing the missing
  `core/modal_registry` module.
- Fixed the teacher control panel Jump action so selecting a question moves the
  running session to that exact question.
- Added migration diagnostics and PHPUnit coverage for permissions, version
  resolution, locking, concurrent mutations, backup/restore, the version
  selector and question bank UI. The KUET integration suite passes on Moodle
  5.0, 5.1 and 5.2.

## v1.0.0-MOODLE_405
- Security: every web service function now validates the context and the
  required capability, and checks session membership. Callers without the
  proper permission no longer get a response, which is a breaking change of
  the external API contract.
- Security: starting or stopping the local WebSocket server requires a valid
  sesskey.
- Privacy API: full provider for the plugin data (answers, progress, grades and
  manually overridden marks), including the external WebSocket server.
- A response grade can be overridden manually after the session has ended,
  together with a comment justifying it.
- Sessions are no longer mandatory, with new plugin settings.
- New maximum session grade setting, and grades are recalculated when a session
  is deleted.
- Multisite compatibility.
- Students joining a session already in progress wait in the waiting room until
  the next question.
- Moodle 4.3 support fixed: the renderer no longer relies on output classes
  that do not exist in 4.3, and question fractions are compared numerically.
- Language parity across en, es, ca, eu and gl.
- Moodle coding standard applied, LF line endings, LICENSE file, and packaging
  rules so the distribution ZIP carries no development files.
- Declares support for Moodle 4.3 to 4.5. Not compatible with Moodle 5.0.

## v0.0.3-MOODLE405
- Moodle 4.5 compatibility.
- Websocket server upgrade to improve stability.
- Websocket server managing (local) and checking status.
