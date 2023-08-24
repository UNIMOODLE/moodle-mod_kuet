"use strict";

import jQuery from 'jquery';
import MatchConnections from "mod_jqshow/matchConnections";

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

let REGION = {
    LEFT_OPTION: '[data-action="mark-left-option"]',
    RIGHT_OPTION: '[data-action="mark-right-option"]',
};

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
    this.node.find(REGION.LEFT_OPTION).on('click', this.markLeftOption.bind(this));
};

Match.prototype.markLeftOption = function(l) {
    jQuery(REGION.LEFT_OPTION).addClass('disabled');
    jQuery(l.currentTarget).removeClass('disabled');
    this.node.find(REGION.RIGHT_OPTION).on('click', function(r) {
        let leftSide = jQuery(l.currentTarget).attr('data-stems');
        let rightSide = jQuery(r.currentTarget).attr('data-stems');
        MatchConnections.connections(
            jQuery('.left-side [data-stems=' + leftSide + '] .option-pointer'),
            {to: '.right-side [data-stems=' + rightSide + '] .option-pointer'}
        );
        jQuery(REGION.LEFT_OPTION).removeClass('disabled');
        jQuery(r.currentTarget).addClass('disabled');
    });
};

export const initMatch = (selector, showquestionfeedback, manualmode, jsonresponse) => {
    return new Match(selector, showquestionfeedback, manualmode, jsonresponse);
};