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
 * SIS client cache.
 *
 * @package    enrol_ucsfsis
 * @category   external
 * @copyright  2018 The Regents of the University of California
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_ucsfsis;

use curl_cache;

/**
 * SIS client cache class.
 * This class is inherited from curl_cache class for caching.
 *
 * @package    enrol_ucsfsis
 * @copyright  2016 The Regents of the University of California
 * @author     Carson Tam <carson.tam@ucsf.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sis_client_cache extends curl_cache {
    /**
     * Constructor.
     *
     * @param string $module which module is using curl_cache
     * @param int $ttl time to live default to 20 mins (1200 sec)
     */
    public function __construct($module = 'enrol_ucsfsis', $ttl = 1200) {
        parent::__construct($module);
        $this->ttl = $ttl;
    }

    /**
     * Get cached value for a given parameter name.
     *
     * @param mixed $param The parameter name.
     * @return bool|string The parameter value, or FALSE if none could be found.
     */
    public function get($param) {
        $this->cleanup($this->ttl);

        // Sort param so that filename can be consistent.
        ksort($param);

        $filename = 'u'.'_'.md5(serialize($param));
        if (file_exists($this->dir.$filename)) {
            $lasttime = filemtime($this->dir.$filename);
            if (time() - $lasttime > $this->ttl) {
                return false;
            } else {
                $fp = fopen($this->dir.$filename, 'r');
                $size = filesize($this->dir.$filename);
                $content = fread($fp, $size);
                $result = unserialize($content);
                return $result;
            }
        }
        return false;
    }

    /**
     * Set cache value.
     *
     * @param mixed $param The parameter name.
     * @param mixed $val The parameter value.
     */
    public function set($param, $val): void {
        global $CFG;

        // Cache only valid data.
        if (!empty($val)) {
            $obj = json_decode($val);
            if (!empty($obj) && isset($obj->data) && !empty($obj->data)) {
                // Sort param so that filename can be consistent.
                ksort($param);

                $filename = 'u'.'_'.md5(serialize($param));
                $fp = fopen($this->dir.$filename, 'w');
                fwrite($fp, serialize($val));
                fclose($fp);
                @chmod($this->dir.$filename, $CFG->filepermissions);
            }
        }
    }

    /**
     * Delete current user's cache file.
     */
    public function refresh(): void {
        if ($dir = opendir($this->dir)) {
            while (false !== ($file = readdir($dir))) {
                if (!is_dir($file) && $file != '.' && $file != '..') {
                    if (strpos($file, 'u'.'_') !== false) {
                        @unlink($this->dir.$file);
                    }
                }
            }
        }
    }
}
