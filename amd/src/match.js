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
// Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria y Burgos.

/**
 *
 * @module    mod_kuet/match
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

"use strict";
/* eslint-disable no-unused-vars */

import jQuery from 'jquery';
import Ajax from 'core/ajax';
import Templates from 'core/templates';
import mEvent from 'core/event';

let ACTION = {
    SEND_RESPONSE: '[data-action="send-match"]',
    SELECTOPTION: '[data-action="mark-left-option-mobile"]'
};

let REGION = {
    ROOT: '[data-region="question-content"]',
    MATCH: '[data-region="match"]',
    LOADING: '[data-region="overlay-icon-container"]',
    LEFT_OPTION: '#dragOption',
    RIGHT_OPTION: '#dropOption',
    CONTAINER_ANSWERS: '#dragQuestion',
    CONTAINER_ANSWERS_MOBILE: '#selectQuestion',
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
    REPLY: 'mod_kuet_match'
};

let TEMPLATES = {
    LOADING: 'core/overlay_loading'
};

let cmId;
let sId;
let questionid;
let kuetId;
let kid;
let questionEnd = false;
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
    kuetId = this.node.attr('data-kuetid');
    kid = this.node.attr('data-kid');
    showQuestionFeedback = showquestionfeedback;
    manualMode = manualmode;
    questionEnd = false;
    linkList = [];
    this.createLinkCorrection();
    if (jsonresponse !== '') {
        this.answered(JSON.parse(atob(jsonresponse)));
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
    linkList = jsonresponse;
    Match.prototype.drawResponse();
    Match.prototype.drawSelects();
    questionEnd = true;
    jQuery(ACTION.SEND_RESPONSE).addClass('d-none');
    jQuery(REGION.FEEDBACKBACGROUND).css('display', 'block');
    jQuery(REGION.CONTAINER_ANSWERS).css('z-index', 3);
    jQuery(REGION.CONTAINER_ANSWERS_MOBILE).css('z-index', 3);
    if (manualMode === false) {
        jQuery(REGION.NEXT).removeClass('d-none');
    }
    let contentFeedbacks = document.querySelector(REGION.CONTENTFEEDBACKS);
    if (contentFeedbacks !== null) {
        mEvent.notifyFilterContentUpdated(document.querySelector(REGION.CONTENTFEEDBACKS));
    }
};

Match.prototype.initMatch = function() {
    Match.prototype.normalizeCanvas();
    jQuery(REGION.CONTAINER_ANSWERS).bind('dragover', function(){
        let top = window.event.pageY,
            left = window.event.pageX;
        Match.prototype.drawLinkTemp(startPoint, {top, left});
    });
    jQuery(REGION.LEFT_OPTION).find(REGION.OPTION).toArray().forEach(dragEl => this.addEventsDragAndDrop(dragEl));
    jQuery(REGION.RIGHT_OPTION).find(REGION.OPTION).toArray().forEach(dropEl => this.addTargetEvents(dropEl));
    Match.prototype.drawLinks();
    jQuery(REGION.CLEARPATH).on('click touchend', Match.prototype.clearPath.bind(this));
    jQuery(REGION.LEFT_OPTION_CLICKABLE).on('click touchend', Match.prototype.leftOptionSelected.bind(this));
    jQuery(ACTION.SEND_RESPONSE).off('click');
    jQuery(ACTION.SEND_RESPONSE).on('click', Match.prototype.sendResponse);

    // Mobile.
    jQuery(ACTION.SELECTOPTION).on('change', Match.prototype.OptionSelected.bind(this));
    jQuery(window).on('resize', function() {
        Match.prototype.normalizeCanvas();
        if ((manualMode === false || jQuery('.modal-body').length) && questionEnd === true) {
            Match.prototype.showAnswers();
        } else {
            Match.prototype.drawLinks();
            Match.prototype.drawSelects();
        }
    });
    Match.prototype.initEvents();
};

Match.prototype.normalizeCanvas = function() {
    let heightLeft = jQuery(REGION.LEFT_OPTION).height();
    let heightRight = jQuery(REGION.RIGHT_OPTION).height();
    let canvasHeight = heightLeft > heightRight ? heightLeft : heightRight;
    jQuery(REGION.CANVAS).attr('height', canvasHeight);
    jQuery(REGION.CANVAS).attr('width', jQuery(REGION.CANVAS).width());
    jQuery(REGION.CANVASTEMP).attr('width', jQuery(REGION.CANVASTEMP).width());
    jQuery(REGION.CANVASTEMP).attr('height', canvasHeight);
};

