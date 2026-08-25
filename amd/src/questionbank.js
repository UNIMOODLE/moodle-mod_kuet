// This file is part of Moodle - http://moodle.org/
// Moodle is free software: you can redistribute it and/or modify it under the
// terms of the GNU General Public License as published by the Free Software
// Foundation, either version 3 of the License, or any later version.
/**
 * Core question bank selection for KUET. Uses the same APIs on Moodle 5.0–5.2.
 * @module mod_kuet/questionbank
 * @copyright 2026 KUET contributors
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Modal from 'core/modal';
import ModalEvents from 'core/modal_events';
import Fragment from 'core/fragment';
import Ajax from 'core/ajax';
import Templates from 'core/templates';
import Notification from 'core/notification';
import {getString} from 'core/str';

/**
 * Initialise the bank modal for one session.
 * @param {Number} contextid KUET module context
 * @param {Number} cmid KUET course module ID
 * @param {Number} kuetid KUET instance ID
 * @param {Number} sessionid Session being edited
 */
export const init = (contextid, cmid, kuetid, sessionid) => {
    const trigger = document.querySelector('[data-action="open-kuet-bank"]');
    const picker = document.querySelector('[data-region="kuet-bank-picker"]');
    if (!trigger || !picker || trigger.dataset.initialised) {
        return;
    }
    trigger.dataset.initialised = 'true';
    trigger.addEventListener('click', async() => {
        trigger.disabled = true;
        try {
            const modal = await Modal.create({title: await getString('addfromquestionbank', 'mod_kuet'), large: true});
            modal.setBody(picker.innerHTML);
            const root = modal.getRoot()[0];
            const content = root.querySelector('[data-region="kuet-bank-content"]');
            const bank = root.querySelector('[data-action="select-kuet-bank"]');
            let generation = 0;
            let busy = false;
            let retry = null;
            const loadBank = async() => {
                const current = ++generation;
                content.setAttribute('aria-busy', 'true');
                try {
                    const result = await new Promise((resolve, reject) => {
                        Fragment.loadFragment('mod_kuet', 'kuet_question_bank', contextid, {
                            kuetcmid: cmid, sessionid, bankcmid: Number(bank.value),
                        }).then((html, js) => resolve({html, js})).catch(reject);
                    });
                    if (current === generation) {
                        Templates.replaceNodeContents(content, result.html, result.js);
                    }
                } catch (error) {
                    Notification.exception(error);
                } finally {
                    content.removeAttribute('aria-busy');
                }
            };
            const add = async(ids) => {
                if (busy || !ids.length) {
                    return;
                }
                busy = true;
                bank.disabled = true;
                const questions = ids.map(questionid => ({questionid, sessionid, kuetid,
                    uselatest: root.querySelector('[data-action="kuet-latest-version"]').checked}));
                const fingerprint = JSON.stringify(questions);
                if (!retry || retry.fingerprint !== fingerprint) {
                    retry = {fingerprint, token: `${Date.now()}-${Math.random().toString(36).slice(2)}`};
                }
                try {
                    await Ajax.call([{methodname: 'mod_kuet_addquestions', args: {questions, requestid: retry.token}}])[0];
                    const data = await Ajax.call([{methodname: 'mod_kuet_sessionquestions',
                        args: {kuetid, cmid, sid: sessionid}}])[0];
                    const {html, js} = await Templates.renderForPromise('mod_kuet/createsession/sessionquestions', data);
                    Templates.replaceNodeContents(document.querySelector('[data-region="session-questions"]'), html, js);
                    modal.hide();
                } catch (error) {
                    Notification.exception(error);
                } finally {
                    busy = false;
                    bank.disabled = false;
                }
            };
            bank.addEventListener('change', loadBank);
            root.addEventListener('click', (event) => {
                const button = event.target.closest('[data-action="kuet-add-question"]');
                if (button) {
                    event.preventDefault();
                    add([Number(button.dataset.questionid)]);
                }
            });
            root.addEventListener('submit', (event) => {
                if (event.target.id !== 'questionsubmit') {
                    return;
                }
                event.preventDefault();
                const ids = Array.from(event.target.querySelectorAll('input[type="checkbox"]:checked'))
                    .filter(input => /^q\d+$/.test(input.name)).map(input => Number(input.name.slice(1)));
                add(ids);
            });
            modal.getRoot().on(ModalEvents.hidden, () => {
                generation++;
                modal.destroy();
                trigger.disabled = false;
                trigger.focus();
            });
            modal.show();
            await loadBank();
        } catch (error) {
            trigger.disabled = false;
            Notification.exception(error);
        }
    });
};
