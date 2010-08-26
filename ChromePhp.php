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
    const VERSION = '0.146';

    /**
     * @var array
     */
    protected $_values = array();

    /**
     * @var array
     */
    protected $_callers = array();

    /**
     * @var array
     */
    protected $_labels = array();

    /**
     * @var string
     */
    protected $_php_version;

    /**
     * @var ChromePhp
     */
    protected static $_instance;

    /**
     * @var string
     */
    protected static $_log_path;

    /**
     * @var string
     */
    protected static $_url_path;

    /**
     * constructor
     */
    private function __construct()
    {
        $this->_deleteCookie();
        $this->_php_version = phpversion();
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

        $logger->_addToCookie($value, $backtrace_message, $label);
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
        return str_replace(' ', '%20', $value);
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
    protected function _addToCookie($value, $backtrace, $label)
    {
        $this->_values[] = $value;

        $last_backtrace = $this->_getLastUsedBacktrace();

        // if this is logged on the same line for example in a loop, set it to null to save space
        if ($backtrace == $last_backtrace) {
            $backtrace = null;
        }

        $this->_callers[] = $backtrace;
        $this->_labels[] = $label;
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
        $backtraces = array_filter($this->_callers, 'strlen');
        $len = count($backtraces);

        if ($len == 0) {
            return;
        }

        return $backtraces[$len - 1];
    }

    /**
     * writes the cookie
     *
     * @return bool
     */
    protected function _writeCookie()
    {
        $data = array(
            'labels' => $this->_labels,
            'data' => $this->_values,
            'backtrace' => $this->_callers,
            'version' => self::VERSION);

        $json = json_encode($data);

        // if we are going to use a file then use that
        if (self::$_log_path !== null) {
            return $this->_writeToFile($json);
        }

        // if the data is more than 4000 bytes
        if (count(str_split($json, 4000)) > 1) {
            return $this->_cookieMonster();
        }

        return setcookie(self::COOKIE_NAME, json_encode($data), time() + 30);
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
     * this will allow you to specify a path on disk and a uri to access a static file that can store json
     *
     * this allows you to log data that is more than 4k
     *
     * @param string path to directory on disk to keep log files
     * @param string url path to url to access the files
     */
    public static function useFile($path, $url)
    {
        self::$_log_path = rtrim($path, '/');
        self::$_url_path = rtrim($url, '/');
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
        if (!is_dir(self::$_log_path)) {
            mkdir(self::$_log_path);
        }

        $file_name = 'run_' . time() . '.json';

        file_put_contents(self::$_log_path . '/' . $file_name, $json);

        $data = array(
            'uri' => self::$_url_path . '/' . $file_name,
            'version' => self::VERSION
        );

        setcookie(self::COOKIE_NAME, json_encode($data), time() + 30);
    }
}
