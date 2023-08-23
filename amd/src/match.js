"use strict";

import jQuery from 'jquery';

/* eslint-disable no-unused-vars */
let cmId;
let sId;
let questionid;
let jqshowId;
let jqid;
let questionEnd = false;
let correctAnswers = null;
let showQuestionFeedback = false;
let manualMode = false;

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
    this.initMatch();
}

/** @type {jQuery} The jQuery node for the page region. */
Match.prototype.node = null;
Match.prototype.endTimer = new Event('endTimer');
Match.prototype.studentQuestionEnd = new Event('studentQuestionEnd');

Match.prototype.initMatch = function() {

};

export const initMatch = (selector, showquestionfeedback, manualmode, jsonresponse) => {
    return new Match(selector, showquestionfeedback, manualmode, jsonresponse);
};