Match.prototype.initEvents = function() {
    addEventListener('timeFinish', Match.prototype.sendResponse, {once: true});
    if (manualMode !== false) {
        addEventListener('alreadyAnswered_' + kid, (ev) => {
            let userid =  jQuery('[data-region="student-canvas"]').data('userid');
            if (userid != ev.detail.userid) {
                jQuery('[data-region="group-message"]').css({'z-index': 3, 'padding': '15px'});
                jQuery('[data-region="group-message"]').show();
            }
            if (questionEnd !== true) {
                Match.prototype.sendResponse();
            }
        }, {once: true});
        addEventListener('teacherQuestionEnd_' + kid, (e) => {
            if (questionEnd !== true) {
                Match.prototype.sendResponse();
            }
            e.detail.statistics.forEach((statistic) => {
                jQuery('#statistics .correct').attr('aria-valuenow', statistic.correct)
                    .width(statistic.correct + '%').html(statistic.correct + '%');
                jQuery('#statistics .failure').attr('aria-valuenow', statistic.failure)
                    .width(statistic.failure + '%').html(statistic.failure + '%');
                jQuery('#statistics .invalid').attr('aria-valuenow', statistic.invalid)
                    .width(statistic.invalid + '%').html(statistic.invalid + '%');
                jQuery('#statistics .noresponse').attr('aria-valuenow', statistic.noresponse)
                    .width(statistic.noresponse + '%').html(statistic.noresponse + '%');
                jQuery('#statistics .partially').attr('aria-valuenow', statistic.partially)
                    .width(statistic.partially + '%').html(statistic.partially + '%');
            });
        }, {once: true});
        addEventListener('pauseQuestion_' + kid, () => {
            Match.prototype.pauseQuestion();
        }, false);
        addEventListener('playQuestion_' + kid, () => {
            Match.prototype.playQuestion();
        }, false);
        addEventListener('showAnswers_' + kid, () => {
            Match.prototype.showAnswers();
        }, false);
        addEventListener('hideAnswers_' + kid, () => {
            Match.prototype.hideAnswers();
        }, false);
        addEventListener('showStatistics_' + kid, () => {
            Match.prototype.showStatistics();
        }, false);
        addEventListener('hideStatistics_' + kid, () => {
            Match.prototype.hideStatistics();
        }, false);
        addEventListener('showFeedback_' + kid, () => {
            Match.prototype.showFeedback();
        }, false);
        addEventListener('hideFeedback_' + kid, () => {
            Match.prototype.hideFeedback();
        }, false);
        addEventListener('removeEvents', () => {
            Match.prototype.removeEvents();
        }, {once: true});
    }

    window.onbeforeunload = function() {
        if (jQuery(REGION.SECONDS).length > 0 && questionEnd === false) {
            Match.prototype.sendResponse();
            return 'Because the question is overdue and an attempt has been made to reload the page,' +
                ' the question has remained unanswered.';
        }
    };
};

