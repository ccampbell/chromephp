<?php
/**
 * Copyright 2010 Craig Campbell
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Server Side Chrome PHP debugger class
 *
 * @package ChromePhp
 * @author Craig Campbell <iamcraigcampbell@gmail.com>
 */
class ChromePhp
{
    /**
     * @var string
     */
    const COOKIE_NAME = 'chromephp_log';

    /**
     * @var string
     */
    const VERSION = '0.1471';

    /**
     * @var string
     */
    const LOG_PATH = 'log_path';

    /**
     * @var string
     */
    const URL_PATH = 'url_path';

    /**
     * @var string
     */
    const STORE_LOGS = 'store_logs';

    /**
     * @var string
     */
    protected $_php_version;

    /**
     * @var int
     */
    protected $_timestamp;

    /**
     * @var array
     */
    protected $_json = array(
        'version' => self::VERSION,
        'columns' => array('label', 'log', 'backtrace'),
        'rows' => array()
    );

    /**
     * @var int
     */
    protected $_bytes_transferred = 0;

    /**
     * @var array
     */
    protected $_settings = array(
        self::LOG_PATH => null,
        self::URL_PATH=> null,
        self::STORE_LOGS => false
    );

    /**
     * @var ChromePhp
     */
    protected static $_instance;

    /**
     * constructor
     */
    private function __construct()
    {
        $this->_deleteCookie();
        $this->_php_version = phpversion();
        $this->_timestamp = $this->_php_version >= 5.1 ? $_SERVER['REQUEST_TIME'] : time();
        $this->_json['request_uri'] = $_SERVER['REQUEST_URI'];
    }

    /**
     * gets instance of this class
     *
     * @return ChromePhp
     */
    public static function getInstance()
    {
        if (self::$_instance === null) {
            self::$_instance = new ChromePhp();
        }
        return self::$_instance;
    }

    /**
     * logs a variable to the console
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public static function log()
    {
        $args = func_get_args();

        // nothing passed in, don't do anything
        if (count($args) == 0) {
            return;
        }

        // default to single
        $label = null;
        $value = $args[0];

        $logger = self::getInstance();

        // if there are two values passed in then the first one is the label
        if (count($args) == 2) {
            $label = $logger->_encode($args[0]);
            $value = $args[1];
        }

        $value = $logger->_convert($value);

        $backtrace = debug_backtrace(false);
        $backtrace_message = $logger->_encode($backtrace[0]['file'] . ' : ' . $backtrace[0]['line']);

        $logger->_addRow($label, $value, $backtrace_message);
    }

    /**
     * encodes a string for cookie
     *
     * @param mixed
     * @return mixed
     */
    protected function _encode($value)
    {
        if (!is_string($value)) {
            return $value;
        }

        // temporary hack so we don't break with new line characters
        if (strpos($value, "\n") || strpos($value, "\r"))
            return $value;

        $value = rawurlencode($value);
        return $value;
    }

    /**
     * converts an object to a better format for logging
     *
     * @param Object
     * @return array
     */
    protected function _convert($object)
    {
        // if this isn't an object then just return it
        if (!is_object($object)) {
            return $this->_encode($object);
        }

        $object_as_array = array();

        // first add the class name
        $object_as_array[$this->_encode('class')] = get_class($object);

        // loop through object vars
        $object_vars = get_object_vars($object);
        foreach ($object_vars as $key => $value) {

            // same instance as parent object
            if ($value === $object) {
                $value = 'recursion - parent object';
            }
            $object_as_array[$this->_encode($key)] = $this->_convert($value);
        }

        $reflection = new ReflectionClass($object);

        // loop through the properties and add those
        foreach ($reflection->getProperties() as $property) {

            // if one of these properties was already added above then ignore it
            if (array_key_exists($property->getName(), $object_vars)) {
                continue;
            }
            $type = $this->_getPropertyKey($property);

            if ($this->_php_version >= 5.3) {
                $property->setAccessible(true);
            }

            $value = $property->getValue($object);

            // same instance as parent object
            if ($value === $object) {
                $value = 'recursion - parent object';
            }

            $object_as_array[$this->_encode($type)] = $this->_convert($value);
        }
        return $object_as_array;
    }

