<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
$setWindows = file_get_contents("windowsState.txt");
echo $setWindows;
?>