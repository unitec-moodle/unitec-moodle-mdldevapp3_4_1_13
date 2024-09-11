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
 * Atto text editor integration
 * This class initializes the LTI authentication information
 *
 * @package   atto_echo360attoplugin
 * @copyright 2020 Echo360 Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace Echo360;

use \Exception as Exception;
use \DateTime as DateTime;

defined('MOODLE_INTERNAL') || die();

const LTI_ROLE_REQUEST_ADMINISTRATOR = 'urn:lti:sysrole:ims/lis/Administrator';
const LTI_ROLE_REQUEST_INSTRUCTOR = 'urn:lti:role:ims/lis/Instructor';
const LTI_ROLE_REQUEST_LEARNER = 'urn:lti:role:ims/lis/Learner';

const LTI_ADMIN = 'admin';
const LTI_ADMINISTRATOR = 'administrator';
const LTI_FACULTY = 'faculty';
const LIS_SYSTEM_ADMIN = 'urn:lti:sysrole:ims/lis/administrator';
const LIS_INSTITUTION_ADMIN = 'urn:lti:instrole:ims/lis/administrator';

const LTI_INSTRUCTOR = 'instructor';
const LTI_TEACHER = 'teacher';
const LTI_EDITING_TEACHER = 'editingteacher';
const LTI_NON_EDITING_TEACHER = 'non-editing teacher';
const LTI_COURSE_CREATOR = 'coursecreator';
const LTI_MANAGER = 'manager';
const LTI_MENTOR = 'urn:lti:role:ims/lis/mentor';
const LTI_CONTENT_DEVELOPER = 'urn:lti:role:ims/lis/contentdeveloper';
const LTI_TEACHING_ASSISTANT = 'urn:lti:role:ims/lis/teachingassistant';
const LTI_GRADER = "urn:lti:role:ims/lis/teachingassistant/grader";

const LTI_STUDENT = 'student';

const LTI_ADMIN_ROLES = array(
  LTI_ADMIN,
  LTI_ADMINISTRATOR,
  LIS_SYSTEM_ADMIN,
  LIS_INSTITUTION_ADMIN
);

const LTI_INSTRUCTOR_ROLES = array(
  LTI_FACULTY,
  LTI_INSTRUCTOR,
  LTI_TEACHER,
  LTI_EDITING_TEACHER,
  LTI_NON_EDITING_TEACHER,
  LTI_MANAGER,
  LTI_MENTOR,
  LTI_CONTENT_DEVELOPER,
  LTI_TEACHING_ASSISTANT,
  LTI_GRADER,
  LTI_COURSE_CREATOR
);

const LTI_STUDENT_ROLES = array(
  LTI_STUDENT
);

class LtiConfiguration {

    private $launchurl;
    private $consumerkey;
    private $secretkey;
    private $rolesarray;
    private $context;
    private $pagetype;

    /**
     * The lti_configuration constructor.
     *
     * @param  $context     array - the course context, https://docs.moodle.org/34/en/Context
     * @param  $pluginname
     * @throws \Exception
     */
    public function __construct($context, $pluginname, $pagetype = '') {
        global $USER, $CFG;
        if (is_file($CFG->dirroot . '/mod/lti/locallib.php')) {
            include_once($CFG->dirroot . '/mod/lti/locallib.php');
        }

        if (empty($context)) {
            throw new Exception('[' . $pluginname . '] No context provided to constructor');
        }

        // LMS configuration settings generated by Echo360 admin configurations page.
        $this->launch_url = self::get_plugin_config('hosturl', $pluginname);
        $this->consumer_key = self::get_plugin_config('consumerkey', $pluginname);
        $this->secret_key = self::get_plugin_config('sharedsecret', $pluginname);

        $this->roles_array = self::get_role_names($context, $USER, $pluginname);
        $this->context = $context;
        $this->pagetype = $pagetype;
    }

    /**
     * Use predefined get_config method to retrieve value set by admin user;
     * performs validation and returns the value.
     *
     * @param  $key         String the key we want to retrieve
     * @param  $pluginname string the name of the plugin
     * @return mixed
     * @throws \Exception
     */
    public static function get_plugin_config($key, $pluginname) {
        $value = get_config($pluginname, $key);
        if (empty($value)) {
            throw new Exception('[' . $pluginname . '] Configuration Missing: ' . (string) $key);
        }
        return $value;
    }

    /**
     * Get user role names for user
     *
     * @param    $context
     * @param    $user
     * @param    $pluginname
     * @return   mixed
     * @throws   Exception
     * @internal param $context
     */
    public static function get_role_names($context, $user, $pluginname) {
        global $COURSE;
        $rolenames = array();
        // Check IMS Roles.
        $imsroles = explode(",", lti_get_ims_role($user, '', $COURSE->id, ''));
        foreach ($imsroles as $imsrole) {
            array_push($rolenames, $imsrole);
        }

        // Check Context Roles.
        $roles = get_user_roles($context, $user->id);
        foreach ($roles as $role) {
            array_push($rolenames, role_get_name($role, $context));
        }
        // Check Admins.
        $admins = get_admins();
        foreach ($admins as $admin) {
            if ($user->id == $admin->id) {
                array_push($rolenames, 'Admin');
                break;
            }
        }
        if (empty($rolenames)) {
            throw new Exception('[' . $pluginname . '] No user roles found for user: ' . $user->id . '.');
        }
        return $rolenames;
    }

