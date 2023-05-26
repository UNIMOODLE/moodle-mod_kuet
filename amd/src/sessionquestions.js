"use strict";

import jQuery from 'jquery';
import {get_strings as getStrings} from 'core/str';
import Ajax from 'core/ajax';
import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';
import Templates from 'core/templates';
import Notification from 'core/notification';
import SortableList from 'core/sortable_list';

let ACTION = {
    DELETEQUESTION: '[data-action="delete_question"]',
    QUESTION: '.question-item',
};

let SERVICES = {
    DELETEQUESTION: 'mod_jqshow_deletequestion',
    SESSIONQUESTIONS: 'mod_jqshow_sessionquestions',
    REORDER: 'mod_jqshow_reorderquestions'
};

let REGION = {
    PANEL: '[data-region="questions-panel"]',
    LOADING: '[data-region="overlay-icon-container"]',
    SESSIONQUESTIONS: '[data-region="session-questions"]',
    QUESTIONLIST: '[data-region="question_list"]'
};

let TEMPLATES = {
    LOADING: 'core/overlay_loading',
    SUCCESS: 'core/notification_success',
    ERROR: 'core/notification_error',
    QUESTIONSSELECTED: 'mod_jqshow/createsession/sessionquestions'
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
    if (!(sortable instanceof SortableList)) {
        sortable = new SortableList(REGION.QUESTIONLIST);
    }
    jQuery(REGION.QUESTIONLIST + ' > ' + ACTION.QUESTION).on(SortableList.EVENTS.DROP, this.reorderQuestions.bind(this));
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
