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
 * Debounced autosave for Dictogloss text areas.
 *
 * @module     mod_dictogloss/notebook
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export const init = (cfg) => {
    const selector = 'textarea[name="keywords"], textarea[name="reconstruction"], textarea[name="followup"]';
    const textareas = document.querySelectorAll(selector);

    textareas.forEach((textarea) => {
        let timer = null;
        const status = document.createElement('span');
        status.className = 'mod-dictogloss-autosave ms-2';
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');
        if (textarea.parentNode) {
            textarea.parentNode.appendChild(status);
        }

        textarea.addEventListener('input', () => {
            window.clearTimeout(timer);
            status.textContent = cfg.saving;
            timer = window.setTimeout(async () => {
                const body = new URLSearchParams({
                    id: cfg.cmid,
                    field: textarea.name,
                    value: textarea.value,
                    sesskey: cfg.sesskey,
                });
                try {
                    const response = await fetch(cfg.endpoint, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: body.toString(),
                    });
                    const data = await response.json();
                    status.textContent = data.ok ? cfg.saved : '';
                } catch (error) {
                    status.textContent = '';
                }
            }, 1200);
        });
    });

    // Record the first time the student plays the audio (listening stage).
    document.querySelectorAll('audio').forEach((audio) => {
        let reported = false;
        audio.addEventListener('play', () => {
            if (reported) {
                return;
            }
            reported = true;
            const body = new URLSearchParams({
                id: cfg.cmid,
                field: 'played',
                sesskey: cfg.sesskey,
            });
            fetch(cfg.endpoint, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: body.toString(),
            }).catch(() => {
                reported = false;
            });
        });
    });
};