Match.prototype.removeEvents = function() {
    removeEventListener('timeFinish', () => Match.prototype.sendResponse, {once: true});
    if (manualMode !== false) {
        removeEventListener('alreadyAnswered_' + kid, (ev) => {
            let userid =  jQuery('[data-region="student-canvas"]').data('userid');
            if (userid != ev.detail.userid) {
                jQuery('[data-region="group-message"]').css({'z-index': 3, 'padding': '15px'});
                jQuery('[data-region="group-message"]').show();
            }
            if (questionEnd !== true) {
                Match.prototype.sendResponse();
            }
        }, {once: true});
        removeEventListener('teacherQuestionEnd_' + kid, (e) => {
            if (questionEnd !== true) {
                Match.prototype.sendResponse();
            }
            e.detail.statistics.forEach((statistic) => {
                jQuery('#statistics .correct').attr('aria-valuenow', statistic.correct)
                    .width(statistic.correct + '%').html(statistic.correct + '%');
                jQuery('#statistics .failure').attr('aria-valuenow', statistic.failure)
                    .width(statistic.failure + '%').html(statistic.failure + '%');
                jQuery('#statistics .invalid').attr('aria-valuenow', statistic.invalid)
                    .width(statistic.invalid + '%').html(statistic.invalid + '%');
                jQuery('#statistics .noresponse').attr('aria-valuenow', statistic.noresponse)
                    .width(statistic.noresponse + '%').html(statistic.noresponse + '%');
                jQuery('#statistics .partially').attr('aria-valuenow', statistic.partially)
                    .width(statistic.partially + '%').html(statistic.partially + '%');
            });
        }, {once: true});
        removeEventListener('pauseQuestion_' + kid, () => {
            Match.prototype.pauseQuestion();
        }, false);
        removeEventListener('playQuestion_' + kid, () => {
            Match.prototype.playQuestion();
        }, false);
        removeEventListener('showAnswers_' + kid, () => {
            Match.prototype.showAnswers();
        }, false);
        removeEventListener('hideAnswers_' + kid, () => {
            Match.prototype.hideAnswers();
        }, false);
        removeEventListener('showStatistics_' + kid, () => {
            Match.prototype.showStatistics();
        }, false);
        removeEventListener('hideStatistics_' + kid, () => {
            Match.prototype.hideStatistics();
        }, false);
        removeEventListener('showFeedback_' + kid, () => {
            Match.prototype.showFeedback();
        }, false);
        removeEventListener('hideFeedback_' + kid, () => {
            Match.prototype.hideFeedback();
        }, false);
        removeEventListener('removeEvents', () => {
            Match.prototype.removeEvents();
        }, {once: true});
    }
};

Match.prototype.createLinkCorrection = function() {
    linkCorrection = [];
    jQuery.each(jQuery(REGION.LEFT_OPTION_SELECTOR), function(index, item) {
        let dragId = jQuery(item).data('stems') + '-draggable';
        let steam = Match.prototype.baseConvert(jQuery(item).data('stems'), 2, 16);
        let dropId = Match.prototype.baseConvert(steam, 10, 26) + '-dropzone';
        let stemsLeft = jQuery('#' + dragId).data('forstems');
        let stemsRight = jQuery('#' + dropId).data('forstems');
        linkCorrection.push({dragId: dragId, dropId: dropId, stemsLeft: stemsLeft, stemsRight: stemsRight});
    });
};

Match.prototype.getRandomColor = function(position) {
    let colorArray = ['#FF6633', '#E64D66', '#FF33FF', '#FF9999', '#00B3E6',
                      '#E6B333', '#3366E6', '#999966', '#99FF99', '#B34D4D',
                      '#80B300', '#809900', '#E6B3B3', '#6680B3', '#66991A',
                      '#FF99E6', '#CCFF1A', '#FF1A66', '#E6331A', '#33FFCC',
                      '#66994D', '#B366CC', '#4D8000', '#B33300', '#CC80CC',
                      '#66664D', '#991AFF', '#E666FF', '#4DB3FF', '#1AB399',
                      '#E666B3', '#33991A', '#CC9999', '#B3B31A', '#00E680',
                      '#4D8066', '#809980', '#E6FF80', '#1AFF33', '#999933',
                      '#FF3380', '#CCCC00', '#66E64D', '#4D80CC', '#9900B3',
                      '#FFB399', '#4DB380', '#FF4D4D', '#99E6E6', '#6666FF'];
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
    Match.prototype.drawMobileResponse(fromTeacher);
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
    Match.prototype.hideMobileResponse();
    return [corrects, fails];
};

Match.prototype.showCorrects = function() {
    linkCorrection.forEach(userR => {
        userR.color = '#0fd08c';
    });
};

