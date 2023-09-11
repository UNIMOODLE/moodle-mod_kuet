"use strict";
/* eslint-disable no-unused-vars */

import jQuery from 'jquery';
import Ajax from 'core/ajax';
import Templates from 'core/templates';
import mEvent from 'core/event';

let ACTION = {
    SEND_RESPONSE: '[data-action="send-match"]',
};

let REGION = {
    ROOT: '[data-region="question-content"]',
    MATCH: '[data-region="match"]',
    LOADING: '[data-region="overlay-icon-container"]',
    LEFT_OPTION: '#dragOption',
    RIGHT_OPTION: '#dropOption',
    CONTAINER_ANSWERS: '#dragQuestion',
    CANVAS: '#canvas',
    CANVASTEMP: '#canvasTemp',
    LEFT_OPTION_SELECTOR: '#dragOption .option',
    LEFT_OPTION_CLICKABLE: '#dragOption .option.option-left .content-option',
    RIGHT_OPTION_CLICKABLE: '#dropOption .option.option-right .content-option',
    OPTION: '.option',
    CLEARPATH: '#dropOption .drop-element',
    POINTER: '.option-pointer',
    NEXT: '[data-action="next-question"]',
    TIMER: '[data-region="question-timer"]',
    SECONDS: '[data-region="seconds"]',
    CONTENTFEEDBACKS: '[data-region="containt-feedbacks"]',
    FEEDBACK: '[data-region="statement-feedback"]',
    FEEDBACKANSWER: '[data-region="answer-feedback"]',
    FEEDBACKBACGROUND: '[data-region="feedback-background"]',
};