    /**
     * takes a reflection property and returns a nicely formatted key of the property name
     *
     * @param ReflectionProperty
     * @return string
     */
    protected function _getPropertyKey(ReflectionProperty $property)
    {
        $static = $property->isStatic() ? ' static' : '';
        if ($property->isPublic()) {
            return 'public' . $static . ' ' . $property->getName();
        }

        if ($property->isProtected()) {
            return 'protected' . $static . ' ' . $property->getName();
        }

        if ($property->isPrivate()) {
            return 'private' . $static . ' ' . $property->getName();
        }
    }

    /**
     * adds a value to the cookie
     *
     * @var mixed
     * @return void
     */
    protected function _addRow($label, $log, $backtrace)
    {
        $last_backtrace = $this->_getLastUsedBacktrace();

        // if this is logged on the same line for example in a loop, set it to null to save space
        if ($backtrace == $last_backtrace) {
            $backtrace = null;
        }

        $this->_json['rows'][] = array($label, $log, $backtrace);
        $this->_writeCookie();
    }

    /**
     * gets the last backtrace that was used on this request
     *
     * @return string
     */
    protected function _getLastUsedBacktrace()
    {
        // filter out empty stuff
        $backtraces = array();
        foreach ($this->_json['rows'] as $row) {
            $backtraces[] = $row[2];
        }
        $backtraces = array_filter($backtraces, 'strlen');

        return array_pop($backtraces);
    }

    /**
     * writes the cookie
     *
     * @return bool
     */
    protected function _writeCookie()
    {
        $json = json_encode($this->_json);

        // if we are going to use a file then use that
        if ($this->getSetting(self::LOG_PATH) !== null) {
            return $this->_writeToFile($json);
        }

        // if we don't have multibyte string length available just use regular string length
        // this doesn't have to be perfect, just want to prevent sending more data
        // than chrome or apache can handle in a cookie
        $this->_bytes_transferred += function_exists('mb_strlen') ? mb_strlen($json) : strlen($json);

        if ($this->_bytes_transferred > 4000) {
            return $this->_cookieMonster();
        }

        return setcookie(self::COOKIE_NAME, json_encode($json), time() + 30);
    }

    /**
     * deletes the main cookie
     *
     * @return bool
     */
    protected function _deleteCookie()
    {
        return setcookie(self::COOKIE_NAME, null, 1);
    }

    /**
     * adds a setting
     *
     * @param string key
     * @param mixed value
     * @return void
     */
    public function addSetting($key, $value)
    {
        $this->_settings[$key] = $value;
    }

    /**
     * gets a setting
     *
     * @param string key
     * @return mixed
     */
    public function getSetting($key)
    {
        if (!isset($this->_settings[$key])) {
            return null;
        }
        return $this->_settings[$key];
    }

    /**
     * this will allow you to specify a path on disk and a uri to access a static file that can store json
     *
     * this allows you to log data that is more than 4k
     *
     * @param string path to directory on disk to keep log files
     * @param string url path to url to access the files
     */
    public static function useFile($path, $url)
    {
        $logger = self::getInstance();
        $logger->addSetting(self::LOG_PATH, rtrim($path, '/'));
        $logger->addSetting(self::URL_PATH, rtrim($url, '/'));
    }

    /**
     * handles cases when there is too much data
     *
     * @param string
     * @return void
     */
    protected function _cookieMonster()
    {
        $this->_deleteCookie();
        throw new Exception('chrome can only handle 4kb in a cookie!  you can try the experimental file mode if you\'d like');
    }

    /**
     * writes data to a file
     *
     * @param string
     * @return void
     */
    protected function _writeToFile($json)
    {
        // if the log path is not setup then create it
        if (!is_dir($this->getSetting(self::LOG_PATH))) {
            mkdir($this->getSetting(self::LOG_PATH));
        }

        $file_name = 'last_run.json';
        if ($this->getSetting(self::STORE_LOGS)) {
            $file_name = 'run_' . $this->_timestamp . '.json';
        }

        file_put_contents($this->getSetting(self::LOG_PATH) . '/' . $file_name, $json);

        $data = array(
            'uri' => $this->getSetting(self::URL_PATH) . '/' . $file_name,
            'request_uri' => $_SERVER['REQUEST_URI'],
            'time' => $this->_timestamp,
            'version' => self::VERSION
        );

        setcookie(self::COOKIE_NAME, json_encode($data), time() + 30);
    }
}