Match.prototype.sendResponse = function() {
    Match.prototype.allDisabled();
    Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
        jQuery(REGION.ROOT).append(html);
        dispatchEvent(Match.prototype.endTimer);
        removeEventListener('timeFinish', () => Match.prototype.sendResponse, {once: true});
        Match.prototype.removeEvents();
        let timeLeft = parseInt(jQuery(REGION.SECONDS).text());
        let result = 0; // Failure
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
        if (jQuery(REGION.LEFT_OPTION_SELECTOR).length === 0 && jQuery(REGION.LEFT_OPTION_SELECTOR).length === 0) {
            result = 3; // No response.
        }
        Match.prototype.drawLinks();

        let request = {
            methodname: SERVICES.REPLY,
            args: {
                jsonresponse: JSON.stringify(linkList),
                result: result,
                sessionid: sId,
                kuetid: kuetId,
                cmid: cmId,
                questionid: questionid,
                kid: kid,
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
                jQuery(REGION.CONTAINER_ANSWERS_MOBILE).css('z-index', 3);
                jQuery(REGION.FEEDBACKBACGROUND).css('display', 'block');
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
    event.dataTransfer.setData('text/plain', event.target.id);
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
    const dragId = event.dataTransfer.getData('text');
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

    let stemsLeft = jQuery('#' + dragId).data('forstems');
    let stemsRight = jQuery('#' + dropId).data('forstems');
    let stemDragId = Match.prototype.baseConvert(stemsLeft, 2, 16);
    let stemDropId = Match.prototype.baseConvert(stemsRight, 26, 10);

    linkList.push({dragId, dropId, color, stemDragId, stemDropId, stemsLeft, stemsRight});
    Match.prototype.drawLinks();
    Match.prototype.drawSelects();
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
    linkCorrection.forEach(link => Match.prototype.drawLink(link.dragId, link.dropId, link.color));
    linkCorrection.forEach(link => Match.prototype.drawSelect(link.stemsLeft, link.stemsRight, link.color));
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
    let oldStemsLeft = '';
    let oldStemsRight = '';
    linkList = linkList.filter(obj => {
        if (obj.dropId === ident) {
            oldStemsLeft = obj.stemsLeft;
            oldStemsRight = obj.stemsRight;
        }
        return obj.dropId !== ident;
    });
    let dragQuestionObject = jQuery(REGION.CONTAINER_ANSWERS);
    dragQuestionObject.find("i").removeClass('linked');
    dragQuestionObject.find("i").css('font-weight', '400');
    dragQuestionObject.find("i").css('color', '#5a57ff');
    Match.prototype.drawLinks();
    if (oldStemsLeft !== '' && oldStemsRight !== '') {
        Match.prototype.optionDeselected(oldStemsLeft, oldStemsRight);
    }
    Match.prototype.drawSelects();
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
    jQuery(REGION.RIGHT_OPTION_CLICKABLE).on('click touchend', Match.prototype.rightOptionSelected.bind(this));
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
        jQuery(REGION.RIGHT_OPTION_CLICKABLE).off('click touchend');
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
    jQuery(ACTION.SELECTOPTION).unbind('change').css({'pointer-events': 'none'});
    jQuery(REGION.LEFT_OPTION).find(REGION.OPTION).toArray().forEach(dragEl => this.removeEventsDragAndDrop(dragEl));
    jQuery(REGION.RIGHT_OPTION).find(REGION.OPTION).toArray().forEach(dropEl => this.removeTargetEvents(dropEl));
    jQuery(REGION.CLEARPATH).off('click touchend');
    jQuery(REGION.LEFT_OPTION_CLICKABLE).off('click touchend');
    jQuery(ACTION.SEND_RESPONSE).off('click');
};

/* MOBILE */
Match.prototype.OptionSelected = function(e) {
    e.preventDefault();
    e.stopPropagation();
    let optionLeft = jQuery(e.target);
    let optionRight = jQuery(e.target).find('option:selected');
    let stemsLeft = optionLeft.attr('data-stems');
    let stemsRight = optionRight.attr('data-stems');
    if (stemsRight !== 'default') {
        jQuery('#' + stemsLeft + '-left-clickable').trigger('click');
        setTimeout(function() {
            jQuery('#' + stemsRight + '-right-clickable').trigger('click');
            optionLeft.css({'border': '1px solid ' + color});
        }, 200);
    } else {
        let dragId = stemsLeft + '-draggable';
        let oldDropId = '';
        let oldStemsRight = '';
        linkList = linkList.filter(obj => {
            if (obj.dragId === dragId) {
                oldDropId = obj.dropId;
                oldStemsRight = obj.stemsRight;
            }
            return obj.dragId !== dragId;
        });
        Match.prototype.optionDeselected(stemsLeft, oldStemsRight);
        jQuery('#' + oldDropId).trigger('click');
    }
};

Match.prototype.optionSelected = function(stemsLeft, stemsRight, color) {
    jQuery(ACTION.SELECTOPTION).each(function() {
        jQuery(this).find('option[data-stems="' + stemsRight + '"]')
            .each(function() {
                jQuery(this).css('background-color', color);
        });
    });
};

Match.prototype.optionDeselected = function(stemsLeft, stemsRight) {
    jQuery(ACTION.SELECTOPTION).each(function() {
        if (parseInt(jQuery(this).attr('data-stems')) === parseInt(stemsLeft)) {
            jQuery(this).css({'border': '1px solid #8f959e'});
        }
        if (jQuery(this).find('option[data-stems="' + stemsRight + '"]:selected').length) {
            jQuery(this).find('option[data-stems="default"]').prop('selected', true);
        }
        jQuery(this).find('option[data-stems="' + stemsRight + '"]').each(function() {
            jQuery(this).css('background-color', 'white');
        });
    });
};

Match.prototype.drawSelects = function() {
    jQuery(ACTION.SELECTOPTION).each(function() {
        jQuery(this).find('option').each(function() {
            jQuery(this).css('background-color', 'white');
        });
    });
    linkList.forEach(
        select => Match.prototype.drawSelect(select.stemsLeft, select.stemsRight, select.color)
    );
};

Match.prototype.drawSelect = function(stemsLeft, stemsRight, color) {
    jQuery('.content-options-right-mobile[data-stems="' + stemsLeft + '"]').css({'border': '1px solid ' + color});
    jQuery(
        '.content-options-right-mobile[data-stems="' + stemsLeft + '"]' +
        ' .option-right-mobile[data-stems="' + stemsRight + '"]').prop('selected', true);
    Match.prototype.optionSelected(stemsLeft, stemsRight, color);
};

Match.prototype.drawMobileResponse = function(fromTeacher = false) {
    linkList.forEach(userR => {
        let feedbacks = jQuery('.feedback-icons[data-stems="' + userR.stemsLeft + '"]');
        let optionLeft = jQuery('.content-options-right-mobile[data-stems="' + userR.stemsLeft + '"]');
        let optionRight = jQuery(
            '.content-options-right-mobile[data-stems="' + userR.stemsLeft + '"]' +
            ' .option-right-mobile[data-stems="' + userR.stemsRight + '"]');
        if (manualMode === false || jQuery('.modal-body').length || fromTeacher === true) {
            if (userR.stemDragId === userR.stemDropId) {
                userR.color = '#0fd08c';
                feedbacks.find('.feedback-icon.correct').css({'display': 'flex'});
                optionLeft.css({'border': '1px solid #0fd08c'});
                optionRight.css({'background-color': '#0fd08c'}).prop('selected', true);
            } else {
                userR.color = '#f85455';
                feedbacks.find('.feedback-icon.incorrect').css({'display': 'flex'});
                optionLeft.css({'border': '1px solid #f85455'});
                optionRight.css({'background-color': '#f85455'}).prop('selected', true);
            }
        }
    });
};

Match.prototype.hideMobileResponse = function() {
    linkList.forEach(userR => {
        let feedbacks = jQuery('.feedback-icons[data-stems="' + userR.stemsLeft + '"]');
        let optionLeft = jQuery('.content-options-right-mobile[data-stems="' + userR.stemsLeft + '"]');
        let optionRight = jQuery(
            '.content-options-right-mobile[data-stems="' + userR.stemsLeft + '"]' +
            ' .option-right-mobile[data-stems="' + userR.stemsRight + '"]');
        feedbacks.find('.feedback-icon').css({'display': 'none'});
        optionLeft.css({'border': '1px solid' + userR.color});
        optionRight.css({'background-color': userR.color});
    });
};

/* EVENTS */
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

Match.prototype.showStatistics = function() {
    if (questionEnd === true) {
        jQuery('#statistics').css({'display': 'block', 'z-index': 3});
    }
};

Match.prototype.hideStatistics = function() {
    if (questionEnd === true) {
        jQuery('#statistics').css({'display': 'none', 'z-index': 0});
    }
};

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
