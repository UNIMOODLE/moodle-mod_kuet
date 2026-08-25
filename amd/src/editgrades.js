// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Inline manual grade editing (KUETEDUCAM-73).
 *
 * @module     mod_kuet/editgrades
 * @copyright  3iPunt <https://www.tresipunt.com/>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {get_string as getString} from 'core/str';

const SELECTORS = {
    ROW: '[data-region="kuet-editgrades-row"]',
    SAVE: '.kuet-savegrade',
    MARK: '.kuet-manualmark',
    COMMENT: '.kuet-manualcomment',
    FEEDBACK: '[data-region="feedback"]',
    RESULT: '[data-region="result"]',
};

/**
 * Show a message in the row feedback area.
 *
 * @param {HTMLElement} feedback
 * @param {String} message
 * @param {Boolean} success
 */
const showFeedback = (feedback, message, success) => {
    if (!feedback) {
        return;
    }
    feedback.textContent = message;
    feedback.classList.toggle('text-success', success);
    feedback.classList.toggle('text-danger', !success);
};

/**
 * Save the manual grade of a row.
 *
 * @param {HTMLElement} button
 * @param {Number} cmid
 * @param {Number} sid
 * @param {Number} userid
 */
const saveRow = (button, cmid, sid, userid) => {
    const row = button.closest(SELECTORS.ROW);
    const markInput = row.querySelector(SELECTORS.MARK);
    const commentInput = row.querySelector(SELECTORS.COMMENT);
    const feedback = row.querySelector(SELECTORS.FEEDBACK);
    const kid = parseInt(row.dataset.kid, 10);
    const defaultmark = parseFloat(row.dataset.defaultmark);
    const mark = parseFloat(markInput.value);
    const comment = commentInput.value.trim();

    if (comment === '') {
        getString('editgrades_commentrequired', 'mod_kuet')
            .then((msg) => showFeedback(feedback, msg, false))
            .catch(Notification.exception);
        return;
    }
    if (isNaN(mark) || mark < 0 || mark > defaultmark) {
        getString('editgrades_markrange', 'mod_kuet', defaultmark)
            .then((msg) => showFeedback(feedback, msg, false))
            .catch(Notification.exception);
        return;
    }

    button.disabled = true;
    Ajax.call([{
        methodname: 'mod_kuet_setmanualgrade',
        args: {cmid: cmid, sid: sid, kid: kid, userid: userid, mark: mark, comment: comment},
    }])[0]
        .then((response) => {
            const resultCell = row.querySelector(SELECTORS.RESULT);
            if (resultCell) {
                resultCell.textContent = response.resultstr;
            }
            markInput.value = response.mark;
            return getString('editgrades_saved', 'mod_kuet');
        })
        .then((msg) => {
            showFeedback(feedback, msg, true);
            button.disabled = false;
            return msg;
        })
        .catch((error) => {
            button.disabled = false;
            Notification.exception(error);
        });
};

/**
 * Initialise the inline grade editor.
 *
 * @param {String} selector container selector
 */
export const init = (selector) => {
    const region = document.querySelector(selector);
    if (!region) {
        return;
    }
    const cmid = parseInt(region.dataset.cmid, 10);
    const sid = parseInt(region.dataset.sid, 10);
    const userid = parseInt(region.dataset.userid, 10);

    region.querySelectorAll(SELECTORS.SAVE).forEach((button) => {
        button.addEventListener('click', () => saveRow(button, cmid, sid, userid));
    });
};
