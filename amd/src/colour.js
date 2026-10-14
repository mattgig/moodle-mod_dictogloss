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
 * Generic "colour words by closeness to the original" toggle (persisted).
 *
 * A checkbox carrying data-dictogloss-colour-toggle toggles the
 * "colorby-similarity" class on its nearest .mod-dictogloss-colourable ancestor.
 *
 * @module     mod_dictogloss/colour
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export const init = () => {
    document.querySelectorAll('[data-dictogloss-colour-toggle]').forEach((toggle) => {
        const container = toggle.closest('.mod-dictogloss-colourable');
        if (!container) {
            return;
        }
        const storagekey = toggle.getAttribute('data-dictogloss-colour-toggle');

        let enabled = false;
        try {
            enabled = window.localStorage.getItem(storagekey) === '1';
        } catch (error) {
            enabled = false;
        }

        const apply = (state) => {
            container.classList.toggle('colorby-similarity', state);
            toggle.checked = state;
        };

        apply(enabled);

        toggle.addEventListener('change', () => {
            apply(toggle.checked);
            try {
                window.localStorage.setItem(storagekey, toggle.checked ? '1' : '0');
            } catch (error) {
                // Ignore storage errors.
            }
        });
    });
};
