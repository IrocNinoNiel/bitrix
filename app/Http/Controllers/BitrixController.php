<?php

namespace App\Http\Controllers;

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

        return $resultGroups;

    }

    public function getAllProjectEstimate() {

        $domain = 'syntactics.bitrix24.com';
        $authToken = 'pu2m3yb4epvrdw95';

        $resultGroups = $this->getAllProject();


        foreach ($resultGroups['result'] as $group) {
            $totalTime = 0;
            $groupId = $group['ID'];

            $urlTasks = "https://{$domain}/rest/29/{$authToken}/tasks.task.list?filter[group_id]={$groupId}";
            $responseTasks = file_get_contents($urlTasks);
            $resultTasks = json_decode($responseTasks, true);

            // Check for errors in the response
            if (isset($resultTasks['error'])) {
                echo "Error retrieving tasks for group ID {$groupId}: " . $resultTasks['error_description'] . "\n";
                continue; // skip to the next group
            }

            foreach ($resultTasks['result'] as $task) {
                // Retrieve the task

                $taskId = $task[0]['id'];

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

                foreach ($resultElapsedItems['result'] as $elapsedItem) {
                    $totalTime += $elapsedItem['SECONDS'];
                }

            }



        }

        return 'hi';

    }

    public function secondsToTime($seconds) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;
        return sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
     }
}
