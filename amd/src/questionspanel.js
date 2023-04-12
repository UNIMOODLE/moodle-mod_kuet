"use strict";
/* eslint-disable no-unused-vars */ // TODO remove.

import jQuery from 'jquery';
import {get_strings as getStrings} from 'core/str';
import Ajax from 'core/ajax';
import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';
import Templates from 'core/templates';
import Notification from 'core/notification';

let ACTION = {
    COPYQUESTION: '[data-action="copy_question"]',
    DELETEQUESTION: '[data-action="delete_question"]',
    SELECTCATEGORY: '#id_movetocategory',
    ADDQUESTIONS: '[data-action="add_questions"]',
    ADDQUESTION: '[data-action="add_question"]',
    SELECTTALL: '#selectall',
};

let SERVICES = {
    COPYQUESTION: 'mod_jqshow_copyquestion',
    DELETEQUESTION: 'mod_jqshow_deletequestion',
    SELECTCATEGORY: 'mod_jqshow_selectquestionscategory',
};

let REGION = {
    PANEL: '[data-region="questions-panel"]',
    NUMBERSELECT: '#number_select',
    SELECTQUESTION: '.select_question',
    CONTENTQUESTIONS: '[data-region="content-question"]',
    LOADING: '[data-region="overlay-icon-container"]'
};

let TEMPLATES = {
    LOADING: 'core/overlay_loading',
    SUCCESS: 'core/notification_success',
    ERROR: 'core/notification_error',
    QUESTIONSFORSELECT: 'mod_jqshow/createsession/contentquestions'
};

let cmId;
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
    this.node.find(ACTION.COPYQUESTION).on('click', this.copyQuestion);
    this.node.find(ACTION.DELETEQUESTION).on('click', this.deleteQuestion);
    this.node.find(ACTION.SELECTCATEGORY).on('change', this.selectCategory.bind(this));
};

QuestionsPanel.prototype.selectCategory = function(e) {
    e.preventDefault();
    e.stopPropagation();
    let categoryKey = jQuery(e.currentTarget).val();
    let identifier = jQuery(REGION.CONTENTQUESTIONS);
    // eslint-disable-next-line no-console
    console.log(jQuery(REGION.SELECTQUESTION + ':checked').length);
    if (jQuery(REGION.SELECTQUESTION + ':checked').length > 0) {
        const stringkeys = [
            {key: 'changecategory', component: 'mod_jqshow'},
            {key: 'changecategory_desc', component: 'mod_jqshow'},
            {key: 'confirm', component: 'mod_jqshow'},
            {key: 'copysessionerror', component: 'mod_jqshow'},
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
                        methodname: SERVICES.SELECTCATEGORY,
                        args: {
                            categorykey: categoryKey,
                            cmid: cmId
                        }
                    };
                    Ajax.call([request])[0].done(function(response) {
                        let templateQuestions = TEMPLATES.QUESTIONSFORSELECT;
                        Templates.render(templateQuestions, response).then(function(html, js) {
                            identifier.html(html);
                            Templates.runTemplateJS(js);
                            jQuery(REGION.LOADING).remove();
                        }).fail(Notification.exception);
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
    } else {
        Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
            let identifier = jQuery(REGION.PANEL);
            identifier.append(html);
        });
        let request = {
            methodname: SERVICES.SELECTCATEGORY,
            args: {
                categorykey: categoryKey,
                cmid: cmId
            }
        };
        Ajax.call([request])[0].done(function(response) {
            let templateQuestions = TEMPLATES.QUESTIONSFORSELECT;
            Templates.render(templateQuestions, response).then(function(html, js) {
                identifier.html(html);
                Templates.runTemplateJS(js);
                jQuery(REGION.LOADING).remove();
            }).fail(Notification.exception);
        });
    }
};

QuestionsPanel.prototype.copyQuestion = function(e) {
    e.preventDefault();
    e.stopPropagation();
    let questionId = jQuery(e.currentTarget).attr('data-questionnid');
    alert('copyQuestion ' + questionId);
};

QuestionsPanel.prototype.deleteQuestion = function(e) {
    e.preventDefault();
    e.stopPropagation();
    let questionId = jQuery(e.currentTarget).attr('data-questionnid');
    alert('deleteQuestion ' + questionId);
};

export const initQuestionsPanel = (selector) => {
    return new QuestionsPanel(selector);
};