let SERVICES = {
    REPLY: 'mod_jqshow_match'
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
let correctAnswers = null;
let showQuestionFeedback = false;
let manualMode = false;
let startPoint;
let color;
let toucheX;
let toucheY;
let linkList = [];
let linkCorrection = [];
let showingAnswers = false;
let showingFeedback = false;

/** @type {jQuery} The jQuery node for the page region. */
Match.prototype.node = null;
Match.prototype.endTimer = new Event('endTimer');
Match.prototype.studentQuestionEnd = new Event('studentQuestionEnd');

/**
 * @constructor
 * @param {String} selector
 * @param {Boolean} showquestionfeedback
 * @param {Boolean} manualmode
 * @param {String} jsonresponse
 */
function Match(selector, showquestionfeedback = false, manualmode = false, jsonresponse = '') {
    this.node = jQuery(selector);
    sId = this.node.attr('data-sid');
    cmId = this.node.attr('data-cmid');
    questionid = this.node.attr('data-questionid');
    jqshowId = this.node.attr('data-jqshowid');
    jqid = this.node.attr('data-jqid');
    showQuestionFeedback = showquestionfeedback;
    manualMode = manualmode;
    questionEnd = false;
    linkList = [];
    this.createLinkCorrection();
    if (jsonresponse !== '') {
        this.answered(jsonresponse);
        if (manualMode === false || jQuery('.modal-body').length) {
            questionEnd = true;
            if (showQuestionFeedback === true) {
                this.showFeedback();
            }
            this.showAnswers();
        }
    }
    setTimeout(() => { // For modal preview, is executed before the DOM is rendered.
        this.initMatch();
    }, 500);
}

Match.prototype.answered = function(jsonresponse) {
    Match.prototype.allDisabled();
    linkList = JSON.parse(jsonresponse);
    Match.prototype.drawResponse();
    questionEnd = true;
    jQuery(ACTION.SEND_RESPONSE).addClass('d-none');
    jQuery(REGION.FEEDBACKBACGROUND).css('display', 'block');
    jQuery(REGION.CONTAINER_ANSWERS).css('z-index', 3);
    if (manualMode === false) {
        jQuery(REGION.NEXT).removeClass('d-none');
    }
    mEvent.notifyFilterContentUpdated(document.querySelector(REGION.CONTENTFEEDBACKS));
};

Match.prototype.initMatch = function() {
    let heightLeft = jQuery(REGION.LEFT_OPTION).height();
    let heightRight = jQuery(REGION.RIGHT_OPTION).height();
    let canvasHeight = heightLeft > heightRight ? heightLeft : heightRight;
    jQuery(REGION.CANVAS).attr('height', canvasHeight);
    jQuery(REGION.CANVAS).attr('width', jQuery(REGION.CANVAS).width());

    jQuery(REGION.CANVASTEMP).attr('width', jQuery(REGION.CANVASTEMP).width());
    jQuery(REGION.CANVASTEMP).attr('height', canvasHeight);
    jQuery(REGION.CONTAINER_ANSWERS).bind('dragover', function(){
        let top = window.event.pageY,
            left = window.event.pageX;
        Match.prototype.drawLinkTemp(startPoint, {top, left});
    });
    jQuery(REGION.LEFT_OPTION).find(REGION.OPTION).toArray().forEach(dragEl => this.addEventsDragAndDrop(dragEl));
    jQuery(REGION.RIGHT_OPTION).find(REGION.OPTION).toArray().forEach(dropEl => this.addTargetEvents(dropEl));
    Match.prototype.drawLinks();
    jQuery(REGION.CLEARPATH).on('click', Match.prototype.clearPath.bind(this));
    jQuery(REGION.LEFT_OPTION_CLICKABLE).on('click', Match.prototype.leftOptionSelected.bind(this));
    jQuery(ACTION.SEND_RESPONSE).on('click', Match.prototype.sendResponse);
    Match.prototype.initEvents();
};

Match.prototype.initEvents = function() {
    addEventListener('timeFinish', () => {
        Match.prototype.sendResponse();
    }, {once: true});
    if (manualMode !== false) {
        addEventListener('teacherQuestionEnd_' + jqid, (e) => {
            if (questionEnd !== true) {
                Match.prototype.sendResponse();
            }
        }, {once: true});
        addEventListener('pauseQuestion_' + jqid, () => {
            Match.prototype.pauseQuestion();
        }, false);
        addEventListener('playQuestion_' + jqid, () => {
            Match.prototype.playQuestion();
        }, false);
        addEventListener('showAnswers_' + jqid, () => {
            Match.prototype.showAnswers();
        }, false);
        addEventListener('hideAnswers_' + jqid, () => {
            Match.prototype.hideAnswers();
        }, false);
        addEventListener('showStatistics_' + jqid, () => {
            Match.prototype.showStatistics();
        }, false);
        addEventListener('hideStatistics_' + jqid, () => {
            Match.prototype.hideStatistics();
        }, false);
        addEventListener('showFeedback_' + jqid, () => {
            Match.prototype.showFeedback();
        }, false);
        addEventListener('hideFeedback_' + jqid, () => {
            Match.prototype.hideFeedback();
        }, false);
    }

    window.onbeforeunload = function() {
        if (jQuery(REGION.SECONDS).length > 0 && questionEnd === false) {
            Match.prototype.sendResponse();
            return 'Because the question is overdue and an attempt has been made to reload the page,' +
                ' the question has remained unanswered.';
        }
    };
};

Match.prototype.createLinkCorrection = function() {
    linkCorrection = [];
    jQuery.each(jQuery(REGION.LEFT_OPTION_SELECTOR), function(index, item) {
        let dragId = jQuery(item).data('stems') + '-draggable';
        let steam = Match.prototype.baseConvert(jQuery(item).data('stems'), 2, 16);
        let dropId = Match.prototype.baseConvert(steam, 10, 26) + '-dropzone';
        linkCorrection.push({dragId: dragId, dropId: dropId});
    });
};

Match.prototype.getRandomColor = function(position) {
    let colorArray = ['#FF6633', '#FFB399', '#FF33FF', '#FF9999', '#00B3E6',
                      '#E6B333', '#3366E6', '#999966', '#99FF99', '#B34D4D',
                      '#80B300', '#809900', '#E6B3B3', '#6680B3', '#66991A',
                      '#FF99E6', '#CCFF1A', '#FF1A66', '#E6331A', '#33FFCC',
                      '#66994D', '#B366CC', '#4D8000', '#B33300', '#CC80CC',
                      '#66664D', '#991AFF', '#E666FF', '#4DB3FF', '#1AB399',
                      '#E666B3', '#33991A', '#CC9999', '#B3B31A', '#00E680',
                      '#4D8066', '#809980', '#E6FF80', '#1AFF33', '#999933',
                      '#FF3380', '#CCCC00', '#66E64D', '#4D80CC', '#9900B3',
                      '#E64D66', '#4DB380', '#FF4D4D', '#99E6E6', '#6666FF'];
    return colorArray[position];
};

Match.prototype.drawResponse = function(fromTeacher = false) {
    let corrects = 0;
    let fails = 0;
    linkList.forEach(userR => {
        let optionLeft = jQuery('#' + userR.dragId).parents('.option-left').first();
        let optionRight = jQuery('#' + userR.dropId).parents('.option-right').first();
        if (userR.stemDragId === userR.stemDropId) { // CORRECT.
            if (manualMode === false || jQuery('.modal-body').length || fromTeacher === true) {
                userR.color = '#0fd08c';
                optionLeft.find('.feedback-icon.correct').css({'display': 'flex'});
                optionLeft.find('.content-option').removeClass('bg-primary').css({'background-color': '#0fd08c'});
                optionRight.find('.content-option').removeClass('bg-primary').css({'background-color': '#0fd08c'});
            }
            corrects++;
        } else { // INCORRECT.
            if (manualMode === false || jQuery('.modal-body').length || fromTeacher === true) {
                userR.color = '#f85455';
                optionLeft.find('.feedback-icon.incorrect').css({'display': 'flex'});
                optionLeft.find('.content-option').removeClass('bg-primary').css({'background-color': '#f85455'});
                optionRight.find('.content-option').removeClass('bg-primary').css({'background-color': '#f85455'});
            }
            fails++;
        }
    });
    return [corrects, fails];
};

Match.prototype.hideResponse = function() {
    let corrects = 0;
    let fails = 0;
    linkList.forEach(userR => {
        let optionLeft = jQuery('#' + userR.dragId).parents('.option-left').first();
        let optionRight = jQuery('#' + userR.dropId).parents('.option-right').first();
        userR.color = Match.prototype.getRandomColor(linkList.indexOf(userR));
        optionLeft.find('.feedback-icon').css({'display': 'none'});
        optionLeft.find('.content-option').css({'background-color': 'inherit'}).addClass('bg-primary');
        optionRight.find('.content-option').css({'background-color': 'inherit'}).addClass('bg-primary');
    });
    return [corrects, fails];
};

Match.prototype.showCorrects = function() {
    let corrects = 0;
    let fails = 0;
    linkCorrection.forEach(userR => {
        let optionLeft = jQuery('#' + userR.dragId).parents('.option-left').first();
        let optionRight = jQuery('#' + userR.dropId).parents('.option-right').first();
        userR.color = '#0fd08c';
    });
    return [corrects, fails];
};

Match.prototype.sendResponse = function() {
    Match.prototype.allDisabled();
    Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
        jQuery(REGION.ROOT).append(html);
        dispatchEvent(Match.prototype.endTimer);
        let timeLeft = parseInt(jQuery(REGION.SECONDS).text());
        let result = 3; // No response.
        let [corrects, fails] = Match.prototype.drawResponse();
        if (corrects !== 0) {
            result = 2; // Partially.
        }
        if (jQuery(REGION.LEFT_OPTION_SELECTOR).length === corrects) {
            result = 1; // Correct.
        }
        if (jQuery(REGION.LEFT_OPTION_SELECTOR).length === fails) {
            result = 0; // Failure.
        }
        Match.prototype.drawLinks();

        let request = {
            methodname: SERVICES.REPLY,
            args: {
                jsonresponse: JSON.stringify(linkList),
                result: result,
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
                jQuery(ACTION.SEND_RESPONSE).addClass('d-none');
                jQuery(REGION.NEXT).removeClass('d-none');
                dispatchEvent(Match.prototype.studentQuestionEnd);
                if (response.hasfeedbacks) {
                    jQuery(REGION.FEEDBACK).html(response.statment_feedback);
                    jQuery(REGION.FEEDBACKANSWER).html(response.answer_feedback);
                }
                if (jQuery('.modal-body').length) { // Preview.
                    Match.prototype.showAnswers();
                    if (showQuestionFeedback === true) {
                        Match.prototype.showFeedback();
                    }
                } else {
                    if (manualMode === false) {
                        Match.prototype.showAnswers();
                        if (showQuestionFeedback === true) {
                            Match.prototype.showFeedback();
                        }
                    }
                }
                jQuery(REGION.CONTAINER_ANSWERS).css('z-index', 3);
                jQuery(REGION.FEEDBACKBACGROUND).css('display', 'block');
                if (manualMode === false) {
                    linkList = [];
                }
            } else {
                alert('error');
            }
            jQuery(REGION.LOADING).remove();
        });
    });
};

