"use strict";

import jQuery from 'jquery';
import Ajax from 'core/ajax';
import Templates from 'core/templates';
import Notification from 'core/notification';
import mEvent from 'core/event';

let ACTION = {
    SEND_RESPONSE: '[data-action="send-response"]',
};

let REGION = {
    ROOT: '[data-region="question-content"]',
    LOADING: '[data-region="overlay-icon-container"]',
    NEXT: '[data-action="next-question"]',
    TIMER: '[data-region="question-timer"]',
    SECONDS: '[data-region="seconds"]',
    CONTENTFEEDBACKS: '[data-region="containt-feedbacks"]',
    FEEDBACK: '[data-region="statement-feedback"]',
    FEEDBACKANSWER: '[data-region="answer-feedback"]',
    FEEDBACKBACGROUND: '[data-region="feedback-background"]',
    AREA: '#drag_and_drop_area',
    DRAGELEMENT: '.draghome.user-select-none',
    DROPELEMENT: '.drop.active'
};

let SERVICES = {
    REPLY: 'mod_jqshow_ddwtos'
};

let TEMPLATES = {
    LOADING: 'core/overlay_loading'
};

let cmId;
let sId;
let questionid;
let jqshowId;
let jqid;
let questionEnd = false;
let showQuestionFeedback = false;
let manualMode = false;

/** @type {jQuery} The jQuery node for the page region. */
Ddwtos.prototype.node = null;
Ddwtos.prototype.endTimer = new Event('endTimer');
Ddwtos.prototype.studentQuestionEnd = new Event('studentQuestionEnd');

/**
 * @constructor
 * @param {String} selector
 * @param {Boolean} showquestionfeedback
 * @param {Boolean} manualmode
 * @param {String} jsonresponse
 */
function Ddwtos(selector, showquestionfeedback = false, manualmode = false, jsonresponse = '') {
    this.node = jQuery(selector);
    sId = this.node.attr('data-sid');
    cmId = this.node.attr('data-cmid');
    questionid = this.node.attr('data-questionid');
    jqshowId = this.node.attr('data-jqshowid');
    jqid = this.node.attr('data-jqid');
    showQuestionFeedback = showquestionfeedback;
    manualMode = manualmode;
    questionEnd = false;
    if (jsonresponse !== '') {
        this.answered(JSON.parse(jsonresponse));
        if (manualMode === false || jQuery('.modal-body').length) {
            questionEnd = true;
            if (showQuestionFeedback === true) {
                this.showFeedback();
            }
            this.showAnswers();
        }
    }
    Ddwtos.prototype.initDdwtos();
}

Ddwtos.prototype.initDdwtos = function() {
    jQuery(ACTION.SEND_RESPONSE).on('click', Ddwtos.prototype.reply);
    Ddwtos.prototype.initEvents();
    Ddwtos.prototype.resizeAllDragsAndDrops();
    Ddwtos.prototype.initDragAndDrop();
};

Ddwtos.prototype.initEvents = function() {
    addEventListener('timeFinish', Ddwtos.prototype.reply, {once: true});
    if (manualMode !== false) {
        addEventListener('teacherQuestionEnd_' + jqid, (e) => {
            if (questionEnd !== true) {
                Ddwtos.prototype.reply();
            }
            e.detail.statistics.forEach((statistic) => {
                jQuery('[data-answerid="' + statistic.answerid + '"] .numberofreplies').html(statistic.numberofreplies);
            });
        }, {once: true});
        addEventListener('pauseQuestion_' + jqid, () => {
            Ddwtos.prototype.pauseQuestion();
        }, false);
        addEventListener('playQuestion_' + jqid, () => {
            Ddwtos.prototype.playQuestion();
        }, false);
        addEventListener('showAnswers_' + jqid, () => {
            Ddwtos.prototype.showAnswers();
        }, false);
        addEventListener('hideAnswers_' + jqid, () => {
            Ddwtos.prototype.hideAnswers();
        }, false);
        addEventListener('showStatistics_' + jqid, () => {
            Ddwtos.prototype.showStatistics();
        }, false);
        addEventListener('hideStatistics_' + jqid, () => {
            Ddwtos.prototype.hideStatistics();
        }, false);
        addEventListener('showFeedback_' + jqid, () => {
            Ddwtos.prototype.showFeedback();
        }, false);
        addEventListener('hideFeedback_' + jqid, () => {
            Ddwtos.prototype.hideFeedback();
        }, false);
    }

    window.onbeforeunload = function() {
        if (jQuery(REGION.SECONDS).length > 0 && questionEnd === false) {
            Ddwtos.prototype.reply();
            return 'Because the question is overdue and an attempt has been made to reload the page,' +
                ' the question has remained unanswered.';
        }
    };
};

