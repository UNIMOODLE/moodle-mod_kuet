# Changelog

## v1.0.1-MOODLE_502
- Fixed the mark of a match question, which was computed over the number of
  answers instead of the number of elements to match. Any question with an
  answer that has no element - a distractor, which is ordinary - was marked
  down: a fully correct answer to a question with three elements and one
  distractor scored 0.75 while the participant was told it was correct. The
  same fix scores a question where several elements share one answer, which
  qtype_match supports and Kuet was reporting as almost entirely wrong.
- Fixed the list of correct answers of a match question in the session report,
  which paired each element with the answer that carries the element's own key
  instead of with its real answer. An element sharing its answer with another
  element was listed with nothing after the arrow.
- Two elements of a match question can now be joined to the same answer, which
  qtype_match supports by merging the answers that have the same text. Joining a
  second element to an answer used to unjoin the first, so such a question could
  not be answered at all; and the answer pointed at as correct, the green and red
  feedback of each element and the mark now agree with each other and with core.
  On the mobile layout, sending an element back to "Choose..." no longer throws a
  JavaScript error.
- Fixed choosing a question bank for a session, which did nothing at all. Both
  ways of choosing one - the links to the banks of the course and the search box
  over the shared banks of the site - raised a JavaScript error before reaching
  the server, so the panel never changed bank.
- Fixed the list of questions of a bank, which painted every row without its
  name: the name was not declared in the return structure of the external
  function, so it was stripped on the way to the browser.
- Fixed the general feedback of a calculated question, which reached the
  participant with its wildcards unresolved, as "{a} + {b}" instead of the
  values of the variant they were asked.
- Fixed the final ranking, which showed an empty box instead of the score of
  every participant who scored zero, on the podium and on the list below it.
- Fixed the right answer offered after a numerical or a calculated question,
  which listed every answer of the question - the ones that only carry a
  specific feedback, and the catch-all "*" - so a wrong answer was shown as if
  it had been right.
- The right answer of a short answer, a numerical and a calculated question is
  now shown in a white box with the icon that marks a correct answer, under the
  input, instead of as fluorescent green text.
- Fixed the feedback shown after a numerical or a calculated question with
  units, which could be the feedback of an answer other than the one the mark
  came from: a response graded wrong was told it was right.
- Fixed the podium of the final ranking, whose picture, position and name were
  drawn out of their column and boxed in a white border under some themes. The
  wrapper no longer uses the class name "userimage", which is generic enough for
  a theme to style it for its own widgets.
- A copy of the activity on its own now carries the questions of its sessions.
  Since Moodle 5 a question bank is an activity of its own, shared between
  courses, so the bank a session takes its questions from is not inside the
  activity being copied and the package arrived with no questions at all: the
  restored session pointed at question ids of the site the copy was made in,
  which in another site are missing or belong to other questions. Every question
  of a session is now referenced in the bank entry it comes from, the way core
  does it, and the questions already in a session get their reference on upgrade.
  Restored where that bank is still reachable, which is what duplicating an
  activity does, the session goes on sharing the same questions; restored
  anywhere else it takes the copies that travelled in the package, which core
  puts in a bank of the target course.
- The question bank chooser of a session no longer offers the activity itself as a
  bank. It was rendering the chooser of core, which opens with a link to the
  question bank of the activity it belongs to; a Kuet has no bank of its own, so
  that link asked the server for a question bank module that is not one and the
  panel died with a database error. It also read "quiz" in a plugin that is not
  one. The plugin now renders its own chooser, with the shared banks of the
  course, the recently used ones and the search over every shared bank of the
  site.
- The question bank panel now opens on a category that holds questions instead of
  on the top one, which by convention holds none: the bank used to open showing
  whatever its subcategories happened to have.
- A match question sent with nothing joined at all is now recorded as unanswered
  instead of as a wrong answer. It scores the same, but the session report tells
  the two apart, and every other type of question already did.
- On the mobile layout of a match question, joining two elements to the same
  choice no longer repaints the choice in every element's dropdown: each one
  keeps the colour of its own link, and undoing a link only clears its own.
