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
 * Lock the audio recording once the student may no longer listen to it.
 *
 * Re-checks with the server whenever the tab becomes active or the student tries
 * to play the recording, and tears down the player if the current stage no longer
 * permits listening. There is no cross-tab signalling: each tab verifies
 * independently against the server, so this also works across different browsers.
 *
 * @module     mod_dictogloss/audiolock
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

export const init = (cfg) => {
    let locked = false;

    const audios = () => Array.from(document.querySelectorAll('audio'));

    const lock = () => {
        locked = true;
        audios().forEach((audio) => {
            audio.pause();
            audio.removeAttribute('src');
            audio.load();
        });
    };

    const verify = () => Ajax.call([{
        methodname: 'mod_dictogloss_can_listen',
        args: {cmid: cfg.cmid},
    }])[0].then((data) => {
        if (!data.canlisten) {
            lock();
        }
        return data.canlisten;
    }).catch(() => false);

    // Verify whenever this tab becomes active (covers switching back to this window).
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            verify();
        }
    });
    window.addEventListener('focus', () => {
        verify();
    });
    window.addEventListener('pageshow', () => {
        verify();
    });

    // Guard playback itself: if listening is no longer allowed, stop immediately.
    document.addEventListener('play', (event) => {
        const audio = event.target;
        if (!(audio instanceof HTMLAudioElement)) {
            return;
        }
        if (locked) {
            audio.pause();
            return;
        }
        verify().then((ok) => {
            if (!ok) {
                audio.pause();
            }
        });
    }, true);
};