/* Add event */
Match.prototype.addEventsDragAndDrop = function(el) {
    el.addEventListener('dragstart', Match.prototype.onDragStart, false);
    el.addEventListener('dragend', Match.prototype.onDragEnd, false);
    el.addEventListener('touchstart', Match.prototype.touchStart, false);
    el.addEventListener('touchmove', Match.prototype.touchMove, false);
    el.addEventListener('touchend', Match.prototype.touchEnd, false);
};

Match.prototype.removeEventsDragAndDrop = function(el) {
    el.removeEventListener('dragstart', Match.prototype.onDragStart, false);
    el.removeEventListener('dragend', Match.prototype.onDragEnd, false);
    el.removeEventListener('touchstart', Match.prototype.touchStart, false);
    el.removeEventListener('touchmove', Match.prototype.touchMove, false);
    el.removeEventListener('touchend', Match.prototype.touchEnd, false);
};

Match.prototype.addTargetEvents = function(target) {
    target.addEventListener('dragover', Match.prototype.onDragOver, false);
    target.addEventListener('drop', Match.prototype.onDrop, false);
};

Match.prototype.removeTargetEvents = function(target) {
    target.removeEventListener('dragover', Match.prototype.onDragOver, false);
    target.removeEventListener('drop', Match.prototype.onDrop, false);
};

