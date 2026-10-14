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
 * Secondary navigation view for the Dictogloss activity.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_dictogloss\navigation\views;

use core\navigation\views\secondary as core_secondary;
use moodle_url;
use navigation_node;

defined('MOODLE_INTERNAL') || die();

/**
 * Adds the teacher-facing Dictogloss pages to the activity secondary navigation.
 *
 * Core builds the module secondary navigation from the settings navigation, but any node that
 * is appended after the built-in ones can be pushed into the "More" menu once the five-node
 * display limit is reached. This class guarantees the Dictogloss pages are always offered as
 * tabs, regardless of that limit.
 */
class secondary extends core_secondary {

    /**
     * Initialise the secondary navigation.
     *
     * @return void
     */
    public function initialise(): void {
        global $DB;

        parent::initialise();

        // Note: $this->page->cm and ->course are magic properties backed by __get() with no
        // __isset(), so empty() would always evaluate to true here. Use truthy checks instead.
        if (!$this->page->cm || !$this->page->course) {
            return;
        }

        $cm = $this->page->cm;
        $context = \core\context\module::instance($cm->id);

        $instance = $DB->get_record('dictogloss', ['id' => $cm->instance]);
        if (!$instance) {
            return;
        }

        $activity = new \mod_dictogloss\activity($cm, $this->page->course, $instance);
        foreach (\mod_dictogloss\local\nav_tabs::get($activity, $context) as $tab) {
            $this->add_or_reveal($tab['key'], $tab['label'], $tab['url']);
        }
    }

    /**
     * Add a tab, or reveal an existing one that core moved into the "More" menu.
     *
     * @param string $key The navigation node key.
     * @param string $text The node label.
     * @param moodle_url $url The node target.
     * @return void
     */
    protected function add_or_reveal(string $key, string $text, moodle_url $url): void {
        $existing = $this->get($key);
        if ($existing) {
            $existing->set_force_into_more_menu(false);
            $existing->action = $url;
        } else {
            $existing = navigation_node::create($text, $url, navigation_node::TYPE_SETTING, null, $key);
            $this->add_node($existing);
        }

        if (!empty($this->page->url) && $this->page->url->compare($url, URL_MATCH_BASE)) {
            $existing->make_active();
        }
    }
}
