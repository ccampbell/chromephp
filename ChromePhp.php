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
 * @version 0.1 beta
 * @package ChromePhp
 * @author Craig Campbell <iamcraigcampbell@gmail.com>
 */
class ChromePhp
{
    /**
     * @var string
     */
    const COOKIE_NAME = 'chromephp';

    /**
     * @var array
     */
    protected $_values = array();

    /**
     * @var ChromePhp
     */
    protected static $_instance;

    /**
     * constructor
     */
    private function __construct() {}

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
     * @param mixed $value
     * @return void
     */
    public static function log($value)
    {
        // little hack so strings don't end up with + for spaces
        if (is_string($value)) {
            $value = str_replace(' ', '%20', $value);
        }

        self::_addToCookie($value);
    }

    /**
     * adds a value to the cookie
     *
     * @var mixed
     * @return void
     */
    protected static function _addToCookie($value)
    {
        $chrome_php = self::getInstance();
        $chrome_php->_values[] = $value;
        $chrome_php->_writeCookie();
    }

    /**
     * writes the cookie
     *
     * @return bool
     */
    protected function _writeCookie()
    {
        return setcookie(self::COOKIE_NAME, json_encode($this->_values), time() + 30);
    }
}