/* DRAG AND DROP */
Match.prototype.onDragStart = function(event) {
    event
        .dataTransfer
        .setData('text/plain', event.target.id);
    startPoint = event.target.id;
    let options = document.querySelectorAll(REGION.LEFT_OPTION_SELECTOR);
    color = Match.prototype.getRandomColor(Array.from(options).indexOf(event.currentTarget));
};

Match.prototype.onDragOver = function(event) {
    Match.prototype.clearPathTemp();
    event.preventDefault();
};

Match.prototype.onDragEnd = function(event) {
    Match.prototype.clearPathTemp();
    event.preventDefault();
};

Match.prototype.onDrop = function(event) {
    const dragId = event
        .dataTransfer
        .getData('text');

    const dropId = jQuery(event.currentTarget).find(REGION.POINTER).attr('id');
    Match.prototype.Drop(dragId, dropId);
};

Match.prototype.Drop = function(dragId, dropId){
    let deselected = linkList.filter(obj => {
        return obj.dragId === dragId || obj.dropId === dropId;
    });
    if (deselected.length) {
        deselected.forEach(x => {
            let selectroDropId = jQuery("#" + x.dropId);
            selectroDropId.find("i").css('font-weight', '400');
            selectroDropId.find("i").css('color', '#5a57ff');
            selectroDropId.find("i").removeClass('linked');
            selectroDropId.find("i").css('font-weight', '400');
            selectroDropId.find("i").css('color', '#5a57ff');
        });
    }

    linkList = linkList.filter(obj => {
        return obj.dragId !== dragId;
    });
    linkList = linkList.filter(obj => {
        return obj.dropId !== dropId;
    });

    let stemDragId = Match.prototype.baseConvert(jQuery('#' + dragId).data('forstems'), 2, 16);
    let stemDropId = Match.prototype.baseConvert(jQuery('#' + dropId).data('forstems'), 26, 10);

    linkList.push({dragId, dropId, color, stemDragId, stemDropId});
    Match.prototype.drawLinks();
    Match.prototype.clearPathTemp();
};

