<?php
require_once __DIR__ . '/../../src/PixelTools/PTsCronTasker.php';

/**
 * Демон получает задачи которые ждут запуска, проверяет не превышает ли они допустимое количество и запускает
 */
while (true) {
    $CronTasker = new PixelTools\PTsCronTasker();//Модель которая работает с cron-файлами, которые помогают запускать инструмент асинхронно

    $allToolsNames = getAllToolsName($CronTasker);
    $waitTasks = getCurrentWaitTasks($CronTasker, $allToolsNames);//Получаем все задачи, которые ждут выполнения


    if (empty($waitTasks)) {//если пусто спим две секунды
        sleep(2);
        continue;
    }

    $currentRunTasks = getCurrentRunTasks($allToolsNames);//Получаем задачи которые работаю тсейчас

    foreach ($currentRunTasks as $toolName => $countRunTasksForTool) {

        if (toolNotWaitLaunch($toolName, $waitTasks)) {
            continue;
        }

        foreach ($waitTasks[$toolName] as $toolData) {//проверяет лимиты на задачи
            if (countTasksMoreLimit($countRunTasksForTool)) {
                continue 2;
            }

            $pathToCronFile = getPathToCronFile($toolName);//получает путь к крон файлу
            $options = generateOptions($toolData);//создаем необходимые настройки для крон

            $CT = new PixelTools\PTsCronTasker();
            $task = $CT->getOneObjById($options['task_id']);

            if ($task->getStatus() == 1) {
                changeTaskStatus($task);//меняем статус задачи

                if(!cronFileExist($pathToCronFile)){
                    $phpFile = __DIR__ . "/../tools_cron.php";
                    $slug = $toolName;

                    $CronTasker->startProccessingFileSingleCron($phpFile, $slug, $options);//запускам крон файл
                } else {
                    $CronTasker->startProccessingFile($pathToCronFile,
                        json_encode($options),
                        "/var/log/ct_{$toolName}.log");//запускаем старые инструменты, которые работают на legacy коде
                }
            }

            $countRunTasksForTool++;
        }
    }
}

function changeTaskStatus($task, int $status = 2)
{
    $task->setStatus($status);
    $task->save();
}

/**
 * @param $allToolsNames
 * @return array
 */
function getCurrentRunTasks($allToolsNames)
{
    $allRunTasks = [];
    foreach ($allToolsNames as $toolData) {
        $toolName = $toolData['name'];

        $responseCurrentRunTaskByToolName = getArrayRunTasksOnTheTool($toolName);

        $allRunTasks[$toolName] = getCountRunTask($responseCurrentRunTaskByToolName);
    }

    return $allRunTasks;
}

/**
 * @param $cronSlug
 * @return mixed
 */
function getArrayRunTasksOnTheTool($cronSlug)
{
    $pathToCronFile = getPathToCronFile($cronSlug);
    if(cronFileExist($pathToCronFile)) {
        $cmd = 'ps aux | grep ' . $cronSlug . '.php';
        exec($cmd, $responseCurrentRunTaskByToolName);
    } else {
        $CronTasker = new \PixelTools\PTsCronTasker();
        $slug = $CronTasker->getSlugByCronSlug($cronSlug);
        $cmd = "ps aux | grep 'tools_cron.php --slug={$slug}'";
        exec($cmd, $responseCurrentRunTaskByToolName);
    }

    return $responseCurrentRunTaskByToolName;
}


/**
 * @param \PixelTools\PTsCronTasker $CronTasker
 * @param $allToolsNames
 * @return array
 */
function getCurrentWaitTasks(PixelTools\PTsCronTasker $CronTasker, $allToolsNames)
{
    $waitTasks = [];
    foreach ($allToolsNames as $toolData) {
        $toolName = $toolData['name'];
        $toolId = $toolData['id'];

        $waitToolData = $CronTasker->getCurrentTasksByType($toolId);

        if ($waitToolData) {
            $waitTasks[$toolName] = $waitToolData;
        } else {
            continue;
        }

    }

    return $waitTasks;
}

/**
 * @param $toolData
 * @return mixed
 */
function generateOptions($toolData)
{
    #REF есть подозрение, что строка unset($options['file_original_name']) это легаси
    $options = json_decode($toolData['options'], true);
    $options['user_id'] = $toolData['user_id'];
    $options['task_id'] = $toolData['id'];
    unset($options['file_original_name']);

    return $options;
}

/**
 * @param $responseCurrentRunTaskByName
 * @return int
 */
function getCountRunTask($responseCurrentRunTaskByName)
{
    $countSystemInformationInResponse = 2;
    $countRunTask = count($responseCurrentRunTaskByName) - $countSystemInformationInResponse;

    return $countRunTask;
}


/**
 * @param $pathToCronFile
 * @return bool
 */
function cronFileExist($pathToCronFile)
{
    return file_exists($pathToCronFile);
}

/**
 * @param $toolName
 * @return string
 */
function getPathToCronFile($toolName)
{
    $dir = __DIR__ . "/../";;
    $file = $toolName . ".php";

    return $dir . $file;
}

/**
 * @param \PixelTools\PTsCronTasker $CronTasker
 * @return array
 */
function getAllToolsName(PixelTools\PTsCronTasker $CronTasker)
{
    $allToolsNames = $CronTasker->getAllTaskTypes();
    $allToolsNames = deleteEmptyValueFromAllToolsName($allToolsNames);

    return $allToolsNames;

}

/**
 * @param $allToolsName
 * @return array
 */
function deleteEmptyValueFromAllToolsName($allToolsName)
{
    $cleanArray = [];
    foreach ($allToolsName as $value) {
        if (!empty($value['name'])) {
            $cleanArray[] = $value;
        }
    }

    return $cleanArray;
}

/**
 * @param $toolName
 * @param $waitTasks
 * @return bool
 */
function toolNotWaitLaunch($toolName, $waitTasks)
{
    return !array_key_exists($toolName, $waitTasks);
}


/**
 * @param $countRunTasksForTool
 * @return bool
 */
function countTasksMoreLimit($countRunTasksForTool)
{
    $limitRunTaskForSlug = 20;
    return $countRunTasksForTool > $limitRunTaskForSlug ? true : false;
}


