<?php
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_kuet\question;

/**
 * Serialises session starts and question mutations within an activity.
 * @package mod_kuet
 * @copyright 2026 KUET contributors
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mutation {
    /**
     * @var array Reentrant locks for nested persistent hooks.
     */
    private static array $locks = [];
    /**
     * @var int
     */
    private int $kuetid;
    /**
     * @var \moodle_transaction
     */
    private $transaction;
    /**
     * @var bool
     */
    private bool $finished = false;

    /**
     * Acquire before reading state, so edits cannot race with the start snapshot.
     */
    public function __construct(int $kuetid) {
        global $DB;
        $this->kuetid = $kuetid;
        if (!isset(self::$locks[$kuetid])) {
            $lock = \core\lock\lock_config::get_lock_factory('mod_kuet_questions')->get_lock((string)$kuetid, 10);
            if (!$lock) {
                throw new \moodle_exception('sessionquestionbusy', 'mod_kuet');
            }
            self::$locks[$kuetid] = ['lock' => $lock, 'depth' => 0];
        }
        self::$locks[$kuetid]['depth']++;
        $this->transaction = $DB->start_delegated_transaction();
    }

    /**
     * Commit before releasing the lock.
     */
    public function finish(): void {
        $this->transaction->allow_commit();
        $this->release();
    }

    /**
     * Release the nesting level exactly once.
     */
    private function release(): void {
        if ($this->finished) {
            return;
        }
        $this->finished = true;
        if (--self::$locks[$this->kuetid]['depth'] === 0) {
            self::$locks[$this->kuetid]['lock']->release();
            unset(self::$locks[$this->kuetid]);
        }
    }

    /**
     * Explicit exception path: roll back before another request obtains the lock.
     */
    public function abort(\Throwable $exception): void {
        try {
            $this->transaction->rollback($exception);
        } finally {
            $this->release();
        }
    }

    /**
     * Unfinished database transactions are rolled back by Moodle's transaction manager.
     */
    public function __destruct() {
        $this->release();
    }
}
