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
 * Reload the keyword word cloud without reloading the page.
 *
 * Uses event delegation so it keeps working after the section is replaced.
 *
 * @module     mod_dictogloss/wordcloud
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export const init = (cfg) => {
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('#' + cfg.buttonid);
        if (!button) {
            return;
        }
        event.preventDefault();
        if (button.disabled) {
            return;
        }

        button.disabled = true;
        button.innerHTML = cfg.refreshing;

        const url = new URL(cfg.endpoint, window.location.origin);
        url.searchParams.set('id', cfg.cmid);
        url.searchParams.set('sesskey', cfg.sesskey);

        try {
            const response = await fetch(url.toString(), {headers: {'X-Requested-With': 'XMLHttpRequest'}});
            if (response.ok) {
                const container = document.getElementById(cfg.containerid);
                if (container) {
                    container.outerHTML = await response.text();
                    const newsection = document.getElementById(cfg.containerid);
                    if (newsection) {
                        const heading = newsection.querySelector('h3');
                        if (heading) {
                            heading.setAttribute('tabindex', '-1');
                            heading.focus();
                        }
                    }
                }
            }
        } catch (error) {
            // Keep the current cloud on failure.
        } finally {
            const current = document.getElementById(cfg.buttonid);
            if (current) {
                current.disabled = false;
            }
        }
    });
};
