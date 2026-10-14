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
 * Small UI helpers for the Dictogloss teacher controls:
 * destructive-action confirmation and "select all" checkboxes.
 *
 * @module     mod_dictogloss/confirm
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export const init = () => {
    // Ask for confirmation before destructive controls fire.
    document.querySelectorAll('[data-dictogloss-confirm]').forEach((element) => {
        element.addEventListener('click', (event) => {
            const message = element.getAttribute('data-dictogloss-confirm') || '';
            if (message && !window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    // "Select all" toggles every checkbox matching the given selector.
    document.querySelectorAll('[data-dictogloss-selectall]').forEach((toggle) => {
        const selector = toggle.getAttribute('data-dictogloss-selectall');
        if (!selector) {
            return;
        }
        toggle.addEventListener('change', () => {
            document.querySelectorAll(selector).forEach((checkbox) => {
                checkbox.checked = toggle.checked;
            });
        });
    });
};
