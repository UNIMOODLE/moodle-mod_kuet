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

// Project implemented by the "Recovery, Transformation and Resilience Plan.
// Funded by the European Union - Next GenerationEU".
//
// Produced by the UNIMOODLE University Group: Universities of
// Valladolid, Complutense de Madrid, UPV/EHU, León, Salamanca,
// Illes Balears, Valencia, Rey Juan Carlos, La Laguna, Zaragoza, Málaga,
// Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria y Burgos..

/**
 *
 * @module    mod_kuet/questionspanel
 * @copyright  2023 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

"use strict";

import jQuery from 'jquery';
import {get_strings as getStrings} from 'core/str';
import Ajax from 'core/ajax';
import ModalEvents from 'core/modal_events';
import Templates from 'core/templates';
import Notification from 'core/notification';
import ModalSaveCancel from 'core/modal_save_cancel';
import KuetAddQuestionModal from 'mod_kuet/add_question_modal';

let ACTION = {
    SELECTCATEGORY: '#id_movetocategory',
    BANK_SEARCH: '#searchbanks',
    CHANGE_QUESTIONBANK: '[data-action="change_questionbank"]',
    ANCHOR: 'a[href]',
    NEW_BANKMOD_ID: 'data-newmodid',
};

let SERVICES = {
    SELECTCATEGORY: 'mod_kuet_selectquestionscategory',
    RELOAD_PANELQUESTIONBANK: 'mod_kuet_getquestionbank',
};

let REGION = {
    PANEL: '[data-region="questions-panel"]',
    PANELQUESTIONBANK: '[data-region="content-questionbank"]',
    NUMBERSELECT: '#number_select',
    QUESTIONSBANK: 'questions-bank',
    SELECTQUESTION: '.select_question',
    CONTENTQUESTIONS: '[data-region="content-question"]',
    LOADING: '[data-region="overlay-icon-container"]'
};

let TEMPLATES = {
    LOADING: 'core/overlay_loading',
    SUCCESS: 'core/notification_success',
    ERROR: 'core/notification_error',
    QUESTIONSFORSELECT: 'mod_kuet/createsession/contentquestions',
    QUESTIONBANK: 'mod_kuet/createsession/questionbank'
};

let cmId;
// eslint-disable-next-line no-unused-vars
let sId;

/**
 * @constructor
 * @param {String} selector The selector for the page region containing the page.
 */
function QuestionsPanel(selector) {
    this.node = jQuery(selector);
    sId = this.node.attr('data-sid');
    cmId = this.node.attr('data-cmid');
    this.initPanel();
}

/** @type {jQuery} The jQuery node for the page region. */
QuestionsPanel.prototype.node = null;

QuestionsPanel.prototype.initPanel = function() {
    this.node.find(ACTION.SELECTCATEGORY).on('change', this.selectCategory.bind(this));
    this.node.find(ACTION.CHANGE_QUESTIONBANK).on('click', this.changeQuestionBank.bind(this));
};

QuestionsPanel.prototype.selectCategory = function(e) {
    e.preventDefault();
    e.stopPropagation();
    let categoryKey = jQuery(e.currentTarget).val();
    let identifier = jQuery(REGION.CONTENTQUESTIONS);
    let questionsbankcmid = document.getElementById(REGION.QUESTIONSBANK).dataset.questionbankcmid;
    if (jQuery(REGION.SELECTQUESTION + ':checked').length > 0) {
        const stringkeys = [
            {key: 'changecategory', component: 'mod_kuet'},
            {key: 'changecategory_desc', component: 'mod_kuet'},
            {key: 'confirm', component: 'mod_kuet'}
        ];
        getStrings(stringkeys).then((langStrings) => {
            const title = langStrings[0];
            const message = langStrings[1];
            const buttonText = langStrings[2];
            return ModalSaveCancel.create({
                title: title,
                body: message,
            }).then(modal => {
                modal.setSaveButtonText(buttonText);
                modal.getRoot().on(ModalEvents.save, () => {
                    Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
                        let identifier = jQuery(REGION.PANEL);
                        identifier.append(html);
                    });
                    let request = {
                        methodname: SERVICES.SELECTCATEGORY,
                        args: {
                            categorykey: categoryKey,
                            cmid: parseInt(cmId),
                            questionbankcmid: parseInt(questionsbankcmid)
                        }
                    };
                    Ajax.call([request])[0].done(function(response) {
                        let templateQuestions = TEMPLATES.QUESTIONSFORSELECT;
                        Templates.render(templateQuestions, response).then(function(html, js) {
                            identifier.html(html);
                            Templates.runTemplateJS(js);
                            jQuery(REGION.LOADING).remove();
                        }).catch(Notification.exception);
                    }).fail(Notification.exception);
                });
                modal.getRoot().on(ModalEvents.hidden, () => {
                    modal.destroy();
                });
                return modal;
            });
        }).done(function(modal) {
            modal.show();
            // eslint-disable-next-line no-restricted-globals
        }).fail(Notification.exception);
    } else {
        Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
            let identifier = jQuery(REGION.PANEL);
            identifier.append(html);
        });
        let request = {
            methodname: SERVICES.SELECTCATEGORY,
            args: {
                categorykey: categoryKey,
                cmid: parseInt(cmId),
                questionbankcmid: parseInt(questionsbankcmid)
            }
        };
        Ajax.call([request])[0].done(function(response) {
            let templateQuestions = TEMPLATES.QUESTIONSFORSELECT;
            Templates.render(templateQuestions, response).then(function(html, js) {
                identifier.html(html);
                Templates.runTemplateJS(js);
                jQuery(REGION.LOADING).remove();
            }).catch(Notification.exception);
        }).fail(Notification.exception);
    }
};

