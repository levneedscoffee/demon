<?php
require_once __DIR__ . '/../libs/cron_app_runner.php';

if (__DIR__ === '/var/www-hosts/tools-test.pixelplus.ru/crontasks/demon') {
    die("tools_killer в боевом режиме должен работать только на паблике\n");
}


$Redis = \PixelLibs\PTsRedis::getInstance();

/**
 * Код удаляющий зависшие задачи
 */
while (true) {
    $taskForDelete = $Redis->hGetAll('stop_tools');

    if (empty($taskForDelete)) {
        sleep(1);
        continue;
    }

    foreach ($taskForDelete as $field => $data) {
        $data = explode('/', $data);

        $cronSlug = $data[0];
        $hid = $data[1];
        $user_id = $data[2];
        $task_id = $data[3];

        $response = [];
        $cmd = getCmdString($cronSlug, $hid, $task_id, $user_id);
        exec($cmd, $response);

        foreach ($response as $answer) {
            $array2 = preg_split('/\s+/', $answer, -1, PREG_SPLIT_NO_EMPTY);
            $pid = $array2[1];

            //удаляем пиды только php скриптов
            if ($array2[10] === 'php') {
                $cmd = 'kill ' . $pid . '';
                exec($cmd);
            }
        }

        //удаляем из Редиса ключ убитой задачи
        $Redis->hDel('stop_tools', $field);
        usleep(500000);
    }
}



##удалить когда все тулзы будут переведины на единый крон
function getCmdString($cronSlug, $hid, $task_id, $user_id)
{
    $pathToCronFile = getPathToCronFile($cronSlug);
    if(file_exists($pathToCronFile)) {
        $cmd = "ps aux | grep {$cronSlug}.php --hid={$hid} --user_id={$user_id} --task_id={$task_id}";
    } else {
        $PTsIdAdapter = new \PixelTools\PTsIdAdapter();
        $slug = $PTsIdAdapter->getSlugByCronSlug($cronSlug);
        $cmd = "ps aux | grep 'tools_cron.php --slug={$slug} --hid={$hid} --user_id={$user_id} --task_id={$task_id}'";
    }

    return $cmd;
}


function getPathToCronFile($toolName)
{
    $dir = __DIR__ . "/../";;
    $file = $toolName . ".php";

    return $dir . $file;
}








