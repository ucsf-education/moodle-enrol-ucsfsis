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
 * OAuth and SIS API client.
 *
 * @package    enrol_ucsfsis
 * @category   external
 * @copyright  2018 The Regents of the University of California
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_ucsfsis;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/oauthlib.php');

use dml_exception;
use moodle_exception;
use moodle_url;
use oauth2_client;
use stdClass;


defined('MOODLE_INTERNAL') || die;

/**
 * OAuth and API client for UCSF SIS Enrolment Services.
 *
 * @package    enrol_ucsfsis
 * @copyright  2016 The Regents of the University of California
 * @author     Carson Tam <carson.tam@ucsf.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ucsfsis_oauth_client extends oauth2_client {
    /** @var string The SIS default base URL. */
    const DEFAULT_HOST = 'https://unified-api.ucsf.edu';

    /** @var string The SIS API path. */
    const API_URL = '/general/sis/1.0';

    /** @var string The OAuth access token path. */
    const TOKEN_URL = '/oauth/1.0/access_token';

    /** @var string The OAuth authorize path. */
    const AUTH_URL = '/oauth/1.0/authorize';

    /** @var string The SIS API base URL. */
    private $baseurl = self::DEFAULT_HOST;

    /** @var string Resource username. */
    private $username = '';

    /** @var string Resource password. */
    private $password = '';

    /** @var string Refresh token. */
    protected $refreshtoken = '';

    /** @var bool Caches http request contents that do not change often like schools, terms, departments, subjects, etc. */
    public $longercache = false;    // Cache for 24 hours.

    /**
     * Returns the auth url for OAuth 2.0 request.
     *
     * @return string The auth URL.
     */
    protected function auth_url(): string {
        return $this->baseurl . self::AUTH_URL;
    }

    /**
     * Returns the token url for OAuth 2.0 request.
     *
     * @return string The token URL.
     */
    protected function token_url(): string {
        return $this->baseurl . self::TOKEN_URL;
    }

    /**
     * Returns the URL for resource API request.
     *
     * @return string The resource API URL.
     */
    public function api_url(): string {
        return $this->baseurl . self::API_URL;
    }

    /**
     * Constructor.
     *
     * @param string $clientid The OAuth client ID.
     * @param string $clientsecret The OAuth client secret.
     * @param string $username The resource username.
     * @param string $password The resource password.
     * @param string|null $host The SIS API host URL, will use default if none is given.
     * @param bool $enablecache TRUE to enable caching, FALSE otherwise.
     * @throws moodle_exception
     */
    public function __construct($clientid, $clientsecret, $username, $password, $host = null, $enablecache = true) {

        // Don't care what the returnurl is right now until we start implementing callbacks.
        $returnurl = new moodle_url(null);
        $scope = '';

        parent::__construct($clientid, $clientsecret, $returnurl, $scope);

        $this->refreshtoken = $this->get_stored_refresh_token();
        $this->username = $username;
        $this->password = $password;

        // We need these in the header all time.
        $this->setHeader(['client_id: '.$clientid, 'client_secret: '.$clientsecret]);

        if (!empty($host)) {
            $this->baseurl = $host;
        }

        if ($enablecache) {
            $this->cache = new sis_client_cache('enrol_ucsfsis');
            $this->longercache = new sis_client_cache('enrol_ucsfsis/daily', 24 * 60 * 60);
        }

    }

    /**
     * Is the user logged in? Note that if this is called
     * after the first part of the authorisation flow the token
     * is upgraded to an access token.
     *
     * @return boolean TRUE if logged in, FALSE otherwise.
     * @throws moodle_exception
     */
    public function is_logged_in() {
        // Has the token expired?
        $accesstoken = $this->get_accesstoken();
        if (isset($accesstoken->expires) && time() >= $accesstoken->expires) {

            // Try to obtain a new access token with a refresh token.
            if (!empty($this->refreshtoken)) {
                if ($this->refresh_token($this->refreshtoken)) {
                    return true;
                }
            }
            // Clear accesstoken since it already expired.
            $this->log_out();
        }

        // We have a token so we are logged in.
        if (!empty($this->get_accesstoken())) {
            return true;
        }

        // If we've been passed then authorization code generated by the
        // authorization server try and upgrade the token to an access token.
        $code = optional_param('oauth2code', null, PARAM_RAW);
        if ($code && $this->upgrade_token($code)) {
            return true;
        }

        // Try log in using username and password to obtain access token.
        if (!empty($this->username) && !empty($this->password)) {
            if ($this->log_in($this->username, $this->password)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Make an HTTP request, adding the access token we have.
     *
     * @param string $url The URL to request.
     * @param array $options Request options.
     * @param mixed $acceptheader Mimetype (as string) or false to skip sending an accept header.
     * @return bool TRUE if the request was successful, FALSE otherwise.
     */
    protected function request($url, $options = [], $acceptheader = 'application/json') {

        // We need these in the header all time.
        $this->setHeader(['client_id: '.$this->get_clientid(), 'client_secret: '.$this->get_clientsecret()]);

        $response = parent::request($url, $options, $acceptheader);

        return $response;
    }

    /**
     * Store a token between requests.
     *
     * @param stdClass|null $token The token object to store or NULL to clear.
     */
    protected function store_token($token): void {
        global $CFG, $SESSION;

        require_once($CFG->libdir.'/moodlelib.php');

        // The $accesstoken class member is private, need to call parent to set it.
        parent::store_token($token);

        if ($token !== null) {
            if (isset($token->token)) {
                set_config('accesstoken', $token->token, 'enrol_ucsfsis');
                set_config('accesstokenexpiretime', $token->expires, 'enrol_ucsfsis');
            }
            // Remove it from $SESSION, which was set by parent.
            $name = $this->get_tokenname();
            unset($SESSION->{$name});
        } else {
            set_config('accesstoken', null, 'enrol_ucsfsis');
            set_config('accesstokenexpiretime', null, 'enrol_ucsfsis');
        }
    }

    /**
     * Store refresh token between requests.
     *
     * @param stdClass|null $token The token object to store or NULL to clear.
     */
    protected function store_refresh_token($token): void {
        global $CFG;

        require_once($CFG->libdir.'/moodlelib.php');

        $this->refreshtoken = $token;

        if (!empty($token)) {
            set_config('refreshtoken', $token, 'enrol_ucsfsis');
        } else {
            set_config('refreshtoken', null, 'enrol_ucsfsis');
        }
    }

    /**
     * Retrieve a token stored.
     *
     * @return stdClass|null The token object.
     * @throws dml_exception
     */
    protected function get_stored_token() {
        global $CFG;

        require_once($CFG->libdir.'/moodlelib.php');

        $accesstoken = new stdClass();
        $accesstoken->token = get_config('enrol_ucsfsis', 'accesstoken');
        $accesstoken->expires = get_config('enrol_ucsfsis', 'accesstokenexpiretime');

        if (!empty($accesstoken->token)) {
            return $accesstoken;
        }

        return null;
    }

    /**
     * Retrieve a refresh token stored.
     *
     * @return string|null The token string.
     * @throws dml_exception
     */
    protected function get_stored_refresh_token() {
        global $CFG;

        require_once($CFG->libdir.'/moodlelib.php');

        $refreshtoken = get_config('enrol_ucsfsis', 'refreshtoken');

        if (!empty($refreshtoken)) {
            return $refreshtoken;
        }

        return null;
    }

    /**
     * Get refresh token.
     *
     * This is just a getter to read the private property.
     *
     * @return string
     */
    public function get_refreshtoken() {
        return $this->refreshtoken;
    }

    /**
     * Should HTTP GET be used instead of POST?
     * Some APIs do not support POST and want oauth to use
     * GET instead (with the auth_token passed as a GET param).
     *
     * @return bool TRUE if GET should be used.
     * @throws dml_exception
     */
    protected function use_http_get(): bool {
        global $CFG;

        require_once($CFG->libdir.'/moodlelib.php');

        $httpmethod = get_config('enrol_ucsfsis', 'requestmethod');

        if (!empty($httpmethod)) {
            return ($httpmethod === 0);
        }
        return true;
    }

    /**
     * Refresh the access token from a refresh token
     *
     * @param string $code the token used to refresh the access token
     * @return boolean true if token is refreshed successfully
     * @throws moodle_exception
     */
    protected function refresh_token($code) {

        $params = [
            'client_id' => $this->get_clientid(),
            'client_secret' => $this->get_clientsecret(),
            'grant_type' => 'refresh_token',
            'refresh_token' => $code,
        ];

        // Requests can either use http GET or POST.
        if ($this->use_http_get()) {
            $response = $this->get($this->token_url(), $params);
        } else {
            $response = $this->post($this->token_url(), $this->build_post_data($params));
        }

        if (!$this->info['http_code'] === 200) {
            throw new moodle_exception('Could not refresh access token.');
        }

        $r = json_decode($response);

        if (!isset($r->access_token)) {
            return false;
        }

        // Store the token an expiry time.
        $accesstoken = new stdClass();
        $accesstoken->token = $r->access_token;
        $accesstoken->expires = (time() + ($r->expires_in - 10)); // Expires 10 seconds before actual expiry.
        $this->store_token($accesstoken);

        // Store the refresh token.
        if (isset($r->refresh_token)) {
            $this->store_refresh_token($r->refresh_token);
        }

        // Clear cache every time we get a new token.
        if (isset($this->cache)) {
            $this->cache->refresh();
        }
        if (isset($this->longercache)) {
            $this->longercache->refresh();
        }

        return true;
    }

    /**
     * Upgrade an authorization token from oauth 2.0 to an access token.
     *
     * @param string $username The username.
     * @param string $password The password.
     * @return boolean TRUE if token is upgraded successfully, FALSE otherwise.
     * @throws moodle_exception
     */
    public function log_in($username, $password): bool {

        $params = [
            'client_id' => $this->get_clientid(),
            'client_secret' => $this->get_clientsecret(),
            'grant_type' => 'password',
            'username' => $username,
            'password' => $password,
        ];

        // Requests can either use http GET or POST.
        // Unified-api only works with GET for now.
        if ($this->use_http_get()) {
            $response = $this->get($this->token_url(), $params);
        } else {
            $response = $this->post($this->token_url(), $this->build_post_data($params));
        }

        if (!$this->info['http_code'] === 200) {
            throw new moodle_exception('Could not upgrade oauth token');
        }

        $r = json_decode($response);

        if (!isset($r->access_token)) {
            return false;
        }

        // Store the token an expiry time.
        $accesstoken = new stdClass();
        $accesstoken->token = $r->access_token;
        $accesstoken->expires = (time() + ($r->expires_in - 10)); // Expires 10 seconds before actual expiry.
        $this->store_token($accesstoken);

        // Store the refresh token.
        if (isset($r->refresh_token)) {
            $this->store_refresh_token($r->refresh_token);
        }

        // Clear cache every time we log in and get a new token.
        if (isset($this->cache)) {
            $this->cache->refresh();
        }

        return true;
    }

    /**
     * Retrieve the data object from the return result from the URI.
     * Anything other than data will return false.
     *
     * @param string $uri The URI to the resources.
     * @return array|bool An array objects in data retrieved from the URI, or false when there's an error.
     */
    protected function get_data($uri) {
        $result = $this->get($uri);

        if (empty($result)) {
            return false;
        }

        $result = json_decode($result);

        if (isset($result->data)) {
            return $result->data;
        }

        return false;
    }

    /**
     * Make multiple calls to the URI until a complete set of  data are retrieved from the URI.
     * Return false when there's an error.
     *
     * @param string $uri The URI to the resources.
     * @return array|bool an array objects in data retrieved from the URI, or false when there's an error.
     */
    public function get_all_data($uri) {
        $limit = 100;
        $offset = 0;
        $data = null;
        $expectedlistsize = null;
        $retdata = false;

        $queryprefix = strstr($uri, '?') ? '&' : '?';

        do {
            $modifieduri = $uri . $queryprefix."limit=$limit&offset=$offset";

            $result = $this->get($modifieduri);
            $response = $result; // Save response for debugging.

            if (empty($result)) {
                debugging("API call '$modifieduri' returned empty.");
                return false;
            }

            $result = json_decode($result);
            if (isset($result->error)) {
                preg_match('/(Offset \[\d+\] is larger than list size: )([0-9]+)/', $result->error, $errors);
                if (!empty($errors) && isset($errors[2])) {
                    // End of list has reached.
                    $data = null;
                    $expectedlistsize = $errors[2];
                } else {
                    // Return false on any other error.
                    debugging("API call '$modifieduri' returned error: {$result->error}");
                    return false;
                }
            } else {
                if (isset($result->data)) {
                    $data = $result->data;

                    if (!empty($data)) {
                        if (empty($retdata)) {
                            $retdata = [];
                        }
                        $retdata = array_merge($retdata, $data);
                        $offset += $limit;
                    }
                } else {
                    // Something went wrong, no data, no error.
                    debugging("API call '$modifieduri' returned unexpected response: {$response}");

                    return false;
                }
            }

        } while (!empty($data));

        // Double check list size (if available).
        if (!empty($expectedlistsize)) {
            if ($expectedlistsize == count($retdata)) {
                return $retdata;
            } else {
                debugging(
                    "API call '$modifieduri' did not return same number of items as it claims"
                    . " which is $expectedlistsize, actual is "
                    . count($retdata)
                    . "."
                );

                return false;
            }
        }

        $retdata = $this->trim_data($retdata);
        return $retdata;
    }

    /**
     * Get active school terms data in reverse chronological order.
     * Cache for 24 hours, don't expect this to change very often.
     *
     * @return array|bool Array of term objects, or false if none could be found.
     */
    public function get_active_terms() {
        // Save short term cache.
        $cache = $this->cache;
        if (isset($this->cache)) {
            $this->cache = $this->longercache;
        }

        $uri = $this->api_url().'/terms?sort=-termStartDate';
        $terms = $this->get_all_data($uri);

        // Restore short term cache.
        if (isset($cache)) {
            $this->cache = $cache;
        }

        // Remove terms that have fileDateForEnrollment = NULL.
        if (!empty($terms)) {
            $ret = [];
            foreach ($terms as $term) {
                if (!empty($term->fileDateForEnrollment)) {
                    $ret[] = $term;
                }
            }

            return $ret;
        }

        return false;
    }

    /**
     * Get all available subjects in a term ordered by name.
     * Cache for 24 hours, don't expect this to change very often.
     *
     * @param string $termid The term ID.
     * @return array Array of subject objects.
     */
    public function get_subjects_in_term($termid) {
        if (isset($this->cache)) {
            // Save short term cache.
            $cache = $this->cache;
            $this->cache = $this->longercache;
        }

        $uri = $this->api_url()."/terms/$termid/subjects?sort=name";
        $ret = $this->get_all_data($uri);

        // Restore short term cache.
        if (isset($cache)) {
            $this->cache = $cache;
        }

        return $ret;
    }

    /**
     * Get information on a single course by course id.
     *
     * @param string $courseid The course ID.
     * @return stdClass|bool The requested course object, or false if none could be found.
     */
    public function get_course($courseid) {
        $courseid = $courseid;
        $uri = $this->api_url()."/courses/$courseid";
        $ret = $this->get_data($uri);

        return $ret;
    }

    /**
     * Get all available courses in a term ordered by courseNumber.
     * Cache for 24 hours, don't expect this to change very often.
     *
     * @param string $termid The term ID.
     * @return array Array of course objects.
     */
    public function get_courses_in_term($termid) {
        // Save short term cache.
        $cache = $this->cache;
        if (isset($this->cache)) {
            $this->cache = $this->longercache;
        }

        $uri = $this->api_url()."/terms/$termid/courses?sort=courseNumber";
        $ret = $this->get_all_data($uri);

        // Restore short term cache.
        if (isset($cache)) {
            $this->cache = $cache;
        }

        return $ret;
    }

    /**
     * Get enrolment list from a course id.
     *
     * @param int $courseid The course ID.
     * @return array|bool An array of enrollment object or false if error.
     */
    public function get_course_enrollment($courseid) {

        // Never cache the enrollment data.
        $cache = $this->cache;
        $this->cache = null;

        $uri = $this->api_url()."/courseEnrollments?courseId=$courseid";
        $enrollment = $this->get_all_data($uri);

        // Restore the cache object.
        $this->cache = $cache;

        if (empty($enrollment)) {
            return $enrollment;
        }

        // Flatten enrollment objects (Simplify SIS return data to only what we need.).
        $enrollist = [];

        foreach ($enrollment as $e) {
            if (!empty($e->student) && !empty($e->student->empno)) {
                $obj = new stdClass();
                $obj->ucid = $e->student->empno;

                if ($e->courseCodeForCode1 === 'W' || $e->courseCodeForCode2 === 'W') {
                    $obj->status = ENROL_USER_SUSPENDED;
                    $enrollist[$e->student->empno] = $obj;
                } else {
                    switch ($e->status) {
                        case "A":
                            $obj->status = ENROL_USER_ACTIVE;
                            $enrollist[$e->student->empno] = $obj;
                        break;
                        case "I":
                            if (!isset($enrollist[$e->student->empno])) {
                                $obj->status = ENROL_USER_SUSPENDED;
                                $enrollist[$e->student->empno] = $obj;
                            }
                        break;
                        case "S":
                        case "F":
                        default:
                            // Do nothing.
                    }
                }
            }
        }

        if (!empty($enrollist)) {
            return $enrollist;
        } else {
            return false;
        }
    }

    /**
     * Recursively trims whitespace off of all text in given input.
     *
     * @param mixed $data The raw data.
     * @return array The trimmed data.
     */
    public static function trim_data($data) {

        if (is_string($data)) {
            $data = trim($data);
        } else if (is_object($data)) {
            $properties = get_object_vars($data);
            if ($properties) {
                foreach ($properties as $key => $value) {
                    $data->$key = self::trim_data($value);
                }
            }
        } else if (is_array($data)) {
            array_walk($data,  [self::class, 'trim_data']);
        }

        return $data;
    }
}
