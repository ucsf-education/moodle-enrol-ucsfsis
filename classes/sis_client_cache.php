<?php
namespace enrol_ucsfsis;

use curl_cache;

defined('MOODLE_INTERNAL') || die;

/**
 * This class is inherited from curl_cache class for caching, use case:
 *
 * @package    enrol_ucsfsis
 * @copyright  2016 The Regents of the University of California
 * @author     Carson Tam <carson.tam@ucsf.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sis_client_cache extends curl_cache {
    /**
     * Constructor
     *
     * @global \stdClass $CFG
     * @param string $module which module is using curl_cache
     * @param int $ttl time to live default to 20 mins (1200 sec)
     */
    public function __construct($module = 'enrol_ucsfsis', $ttl = 1200) {
        parent::__construct($module);
        $this->ttl = $ttl;
    }

    /**
     * Get cached value
     *
     * @global \stdClass $CFG
     * @param mixed $param
     * @return bool|string
     */
    public function get($param) {
        global $CFG;
        $this->cleanup($this->ttl);

        // Sort param so that filename can be consistent.
        ksort($param);

        $filename = 'u'.'_'.md5(serialize($param));
        if(file_exists($this->dir.$filename)) {
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
     * Set cache value
     *
     * @global object $CFG
     * @param mixed $param
     * @param mixed $val
     */
    public function set($param, $val) {
        global $CFG;

        // Cache only valid data
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
     * delete current user's cache file
     */
    public function refresh() {
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