    /**
     * In OAuth, request parameters must be sorted by name
     *
     * @param  $assocarray
     * @return array $launch_params The parameters sorted alphabetically
     */
    public static function sort_array_alphabetically(array $assocarray) {
        $params = array();
        $keys = array_keys($assocarray);
        sort($keys);
        foreach ($keys as $key) {
            $urlencodedparam = rawurlencode($assocarray[$key]);
            array_push($params, $key . '=' . $urlencodedparam);
        }
        return $params;
    }

    /**
     * Simple helper to convert a PHP object to a JSON object
     *
     * @param  $object object
     * @return string $json
     */
    public static function object_to_json($object) {
        $array = (array) $object;
        return json_encode($array);
    }

    /**
     * @return array $rolesarray the roles the user has in Moodle for a given context
     */
    public function get_roles_array() {
        return $this->roles_array;
    }

    /**
     * Generates the oAuth 1.0 signature
     *
     * @param  $launchurl
     * @param  $oauthparams
     * @param  $secret
     * @return string $signature The result of the HMAC hashing
     */
    public function get_oauth_signature($launchurl, $oauthparams, $secret) {
        $oauthparams = self::sort_array_alphabetically($oauthparams);
        $basestring = 'POST&' . urlencode($launchurl) . '&' . rawurlencode(implode('&', $oauthparams));
        $secret = urlencode($secret) . '&';
        $signature = base64_encode(hash_hmac('sha1', $basestring, $secret, true));

        return $signature;
    }



    /**
     * Checks if embed button should be displayed for user
     *
     * @param  $roles
     * @return bool
     */
    public function sanitize_roles($roles) {
        $roles = array_map('strtolower', $roles);
        $highestrole = LTI_ROLE_REQUEST_LEARNER;
        foreach ($roles as $role) {
            if (in_array($role, LTI_ADMIN_ROLES) || strpos($role, 'administrator') !== false) {
                $highestrole = LTI_ROLE_REQUEST_ADMINISTRATOR;
                break;
            } else if (in_array($role, LTI_INSTRUCTOR_ROLES) || strpos($role, 'instructor') !== false) {
                $highestrole = LTI_ROLE_REQUEST_INSTRUCTOR;
            }
        }
        return $highestrole;
    }

    /**
     * Fetch LTI info
     *
     * @param  $customparams An optional array of key => value parameters that will be added to the LTI request.
     * @return array
     */
    public function generate_lti_configuration($customparams = array()) {
        global $CFG, $COURSE, $USER;

        // Default to embed for users.
        $contentintendeduse = "embed";

        // List of allowed custom parameters.
        $allowedcustomparams = array(
            'custom_echo360_plugin_version',
            'launch_presentation_document_target',
            'launch_presentation_width',
            'launch_presentation_height'
        );

        // Enable homework linking only for students submitting assignments.
        if ((strpos($this->pagetype, 'mod-assign-') === 0)
            && has_capability('mod/assign:submit', $this->context, $USER->id)
            && !has_capability('mod/assign:grade', $this->context, $USER->id)
        ) {
            $contentintendeduse = "homework";
        }

        // Configure the LTI form data.
        $now = new DateTime();
        $launchdata = array(
            'lti_version' => 'LTI-1p0',
            'lti_message_type' => 'basic-lti-launch-request',
            'resource_link_id' => $this->context->id,
            'ext_content_intended_use' => $contentintendeduse,
            'tool_consumer_info_product_family_code' => 'moodle',
            'tool_consumer_info_version' => $CFG->version,
            'selection_directive' => 'embed_content',
            'launch_url' => $this->launch_url,
            'context_id' => $COURSE->id,
            'context_title' => $COURSE->fullname,
            'context_label' => $COURSE->shortname,
            'user_id' => $USER->id,
            'lis_person_name_full' => $USER->firstname . ' ' . $USER->lastname,
            'lis_person_name_family' => $USER->lastname,
            'lis_person_name_given' => $USER->firstname,
            'lis_person_contact_email_primary' => $USER->email,
            'roles' => $this->sanitize_roles($this->roles_array),
            'oauth_callback' => 'about:blank',
            'oauth_consumer_key' => $this->consumer_key,
            'oauth_version' => '1.0',
            'oauth_nonce' => uniqid('', true),
            'oauth_timestamp' => $now->getTimestamp(),
            'oauth_signature_method' => 'HMAC-SHA1'
        );

        foreach ($customparams as $key => $value) {
            // Only add allowed custom parameters.
            if (in_array($key, $allowedcustomparams)) {
                $launchdata[$key] = $value;
            }
        }

        // Sign the oauth request.
        $launchdata['oauth_signature'] = $this->get_oauth_signature($this->launch_url, $launchdata, $this->secret_key);

        return $launchdata;
    }

}
