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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Availability (hqavailable) utilities for Hot Question.
 *
 * 20210225 Started adding new and moving old functions from lib.php and locallib.php to here.
 *
 * @package   mod_hotquestion
 * @copyright AL Rachels (drachels@drachels.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_hotquestion\local;

defined('MOODLE_INTERNAL') || die(); // @codingStandardsIgnoreLine

use stdClass;
use context_module;


/**
 * Utility class for Hot Question hqavailable.
 *
 * Created 20210226.
 * @package   mod_hotquestion
 * @copyright AL Rachels (drachels@drachels.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hqavailable {
    /**
     * Return whether the hotquestion is available due to open time and close time.
     *
     * @return bool
     */
    public static function is_hotquestion_active($hq) {
        $open = ($hq->instance->timeopen !== '0' && time() > $hq->instance->timeopen) || $hq->instance->timeopen === '0';
        $close = ($hq->instance->timeclose !== '0' && time() < $hq->instance->timeclose) || $hq->instance->timeclose === '0';
        return $open && $close;
    }

    /**
     * @return bool
     */
    public static function is_hotquestion_ended($hq) {
        return $hq->instance->timeclose !== 0 && time() > $hq->instance->timeclose;
    }

    /**
     * @return bool
     */
    public static function is_hotquestion_yet_to_start($hq) {
        return $hq->instance->timeopen !== 0 && time() < $hq->instance->timeopen;
    }
}
