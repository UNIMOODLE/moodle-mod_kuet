"use strict";

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
    NEXT: '[data-action="next-question"]'
};

let SERVICES = {
    REPLY: 'mod_jqshow_multichoice'
};

let TEMPLATES = {
    LOADING: 'core/overlay_loading'
};

let cmId;
let sId;
let questionid;
let jqshowId;
let jqid;

/**
 * @constructor
 * @param {String} selector
 */
function MultiChoice(selector) {
    this.node = jQuery(selector);
    sId = this.node.attr('data-sid');
    cmId = this.node.attr('data-cmid');
    questionid = this.node.attr('data-questionid');
    jqshowId = this.node.attr('data-jqshowid');
    jqid = this.node.attr('data-jqid');
    this.initMultichoice();
}

/** @type {jQuery} The jQuery node for the page region. */
MultiChoice.prototype.node = null;
MultiChoice.prototype.endTimer = new Event('endTimer');
MultiChoice.prototype.studentQuestionEnd = new Event('studentQuestionEnd');

MultiChoice.prototype.initMultichoice = function() {
    this.node.find(ACTION.REPLY).on('click', this.reply.bind(this));
    addEventListener('timeFinish', () => {
        this.reply();
    }, {once: true});
    addEventListener('teacherQuestionEnd_' + jqid, () => {
        this.reply();
    }, {once: true});
};

MultiChoice.prototype.reply = function(e) {
    let answerId = 0;
    let that = this;
    if (e !== undefined) {
        e.preventDefault();
        e.stopPropagation();
        answerId = jQuery(e.currentTarget).attr('data-answerid');
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
                questionid: questionid,
                preview: false
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
                jQuery(REGION.NEXT).removeClass('d-none');
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
                dispatchEvent(that.studentQuestionEnd);
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