Match.prototype.baseConvert = function(number, frombase, tobase) {
    // eslint-disable-next-line no-bitwise
    return parseInt(number + '', frombase | 0).toString(tobase | 0);
};

/* TOUCHE DEVICE */
Match.prototype.touchStart = function(e) {
    if (e.target.id === undefined || e.target.id === '') {
        if (jQuery(e.target).parent().attr('id') !== undefined) {
            startPoint = jQuery(e.target).parent().attr('id');
        } else {
            startPoint = jQuery(e.target).closest('.option-left').find(".drag-element").first().attr('id');
        }
    }

    let options = document.querySelectorAll(REGION.LEFT_OPTION_SELECTOR);
    color = Match.prototype.getRandomColor(Array.from(options).indexOf(e.currentTarget));
};

Match.prototype.touchMove = function(e) {
    e.preventDefault();
    let top = toucheY = e.touches[0].pageY,
        left = toucheX = e.touches[0].pageX;
    Match.prototype.drawLinkTemp(startPoint, {top, left});
};

Match.prototype.touchEnd = function(e) {
    jQuery(REGION.RIGHT_OPTION).find(REGION.OPTION).toArray().forEach(target => {
        let box2 = target.getBoundingClientRect(),
            x = box2.left,
            y = box2.top,
            h = target.offsetHeight,
            w = target.offsetWidth,
            b = y + h,
            r = x + w;

        if (toucheX > x && toucheX < r && toucheY > y && toucheY < b) {
            let dropId;
            if (target.id === undefined || target.id === '' || target.id === 'dropOption') {
                if (jQuery(target).parent().attr('id') !== undefined && jQuery(target).parent().attr('id') !== 'dropOption') {
                    dropId = jQuery(target).parent().attr('id');
                } else {
                    dropId = jQuery(target).closest('.option-right').find(".drop-element").first().attr('id');
                }
            } else {
                dropId = target.id;
            }
            Match.prototype.Drop(startPoint, dropId);
        } else {
            return false;
        }
    });
    Match.prototype.clearPathTemp();
    e.preventDefault();
};

/* Draw final line */
Match.prototype.drawLinks = function() {
    let canvas = jQuery(REGION.CANVAS).get(0);
    let ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    linkList.forEach(link => Match.prototype.drawLink(link.dragId, link.dropId, link.color));
};

Match.prototype.drawCorrectLinks = function() {
    let canvas = jQuery(REGION.CANVAS).get(0);
    let ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    // eslint-disable-next-line no-console
    console.log(linkCorrection);
    linkCorrection.forEach(link => Match.prototype.drawLink(link.dragId, link.dropId, link.color));
};

Match.prototype.drawLink = function(obj1, obj2, pColor) {
    let canvas = jQuery(REGION.CANVAS).get(0);
    let ctx = canvas.getContext('2d');

    let $obj1 = jQuery("#" + obj1);
    let $obj2 = jQuery("#" + obj2);
    let parent = jQuery(REGION.CONTAINER_ANSWERS).offset();
    let p1 = $obj1.offset();
    let w1 = $obj1.width();
    let h1 = $obj1.height();
    let p2 = $obj2.offset();
    let w2 = $obj2.width();
    let h2 = $obj2.height();
    let wc = jQuery(REGION.CANVAS).width();
    ctx.beginPath();
    ctx.strokeStyle = pColor ? pColor : color;
    ctx.lineWidth = 3;
    ctx.moveTo(0, p1.top - parent.top + (h1 / 2) - 20 - 2);
    ctx.bezierCurveTo(wc / 2, p1.top - parent.top + (h1 / 2) - 20 - 2,
        wc / 2, p2.top - parent.top + (h2 / 2) - 20 - 2,
        wc - 4, p2.top - parent.top + (h2 / 2) - 20 - 2);
    ctx.stroke();

    $obj1.children().css('color', pColor ? pColor : color);
    $obj1.children().css('font-weight', '900');
    $obj2.children().css('color', pColor ? pColor : color);
    $obj2.children().css('font-weight', '900');
    $obj2.children().addClass('linked');
};

