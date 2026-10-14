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
 * Teacher reconstruction-overview helpers:
 * colour by closeness (persisted), hide names (persisted), shuffle order (persisted)
 * and focus on a single sentence card.
 *
 * @module     mod_dictogloss/variants
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const getStored = (key) => {
    try {
        return window.localStorage.getItem(key) === '1';
    } catch (error) {
        return false;
    }
};

const setStored = (key, value) => {
    try {
        window.localStorage.setItem(key, value ? '1' : '0');
    } catch (error) {
        // Ignore storage errors.
    }
};

export const init = () => {
    const container = document.querySelector('.mod-dictogloss-variants');
    if (!container) {
        return;
    }

    const shuffle = (on) => {
        container.querySelectorAll('.mod-dictogloss-slot .list-group').forEach((list) => {
            const items = Array.from(list.children);
            if (on) {
                const shuffled = items.slice();
                for (let i = shuffled.length - 1; i > 0; i--) {
                    const j = Math.floor(Math.random() * (i + 1));
                    [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
                }
                shuffled.forEach((item) => list.appendChild(item));
            } else {
                items.slice().sort((a, b) => {
                    return Number(a.dataset.optionindex) - Number(b.dataset.optionindex);
                }).forEach((item) => list.appendChild(item));
            }
        });
    };

    const setupToggle = (toggleid, storagekey, classname) => {
        const toggle = document.getElementById(toggleid);
        const state = getStored(storagekey);

        const apply = (on) => {
            container.classList.toggle(classname, on);
            if (toggle) {
                toggle.checked = on;
            }
        };

        apply(state);

        if (toggle) {
            toggle.addEventListener('change', () => {
                apply(toggle.checked);
                setStored(storagekey, toggle.checked);
                if (classname === 'shuffled') {
                    shuffle(toggle.checked);
                }
            });
        }

        return state;
    };

    setupToggle('mod-dictogloss-colorby', 'mod_dictogloss_colorby', 'colorby-similarity');
    setupToggle('mod-dictogloss-hidenames', 'mod_dictogloss_hidenames', 'hidenames');
    if (setupToggle('mod-dictogloss-shuffle', 'mod_dictogloss_shuffle', 'shuffled')) {
        shuffle(true);
    }

    const setFocus = (card) => {
        container.querySelectorAll('.mod-dictogloss-slot').forEach((item) => {
            item.classList.toggle('is-focused', item === card);
            const button = item.querySelector('.mod-dictogloss-focusbtn');
            if (button) {
                button.setAttribute('aria-pressed', item === card ? 'true' : 'false');
            }
        });
        container.classList.toggle('focus-mode', card !== null);
    };

    container.querySelectorAll('.mod-dictogloss-focusbtn').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            const card = button.closest('.mod-dictogloss-slot');
            setFocus(card.classList.contains('is-focused') ? null : card);
        });
    });

    container.addEventListener('click', (event) => {
        if (container.classList.contains('focus-mode') && !event.target.closest('.mod-dictogloss-slot')) {
            setFocus(null);
        }
    });
};