Ddwtos.prototype.resizeAllDragsAndDrops = function() {
    let thisQ = this;
    jQuery(REGION.AREA).find('.answercontainer > div').each(function(i, node) {
        thisQ.resizeAllDragsAndDropsInGroup(
            thisQ.getClassnameNumericSuffix(jQuery(node), 'draggrouphomes'));
    });
};

Ddwtos.prototype.resizeAllDragsAndDropsInGroup = function(group) {
    let thisQ = this,
        dragHomes = jQuery(REGION.AREA).find('.draggrouphomes' + group + ' span.draghome'),
        maxWidth = 0,
        maxHeight = 0;
    dragHomes.each(function(i, drag) {
        maxWidth = Math.max(maxWidth, Math.ceil(drag.offsetWidth));
        maxHeight = Math.max(maxHeight, Math.ceil(0 + drag.offsetHeight));
    });
    maxWidth += 8;
    maxHeight += 2;
    dragHomes.each(function(i, drag) {
        thisQ.setElementSize(drag, maxWidth, maxHeight);
    });
    jQuery(REGION.AREA).find('span.drop.group' + group).each(function(i, drop) {
        thisQ.setElementSize(drop, maxWidth, maxHeight);
    });
};

Ddwtos.prototype.setElementSize = function(element, width, height) {
    jQuery(element).width(width).height(height).css('lineHeight', height + 'px');
};

Ddwtos.prototype.getClassnameNumericSuffix = function(node, prefix) {
    let classes = node.attr('class');
    if (classes !== '') {
        let classesArr = classes.split(' ');
        for (let index = 0; index < classesArr.length; index++) {
            let patt1 = new RegExp('^' + prefix + '([0-9])+$');
            if (patt1.test(classesArr[index])) {
                let patt2 = new RegExp('([0-9])+$');
                let match = patt2.exec(classesArr[index]);
                return Number(match[0]);
            }
        }
    }
    return null;
};

Ddwtos.prototype.initDragAndDrop = function() {
    let dragElements = [...document.querySelectorAll(REGION.DRAGELEMENT)];
    dragElements.forEach(function(dragElement) {
        dragElement.setAttribute('draggable', 'true');
        dragElement.addEventListener('drag', (e) => {
            Ddwtos.prototype.drag(e);
        });
        dragElement.addEventListener('dragstart', (e) => {
            Ddwtos.prototype.drag(e);
        });
    });
    let dropElements = [...document.querySelectorAll(REGION.DROPELEMENT)];
    dropElements.forEach(function(dropElement) {
        dropElement.addEventListener('drop', (e) => {
            Ddwtos.prototype.drop(e);
        });
        dropElement.addEventListener('dragover', (e) => {
            Ddwtos.prototype.allowDrop(e);
        });
    });
    // eslint-disable-next-line no-console
    console.log(dragElements, dropElements);
};

Ddwtos.prototype.allowDrop = function(e) {
    e.preventDefault();
    // eslint-disable-next-line no-console
    console.log('allowDrop');
};

Ddwtos.prototype.drag = function(e) {
    e.preventDefault();
    // eslint-disable-next-line no-console
    console.log('drag', e.target);
    e.dataTransfer.setData('text', e.target.id);
};

