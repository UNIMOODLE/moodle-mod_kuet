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
        // eslint-disable-next-line no-console
        console.log("db open");
    };
    req.onerror = function(event) {
        // eslint-disable-next-line no-console
        console.error(`Database error: ${event.target.errorCode}`);
    };
    req.onupgradeneeded = function(evt) {
        // eslint-disable-next-line no-console
        console.log("openDb.onupgradeneeded");
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
