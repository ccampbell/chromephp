## Overview
ChromePhp is a PHP library for the Chrome Logger Google Chrome extension and Firefox Developer Tools console.

This library allows you to log variables to the Chrome or Firefox devtools console.

## Requirements
- PHP 5 or later

## Installation Chrome
1. Install the Chrome extension from: https://chrome.google.com/extensions/detail/noaneddfkdjfnfdakjjmocngnfkfehhd
2. Click the extension icon in the browser to enable it for the current tab's domain
3. Put ChromePhp.php somewhere in your PHP include path
4. Log some data

    ```php
    include 'ChromePhp.php';
    ChromePhp::log('Hello console!');
    ChromePhp::log($_SERVER);
    ChromePhp::warn('something went wrong!');
    ```

## Installation Firefox
1. Put ChromePhp.php somewhere in your PHP include path
2. Enable Server logging filter in web console
3. Initialize ChromePhp for FirePHP compatibility

    ```php
    include 'ChromePhp.php';
    $firephp = ChromePhp::getInstance();
    $firephp->setEnabled(true, 'FirePHP');
    ```

4. Log some data

    ```php
    $firephp->log($_GET, 'GET variables');
    $firephp->warn('Value out of range');
    ```

More information can be found here:

http://www.chromelogger.com

https://developer.mozilla.org/en-US/docs/Tools/Web_Console/Console_messages#Server