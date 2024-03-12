# Kuet - Gamified Quizzes for Moodle

<img src="pix/kuet-horizontal-color-tagline.png" WITH="400px" />

Welcome to the Kuet repository, a Moodle module developed by a consortium of 16 Spanish universities (Valladolid, Complutense de Madrid, País Vasco/EHU, León, Salamanca, Illes Balears, Valencia, Rey Juan Carlos, La Laguna, Zaragoza, Málaga, Córdoba, Extremadura, Vigo, Las Palmas y Burgos). Kuet introduces an innovative way to conduct interactive, engaging, and competitive voting sessions, quick responses, and gamified quizzes directly within your Moodle platform. It is an innovative gamified questionnaire module developed collaboratively by a consortium of 16 universities in Spain. This module is designed to make learning interactive, engaging, and competitive by incorporating various gamification elements into Moodle activities.

## Documentation and Tutorials

Website: (https://unimoodle.github.io/moodle-mod_kuet/)

Moodle Docs: (https://docs.moodle.org/all/es/mod/kuet)

## Features

### Participation Modes

Kuet offers various participation modes to cater to different teaching strategies and to enhance the gamification aspect of quizzes:

- **Manual Mode**: The traditional mode where the sequence of questions is controlled by the instructor.
- **Scheduled Mode**: Questions advance automatically based on individual question timing or total quiz duration.
- **Game Modes**: Includes inactive, race, and podium modes to add competitive elements to the quizzes.
- **Timed Quizzes**: Set a specific date range and maximum duration for your quiz, including an automatic start feature.

### Team Participation

Beyond Moodle's standard group modes (separated or visible), Kuet allows for team-based participation. When this feature is activated, each group within a selected grouping operates as a team, sharing points and appearing together in feedback screens. This setting is configured within the task settings, and access to the session is restricted to participants of the specific grouping.

### Questions Without Correct Answers

To support real-time polling, brainstorming, and tag cloud generation, Kuet allows for the creation of questions without designated correct answers. This flexibility enables the integration of surveys and open-ended questions alongside traditional quiz questions. An option to ignore correct answers and scoring is available for specific questions, and a tag cloud visualization feature is added for on-the-fly improvisation. The student page refresh issue during voting is also addressed.

### Intuitive and Attractive Interfaces

The presentation of questions, answer options, and results is designed for visibility in large spaces or on small mobile screens, suitable for both projector and mobile device use. Kuet emphasizes full-screen display modes with minimal user interface and distinctive color panels for clear visibility.

### Results Presentation

Kuet offers sophisticated result presentation options:

- Results are grouped by accuracy, with percentages showing the distribution of responses.
- A summary table is displayed at the end of the quiz, showing participants and their scores.
- Podium-style results presentation between questions.
- Enhanced display options for multiple-choice questions with images.

### Student Feedback

Students can see whether their answer was correct, the points scored, and any feedback provided. They can review their responses after the session, and their results are integrated into Moodle's Gradebook.

## Installation

To install Kuet, clone this repository into your Moodle's `mod` directory and follow the standard Moodle module installation process. Detailed instructions can be found in the `INSTALL.md` file.

```shell
git clone https://github.com/UNIMOODLE/moodle-mod_kuet.git path/to/moodle/mod/kuet
```

## Configuration

After installation, you can configure Kuet through the Moodle plugin settings page. Here, you can set default options and technical data to use a Websockets server (included).

## Scalability and Real-Time Interaction with WebSockets

Kuet leverages WebSockets to enhance real-time interaction and scalability, ensuring that quizzes and polls are responsive and engaging, even with a large number of participants. WebSockets provide a full-duplex communication channel over a single, long-lived connection, allowing Kuet to deliver immediate updates and feedback to and from users.

### Why WebSockets?

- **Low Latency**: WebSockets reduce the communication delay between the server and clients, making real-time student participation smooth and efficient.
- **Scalability**: By maintaining a persistent connection to each client, Kuet can efficiently manage thousands of concurrent connections, making it ideal for large classes or university-wide events.
- **Real-Time Feedback**: Immediate synchronization of votes, responses, and results enhances the interactive and competitive elements of gamified quizzes.

### Configuring WebSocket Ports

To take full advantage of WebSockets in Kuet, you'll need to configure your server to handle WebSocket connections, including specifying the ports that Kuet will use for WebSocket communication.

1. **Identify Available Ports**: Determine which ports are available on your server for WebSocket connections. You may need to consult with your network administrator to ensure these ports are open and not blocked by firewalls.

2. **Update Kuet Configuration**: In your Moodle installation, navigate to the Kuet plugin settings page. Here, you'll find an option to specify the WebSocket port(s) and URL that Kuet should use. Enter the port numbers you've identified as available.

3. **Server Configuration**: On your server, configure the WebSocket server to listen on the specified ports. This may involve setting up a reverse proxy if you're using a web server like Nginx or Apache, to forward WebSocket requests to the correct port.

    Example Nginx configuration snippet for WebSocket forwarding:

    ```nginx
    location /kuet/ws {
        proxy_pass http://localhost:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
    }
    ```

4. **Firewall and Security Settings**: Ensure your firewall rules allow incoming connections on the chosen WebSocket ports. Additionally, be sure you are using WSS (WebSocket Secure) to encrypt WebSocket communication.

By properly configuring WebSockets and ports, you can significantly enhance the responsiveness and scalability of the Kuet quizzes, making your Moodle platform more interactive and engaging for users. For detailed setup instructions and best practices for WebSocket security, please consult the documentation provided by your server and network infrastructure providers.


## Contributing

Contributions to Kuet are welcome! Please read our `CONTRIBUTING.md` for guidelines on how to submit bug reports, feature suggestions, and pull requests.

## License

Kuet is released under the GPL v3 license.

Thank you for considering Kuet for your Moodle platform. We believe it will significantly enhance the engagement and learning experience for your students.

# Credits and funding

KUET was designed by [UNIMOODLE Universities Group](https://unimoodle.github.io/) 

<img src="https://unimoodle.github.io/assets/images/allunimoodle-2383x376.png" height="120px" />

KUET was implemented by Moodle's Partner [3iPunt](https://tresipunt.com/)

<img src="https://unimoodle.github.io/assets/images/logo3ip.svg" height="70px" />

This project was funded by the European Union Next Generation Program.

<img src="https://unimoodle.github.io/assets/images/unidigital.png" height="70px" />

# Development

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

## SCSS Compilation for mod_kuet
Moodle does not automatically compile the scss generated within the mods, so it is necessary to make a unique compilation for mod_kuet.

To do this, the "node-sass" tool has been provided which provides a watcher that will automatically compile all the scss we write.

To use it, it is first necessary to install it inside the "/mod/kuet/" folder, by executing the following command from this directory:
```sh
npm i
```
Once installed, to use it run the following command:
```sh
npm run scss
```
This will start the watcher, and while it is active it will detect any changes made to the "/scss/" folder and compile them into the "/style/kuet.css" file.

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
<testsuite name="mod_kuet_testsuite">
    <directory suffix="_test.php">mod/kuet/tests</directory>
</testsuite>
```

After that you can run all mod_jshow tests with the following command:
```sh
vendor/bin/phpunit --testsuite mod_kuet_testsuite
```

Or you can run specific files and test methods, for example:
```sh
vendor/bin/phpunit --filter test_mod_kuet_get_kuets_by_courses mod/kuet/test/externallib_test.php
vendor/bin/phpunit --filter test_generator mod/kuet/tests/generator_test.php
vendor/bin/phpunit --filter test_kuet_core_calendar_provide_event_action mod/kuet/tests/lib_test.php
vendor/bin/phpunit --filter test_kuet_core_calendar_provide_event_action_as_non_user mod/kuet/tests/lib_test.php
```

More information at:

- <https://docs.moodle.org/dev/PHPUnit>
- <https://docs.phpunit.de/en/9.6/>

## Behat

More information at:

- <https://docs.moodle.org/dev/Writing_acceptance_tests>
- <https://docs.behat.org/en/latest/guides.html>
