"use strict";
/* eslint-disable no-unused-vars */

import jQuery from 'jquery';
import Templates from 'core/templates';
import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';
import ModalJqshow from 'mod_jqshow/modal';
import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {get_string as getString} from 'core/str';

let REGION = {
    ROOT: '[data-region="question-report"]', // This root.
    LOADING: '[data-region="overlay-icon-container"]',
};

let ACTION = {
    QUESTIONPREVIEW: '[data-action="question-preview"]',
};

let SERVICES = {
    GETQUESTION: 'mod_jqshow_getquestion',
};

let TEMPLATES = {
    LOADING: 'core/overlay_loading',
    QUESTION: 'mod_jqshow/questions/encasement'
};

QuestionReport.prototype.root = null;

/**
 * @constructor
 * @param {String} region
 */
function QuestionReport(region) {
    this.root = jQuery(region);
    this.root.find(ACTION.QUESTIONPREVIEW).on('click', this.questionPreview);
}

QuestionReport.prototype.questionPreview = function(e) {
    e.preventDefault();
    e.stopPropagation();
    let sessionId = jQuery(e.currentTarget).attr('data-sessionid');
    let questionnId = jQuery(e.currentTarget).attr('data-questionnid');
    let cmId = jQuery(e.currentTarget).attr('data-cmid');
    Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
        let identifier = jQuery(REGION.ROOT);
        identifier.append(html);
        let request = {
            methodname: SERVICES.GETQUESTION,
            args: {
                cmid: cmId,
                sid: sessionId,
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

export const questionReport = (region) => {
    return new QuestionReport(region);
};
