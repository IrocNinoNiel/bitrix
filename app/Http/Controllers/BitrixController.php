<?php

namespace App\Http\Controllers;

use DateTime;
use DateInterval;

class BitrixController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    //
    public function getAllProject() {

        $domain = 'syntactics.bitrix24.com';
        $authToken = 'pu2m3yb4epvrdw95';

        $urlGroups = "https://{$domain}/rest/29/{$authToken}/sonet_group.get.json";

        // Retrieve the list of groups (projects) in Bitrix24
        $responseGroups = file_get_contents($urlGroups);
        $resultGroups = json_decode($responseGroups, true);

        if (isset($resultGroups['error'])) {
            echo "Error retrieving groups: " . $resultGroups['error_description'] . "\n";
            exit;
        }

        return response()->json(['message' => 'Project List','result' => $resultGroups]);

    }

    public function getProjectEstimate() {

        $domain = 'syntactics.bitrix24.com';
        $authToken = 'pu2m3yb4epvrdw95';

        $groupId = 71;
        $list = [];

        $urlTasks = "https://{$domain}/rest/29/{$authToken}/tasks.task.list?filter[group_id]={$groupId}";
        $responseTasks = file_get_contents($urlTasks);
        $resultTasks = json_decode($responseTasks, true);

        // Check for errors in the response
        if (isset($resultTasks['error'])) {
            echo "Error retrieving tasks for group ID {$groupId}: " . $resultTasks['error_description'] . "\n";
            exit;
        }

        foreach ($resultTasks['result']['tasks'] as $task) {
            $totalTime = 0;
            // Retrieve the task

            $taskId = $task['id'];

            // Construct the API endpoint for task.elapseditem.getlist method
            $urlElapsedItems = "https://{$domain}/rest/29/{$authToken}/task.elapseditem.getlist?TASKID={$taskId}";

            // Retrieve the list of elapsed time records for the task
            $responseElapsedItems = file_get_contents($urlElapsedItems);
            $resultElapsedItems = json_decode($responseElapsedItems, true);

            // Check for errors in the response
            if (isset($resultElapsedItems['error'])) {
                echo "Error retrieving elapsed time items for task ID {$taskId}: " . $resultElapsedItems['error_description'] . "\n";
                continue; // skip to the next task
            }

            // Get all the elapsed time being used in the task
            foreach ($resultElapsedItems['result'] as $elapsedItem) {
                $totalTime += $elapsedItem['SECONDS'];
            }

            // Get the estimate Date
            $startDate = ($task['dateStart'] == null) ? $task['createdDate'] : $task['dateStart'];
            if ($task['deadline'] == null) {
                $estimate = 0;
            }

            if ($task['deadline'] != null) {
                $endDate = $task['deadline'];

                $estimate = $this->getEstimateHours($startDate, $endDate);
            }

            // Convert actual time in seconds to readable one
            $totalConvert = $this->secondsToTime($totalTime);

            // Subtract actual to estimate
            $remainingHours = $this->calculateRemainingHours($totalConvert, $estimate);

            array_push($list,
                [
                    'task_id' => $taskId,
                    'name' => $task['title'],
                    'datestart' => $startDate ,
                    'deadline' => $task['deadline'],
                    'actual' => $totalConvert,
                    'estimate' => $estimate,
                    'remaininghours' => $remainingHours,
                    'totalActualTimeInSeconds' => $totalTime
                ]
            );

        }
        $totalActualProject = $this->calculateActualProject($list);
        return response()->json(
            [
                'message' => 'Project Estimate vs Actual',
                'Project ID' => $groupId,
                'actualtime' =>$totalActualProject,
                'estimatetime' => 0,
                'tasks' => $list
            ]);

    }

    private function calculateRemainingHours ($totalConvert, $estimate) {

        $minutes = 0;
        $seconds = 0;

        $hours = (int)$estimate - (int) $totalConvert['hours'];
        if((int)$totalConvert['minutes'] > 0) {
            $hours = $hours-1;
            $minutes = 60 - (int)$totalConvert['minutes'];

        }

        if((int)$totalConvert['seconds'] > 0) {
           $minutes = $minutes - 1;
           $seconds = 60 -(int)$totalConvert['seconds'];

            if($minutes == 0) {
                $minutes = 60 - 1;
                $hours = $hours -1;
            }

        }

        // Output the result
        return ['text' => sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds), 'hours' => $hours, 'minutes' => $minutes, 'seconds' => $seconds];

    }

    private function secondsToTime($seconds) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;
        return ['text' => sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds), 'hours' => $hours, 'minutes' => $minutes, 'seconds' => $seconds];
    }

    private function getEstimateHours($start_date, $end_date) {

        // Convert start and end dates to DateTime objects
        $start_date = DateTime::createFromFormat("Y-m-d\TH:i:sP", $start_date);
        $end_date   = DateTime::createFromFormat("Y-m-d\TH:i:sP", $end_date);

        // Calculate the total number of days between start and end date
        $interval = $start_date->diff($end_date);
        $total_days = $interval->days;

        $total_hours = 0;

        // Loop through each day between start and end date
        for ($i = 0; $i <= $total_days; $i++) {
            // Get the current date
            $current_date = $start_date->add(new DateInterval('P1D'));

            // Check if the current day is a weekend (Saturday or Sunday)
            if ($current_date->format('N') < 6) {
                // Add 8 hours to the total hours
                $total_hours += 8;
            }
        }

        return $total_hours;

    }

    private function calculateActualProject( $list ) {

        $totalTime = 0;
        foreach ($list as $stringTime) {
            $totalTime += $stringTime['totalActualTimeInSeconds'];
        }

        return $this->secondsToTime($totalTime);
    }

}
