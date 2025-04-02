<?php

//DECODIFICAR JSON
if(!isset($_POST['printData'])){
echo json_encode(["success" => false, "message" => "Print Data."]);
exit;
}
$data = json_decode($_POST['printData'], true);

//POST DATA
$setPrinterName  = $data['setPrinterName'] ?? '';
$setPrint        = isset($data['setPrint']) ? (int)$data['setPrint'] : 0;
$setPdfName      = $data['setPdfName'] ?? '';

//VALIDAR DATA
if($setPrint != 1 || empty($setPdfName)){
echo json_encode(["success" => false, "message" => "Print Data."]);
exit;
}

//PROCESAR PDF
$pdfBlob = null;
if(isset($_FILES['pdfBlob']) && $_FILES['pdfBlob']['error'] === UPLOAD_ERR_OK){
$fileType = mime_content_type($_FILES['pdfBlob']['tmp_name']);
if ($fileType === 'application/pdf') {
$pdfBlob = file_get_contents($_FILES['pdfBlob']['tmp_name']);
}
}

//PDF OBLIGATORIO
if($pdfBlob === null){
echo json_encode(["success" => false, "message" => "PDF."]);
exit;
}

//API PRINT FOLDER
$targetFolder = "C:\\apiPrint";
if(!is_dir($targetFolder) || !is_writable($targetFolder)){
echo json_encode(["success" => false, "message" => "apiPrint Not Found."]);
exit;
}

//GUARDAR PDF
$pdfFilePath = $targetFolder . DIRECTORY_SEPARATOR . $setPdfName;
if(file_put_contents($pdfFilePath, $pdfBlob) === false){
echo json_encode(["success" => false, "message" => "PDF Save."]);
exit;
}

//GENERAR ID PRINT
$idPrint = uniqid();

//JSON DATA
$json_data = [
"pdfUrl"      => $pdfFilePath,
"exeUrl"      => "C:\\Program Files (x86)\\Foxit Software\\Foxit PDF Reader\\FoxitPDFReader.exe",
"idPrint"     => $idPrint,
"printerName" => $setPrinterName
];

//GUARDAR JSON DATA EN TXT
$txtFilePath = $targetFolder . DIRECTORY_SEPARATOR . $idPrint . ".txt";
if(file_put_contents($txtFilePath, json_encode($json_data, JSON_UNESCAPED_UNICODE)) === false){
echo json_encode(["success" => false, "message" => "TXT Save."]);
exit;
}

//RESPUESTA EXITOSA
echo json_encode(["success" => true, "message" => "Print Sent Successfully !!!"]);
echo "<script>window.close();</script>";