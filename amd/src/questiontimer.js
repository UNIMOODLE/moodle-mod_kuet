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
// Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria y Burgos..

/**
 *
 * @module    mod_kuet/questiontimer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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
    }, {once: true});
    addEventListener('pauseQuestion', () => {
        this.isPaused = true;
    }, false);
    addEventListener('playQuestion', () => {
        this.isPaused = false;
    }, false);
    addEventListener('removeEvents', () => {
        removeEventListener('endTimer', clearInterval(this.countDown), {once: true});
    }, {once: true});
};

export const initQuestionTimer = (selector) => {
    return new QuestionTimer(selector);
};
