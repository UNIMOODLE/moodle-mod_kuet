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
    ROOT: '[data-region="question-list"]', // This root.
    LOADING: '[data-region="overlay-icon-container"]',
};

let ACTION = {
    SEEANSWER: '[data-action="seeanswer"]',
};

let SERVICES = {
    GETQUESTION: 'mod_jqshow_getquestion',
    USERQUESTIONRESPONSE: 'mod_jqshow_getuserquestionresponse',
};

let TEMPLATES = {
    LOADING: 'core/overlay_loading',
    QUESTION: 'mod_jqshow/questions/encasement'
};

UserReport.prototype.root = null;

/**
 * @constructor
 * @param {String} region
 */
function UserReport(region) {
    this.root = jQuery(region);
    this.root.find(ACTION.SEEANSWER).on('click', this.seeAnswer);
}

UserReport.prototype.seeAnswer = function(e) {
    e.preventDefault();
    e.stopPropagation();
    let userId = jQuery(e.currentTarget).attr('data-userid');
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
            let requestAnswer = {
                methodname: SERVICES.USERQUESTIONRESPONSE,
                args: {
                    jqid: question.jqid,
                    cmid: cmId,
                    sid: sessionId,
                    uid: userId
                }
            };
            Ajax.call([requestAnswer])[0].done(function(answer) {
                const questionData = {
                    ...question,
                    ...answer
                };
                getString('viewquestion_user', 'mod_jqshow').done((title) => {
                    Templates.render(TEMPLATES.QUESTION, questionData).then(function(html, js) {
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
                }).fail(Notification.exception);
            });
        });
    });
};

export const userReport = (region) => {
    return new UserReport(region);
};
