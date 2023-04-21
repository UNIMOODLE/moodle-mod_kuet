"use strict";
/* eslint-disable no-unused-vars */ // TODO remove.

import jQuery from 'jquery';
import Ajax from 'core/ajax';
import Notification from 'core/notification';
import Templates from 'core/templates';

let ACTION = {
    REPLY: '[data-action="multichoice-answer"]'
};

let REGION = {
    MULTICHOICE: '[data-region="multichoice"]',
    LOADING: '[data-region="overlay-icon-container"]',
    CONTENTFEEDBACKS: '[data-region="containt-feedbacks"]',
    FEEDBACK: '[data-region="statement-feedback"]',
    FEEDBACKANSWER: '[data-region="answer-feedback"]',
    FEEDBACKBACGROUND: '[data-region="feedback-background"]',
    STATEMENTTEXT: '[data-region="statement-text"]',
    TIMER: '[data-region="question-timer"]',
};

let SERVICES = {
    REPLY: 'mod_jqshow_multichoice'
};

let TEMPLATES = {
    LOADING: 'core/overlay_loading'
};

let cmId;
let sId;
let jqshowId;

/**
 * @constructor
 * @param {String} selector
 */
function MultiChoice(selector) {
    this.node = jQuery(selector);
    sId = this.node.attr('data-sid');
    cmId = this.node.attr('data-cmid');
    jqshowId = this.node.attr('data-jqshowid');
    this.initMultichoice();
}

/** @type {jQuery} The jQuery node for the page region. */
MultiChoice.prototype.node = null;
MultiChoice.prototype.endTimer = new Event('endTimer');

MultiChoice.prototype.initMultichoice = function() {
    this.node.find(ACTION.REPLY).on('click', this.reply.bind(this));
    addEventListener('timeFinish', () => {
        this.reply();
    }, false);
};

MultiChoice.prototype.reply = function(e) {
    let answerId = 0;
    let questionId = 0;
    let that = this;
    if (e !== undefined) {
        e.preventDefault();
        e.stopPropagation();
        answerId = jQuery(e.currentTarget).attr('data-answerid');
        questionId = jQuery(e.currentTarget).attr('data-questionid');
    }
    Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
        that.node.append(html);
        dispatchEvent(that.endTimer);
        let request = {
            methodname: SERVICES.REPLY,
            args: {
                answerid: answerId,
                sessionid: sId,
                jqshowid: jqshowId,
                cmid: cmId,
                questionid: questionId,
                preview: true
            }
        };
        Ajax.call([request])[0].done(function(response) {
            if (response.reply_status === true) {
                if (response.hasfeedbacks) {
                    jQuery(REGION.FEEDBACK).html(response.statment_feedback);
                    jQuery(REGION.FEEDBACKANSWER).html(response.answer_feedback);
                    jQuery(REGION.CONTENTFEEDBACKS).css({'display': 'block', 'z-index': 3});
                }
                jQuery(REGION.FEEDBACKBACGROUND).css('display', 'block');
                jQuery(REGION.STATEMENTTEXT).css({'z-index': 3, 'padding': '15px'});
                jQuery(REGION.TIMER).css('z-index', 3);
                if (e !== undefined) {
                    jQuery(e.currentTarget).css({'z-index': 3, 'pointer-events': 'none'});
                }
                if (response.correct_answers) {
                    jQuery('.feedback-icon').css('display', 'flex');
                    let correctAnswers = response.correct_answers.split(',');
                    correctAnswers.forEach((answ) => {
                        jQuery('[data-answerid="' + answ + '"] .incorrect').css('display', 'none');
                    });
                }
                setTimeout(function() {
                    let contentHeight = jQuery(REGION.MULTICHOICE).outerHeight();
                    jQuery(REGION.FEEDBACKBACGROUND).css('height', contentHeight + 'px');
                }, 15);
            } else {
                alert('error');
            }
            jQuery(REGION.LOADING).remove();
        }).fail(Notification.exception);
    });
};

export const initMultiChoice = (selector) => {
    return new MultiChoice(selector);
};
