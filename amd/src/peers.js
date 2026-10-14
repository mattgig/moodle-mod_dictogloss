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
 * Pull the group reconstructions panel, handle the cooldown countdown, tabs,
 * "Add" buttons (insert at cursor) and the optional copy guard.
 *
 * @module     mod_dictogloss/peers
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export const init = (cfg) => {
    const button = document.getElementById(cfg.buttonid);
    const container = document.getElementById(cfg.containerid);
    if (!button || !container) {
        return;
    }

    const label = button.innerHTML;
    let timer = null;

    const startCooldown = (seconds) => {
        let remaining = seconds;
        window.clearTimeout(timer);
        button.disabled = true;
        const tick = () => {
            if (remaining <= 0) {
                button.disabled = false;
                button.innerHTML = label;
                return;
            }
            button.innerHTML = cfg.waitlabel.replace('{$a}', remaining);
            remaining--;
            timer = window.setTimeout(tick, 1000);
        };
        tick();
    };

    const insertText = (text) => {
        const textarea = document.querySelector('textarea[name="reconstruction"]');
        if (!textarea) {
            return;
        }
        const start = textarea.selectionStart === null ? textarea.value.length : textarea.selectionStart;
        const end = textarea.selectionEnd === null ? textarea.value.length : textarea.selectionEnd;
        const before = textarea.value.slice(0, start);
        const after = textarea.value.slice(end);
        const separator = (before && !/\s$/.test(before)) ? ' ' : '';
        const inserted = separator + text;
        textarea.value = before + inserted + after;
        const caret = (before + inserted).length;
        textarea.selectionStart = caret;
        textarea.selectionEnd = caret;
        textarea.focus();
        textarea.dispatchEvent(new Event('input', {bubbles: true}));
    };

    const showMessage = (message) => {
        container.innerHTML = '';
        const paragraph = document.createElement('p');
        paragraph.className = 'text-muted';
        paragraph.textContent = message;
        container.appendChild(paragraph);
    };

    // Event delegation for the (dynamically injected) panel.
    container.addEventListener('click', (event) => {
        const addbutton = event.target.closest('.mod-dictogloss-peer-add');
        if (addbutton) {
            event.preventDefault();
            insertText(addbutton.getAttribute('data-text') || '');
            return;
        }

        const tab = event.target.closest('[data-dictogloss-peertab]');
        if (tab) {
            event.preventDefault();
            const name = tab.getAttribute('data-dictogloss-peertab');
            container.querySelectorAll('[data-dictogloss-peertab]').forEach((item) => {
                const selected = (item === tab);
                item.classList.toggle('active', selected);
                item.setAttribute('aria-selected', selected ? 'true' : 'false');
            });
            container.querySelectorAll('[data-dictogloss-peertabpane]').forEach((pane) => {
                pane.classList.toggle('d-none', pane.getAttribute('data-dictogloss-peertabpane') !== name);
            });
        }
    });

    const blockCopy = (event) => {
        if (container.classList.contains('nocopy')) {
            event.preventDefault();
        }
    };
    container.addEventListener('copy', blockCopy);
    container.addEventListener('cut', blockCopy);

    button.addEventListener('click', async (event) => {
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
            const data = await response.json();
            if (data.ok) {
                container.innerHTML = data.html;
                const firsttab = container.querySelector('[data-dictogloss-peertab]');
                if (firsttab) {
                    firsttab.focus();
                }
                button.innerHTML = label;
                if (data.cooldown > 0) {
                    startCooldown(data.cooldown);
                } else {
                    button.disabled = false;
                }
            } else {
                showMessage(data.message);
                if (data.reason === 'cooldown' && data.wait > 0) {
                    startCooldown(data.wait);
                } else {
                    button.disabled = false;
                    button.innerHTML = label;
                }
            }
        } catch (error) {
            button.disabled = false;
            button.innerHTML = label;
        }
    });
};
