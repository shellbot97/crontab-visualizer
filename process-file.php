<?php
require_once './vendor/autoload.php';
require_once './CronSchedule.php';

$allCrons = [];
$delimiter = ','; //parameter for fputcsv
$enclosure = '"'; //parameter for fputcsv

$phpExecutionPath = (isset($_POST['executionPath']) && !empty($_POST['executionPath'])) ? $_POST['executionPath'] : '/usr/bin/php';
$timezone = (isset($_POST['timezone']) && !empty($_POST['timezone'])) ? $_POST['timezone'] : "UTC";
$countOfNextOccurances = (isset($_POST['countOfNextOccurances']) && !empty($_POST['countOfNextOccurances'])) ? $_POST['countOfNextOccurances'] : "1";

date_default_timezone_set($timezone);
$currentDate = date('Ymd');
$currentTime = (isset($_POST['time']) && !empty($_POST['time'])) ? $_POST['time'] : date('Y-m-d H:i:s');
$cronTabPointer = fopen($_FILES['file']['tmp_name'], 'r');

while ($cronSchedule = fgets($cronTabPointer)) {
    if ($cronSchedule[0] === '#') {
        continue;
    }
    $cronInfo = explode($phpExecutionPath, $cronSchedule);
    // $times = getAllTimesFrom($cronInfo[0], $timeStamp);
    if (count($cronInfo) < 2) {
        continue;
    }
    $allCrons[] = [
        'time' => \Cron\CronExpression::factory($cronInfo[0])->getNextRunDate($currentTime)->format('Y-m-d H:i:s'),
        'expression' => CronSchedule::fromCronString(trim($cronInfo[0]))->asNaturalLanguage(),
        'nextExecutionTimes' => implode("\n", array_map(function($iteration) use ($currentTime, $cronInfo){
            return \Cron\CronExpression::factory($cronInfo[0])->getNextRunDate($currentTime, $iteration)->format('Y-m-d H:i:s');
        }, range(1, $countOfNextOccurances))),
        'filePath' => explode(" ", trim(explode('>>', $cronInfo[1])[0]))[0],
        'arguments' => implode(" | ", array_slice(explode(" ", trim(explode('>>', $cronInfo[1])[0])), 1)),
        'logPath' => explode('>>', $cronInfo[1])[1],
        'previousRunDate' => \Cron\CronExpression::factory($cronInfo[0])->getPreviousRunDate($currentTime)->format('Y-m-d H:i:s'),
    ];
}
fclose($cronTabPointer);
$allCrons[] = [
    'time' => $currentTime,
    'filePath' => 'current time',
    'logPath' => 'current time',
    "previousRunDate" => '',
    'other' => '',
];
array_multisort(
    array_map('strtotime', array_column($allCrons, 'time')), 
    SORT_ASC, 
    $allCrons
);

$file = fopen("crontab-$currentDate.csv", 'w');
fputcsv($file, array_keys($allCrons[1]));
foreach ($allCrons as $cron) {
    @fputcsv($file, $cron, $delimiter, $enclosure);
}
fclose($file);
echo json_encode($allCrons, JSON_PRETTY_PRINT);
exit();