<?php
/// ---------------------------------------------------------
/// CLI script om alle websites in wpsites (wordpress)
///  tenminste 1X te bezoeken. Hoe vaak per dag is afhankelijk
///  van de cron job die dit script aanroept.
/// Hiermee omzeilen we de werking van wp-cron die wacht tot
///  de site bezocht wordt.

require __DIR__ . '/vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$dotenv->required('WPSITES_DIR');
$dotenv->required('WPC_LOG');

$wpsitesDir = $_ENV['WPSITES_DIR'];

$output = "[%datetime%] %channel%.%level_name%: %message% %context.user%\n";
$formatter = new LineFormatter($output);
$log = new Logger('call_wp_cron');
$streamHandler = new StreamHandler($_ENV["WPC_LOG"], Logger::INFO);
$streamHandler->setFormatter($formatter);
$log->pushHandler($streamHandler);

$log->info("---------------------------------");
$log->info("analysing directory: $wpsitesDir");

// collect errors in array, to be shown at the end of the log
$errorMessages = [];

// get all wordpress sites
$dirlist = scandir($wpsitesDir);
$log->info("found " . count($dirlist) . " directory entries");
foreach ($dirlist as $dir) {
    if ($dir !== '.' && $dir !== '..' & is_dir("$wpsitesDir/$dir")) {
        // can i find a wp_cron in there? then call it
        if (file_exists("$wpsitesDir/$dir/wp-config.php")) {
            // get frontpage URL
            // get DB_NAME from wp_config
            $dbName = findDbName("$wpsitesDir/$dir/wp-config.php");
            if (empty($dbName)) {
                $errorMessages[] = "Can't determine databasename for $dir";
                continue;
            }
            $log->info("$wpsitesDir/$dir uses database: $dbName");
            // Get homepage URL
            // mysql> select option_value from wp_options where option_name='home';
            $mysqli = new mysqli("localhost",$_ENV['UN'],$_ENV['PW'],$dbName);
            if ($mysqli->connect_errno) {
                $errorMessages[] = "Failed to connect to $dbName (error $mysqli->connect_errno)";
                continue;
            }
            // call homepage URL using cUrl
            $optionsTable = getOptionsTabeleName("$wpsitesDir/$dir/wp-config.php");
            $sql = "SELECT option_value FROM $optionsTable WHERE option_name='home'";
            $result = $mysqli->query($sql);
            try {
                $row = $result->fetch_assoc();
            } catch (Exception $e) {
                $errorMessages[] = "Error getting home url from table wp_options for $dir";
                continue;
            }
            $homeUrl = $row["option_value"];
            $log->info("HomeURL $homeUrl");
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $homeUrl);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $output = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            // result
            if(intval($status) === 200) {
                $log->info("$homeUrl successfully called");
            } else {
                $errorMessages[] = "tried to call $homeUrl but response was: $status";
                $errorMessages[] = "first characters in response: " . substr($output, 0, 30);
            }
        } else {
            $log->warning("$wpsitesDir/$dir doesn't appear to be a WP site");
        }
    }
}

// Show all errors in summary
foreach ($errorMessages as $errorMessage) {
    $log->error($errorMessage);
}

/**
 * Determine the DB_NAME as defined in $filename
 * @param $filename
 * @return array|string|string[]
 */
function findDbName($filename) {
    $result = '';
    $line = findLineBySearchkey($filename, "DB_NAME");
    if($line !== '') {
        $parts = explode(',', $line);
        $result = trim($parts[1]);
        // remove all whitespace and control characters
        $result = preg_replace('/[ \t]+/', ' ', preg_replace('/\s*\$\^\s*/m', "\n", $result));
        $result = substr($result, 1, strlen($result) - 4);
        $result = str_replace("'", "", $result);
        $result = str_replace('"', "", $result);
    }
    return $result;
}

// look for $table_prefix = 'mmm___'; usa: $table_prefix  = 'wp_';
// return actual options table name, like: mmm___options or wp_options

/**
 * Determine the name of the options table, taking the prefix into account
 * @param String $filename
 * @return String
 */
function getOptionsTabeleName(String $filename): String {
    $result = '';
    $line = findLineBySearchkey($filename, "table_prefix");
    if ($line !== '') {
        $parts = explode("=", $line);
        $result = trim($parts[1]);
        $result = substr($result, 1, strlen($result) - 2) . "options";
        $result = str_replace("'", "", $result);
        $result = str_replace('"', "", $result);
    }
    return $result;
}

/**
 * Look for a line in a file containing a specific search key
 * @param String $filename
 * @param String $key
 * @return string (not found returns empty string)
 */
function findLineBySearchkey(String $filename, String $key):string {
    $result = '';
    $handle = fopen($filename, "r");
    if ($handle) {
        while (($line = fgets($handle)) !== false) {
            if (strpos($line, $key) !== false) {
                $result = $line;
                break;
            }
        }
        fclose($handle);
    }
    return $result;
}
