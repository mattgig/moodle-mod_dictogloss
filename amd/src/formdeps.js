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
 * Dictogloss activity form helpers.
 *
 * Hides the "Random assignment" section unless the word cloud source or the group
 * reconstructions scope is actually set to random assignment. This is an OR condition,
 * which the standard form hideIf dependencies cannot express.
 *
 * @module     mod_dictogloss/formdeps
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export const init = () => {
    const form = document.querySelector('form.mform') || document;
    const wordcloud = form.querySelector('select[name="wordcloudmode"]');
    const shownotes = form.querySelector('select[name="shownotes"]');
    const peerscope = form.querySelector('select[name="peerscope"]');
    const anchor = form.querySelector('[name="randomshared"]');

    if (!wordcloud || !peerscope || !anchor) {
        return;
    }

    const section = anchor.closest('fieldset') || anchor.closest('.fitem');
    if (!section) {
        return;
    }

    const update = () => {
        const notesOn = !shownotes || shownotes.value === '1';
        const wordcloudRandom = wordcloud.value === 'random' && notesOn;
        const anyRandom = wordcloudRandom || peerscope.value === 'random';
        section.hidden = !anyRandom;
        section.style.display = anyRandom ? '' : 'none';
    };

    [wordcloud, shownotes, peerscope].forEach((element) => {
        if (element) {
            element.addEventListener('change', update);
        }
    });

    update();
};
