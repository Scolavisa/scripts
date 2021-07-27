<?php
/// ---------------------------------------------------------
/// CLI script om snel te onderzoeken of alle backups gelopen
/// hebben. Een probleem: we kijken alleen in de directories
/// die bestaan. Als een backup nog nooit gedraaid heeft,
/// bestaat de dir niet en valt het dus niet op dat de backup
/// niet loopt. Ik ga ervan uit dat bij in productie nemen
/// van de site de backup in elk geval 1X gecontroleerd is.
///
/// in deze directory starten met `php show_latest.php`
/// ---------------------------------------------------------

/// --------------------------------------------------------- MAIN
require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// get dirlist
$dirlist = scandir('.');
foreach ($dirlist as $dir) {
    if ($dir !== '.' && $dir !== '..' & is_dir($dir)) {
        // analyze each dir
        $results[] = getNewest(__DIR__ . "/" . $dir);
    }
}

// format output
$countProblems = 0;
foreach($results as $directory) {
    $name = $directory["name"];
    $lb = $directory["last_backup"];
    $hp = $directory["hours_past"];
    $prefix = ($hp === '' || intval($hp) > 24) ? " ====>>> " : "";
    $countProblems = $prefix !== '' ? $countProblems +1 : $countProblems;
    print sprintf($prefix . "laaste backup van %s is %s (%s uur geleden)\n", $name, $lb, $hp);
}

// summarize
print("\nTotaal: " . count($results) . " sites.");
print("\nTotaal problemen: >> $countProblems <<\n");

/// --------------------------------------------------------- FUNCTIONS

/**
 * Zoek de laatste backup.
 * Erg specifiek voor deze directories, qua indeling
 */
function getNewest($path) {
    $latest_filename = '';
    $latest_ctime = '';
    $output = null;
    $retval = null;
    exec('ls -lt ' . $path, $output, $retval);

    $newest = $output[1] ?? "not found";
    // newest entry maybe the rollback directorty.
    // in that case look for the next file, if present
    if(strpos($newest, 'rollback') !== false) {
        $newest = $output[2] ?? "not found";
    }

    if ($newest !== 'not found') {
        // get date_time of newest
        $parts = explode(' ', $newest);
        $filename = end($parts);
        $timestamp = filemtime($path . '/'. $filename); // last entry is the filename
        // nr of hours that have passed since the last backup
        $date1 = new DateTime(date("Y-m-d H:i", $timestamp));
        $date2 = new DateTime("now");
        $diff = $date2->diff($date1);
        $hours = $diff->h;
        // strip path
        $parts = explode('/', $path);

        return ["name" => end($parts), "last_backup"=> date("Y-m-d H:i", $timestamp), "hours_past" => $hours + ($diff->days*24)];
    } else {
        return ["name" => "not found: $path", "last_backup"=> "", "hours_past" => ""];
    }
}
