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
 * @module    mod_jqshow/database
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

"use strict";

let indexedDb = null;
let dbName;
let dbVersion = 1;

/**
 * @constructor
 * @param {int} sid
 * @param {int} userid
 */
function Db(sid, userid) {
    dbName = `${sid}_${userid}`;
    this.openDb();
}

Db.prototype.openDb = function() {
    let req = indexedDB.open(dbName, dbVersion);
    req.onsuccess = function(evt) {
        indexedDb = evt.target.result;
    };
    req.onerror = function(event) {
        // eslint-disable-next-line no-console
        console.error(`Database error: ${event.target.errorCode}`);
    };
    req.onupgradeneeded = function(evt) {
        // We create the database with the necessary objects and indexes.
        let questions = evt.currentTarget.result.createObjectStore(
            'questions', {keyPath: 'jqid'});
        questions.createIndex('jqid', 'jqid', {unique: true});

        let statequestions = evt.currentTarget.result.createObjectStore('statequestions', {keyPath: 'state'});
        statequestions.createIndex('state', 'state', {unique: true});
    };
};

Db.prototype.add = function(storeName, value) {
    let store = this.getObjectStore(storeName, 'readwrite');
    store.add(value);
};

Db.prototype.update = function(storeName, value) {
    let store = this.getObjectStore(storeName, 'readwrite');
    store.put(value);
};

Db.prototype.get = function(storeName, id) {
    let store = this.getObjectStore(storeName, 'readonly');
    return store.get(id);
};

Db.prototype.delete = function(storeName, id) {
    let store = this.getObjectStore(storeName, 'readwrite');
    store.delete(id);
};

Db.prototype.getObjectStore = function(storeName, mode) {
    let tx = indexedDb.transaction(storeName, mode);
    return tx.objectStore(storeName);
};

Db.prototype.clearObjectStore = function(storeName) {
    let store = this.getObjectStore(storeName, 'readwrite');
    let req = store.clear();
    req.onsuccess = function() {
        // eslint-disable-next-line no-console
        console.log("clearObjectStore");
    };
    req.onerror = function(evt) {
        // eslint-disable-next-line no-console
        console.error("clearObjectStore:", evt.target.errorCode);
    };
};

Db.prototype.deleteDatabase = function() {
    indexedDb.close();
    return window.indexedDB.deleteDatabase(dbName);
};

export const initDb = (sid, userid) => {
    return new Db(sid, userid);
};

export default {
    initDb
};