Match.prototype.clearPath = function(event) {
    let ident = event.currentTarget.id;
    linkList = linkList.filter(obj => {
        return obj.dropId !== ident;
    });
    let dragQuestionObject = jQuery(REGION.CONTAINER_ANSWERS);
    dragQuestionObject.find("i").removeClass('linked');
    dragQuestionObject.find("i").css('font-weight', '400');
    dragQuestionObject.find("i").css('color', '#5a57ff');
    Match.prototype.drawLinks();
};

/* Draw path mouse line */
Match.prototype.drawLinkTemp = function(obj1, coordPt) {
    let canvas = jQuery(REGION.CANVASTEMP).get(0);
    let ctx = canvas.getContext('2d');

    let $obj1 = jQuery("#" + obj1);
    let parent = jQuery(REGION.CONTAINER_ANSWERS).offset();
    let p1 = $obj1.offset();
    let w1 = $obj1.width();
    let h1 = $obj1.height();
    let p2 = coordPt;
    let c = jQuery(REGION.CANVASTEMP).offset();

    ctx.beginPath();
    ctx.strokeStyle = color;
    ctx.lineWidth = 3;
    ctx.moveTo(0, p1.top - parent.top + (h1 / 2) - 20 - 2);

    ctx.bezierCurveTo((p2.left - c.left) / 2, p1.top - parent.top - 19 - 2,
        (p2.left - c.left) / 2,p2.top - parent.top - 19 - 2,
        p2.left - c.left, p2.top - parent.top - 19 - 2);
    Match.prototype.clearPathTemp();
    ctx.stroke();
};

Match.prototype.clearPathTemp = function() {
    let canvas = jQuery(REGION.CANVASTEMP).get(0);
    let ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
};

/* CLICKS */
Match.prototype.leftOptionSelected = function(e) {
    e.preventDefault();
    e.stopPropagation();
    jQuery.each(jQuery(REGION.LEFT_OPTION_CLICKABLE), function(index, item) {
        if (e.target !== item) {
            jQuery(item).parent().addClass('disabled');
        } else {
            jQuery(item).parents('.option').first().find('.option-pointer').addClass('disabled');
        }
    });
    jQuery.each(jQuery(REGION.CLEARPATH), function(index, item) {
        jQuery(item).addClass('disabled');
    });
    jQuery.each(jQuery(REGION.RIGHT_OPTION_CLICKABLE), function(index, item) {
        jQuery(item).css({'pointer-events': 'inherit'});
    });
    jQuery(REGION.RIGHT_OPTION_CLICKABLE).on('click', Match.prototype.rightOptionSelected.bind(this));
    let options = document.querySelectorAll(REGION.LEFT_OPTION_SELECTOR);
    color = Match.prototype.getRandomColor(Array.from(options).indexOf(jQuery(e.target).parents('.option').first().get(0)));
};

Match.prototype.rightOptionSelected = function(e) {
    e.preventDefault();
    e.stopPropagation();
    let dragId = jQuery('.option-left:not(.disabled)').find('.drag-element').attr('id');
    jQuery.each(jQuery(REGION.RIGHT_OPTION_CLICKABLE), function(index, item) {
        if (e.target === item) {
            let dropId = jQuery(item).parents('.option-right').first().find('.drop-element').attr('id');
            document.getElementById(dropId).click();
            Match.prototype.Drop(dragId, dropId);
            jQuery(dragId).removeClass('disabled');
        }
        jQuery('.option-left').removeClass('disabled');
        jQuery('.drag-element').removeClass('disabled');
        jQuery(REGION.RIGHT_OPTION_CLICKABLE).off('click');
        jQuery.each(jQuery(REGION.CLEARPATH), function(index, item) {
            jQuery(item).removeClass('disabled');
        });
        jQuery.each(jQuery(REGION.RIGHT_OPTION_CLICKABLE), function(index, item) {
            jQuery(item).css({'pointer-events': 'none'});
        });
    });
};

