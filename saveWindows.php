<?php
if($_SERVER['REQUEST_METHOD'] === 'POST'){
if(isset($_POST['setWindows'])){
$setWindowsJson = $_POST['setWindows'];
$filePath = __DIR__ . '/windowsState.txt';
if(file_put_contents($filePath, $setWindowsJson) !== false){
echo json_encode([
"success" => true, 
"message" => "Successfully!"
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