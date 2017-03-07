## Overview
ChromePhp is a PHP library to log variables to the Chrome or Firefox devtools console.
For Google Chrome the Chrome Logger extension is needed.

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
2. Enable Server logging filter in the web console
3. Initialize ChromePhp for FirePHP compatibility

    ```php
    include 'ChromePhp.php';
    $firephp = ChromePhp::getInstance();
    $firephp->setEnabled(true, 'FirePHP');
    ```
    The second parameter 'FirePHP' is optional and can be omitted in subsequent calls to setEnabled. FirePHP compatibility mode can be changed by calling
    ```php
    // disable FirePHP mode
    $firephp->addSetting('log_style', '');
    
    //enable FirePHP mode
    $firephp->addSetting('log_style', 'FirePHP');
    ```

4. Log some data

    ```php
    $firephp->log($_GET, 'GET variables');
    $firephp->warn('Value out of range');
    ```

More information can be found here:

http://www.chromelogger.com

https://developer.mozilla.org/en-US/docs/Tools/Web_Console/Console_messages#Server

## Use this repository with composer

To use this repository, change your composer.json to add `ccampbell/chromephp`
in require-dev and add this in your repository list. For example:

```
"require-dev": {
    "ccampbell/chromephp" : "dev-master"
},

"repositories": [
    {
        "type" : "vcs",
        "url"  : "git@github.com:ErikKrause/chromephp.git"
    }
]

```


