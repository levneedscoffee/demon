<?php
$config = [
    'demon.php' => [
        'dir' => __DIR__ . "/",
    ],
    'tools_killer.php' => [ //не нужен на тесте
        'dir' => __DIR__ . "/",
    ]
];

$actions = ['run', 'restart'];

$options = getParamsFromTerminal();// получаем опции из терминала. например demon.php --action=restart
$action = $options['action'];

if(!in_array($action, $actions)) {
    $action = 'restart';
}

foreach ($config as $demon => $conf) {

    try {
        $pathToDemon = getPathToDemon($conf, $demon);
    } catch (Exception $e) {
        continue;
    }

    $method = getMethod($action);
    $method($pathToDemon, $conf);

}

function restartAction($pathToDemon, $options=[])
{
    deleteAllDemon($pathToDemon);
    runDemon($pathToDemon);
}

function runAction($pathToDemon, $options=[])
{
    if (checkDemons($pathToDemon)) {
        return false;
    }
    runDemon($pathToDemon);
}

function getMethod($action)
{
    return $action.'Action';
}


/**
 * @param $pathToDemon
 * @return bool
 */
function checkDemons($pathToDemon)
{
    $response = getAllDemonProcess($pathToDemon);
    foreach ($response as $line) {
        #REF изменить поиск в строке команды.
        $els = explode(" php ", $line);
        if (count($els) == 2 && end($els) == $pathToDemon) {
            return true;
        }
    }
    return false;
}

function getAllDemonProcess($pathToDemon)
{
    $cmd = "ps aux | grep '{$pathToDemon}'";
    exec($cmd, $processArray);

    return $processArray;
}

function runDemon($pathToDemon)
{
    $cmd = "nohup nice -19 php '{$pathToDemon}' > /dev/null 2>&1 &";
    exec($cmd, $resp);
}

function deleteAllDemon($pathToDemon)
{
    $processArray = getAllDemonProcess($pathToDemon);
    var_dump($processArray);

    foreach ($processArray as $key => $value) {

        $array = preg_split('/\s+/', $value, -1, PREG_SPLIT_NO_EMPTY);
        $pid = $array[1];

        $cmd = 'kill ' . $pid . '';
        exec($cmd);
    }
}

function getParamsFromTerminal()
{
    $params = [
        'name:',
        'action:',
    ];

    $options = getopt(false, $params);

    return $options;
}

function getPathToDemon($demonOptions, $name)
{
    $pathToDemon = $demonOptions['dir'] . $name;

    if (!file_exists($pathToDemon)) {
        throw new Exception("Файла с путем {$pathToDemon} не существует");
    }

    return $pathToDemon;
}

