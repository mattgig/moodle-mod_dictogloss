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
 * Web service definitions for the Dictogloss activity module.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_dictogloss_save_field' => [
        'classname' => 'mod_dictogloss\external\save_field',
        'description' => 'Autosave a single field of the current user\'s Dictogloss contribution.',
        'type' => 'write',
        'ajax' => true,
    ],
    'mod_dictogloss_get_wordcloud' => [
        'classname' => 'mod_dictogloss\external\get_wordcloud',
        'description' => 'Return the freshly rendered keyword word cloud for the current user.',
        'type' => 'read',
        'ajax' => true,
    ],
    'mod_dictogloss_get_peers' => [
        'classname' => 'mod_dictogloss\external\get_peers',
        'description' => 'Return the group reconstructions panel for the current user.',
        'type' => 'read',
        'ajax' => true,
    ],
    'mod_dictogloss_can_listen' => [
        'classname' => 'mod_dictogloss\external\can_listen',
        'description' => 'Report whether the current user may play the audio recording.',
        'type' => 'read',
        'ajax' => true,
    ],
];