Match.prototype.allDisabled = function() {
    jQuery.each(jQuery(REGION.LEFT_OPTION_CLICKABLE), function(index, item) {
        jQuery(item).css({'pointer-events': 'none'});
    });
    jQuery.each(jQuery(REGION.RIGHT_OPTION_CLICKABLE), function(index, item) {
        jQuery(item).css({'pointer-events': 'none'});
    });
    jQuery.each(jQuery(REGION.POINTER), function(index, item) {
        jQuery(item).css({'pointer-events': 'none'});
    });
    jQuery(REGION.CONTAINER_ANSWERS).unbind('dragover');
    jQuery(REGION.LEFT_OPTION).find(REGION.OPTION).toArray().forEach(dragEl => this.removeEventsDragAndDrop(dragEl));
    jQuery(REGION.RIGHT_OPTION).find(REGION.OPTION).toArray().forEach(dropEl => this.removeTargetEvents(dropEl));
    jQuery(REGION.CLEARPATH).off('click');
    jQuery(REGION.LEFT_OPTION_CLICKABLE).off('click');
    jQuery(ACTION.SEND_RESPONSE).off('click');
};

/* EVENTS */ // TODO adjust.
Match.prototype.pauseQuestion = function() {
    dispatchEvent(new Event('pauseQuestion'));
    jQuery(REGION.TIMER).css('z-index', 3);
    jQuery(REGION.FEEDBACKBACGROUND).css('display', 'block');
    jQuery(ACTION.REPLY).css('pointer-events', 'none');
    jQuery.each(jQuery('.drag-element'), function(index, item) {
        jQuery(item).css({'z-index': '0'});
    });
    jQuery.each(jQuery(REGION.LEFT_OPTION_CLICKABLE), function(index, item) {
        jQuery(item).css({'pointer-events': 'none'});
    });
    jQuery.each(jQuery(REGION.RIGHT_OPTION_CLICKABLE), function(index, item) {
        jQuery(item).css({'pointer-events': 'none'});
    });
};

Match.prototype.playQuestion = function() {
    if (questionEnd !== true) {
        dispatchEvent(new Event('playQuestion'));
        jQuery(REGION.TIMER).css('z-index', 1);
        jQuery(REGION.FEEDBACKBACGROUND).css('display', 'none');
        jQuery(ACTION.REPLY).css('pointer-events', 'auto');
        jQuery.each(jQuery('.drag-element'), function(index, item) {
            jQuery(item).css({'z-index': '2'});
        });
        jQuery.each(jQuery(REGION.LEFT_OPTION_CLICKABLE), function(index, item) {
            jQuery(item).css({'pointer-events': 'inherit'});
        });
        jQuery.each(jQuery(REGION.RIGHT_OPTION_CLICKABLE), function(index, item) {
            jQuery(item).css({'pointer-events': 'inherit'});
        });
    }
};

Match.prototype.showAnswers = function() {
    showingAnswers = true;
    Match.prototype.drawResponse(true);
    Match.prototype.drawLinks();
};

Match.prototype.hideAnswers = function() {
    showingAnswers = false;
    Match.prototype.hideResponse();
    Match.prototype.drawLinks();
    if (showingFeedback) {
        Match.prototype.showFeedback();
    } else {
        Match.prototype.hideFeedback();
    }
};

Match.prototype.showStatistics = function() {};

Match.prototype.hideStatistics = function() {};

Match.prototype.showFeedback = function() {
    if (questionEnd === true) {
        showingFeedback = true;
        if (jQuery('.modal-body').length === 0 && manualMode === true) {
            Match.prototype.hideResponse();
            Match.prototype.showCorrects();
            Match.prototype.drawCorrectLinks();
        }
        jQuery(REGION.CONTENTFEEDBACKS).css({'display': 'block', 'z-index': 3});
    }
};

Match.prototype.hideFeedback = function() {
    if (questionEnd === true) {
        showingFeedback = false;
        jQuery(REGION.CONTENTFEEDBACKS).css({'display': 'none', 'z-index': 0});
        if (showingAnswers) {
            Match.prototype.drawResponse(true);
            Match.prototype.drawLinks();
        } else {
            Match.prototype.hideResponse();
            Match.prototype.drawLinks();
        }
    }
};


export const initMatch = (selector, showquestionfeedback, manualmode, jsonresponse) => {
    return new Match(selector, showquestionfeedback, manualmode, jsonresponse);
};
