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

// get all wordpress sites
$dirlist = scandir($wpsitesDir);
$log->info("found " . count($dirlist) . " directory entries");
foreach ($dirlist as $dir) {
    if ($dir !== '.' && $dir !== '..' & is_dir("$wpsitesDir/$dir")) {
        // can i find a wp_cron in there? then call it
        if (file_exists("$wpsitesDir/$dir/wp-cron.php")) {
            exec("php $wpsitesDir/$dir/wp-cron.php", $output, $code);
            if($code === 0) {
                $log->info("$wpsitesDir/$dir successfully called");
            } else {
                $log->error("tried to call wp-cron in $wpsitesDir/$dir but response was: $code");
            }
        } else {
            $log->warning("$wpsitesDir/$dir doesn't appear to be a WP site");
        }
    }
}

