"use strict";
/* eslint-disable no-unused-vars */

import jQuery from 'jquery';
import Ajax from 'core/ajax';
import Templates from 'core/templates';

let ACTION = {
    SEND_RESPONSE: '[data-action="send-match"]',
};

let REGION = {
    ROOT: '[data-region="question-content"]',
    LOADING: '[data-region="overlay-icon-container"]',
    LEFT_OPTION: '#dragOption',
    RIGHT_OPTION: '#dropOption',
    CONTAINER_ANSWERS: '#dragQuestion',
    CANVAS: '#canvas',
    CANVASTEMP: '#canvasTemp',
    LEFT_OPTION_SELECTOR: '#dragOption .option',
    OPTION: '.option',
    CLEARPATH: '#dropOption .drop-element',
    POINTER: '.option-pointer'
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
/* Let linkList = [{dragId: "7-draggable", dropId: "7-dropzone", color: "#264653"}]; */ // Example for user response
let linkCorrection = []; // TODO
/* let linkCorrection = [{dragId: "7-draggable", dropId: "7-dropzone", color: "#33991a"},
    {dragId: "8-draggable", dropId: "8-dropzone", color: "#4c061d"},
    {dragId: "9-draggable", dropId: "9-dropzone", color: "#d17a22"},
    {dragId: "10-draggable", dropId: "10-dropzone", color: "#3b3923"},
    {dragId: "11-draggable", dropId: "11-dropzone", color: "#3b5249"}]; // TODO */

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
    if (jsonresponse !== '') {
        linkList = JSON.parse(jsonresponse);
        // TODO show feedback, and avoid clicks.
    }
    this.initMatch();
    this.createLinkCorrection();
}

Match.prototype.initMatch = function() {
    let height = jQuery(REGION.LEFT_OPTION).height();
    jQuery(REGION.CANVAS).attr('height', height);
    jQuery(REGION.CANVAS).attr('width', jQuery(REGION.CANVAS).width());

    jQuery(REGION.CANVASTEMP).attr('width', jQuery(REGION.CANVASTEMP).width());
    jQuery(REGION.CANVASTEMP).attr('height', height);
    jQuery(REGION.CONTAINER_ANSWERS).bind('dragover', function(){
        let top = window.event.pageY,
            left = window.event.pageX;
        Match.prototype.drawLinkTemp(startPoint, {top, left});
    });
    jQuery(REGION.LEFT_OPTION).find(REGION.OPTION).toArray().forEach(dragEl => this.addEventsDragAndDrop(dragEl));
    jQuery(REGION.RIGHT_OPTION).find(REGION.OPTION).toArray().forEach(dropEl => this.addTargetEvents(dropEl));
    Match.prototype.drawLinks();
    jQuery(REGION.CLEARPATH).on('click', Match.prototype.clearPath.bind(this));
    jQuery(ACTION.SEND_RESPONSE).on('click', Match.prototype.sendResponse);
};

Match.prototype.createLinkCorrection = function() { // TODO is unnecessary for debugging.
    jQuery.each(jQuery(REGION.LEFT_OPTION_SELECTOR), function(index, item) {
        let dragId = jQuery(item).data('stems') + '-draggable';
        let steam = Match.prototype.baseConvert(jQuery(item).data('stems'), 2, 16);
        let dropId = Match.prototype.baseConvert(steam, 10, 26) + '-draggable';
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

Match.prototype.sendResponse = function() {
    Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
        jQuery(REGION.ROOT).append(html);
        dispatchEvent(Match.prototype.endTimer);
        let timeLeft = parseInt(jQuery(REGION.SECONDS).text());
        let result = 3; // No response
        let corrects = 0;
        let fails = 0;
        linkList.forEach(userR =>{
            if (userR.stemDragId === userR.stemDropId) {
                userR.color = "green";
                corrects++;
            } else {
                userR.color = "red";
                fails++;
            }
        });
        if (corrects !== 0) {
            result = 2; // Partially
        }
        if (jQuery(REGION.LEFT_OPTION_SELECTOR).length === corrects) {
            result = 1; // Correct
        }
        if (jQuery(REGION.LEFT_OPTION_SELECTOR).length === fails) {
            result = 0; // Failure
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
                // TODO show feedbacks.
                jQuery(ACTION.SEND_RESPONSE).addClass('d-none');
                dispatchEvent(Match.prototype.studentQuestionEnd);
            } else {
                alert('error');
            }
            jQuery(REGION.LOADING).remove();
        });
    });
};

/* Add event */
Match.prototype.addEventsDragAndDrop = function(el) {
    // TODO clicks.
    el.addEventListener('dragstart', Match.prototype.onDragStart, false);
    el.addEventListener('dragend', Match.prototype.onDragEnd, false);
    el.addEventListener('touchstart', Match.prototype.touchStart, false);
    el.addEventListener('touchmove', Match.prototype.touchMove, false);
    el.addEventListener('touchend', Match.prototype.touchEnd, false);
};

Match.prototype.addTargetEvents = function(target) {
    target.addEventListener('dragover', Match.prototype.onDragOver, false);
    target.addEventListener('drop', Match.prototype.onDrop, false);
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
    let dragEl = e.path.find(x => {
        return x.className === "drag-element";
    });
    startPoint = jQuery(dragEl).get(0).id;
    let options = document.querySelectorAll(REGION.LEFT_OPTION_SELECTOR);
    color = Match.prototype.getRandomColor(Array.from(options).indexOf(event.currentTarget));
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
            Match.prototype.Drop(startPoint, target.id);
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


export const initMatch = (selector, showquestionfeedback, manualmode, jsonresponse) => {
    return new Match(selector, showquestionfeedback, manualmode, jsonresponse);
};