<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    if(isset($_POST['setWindows'])){
        $incomingJson = $_POST['setWindows'];
        $incomingWindows = json_decode($incomingJson, true);
        if($incomingWindows === null){
            echo json_encode([
                "success" => false, 
                "message" => "Invalid JSON."
            ]);
            exit;
        }
        $filePath = __DIR__ . '/windowsState.txt';
        $existingWindows = [];
        if(file_exists($filePath)){
            $existingContent = file_get_contents($filePath);
            $existingWindows = json_decode($existingContent, true);
            if(!is_array($existingWindows)){
                $existingWindows = [];
            }
        }
        $existingIndex = [];
        foreach($existingWindows as $window){
            if(isset($window['setWindowId'])){
                $existingIndex[$window['setWindowId']] = $window;
            }
        }
        foreach($incomingWindows as $incomingWindow){
            if (!isset($incomingWindow['setWindowId'])) continue;
            $id = $incomingWindow['setWindowId'];
            
            $incomingTime = strtotime($incomingWindow['setLastDateUpdate']);
            
            if(isset($existingIndex[$id])){
                $existingTime = strtotime($existingIndex[$id]['setLastDateUpdate']);
                if($incomingTime >= $existingTime){
                    $existingIndex[$id] = $incomingWindow;
                }
            } else {
                $existingIndex[$id] = $incomingWindow;
            }
        }
        $mergedWindows = array_values($existingIndex);
        if(file_put_contents($filePath, json_encode($mergedWindows)) !== false){
            echo json_encode([
                "success" => true, 
                "message" => "Successfully."
            ]);
        } else {
            echo json_encode([
                "success" => false, 
                "message" => "Data Save Error."
            ]);
        }
    } else {
        echo json_encode([
            "success" => false, 
            "message" => "Param Error."
        ]);
    }
} else {
    echo json_encode([
        "success" => false, 
        "message" => "Method Error."
    ]);
}
?>