- Fixed the question bank chooser of a session on Moodle 5.2, which listed every
  bank as an empty entry that could not be clicked, so no bank could be chosen
  at all. Moodle 5.2 hands the banks back as objects that only build their name
  and their module id when asked, where earlier versions handed them back
  already built.
- The button that sends the answer to a question is a button again, sized to its
  text and aligned to the right, instead of a band across the whole width of the
  question. Bootstrap 5 gives every direct child of a row the full width, which
  Bootstrap 4 did not, and the button had been left with the margin utility of
  Bootstrap 4, which no longer exists. The same applies to the button that
  leaves a question report.
- A question with no feedback no longer shows an empty feedback box after it is
  answered. The server already said whether there was any feedback to show and
  the box was opened regardless, so every question without one ended with an
  empty panel titled "Feedback".
- Fixed a multiple choice question with no feedback still reporting that it had
  some. The line break that separates one answer's feedback from the next was
  added after every answer, including the ones carrying no feedback, which left
  a break behind as if it were feedback and kept the empty box open on this one
  type.
- The feedback box no longer draws the line that opens a half of it when that
  half is empty. The box holds two of them - the feedback of the question above
  and the feedback of the answer below - and a question carrying only one of the
  two was shown a line with nothing under it.
- Fixed the lower half of the feedback box of a short answer question, which
  repeated the general feedback that the upper half already shows instead of
  carrying the feedback of the answer the response fell on. A teacher writing
  feedback on an answer of a short answer question never saw it reach the
  participant, and a question with general feedback said the same thing twice.
- The connection test page no longer shows two links for starting or stopping
  the local WebSocket server. One of them came from an older wording of the
  status message that still ships in the published language packs, which carry a
  link of their own inside the text and override the one in the plugin. That
  link had no session key, so it only ever answered with an invalid session key
  error.
- Fixed a true or false question with no feedback still reporting that it had
  some, which kept an empty feedback box open on it. The break that followed the
  feedback of the answer was added whether that answer carried one or not, and a
  lone break reads as feedback. Same as the multiple choice fix of this release.

## v1.0.0-MOODLE_502
- Moodle 5 support: declares Moodle 5.0 to 5.2 and drops the `incompatible`
  flag that used to prevent the installation on Moodle 5.
- The question bank used to build a session can now be chosen: the bank module
  is selectable, defaults to the one of the course, and its questions can be
  searched by name.
- Security: `mod_kuet_getquestionbank` and `mod_kuet_selectquestionscategory`
  validate both the activity context and the question bank context, require
  `mod/kuet:managesessions`, check that the kuet and the session really belong
  to the given course module, and filter the listed questions one by one with
  `question_has_capability_on()`. A teacher holding only
  `moodle/question:usemine` now enters the bank and sees just their own
  questions, instead of seeing every question with no check at all.
- Fixed the restore of user responses: the stored answer ids were never
  remapped to the ids the restore creates, so in a restored course every
  correct multichoice or true/false answer was reported as failed.
- Fixed changing the question category in the session panel while questions were
  already selected, which failed with "Invalid parameter value detected" and left
  the category unchanged.
- Fixed the plugin uninstall, which aborted with a database error and left
  orphaned grade items in the gradebook of the affected courses. `db/uninstall.php`
  dropped the plugin tables before core had cleaned up the gradebook, and core
  drops them itself right afterwards.
- Fixed the question numbering of the race mode on Moodle 5.2, where
  `core\persistent::get_records()` returns the records indexed by id.
- Fixed a fatal error on Moodle 5.0 caused by `FEATURE_MOD_OTHERPURPOSE`, a
  constant that does not exist before Moodle 5.1.
- Fixed the answer shuffling and the automatic start of a session, and question
  fractions are compared allowing for their decimal representations.
- WebSocket server: bigger read buffer, and correct handling of the members of
  a group and of the client disconnections.
- Styles of the session form and of the category selector adapted to Moodle 5.
- Includes everything released in v1.0.0-MOODLE_405.

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
