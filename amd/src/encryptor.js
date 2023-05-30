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
