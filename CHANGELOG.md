# Changelog

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
