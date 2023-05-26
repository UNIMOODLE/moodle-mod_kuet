# README #

## Installation

## Configuration

## Linting JavaScript (ESLint) and SCSS (stylelint)
To use the JS and SCSS linters provided by Moodle, just run the following code **in the root of your Moodle installation**:
```sh
npm i
```
Once this is done, PHPStorm will detect these linters and whenever we write JS or SCSS code it will point out possible errors.

Whenever we use the grunt compiler to transpile AMD, these linters will also be run.

More information at <https://docs.moodle.org/dev/Linting>

## CodeChecker

- Download the Moodle plugin:
    <https://moodle.org/plugins/local_codechecker/versions>
- Unzip the folder in a place visible to all projects.
- Go to PHP Storm Preferences and look for 'Quality Tools' in PHP.
- Give the ... Configuration 'Local' (or create a new one).
- Search for the file inside the downloaded plugin in PHP_CodeSniffer path: Example: C:\CODECHECKER\local_codechecker_moodle40_2022022500\codechecker\phpcs\bin\phpcs.bat
- **Click on Validate to test that it runs correctly.**
- Click on PHP_CodeSniffer Inspection.
- Set the alerts to ERRORS and the **Coding Standard to 'moodle'** (for the coding standard option to appear, we may need to apply the above changes first).
- Click OK, both in the inspection window and in the setting window.
- Check that if in a PHP file an EQUAL is put together, the PHPCS error appears.

If you cannot configure PHPStorm by following these steps, run this command and try again:
```sh
composer install
```

More information at <https://docs.moodle.org/dev/CodeSniffer>

## Plugins PHPStorm
The following PHPStorm plugins must be installed and activated, as well as following the code suggestions they provide:

- **Php Inspections (EA Extended):** <https://plugins.jetbrains.com/plugin/7622-php-inspections-ea-extended->
- **PhpClean:** <https://plugins.jetbrains.com/plugin/11272-phpclean>
- **Handlebars/Mustache** <https://plugins.jetbrains.com/plugin/6884-handlebars-mustache>

## SCSS Compilation for mod_jqshow
Moodle does not automatically compile the scss generated within the mods, so it is necessary to make a unique compilation for mod_jqshow.

To do this, the "node-sass" tool has been provided which provides a watcher that will automatically compile all the scss we write.

To use it, it is first necessary to install it inside the "/mod/jqshow/" folder, by executing the following command from this directory:
```sh
npm i
```
Once installed, to use it run the following command:
```sh
npm run scss
```
This will start the watcher, and while it is active it will detect any changes made to the "/scss/" folder and compile them into the "/style/jqshow.css" file.

Possible SCSS errors that occur during development will be shown in the console where the watcher has been launched.
If an error stops its execution, only restart it after resolving the error.

## PHPUnit
To be able to run unit tests, you must first run the following command in the root of your Moodle installation:
```sh
composer install
```
In config.php file of our environment (there must be a moodledata folder exclusively for unit tests):
```sh
$CFG->phpunit_prefix = 'phpu_';
$CFG->phpunit_dataroot = 'root\to\phpu_moodledata_unimoodle';
$CFG->phpunit_dbtype    = 'mariadb';
$CFG->phpunit_dblibrary = 'native';
$CFG->phpunit_dbhost    = 'localhost';
$CFG->phpunit_dbname    = 'dbname';
$CFG->phpunit_dbuser    = 'root';
$CFG->phpunit_dbpass    = '';
```
Then run the following command:
```sh
php admin/tool/phpunit/cli/init.php
```
The PHPUnit environment will start to install, which may take a few minutes.
When the installer has finished, we can **run the tests of the whole platform** with the following command (from the root of the installation):
```sh
vendor/bin/phpunit
```
**To run only the mod_jshow tests, you must first add the following code in the phpunit.xml:770 file**
```sh
<testsuite name="mod_jqshow_testsuite">
    <directory suffix="_test.php">mod/jqshow/tests</directory>
</testsuite>
```

After that you can run all mod_jshow tests with the following command:
```sh
vendor/bin/phpunit --testsuite mod_jqshow_testsuite
```

Or you can run specific files and test methods, for example:
```sh
vendor/bin/phpunit --filter test_mod_jqshow_get_jqshows_by_courses mod/jqshow/test/externallib_test.php
vendor/bin/phpunit --filter test_generator mod/jqshow/tests/generator_test.php
vendor/bin/phpunit --filter test_jqshow_core_calendar_provide_event_action mod/jqshow/tests/lib_test.php
vendor/bin/phpunit --filter test_jqshow_core_calendar_provide_event_action_as_non_user mod/jqshow/tests/lib_test.php
```

More information at:

- <https://docs.moodle.org/dev/PHPUnit>
- <https://docs.phpunit.de/en/9.6/>

## Behat

More information at:

- <https://docs.moodle.org/dev/Writing_acceptance_tests>
- <https://docs.behat.org/en/latest/guides.html>