Ddwtos.prototype.drop = function(e) {
    e.preventDefault();
    // eslint-disable-next-line no-console
    console.log('drop');
    let data = e.dataTransfer.getData('text');
    e.target.appendChild(document.getElementById(data));
};

Ddwtos.prototype.removeEvents = function() {
    removeEventListener('timeFinish', Ddwtos.prototype.reply, {once: true});
};

Ddwtos.prototype.reply = function() {
    Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
        jQuery(REGION.ROOT).append(html);
        dispatchEvent(Ddwtos.prototype.endTimer);
        Ddwtos.prototype.removeEvents();
        let timeLeft = parseInt(jQuery(REGION.SECONDS).text());
        let request = {
            methodname: SERVICES.REPLY,
            args: {
                sessionid: sId,
                jqshowid: jqshowId,
                cmid: cmId,
                questionid: questionid,
                jqid: jqid,
                timeleft: timeLeft || 0,
                preview: false
            }
        };
        Ajax.call([request])[0].done(function(response) {
            if (response.reply_status === true) {
                questionEnd = true;
                Ddwtos.prototype.answered(response);
                dispatchEvent(Ddwtos.prototype.studentQuestionEnd);
                if (jQuery('.modal-body').length) { // Preview.
                    Ddwtos.prototype.showAnswers();
                    if (showQuestionFeedback === true) {
                        Ddwtos.prototype.showFeedback();
                    }
                } else {
                    if (manualMode === false) {
                        Ddwtos.prototype.showAnswers();
                        if (showQuestionFeedback === true) {
                            Ddwtos.prototype.showFeedback();
                        }
                    }
                }
            } else {
                alert('error');
            }
            jQuery(REGION.LOADING).remove();
        }).fail(Notification.exception);
    });
};

Ddwtos.prototype.answered = function(response) {
    questionEnd = true;
    if (response.hasfeedbacks) {
        jQuery(REGION.FEEDBACK).html(response.statment_feedback);
        jQuery(REGION.FEEDBACKANSWER).html(response.answer_feedback);
    }
    jQuery(ACTION.SEND_RESPONSE).addClass('d-none');
    jQuery(REGION.FEEDBACKBACGROUND).css('display', 'block');
    jQuery(REGION.NEXT).removeClass('d-none');
    mEvent.notifyFilterContentUpdated(document.querySelector(REGION.CONTENTFEEDBACKS));
};

Ddwtos.prototype.pauseQuestion = function() {
    dispatchEvent(new Event('pauseQuestion'));
    jQuery(REGION.TIMER).css('z-index', 3);
    jQuery(REGION.FEEDBACKBACGROUND).css('display', 'block');
    jQuery(ACTION.REPLY).css('pointer-events', 'none');
};

Ddwtos.prototype.playQuestion = function() {
    if (questionEnd !== true) {
        dispatchEvent(new Event('playQuestion'));
        jQuery(REGION.TIMER).css('z-index', 1);
        jQuery(REGION.FEEDBACKBACGROUND).css('display', 'none');
        jQuery(ACTION.REPLY).css('pointer-events', 'auto');
    }
};

Ddwtos.prototype.showFeedback = function() {
    if (questionEnd === true) {
        jQuery(REGION.CONTENTFEEDBACKS).css({'display': 'block', 'z-index': 3});
    }
};

Ddwtos.prototype.hideFeedback = function() {
    if (questionEnd === true) {
        jQuery(REGION.CONTENTFEEDBACKS).css({'display': 'none', 'z-index': 0});
    }
};

Ddwtos.prototype.showAnswers = function() {
    if (questionEnd === true) {
        // TODO obtain the possible answers, and paint them in a list.
        jQuery(REGION.ANSWERHELP).removeClass('d-none').css({'z-index': 3});
    }
};

Ddwtos.prototype.hideAnswers = function() {
    if (questionEnd === true) {
        jQuery(REGION.ANSWERHELP).addClass('d-none');
    }
};

export const initDdwtos = (selector, showquestionfeedback, manualmode, jsonresponse) => {
    return new Ddwtos(selector, showquestionfeedback, manualmode, jsonresponse);
};
