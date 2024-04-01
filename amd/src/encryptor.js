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
 * @module    mod_kuet/encryptor
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

"use strict";

const password = 'elktkktagqes';
const abc = 'abcdefghijklmnopqrstuvwxyz0123456789=ABCDEFGHIJKLMNOPQRSTUVWXYZ/+-*';

export const decrypt = function(text) {
    const arr = text.split('');
    const arrPass = password.split('');
    let lastPassLetter = 0;
    let decrypted = '';
    for (let i = 0; i < arr.length; i++) {
        const letter = arr[i];
        const passwordLetter = arrPass[lastPassLetter];
        const temp = getInvertedLetterFromAlphabetForLetter(passwordLetter, letter);
        if (temp) {
            decrypted += temp;
        } else {
            return null;
        }
        if (lastPassLetter === (arrPass.length - 1)) {
            lastPassLetter = 0;
        } else {
            lastPassLetter++;
        }
    }
    return atob(decrypted);
};

let getInvertedLetterFromAlphabetForLetter = function(letter, letterToChange) {
    const posLetter = abc.indexOf(letter);
    if (posLetter == -1) {
        // eslint-disable-next-line no-console
        console.log('Password letter ' + letter + ' not allowed.');
        return null;
    }

    const part1 = abc.substring(posLetter, abc.length);
    const part2 = abc.substring(0, posLetter);
    const newABC = '' + part1 + '' + part2;
    const posLetterToChange = newABC.indexOf(letterToChange);

    if (posLetterToChange == -1) {
        // eslint-disable-next-line no-console
        console.log('Password letter ' + letter + ' not allowed.');
        return null;
    }

    return abc.split('')[posLetterToChange];
};

export const encrypt = function(text) {
    const base64 = btoa(text);
    const arr = base64.split('');
    const arrPass = password.split('');
    let lastPassLetter = 0;
    let encrypted = '';
    for (let i = 0; i < arr.length; i++) {
        const letter = arr[i];
        const passwordLetter = arrPass[lastPassLetter];
        const temp = getLetterFromAlphabetForLetter(passwordLetter, letter);
        if (temp) {
            encrypted += temp;
        } else {
            return null;
        }
        if (lastPassLetter === (arrPass.length - 1)) {
            lastPassLetter = 0;
        } else {
            lastPassLetter++;
        }
    }
    return encrypted;
};

let getLetterFromAlphabetForLetter = function(letter, letterToChange) {
    const posLetter = abc.indexOf(letter);
    if (posLetter == -1) {
        // eslint-disable-next-line no-console
        console.log('Password letter ' + letter + ' not allowed.');
        return null;
    }
    const posLetterToChange = abc.indexOf(letterToChange);
    if (posLetterToChange == -1) {
        // eslint-disable-next-line no-console
        console.log('Password letter ' + letter + ' not allowed.');
        return null;
    }
    const part1 = abc.substring(posLetter, abc.length);
    const part2 = abc.substring(0, posLetter);
    const newABC = '' + part1 + '' + part2;
    return newABC.split('')[posLetterToChange];
};
export default {
    encrypt,
    decrypt
};
