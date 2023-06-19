"use strict";

import jQuery from 'jquery';

let REGION = {
    PAGE: '[data-region="question-content"]',
    TIMER: '[data-region="question-timer"]',
    SECONDS: '[data-region="seconds"]'
};

/**
 * @constructor
 * @param {String} selector
 */
function QuestionTimer(selector) {
    this.node = jQuery(selector);
    this.initTimer();
}

/** @type {jQuery} The jQuery node for the page region. */
QuestionTimer.prototype.node = null;
QuestionTimer.prototype.timeValue = null;
QuestionTimer.prototype.countDown = null;
QuestionTimer.prototype.isPaused = false;
QuestionTimer.prototype.timeFinish = new Event('timeFinish');

QuestionTimer.prototype.initTimer = function() {
    this.timeValue = this.node.find(REGION.SECONDS).html();
    this.countDown = setInterval(() => {
        if (this.timeValue <= 0) {
            this.node.find(REGION.SECONDS).html(this.timeValue);
            clearInterval(this.countDown);
            dispatchEvent(this.timeFinish);
        } else {
            this.node.find(REGION.SECONDS).html(this.timeValue);
        }
        if (!this.isPaused) {
            this.timeValue -= 1;
        }
    }, 1000);
    addEventListener('endTimer', () => {
        clearInterval(this.countDown);
    }, false);
    addEventListener('pauseQuestion', () => {
        this.isPaused = true;
    }, false);
    addEventListener('playQuestion', () => {
        this.isPaused = false;
    }, false);
};

export const initQuestionTimer = (selector) => {
    return new QuestionTimer(selector);
};
