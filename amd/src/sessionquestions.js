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
// Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria y Burgos

/**
 *
 * @module    mod_jqshow/sessionquestions
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

"use strict";

import jQuery from 'jquery';
import {get_string as getString, get_strings as getStrings} from 'core/str';
import Ajax from 'core/ajax';
import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';
import Templates from 'core/templates';
import Notification from 'core/notification';
import SortableList from 'core/sortable_list';
import ModalJqshow from 'mod_jqshow/modal';

let ACTION = {
    DELETEQUESTION: '[data-action="delete_question"]',
    QUESTION: '.question-item',
    QUESTIONDROP: '.question-item [data-drag-type="move"]',
    QUESTIONPREVIEW: '[data-action="question_preview"]',
    ENDCREATESESSION: '[data-action="end_create_session"]',
};

let SERVICES = {
    DELETEQUESTION: 'mod_jqshow_deletequestion',
    SESSIONQUESTIONS: 'mod_jqshow_sessionquestions',
    REORDER: 'mod_jqshow_reorderquestions',
    GETQUESTION: 'mod_jqshow_getquestion',
    ACTIVESESSION: 'mod_jqshow_activesession',
};

let REGION = {
    ROOT: '[data-region="question_list"]',
    PANEL: '[data-region="questions-panel"]',
    LOADING: '[data-region="overlay-icon-container"]',
    SESSIONQUESTIONS: '[data-region="session-questions"]',
    QUESTIONLIST: '[data-region="question_list"]'
};

let TEMPLATES = {
    LOADING: 'core/overlay_loading',
    SUCCESS: 'core/notification_success',
    ERROR: 'core/notification_error',
    QUESTIONSSELECTED: 'mod_jqshow/createsession/sessionquestions',
    QUESTION: 'mod_jqshow/questions/encasement'
};

let cmId;
let sId;
let jqshowId;
let sortable;

/**
 * @constructor
 * @param {String} selector The selector for the page region containing the page.
 */
function SessionQuestions(selector) {
    this.node = jQuery(selector);
    sId = this.node.attr('data-sid');
    cmId = this.node.attr('data-cmid');
    jqshowId = this.node.attr('data-jqshowid');
    this.initPanel();
}

/** @type {jQuery} The jQuery node for the page region. */
SessionQuestions.prototype.node = null;

SessionQuestions.prototype.initPanel = function() {
    this.node.find(ACTION.DELETEQUESTION).on('click', this.deleteQuestion.bind(this));
    this.node.find(ACTION.QUESTIONPREVIEW).on('click', this.questionPreview.bind(this));
    if (!(sortable instanceof SortableList)) {
        sortable = new SortableList(REGION.QUESTIONLIST);
    }
    jQuery(REGION.QUESTIONLIST + ' > ' + ACTION.QUESTIONDROP).on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
    });
    jQuery(REGION.QUESTIONLIST + ' > ' + ACTION.QUESTION).on(SortableList.EVENTS.DROP, this.reorderQuestions.bind(this));
    jQuery(ACTION.ENDCREATESESSION).on('click', this.activeSession.bind(this)).removeClass('disabled');
};

SessionQuestions.prototype.activeSession = function(e) {
    e.preventDefault();
    e.stopPropagation();
    let request = {
        methodname: SERVICES.ACTIVESESSION,
        args: {
            cmid: cmId,
            sessionid: sId
        }
    };
    Ajax.call([request])[0].done(function() {
        window.location.replace(M.cfg.wwwroot + '/mod/jqshow/view.php?id=' + cmId);
    }).fail(Notification.exception);
};

SessionQuestions.prototype.questionPreview = function(e) {
    e.preventDefault();
    e.stopPropagation();
    let questionnId = jQuery(e.currentTarget).attr('data-questionnid');
    Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
        let identifier = jQuery(REGION.ROOT);
        identifier.append(html);
        let request = {
            methodname: SERVICES.GETQUESTION,
            args: {
                cmid: cmId,
                sid: sId,
                jqid: questionnId
            }
        };
        Ajax.call([request])[0].done(function(question) {
            Templates.render(TEMPLATES.QUESTION, question).then(function(html, js) {
                getString('preview', 'mod_jqshow').done((title) => {
                    ModalFactory.create({
                        classes: 'modal_jqshow',
                        body: html,
                        title: title,
                        footer: '',
                        type: ModalJqshow.TYPE
                    }).then(modal => {
                        modal.getRoot().on(ModalEvents.hidden, function() {
                            modal.destroy();
                        });
                        jQuery(REGION.LOADING).remove();
                        modal.show();
                        Templates.runTemplateJS(js);
                    }).fail(Notification.exception);
                }).fail(Notification.exception);
            });
        });
    });
};

SessionQuestions.prototype.reloadSessionQuestionsHtml = function() {
    let request = {
        methodname: SERVICES.SESSIONQUESTIONS,
        args: {
            jqshowid: jqshowId,
            cmid: cmId,
            sid: sId
        }
    };
    Ajax.call([request])[0].done(function(response) {
        Templates.render(TEMPLATES.QUESTIONSSELECTED, response).then(function(html, js) {
            jQuery(REGION.SESSIONQUESTIONS).html(html);
            Templates.runTemplateJS(js);
            jQuery(REGION.LOADING).remove();
        }).fail(Notification.exception);
    }).fail(Notification.exception);
};

SessionQuestions.prototype.deleteQuestion = function(e) {
    e.preventDefault();
    e.stopPropagation();
    let that = this;
    let questionId = jQuery(e.currentTarget).attr('data-questionnid');
    const stringkeys = [
        {key: 'deletequestion', component: 'mod_jqshow'},
        {key: 'deletequestion_desc', component: 'mod_jqshow'},
        {key: 'confirm', component: 'mod_jqshow'}
    ];
    getStrings(stringkeys).then((langStrings) => {
        const title = langStrings[0];
        const message = langStrings[1];
        const buttonText = langStrings[2];
        return ModalFactory.create({
            title: title,
            body: message,
            type: ModalFactory.types.SAVE_CANCEL
        }).then(modal => {
            modal.setSaveButtonText(buttonText);
            modal.getRoot().on(ModalEvents.save, () => {
                Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
                    let identifier = jQuery(REGION.PANEL);
                    identifier.append(html);
                });
                let request = {
                    methodname: SERVICES.DELETEQUESTION,
                    args: {
                        qid: questionId,
                        sid: sId
                    }
                };
                Ajax.call([request])[0].done(function(response) {
                    if (response.deleted) {
                        that.reloadSessionQuestionsHtml();
                    } else {
                        jQuery(REGION.LOADING).remove();
                        alert('no se ha podido borrar la pregunta. intentalo de nuevo mas tarde.');
                    }
                });
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
};

SessionQuestions.prototype.reorderQuestions = function(e) {
    e.preventDefault();
    e.stopPropagation();
    let that = this;
    let allQuestions = jQuery(ACTION.QUESTION);
    let newOrder = [];
    let order = 1;
    allQuestions.each(function callback(index, question) {
        let questiondata = {
            qid: jQuery(question).attr('data-questionnid'),
            qorder: order
        };
        newOrder.push(questiondata);
        order++;
    });
    newOrder.pop();
    Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
        let identifier = jQuery(REGION.PANEL);
        identifier.append(html);
    });
    let request = {
        methodname: SERVICES.REORDER,
        args: {
            questions: newOrder
        }
    };
    Ajax.call([request])[0].done(function() {
        that.reloadSessionQuestionsHtml();
    });
};

export const initSessionQuestions = (selector) => {
    return new SessionQuestions(selector);
};