/**
 * Load another question bank into the questions panel and close the bank chooser.
 *
 * Both ways of choosing a bank end up here — the links of the chooser and the
 * search box — because kuet does not render core's question bank inside the
 * modal: there is nothing to reload in it, what changes is the panel behind.
 *
 * @param {Object} modal The bank chooser, destroyed once the panel is redrawn.
 * @param {Number} bankcmid Course module id of the chosen question bank.
 * @param {Number} cmid Course module id of the kuet.
 * @param {Number} kuetid Kuet instance id.
 * @param {Number} sid Session id.
 */
function loadQuestionBank(modal, bankcmid, cmid, kuetid, sid) {
    let identifier = jQuery(REGION.PANELQUESTIONBANK);
    Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
        identifier.append(html);
    });
    let request = {
        methodname: SERVICES.RELOAD_PANELQUESTIONBANK,
        args: {
            questionbankcmid: parseInt(bankcmid),
            cmid: parseInt(cmid),
            kuetid: parseInt(kuetid),
            sid: parseInt(sid)
        }
    };
    Ajax.call([request])[0].done(function(response) {
        Templates.render(TEMPLATES.QUESTIONBANK, response.questions).then(function(html, js) {
            identifier.html(html);
            Templates.runTemplateJS(js);
            jQuery(REGION.LOADING).remove();
            modal.destroy();
            return true;
        }).catch(Notification.exception);
    }).fail(Notification.exception);
}

QuestionsPanel.prototype.changeQuestionBank = async function(e) {
    e.preventDefault();
    e.stopPropagation();
    let cmid = e.currentTarget.dataset.cmid;
    let kuetid = e.currentTarget.dataset.kuetid;
    let contextid = e.currentTarget.dataset.contextid;
    let sid = e.currentTarget.dataset.sid;

    const modal = await KuetAddQuestionModal.create({
        contextId: contextid,
        addOnPage: 0,
        kuetCmId: cmid,
        bankCmId: 0,
    });

    // Renders core's bank chooser in the modal and turns the search field into
    // an autocomplete over every shared bank of the site.
    await modal.handleSwitchBankContentReload(ACTION.BANK_SEARCH).then((bankmodal) => {
        // The autocomplete writes the chosen bank into the original select and
        // fires a native 'change' on it.
        document.querySelector(ACTION.BANK_SEARCH)?.addEventListener('change', (event) => {
            const bankcmid = event.currentTarget.value;
            if (bankcmid > 0) {
                bankmodal.bankCmId = bankcmid;
                loadQuestionBank(bankmodal, bankcmid, cmid, kuetid, sid);
            }
        });
        // The banks of the course and the recently used ones are links.
        bankmodal.getModal().on('click', ACTION.ANCHOR, (event) => {
            const anchorelement = event.currentTarget;
            if (!anchorelement.closest('a[' + ACTION.NEW_BANKMOD_ID + ']')) {
                return;
            }
            event.preventDefault();
            bankmodal.bankCmId = anchorelement.getAttribute(ACTION.NEW_BANKMOD_ID);
            loadQuestionBank(bankmodal, bankmodal.bankCmId, cmid, kuetid, sid);
        });
        return bankmodal;
    }).catch(Notification.exception);

    modal.show();
};

export const initQuestionsPanel = (selector) => {
    return new QuestionsPanel(selector);
};
