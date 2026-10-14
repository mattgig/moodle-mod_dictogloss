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
 * Upgrade steps for the Dictogloss activity module.
 *
 * @package    mod_dictogloss
 * @copyright  2026 Dictogloss contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the Dictogloss module.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool
 */
function xmldb_dictogloss_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026100413) {
        // Add the "timeplayed" field used to track the listening stage.
        $table = new xmldb_table('dictogloss_submissions');
        $field = new xmldb_field('timeplayed', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timefollowup');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2026100413, 'dictogloss');
    }

    if ($oldversion < 2026100420) {
        // Add the audio replay settings (permanent setting + live teacher override).
        $table = new xmldb_table('dictogloss');
        $field = new xmldb_field('allowaudioreplay', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0',
            'allowfollowup');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('replayopen', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0',
            'allowaudioreplay');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2026100420, 'dictogloss');
    }

    if ($oldversion < 2026100421) {
        // Add the "hold reconstruction" setting and its live teacher override.
        $table = new xmldb_table('dictogloss');
        $field = new xmldb_field('holdreconstruction', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0',
            'replayopen');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('reconstructionopen', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0',
            'holdreconstruction');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2026100421, 'dictogloss');
    }

    if ($oldversion < 2026100422) {
        // Student-paced "listen again" step before reconstruction.
        $table = new xmldb_table('dictogloss');
        $field = new xmldb_field('replaystage', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0',
            'reconstructionopen');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('dictogloss_submissions');
        $field = new xmldb_field('timereconstructionready', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0',
            'timefollowup');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2026100422, 'dictogloss');
    }

    if ($oldversion < 2026100500) {
        // Collaborative "group reconstructions" feature.
        $table = new xmldb_table('dictogloss');
        $fields = [
            new xmldb_field('peerscope', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'off', 'replaystage'),
            new xmldb_field('peersource', XMLDB_TYPE_CHAR, '12', null, XMLDB_NOTNULL, null, 'both', 'peerscope'),
            new xmldb_field('peercooldown', XMLDB_TYPE_INTEGER, '6', null, XMLDB_NOTNULL, null, '60', 'peersource'),
            new xmldb_field('peermindraft', XMLDB_TYPE_INTEGER, '6', null, XMLDB_NOTNULL, null, '20', 'peercooldown'),
            new xmldb_field('peersshownames', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'peermindraft'),
            new xmldb_field('allowcopy', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1', 'peersshownames'),
            new xmldb_field('peeropen', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'allowcopy'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        $table = new xmldb_table('dictogloss_submissions');
        $fields = [
            new xmldb_field('timepulledpeers', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timeplayed'),
            new xmldb_field('peerpullcount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timepulledpeers'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_mod_savepoint(true, 2026100500, 'dictogloss');
    }

    if ($oldversion < 2026100501) {
        // Final "hand in" of the whole contribution.
        $table = new xmldb_table('dictogloss_submissions');
        $field = new xmldb_field('timefinalised', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0',
            'peerpullcount');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2026100501, 'dictogloss');
    }

    if ($oldversion < 2026100508) {
        // Random assignment option for the word cloud source and peer sharing.
        $table = new xmldb_table('dictogloss');
        $fields = [
            new xmldb_field('randomshared', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'peeropen'),
            new xmldb_field('wordcloudrandomsize', XMLDB_TYPE_INTEGER, '6', null, XMLDB_NOTNULL, null, '3',
                'randomshared'),
            new xmldb_field('wordcloudrandompool', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'class',
                'wordcloudrandomsize'),
            new xmldb_field('wordcloudrandomgroupingid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0',
                'wordcloudrandompool'),
            new xmldb_field('wordcloudrandomgroupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0',
                'wordcloudrandomgroupingid'),
            new xmldb_field('peersrandomsize', XMLDB_TYPE_INTEGER, '6', null, XMLDB_NOTNULL, null, '2',
                'wordcloudrandomgroupid'),
            new xmldb_field('peersrandompool', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'class',
                'peersrandomsize'),
            new xmldb_field('peersrandomgroupingid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0',
                'peersrandompool'),
            new xmldb_field('peersrandomgroupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0',
                'peersrandomgroupingid'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        $table = new xmldb_table('dictogloss_randompartners');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('dictogloss', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('feature', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'wordcloud');
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('partnerid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('dictogloss', XMLDB_KEY_FOREIGN, ['dictogloss'], 'dictogloss', ['id']);
            $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

            $table->add_index('dictoglossfeatureuser', XMLDB_INDEX_NOTUNIQUE, ['dictogloss', 'feature', 'userid']);
            $table->add_index('dictoglossuser', XMLDB_INDEX_NOTUNIQUE, ['dictogloss', 'userid']);

            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2026100508, 'dictogloss');
    }

    if ($oldversion < 2026100509) {
        // Dedicated shared random-source pool for the "use the same source" option.
        $table = new xmldb_table('dictogloss');
        $fields = [
            new xmldb_field('randomsharedpool', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'class',
                'randomshared'),
            new xmldb_field('randomsharedgroupingid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0',
                'randomsharedpool'),
            new xmldb_field('randomsharedgroupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0',
                'randomsharedgroupingid'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_mod_savepoint(true, 2026100509, 'dictogloss');
    }

    if ($oldversion < 2026100517) {
        // Prevent duplicate random-partner rows from concurrent generation.
        $table = new xmldb_table('dictogloss_randompartners');
        $index = new xmldb_index('dictoglossfeatureuserpartner', XMLDB_INDEX_UNIQUE,
            ['dictogloss', 'feature', 'userid', 'partnerid']);
        if (!$dbman->index_exists($table, $index)) {
            // Remove any duplicates created before the unique index existed, keeping the oldest row.
            $duplicates = $DB->get_records_sql(
                "SELECT MIN(id) AS keepid, dictogloss, feature, userid, partnerid
                   FROM {dictogloss_randompartners}
               GROUP BY dictogloss, feature, userid, partnerid
                 HAVING COUNT(*) > 1");
            foreach ($duplicates as $duplicate) {
                $DB->delete_records_select('dictogloss_randompartners',
                    'dictogloss = ? AND feature = ? AND userid = ? AND partnerid = ? AND id <> ?',
                    [$duplicate->dictogloss, $duplicate->feature, $duplicate->userid,
                        $duplicate->partnerid, $duplicate->keepid]);
            }
            $dbman->add_index($table, $index);
        }
        upgrade_mod_savepoint(true, 2026100517, 'dictogloss');
    }

    return true;
}
