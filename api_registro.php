<?php
set_time_limit(180);
ini_set('max_execution_time', 180);
//CABECERA JSON UTF8
header('Content-Type: application/json; charset=utf-8');

// ZONA HORARIA
date_default_timezone_set('America/Lima');

//VALIDAR SI NO ES METODO POST
if($_SERVER['REQUEST_METHOD'] !== 'POST'){
echo lp_json_utf8(false, 'Método No Permitido.');
exit();
}

function lp_json_utf8($success, $message){

// Funcion para aplicar utf8_encode recursivamente
function utf8_encode_recursive($data){
if(is_array($data)){
return array_map('utf8_encode_recursive', $data);
} elseif (is_string($data)) {
if (!mb_check_encoding($data, 'UTF-8')){
return utf8_encode($data);
}
}
return $data;
}

//SI EL MENSAJE ES UN ARRAY RECORRERLO Y FORMATEAR CADA ELEMENTO
if(is_array($message)){
$message = utf8_encode_recursive($message);
} elseif (is_string($message)){
if (!mb_check_encoding($message, 'UTF-8')){
$message = utf8_encode($message);
}
}

//FORMATEAR LA RESPUESTA CON UTF8_ENCODE
$response = [
'success' => $success,
'message' => $message
];

//RETORNAR EL JSON CODIFICADO
ob_clean();
flush(); 
return json_encode($response, JSON_UNESCAPED_UNICODE);
}
function convertToUtf8($data){
if(is_array($data)){
foreach ($data as $key => $value){
$data[$key] = convertToUtf8($value);
}
} elseif (is_string($data)){
if(!mb_check_encoding($data, 'UTF-8')){
$data = mb_convert_encoding($data, 'UTF-8', 'ISO-8859-1');
}
}

//RETORNAR DATA
return $data;
}

// VERIFICAR SI LOS DATOS SON JSON O DATOS DE FORMULARIO
$data = [];
if(isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false){

// OBTENER BODY DE LA SOLICITUD POST COMO JSON
$json = file_get_contents('php://input');
$data = json_decode($json, true);

} else {
// SI NO ES JSON, ASUMIMOS QUE SON DATOS DE FORMULARIO
$data = json_decode($_POST['invoice'], true);
}

// VALIDAR SI NO ES UN JSON VALIDO
if (json_last_error() !== JSON_ERROR_NONE) {
echo lp_json_utf8(false, 'JSON Inválido.');
exit();
}

//FORMATEAR TODOS LOS DATOS A UTF8
$data = convertToUtf8($data);

//DATOS DEL BODY API
$setToken = $data['setToken'];
$apiPOS_getAccessData = (int)$data['apiPOS_getAccessData'];
$apiPOS_getItems = (int)$data['apiPOS_getItems'];
$apiPOS_getAttach = (int)$data['apiPOS_getAttach'];
$apiPOS_getLastCorrelative = (int)$data['apiPOS_getLastCorrelative'];
$apiPOS_shift = (int)$data['apiPOS_shift'];
$apiPOS_getShiftDetails = (int)$data['apiPOS_getShiftDetails'];
$apiPOS_getIncome = (int)$data['apiPOS_getIncome'];

//CONEXION BD
require_once(__DIR__ . '/api_lp_conexion_bd.php');

//VALIDAR DATOS DE EMPRESA CON EL TOKEN Y PORCENTAJE IGV EMPRESA
$query_company = mysqli_prepare($con, "
SELECT le.id_perfil AS IDEMPRESA, lm.codigo AS CURRENCY, 
le.logo_url AS setCompanyLogo, 
le.ruc AS setCompanyDoc, 
le.nombre_empresa AS setCompanyName, 
le.nombre_comercial AS setCompanyNameTrade, 
le.direccion AS setCompanyAddress, 
le.telefono AS setCompanyPhone, 
le.email AS setCompanyEmail, 
le.web AS setCompanyWebsite, 
le.id_impuesto AS setTaxIdDefault 
FROM perfil le 
INNER JOIN pais lp ON lp.id = le.pais 
INNER JOIN moneda lm ON lm.id = lp.id_moneda 
WHERE le.token_apierp = ? 
LIMIT 1 
");
mysqli_stmt_bind_param($query_company, 's', $setToken);
mysqli_stmt_execute($query_company);
$result_company = mysqli_stmt_get_result($query_company);
if(mysqli_num_rows($result_company) > 0){

//DATOS COMPANY
$row_company = mysqli_fetch_array($result_company);
$company_id = $row_company['IDEMPRESA'];
$currency_default = $row_company['CURRENCY'];
if(!empty($row_company['setCompanyLogo'])){
$imageUrl = 'https://neg-img-empresa.s3.amazonaws.com/' . $row_company['setCompanyLogo'];
$imageData = file_get_contents($imageUrl);
if ($imageData !== false) {
$mimeType = mime_content_type($imageUrl);

// Crear el recurso de imagen desde la cadena descargada
$srcImage = imagecreatefromstring($imageData);
if (!$srcImage) {
$setCompanyLogo = '';
} else {
// Obtener dimensiones originales
$imageSize = getimagesizefromstring($imageData);
list($origWidth, $origHeight) = $imageSize;

// Definir dimensiones máximas deseadas
$maxWidth  = 200;
$maxHeight = 200;

// Calcular el factor de escalado manteniendo la proporción
$ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight);
if ($ratio < 1) {
$newWidth  = $origWidth * $ratio;
$newHeight = $origHeight * $ratio;
} else {
$newWidth  = $origWidth;
$newHeight = $origHeight;
}

// Crear una imagen destino con las nuevas dimensiones
$dstImage = imagecreatetruecolor($newWidth, $newHeight);

if ($mimeType === 'image/png') {
// Para PNG, preservar la transparencia
imagealphablending($dstImage, false);
imagesavealpha($dstImage, true);
} else {
// Para JPEG, rellenar con fondo blanco
$white = imagecolorallocate($dstImage, 255, 255, 255);
imagefill($dstImage, 0, 0, $white);
}

// Redimensionar la imagen sin distorsionar
imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

// Capturar la imagen redimensionada en un buffer
ob_start();
if ($mimeType === 'image/png') {
imagepng($dstImage);
} else {
imagejpeg($dstImage, null, 80);
}
$resizedImageData = ob_get_clean();

// Liberar recursos
imagedestroy($srcImage);
imagedestroy($dstImage);

// Convertir a base64 y crear el data URI manteniendo el MIME original
$base64Image = base64_encode($resizedImageData);
$setCompanyLogo = 'data:' . $mimeType . ';base64,' . $base64Image;
}
} else {
$setCompanyLogo = '';
}
} else {
$setCompanyLogo = '';
}
$setCompanyDoc = $row_company['setCompanyDoc'];
$setCompanyName = $row_company['setCompanyName'];
$setCompanyNameTrade = $row_company['setCompanyNameTrade'];
$setCompanyAddress = $row_company['setCompanyAddress'];
$setCompanyPhone = $row_company['setCompanyPhone'];
$setCompanyEmail = $row_company['setCompanyEmail'];
$setCompanyWebsite = $row_company['setCompanyWebsite'];
$setTaxIdDefault = (int)$row_company['setTaxIdDefault'];
} else {
echo lp_json_utf8(false, "TOKEN INCORRECTO.");
exit();
}

//ACCESS DATA
if($apiPOS_getAccessData == 1){

//PARAMS
$setUserLocalUserName = $data['setUserLocalUserName'];

//OBTENER ID DEL USUARIO
$query_user = mysqli_prepare($con, "
SELECT lu.user_id AS setUserId 
FROM users lu 
WHERE lu.user = ? AND lu.empresa = ? AND estado = 1 
LIMIT 1 
");
mysqli_stmt_bind_param($query_user, 'si', $setUserLocalUserName, $company_id);
mysqli_stmt_execute($query_user);
$result_user = mysqli_stmt_get_result($query_user);
if(mysqli_num_rows($result_user) > 0){
$row_user = mysqli_fetch_array($result_user);
$setUserId = $row_user['setUserId'];
} else {
$setUserId = '';
}

//VALIDAR SI HAY USER ID
if($setUserId != ''){
$query_inner_sucursales = "INNER JOIN almacen_usuario lsu ON lsu.id_sucursal = al.id AND lsu.empresa = al.empresa AND lsu.estado = '1' AND lsu.id_usuario = '$setUserId'";
} else {
$query_inner_sucursales = '';
}

//SUCURSALES
$sql_almacenes = "
SELECT 
al.id AS setBranchId, 
al.codigo AS setBranchCode, 
al.nombre AS setBranchName, 
al.direccion AS setBranchAddress, 

COALESCE(
(
SELECT JSON_ARRAYAGG(
JSON_OBJECT(
'setPrinterId', li.id, 
'setPrinterName', li.nombre_impresora, 
'setPrinterAlias', li.alias_impresora
)
)
FROM (
SELECT DISTINCT li.id, li.nombre_impresora, li.alias_impresora
FROM impresion li
WHERE li.id_sucursal = al.id AND li.empresa = al.empresa
) AS li
), '[]'
) AS printers,

COALESCE(
(
SELECT JSON_ARRAYAGG(
JSON_OBJECT(
'setWarehouseId', la.id, 
'setWarehouseName', 
CASE 
WHEN la.tipo = 3 THEN 'Almacen Principal' 
ELSE la.nombre 
END
)
)
FROM (
SELECT DISTINCT la.id, la.nombre, la.tipo
FROM almacen la
WHERE la.id_sucursal = al.id 
AND (la.tipo = 2 OR la.tipo = 3) 
AND la.estado = 1 AND la.empresa = al.empresa
) AS la
), '[]'
) AS warehouses

FROM almacen al 
$query_inner_sucursales
WHERE al.estado = '1' AND (al.tipo = 1 OR al.tipo = 3) AND al.empresa = ? 
GROUP BY al.id
";
$query_almacenes = mysqli_prepare($con, $sql_almacenes);
mysqli_stmt_bind_param($query_almacenes, 'i', $company_id);
mysqli_stmt_execute($query_almacenes);
$result_almacenes = mysqli_stmt_get_result($query_almacenes);

//IMPRESORAS
$sql_printers = "
SELECT li.id AS setPrinterId, li.nombre_impresora AS setPrinterName, li.alias_impresora AS setPrinterAlias, li.id_sucursal AS setPrinterBranchId 
FROM impresion li 
WHERE li.estado = '1' AND li.empresa = ? 
";
$query_printers = mysqli_prepare($con, $sql_printers);
mysqli_stmt_bind_param($query_printers, 'i', $company_id);
mysqli_stmt_execute($query_printers);
$result_printers = mysqli_stmt_get_result($query_printers);

//USUARIOS
$id_permiso_vender_sin_stock = 28;
$id_permiso_ver_stock_sucursales = 191;
$sql_usuarios = "
SELECT 
us.user_id AS setUserCodigo, 
us.dni AS setUserDocNumber, 
CONCAT(us.firstname, ' ', us.lastname) AS setUserNombres, 
us.user AS setUserName, 
us.clave AS setUserPassw, 
CASE WHEN us.firma = '' THEN '' ELSE CONCAT('https://neg-img-firma.s3.amazonaws.com/', us.firma) END AS setUserSignature, 
au.id_sucursal AS setBranchId 

FROM users us 
LEFT JOIN permisos_negocia_usuario pnu ON pnu.id_permiso = '$id_permiso_vender_sin_stock' AND pnu.id_usuario = us.user_id AND pnu.empresa = us.empresa 
LEFT JOIN permisos_negocia_usuario pnu2 ON pnu2.id_permiso = '$id_permiso_ver_stock_sucursales' AND pnu2.id_usuario = us.user_id AND pnu2.empresa = us.empresa 
LEFT JOIN almacen_usuario au ON au.id_usuario = us.user_id AND au.estado = 1 AND au.empresa = us.empresa 
WHERE us.estado = '1' AND us.empresa = ? 
";
$query_usuarios = mysqli_prepare($con, $sql_usuarios);
mysqli_stmt_bind_param($query_usuarios, 'i', $company_id);
mysqli_stmt_execute($query_usuarios);
$result_usuarios = mysqli_stmt_get_result($query_usuarios);

//NUMEROS DE SERIE
$sql_series = "
SELECT 
ns.id AS ID_SERIE, 
ns.numero AS setSerie, 

CASE 
WHEN ns.tipo = 3 THEN '3' 
WHEN ns.tipo = 4 THEN '4' 
WHEN ns.tipo = 5 THEN '2' 
WHEN ns.tipo = 21 THEN '41' 
WHEN ns.tipo = 22 THEN '1' 
WHEN ns.tipo = 34 THEN '48' 
WHEN ns.tipo = 13 THEN '55' 
END AS setSerieTipo, 
(
CASE 

WHEN ns.tipo IN (3, 4, 5) THEN 
(
SELECT CAST(COALESCE(MAX(CAST(fac.numero_factura AS UNSIGNED)), 0) + 1 AS UNSIGNED) 
FROM facturas fac 
WHERE fac.serie = ns.id AND fac.empresa = '$company_id' AND fac.tipo_resumen != 9999 AND fac.estado_sunat != 9999 
)

WHEN ns.tipo = 21 THEN 
(
SELECT CAST(COALESCE(MAX(CAST(cot.numero_factura AS UNSIGNED)), 0) + 1 AS UNSIGNED) 
FROM cotizaciones cot 
WHERE cot.tipo_operacion = '1' AND cot.id_serie = ns.id AND cot.empresa = '$company_id'
)

WHEN ns.tipo = 22 THEN 
(
SELECT CAST(COALESCE(MAX(CAST(cot.numero_factura AS UNSIGNED)), 0) + 1 AS UNSIGNED) 
FROM cotizaciones cot 
WHERE cot.tipo_operacion = '2' AND cot.id_serie = ns.id AND cot.empresa = '$company_id'
)

WHEN ns.tipo = 34 THEN 
(
SELECT CAST(COALESCE(MAX(CAST(oc.numero_factura AS UNSIGNED)), 0) + 1 AS UNSIGNED) 
FROM orden_compras oc 
WHERE oc.id_serie = ns.id AND oc.empresa = '$company_id'
)

WHEN ns.tipo = 13 THEN 
(
SELECT CAST(COALESCE(MAX(CAST(gr.correlativo AS UNSIGNED)), 0) + 1 AS UNSIGNED) 
FROM guia_remision gr 
WHERE gr.serie = ns.id AND gr.empresa = '$company_id'
)

ELSE 1 
END
) AS setCorrelativoInicial, 
aser.almacen AS setIdSucursal, 
ns.correlativo AS setSeriesNumberInitial 
FROM numero_serie ns 
INNER JOIN almacen_serie aser ON aser.serie = ns.id AND aser.empresa = '$company_id' 
WHERE ns.empresa = ? AND (
ns.tipo = 3 OR ns.tipo = 4 OR ns.tipo = 5 OR ns.tipo = 21 OR 
ns.tipo = 22 OR ns.tipo = 34 OR ns.tipo = 13
) 
";
$query_series = mysqli_prepare($con, $sql_series);
mysqli_stmt_bind_param($query_series, 'i', $company_id);
mysqli_stmt_execute($query_series);
$result_series = mysqli_stmt_get_result($query_series);

//BANCOS Y CAJAS
$sql_bancos = "
SELECT 
lb.id AS setBankId, 
lb.tipo AS setBankType, 
lb.banco AS setBankName, 
lb.numero AS setBankNumber, 
lb.cci AS setBankCCI, 
lm.codigo AS setBankCurrency,
(
lb.saldo_inicial
+ COALESCE(c.ingreso, 0)
- COALESCE(p.egreso,  0)
) AS setBankBalance, 
lb.id_usuario AS setBankUserDefault 
FROM datosbancarios_empresa lb 
INNER JOIN moneda lm ON lm.id = lb.moneda 
LEFT JOIN (
SELECT banco, SUM(monto) AS ingreso
FROM cobros
WHERE empresa = ?
GROUP BY banco
) AS c
ON c.banco = lb.id
LEFT JOIN (
SELECT banco, SUM(monto) AS egreso
FROM pagos
WHERE empresa = ?
GROUP BY banco
) AS p
ON p.banco = lb.id
WHERE lb.estado = 1 AND lb.empresa = ? 
";
$query_bancos = mysqli_prepare($con, $sql_bancos);
mysqli_stmt_bind_param($query_bancos, 'iii', $company_id, $company_id, $company_id);
mysqli_stmt_execute($query_bancos);
$result_bancos = mysqli_stmt_get_result($query_bancos);

//CLIENTES
$sql_clientes = "
SELECT 
lc.id_cliente AS setCustomerId, 
lc.dni AS setCustomerDocumentNumber, 
lc.nombre_cliente AS setCustomerName, 
lc.direccion_cliente AS setCustomerAddress, 
lc.distrito AS setCustomerUbigeo, 
lc.fecha_nac AS setCustomerBirthdate, 
lc.id_sistema_puntaje AS setCustomerScoringSystemId, 
lc.puntos AS setCustomerScore 

FROM clientes lc 
WHERE lc.status_cliente = 1 AND lc.empresa = ? 
";
$query_clientes = mysqli_prepare($con, $sql_clientes);
mysqli_stmt_bind_param($query_clientes, 'i', $company_id);
mysqli_stmt_execute($query_clientes);
$result_clientes = mysqli_stmt_get_result($query_clientes);

//PROVEEDORES
$sql_proveedores = "
SELECT 
lp.id AS setSupplierId, 
lp.ruc AS setSupplierDocumentNumber, 
lp.razon AS setSupplierName, 
lp.direccion AS setSupplierAddress, 
lp.distrito AS setSupplierUbigeo, 
lp.fecha_nac AS setSupplierBirthdate 

FROM proveedoresm lp 
WHERE lp.estado = 1 AND lp.empresa = ? 
";
$query_proveedores = mysqli_prepare($con, $sql_proveedores);
mysqli_stmt_bind_param($query_proveedores, 'i', $company_id);
mysqli_stmt_execute($query_proveedores);
$result_proveedores = mysqli_stmt_get_result($query_proveedores);

//COLABORADORES
$sql_colaboradores = "
SELECT 
lc.id_cliente AS setCollaboratorId, 
lc.dni AS setCollaboratorDocNumber, 
lc.nombre_cliente AS setCollaboratorFullName, 
lc.direccion_cliente AS setCollaboratorAddress, 
lc.distrito AS setCollaboratorUbigeo, 
lc.fecha_nacimiento AS setCollaboratorBirthdate 

FROM colaboradores lc 
WHERE lc.status_cliente = 1 AND lc.empresa = ? 
";
$query_colaboradores = mysqli_prepare($con, $sql_colaboradores);
mysqli_stmt_bind_param($query_colaboradores, 'i', $company_id);
mysqli_stmt_execute($query_colaboradores);
$result_colaboradores = mysqli_stmt_get_result($query_colaboradores);

//VALIDAR SI HAY USER ID
if($setUserId != ''){

//TURNOS ABIERTOS EN TODAS LAS SUCURSALES
$sql_turnos = "
SELECT 
lt.id AS setShiftId, 
lt.monto_soles AS setCashInitial, 
lt.fecha_inicio AS setShiftDateStart, 
'1' AS setShiftStatus, 
lt.id_sucursal AS setBranchId 

FROM apertura_caja lt 
WHERE lt.fecha_fin = '' AND lt.id_usuario = ? AND lt.empresa = ? 
GROUP BY lt.id_sucursal 
";
$query_turnos = mysqli_prepare($con, $sql_turnos);
mysqli_stmt_bind_param($query_turnos, 'ii', $setUserId, $company_id);
mysqli_stmt_execute($query_turnos);
$result_turnos = mysqli_stmt_get_result($query_turnos);
}

//DIRECCIONES PUNTO DE PARTIDA - GUIAS DE REMISION
$sql_punto_partida = "
SELECT 
grpp.id AS setPlaceStartId, 
grpp.id_sucursal AS setPlaceStartBranchId, 
grpp.anexo AS setPlaceStartCode, 
grpp.nombre AS setPlaceStartAddress, 
grpp.id_distrito AS setPlaceStartUbigeo 

FROM guia_remision_punto_partida grpp 
WHERE grpp.estado = 1 AND grpp.empresa = ? 
";
$query_punto_partida = mysqli_prepare($con, $sql_punto_partida);
mysqli_stmt_bind_param($query_punto_partida, 'i', $company_id);
mysqli_stmt_execute($query_punto_partida);
$result_punto_partida = mysqli_stmt_get_result($query_punto_partida);

//VEHICULOS / UNIDADES DE TRANSPORTE
$sql_unidad_transporte = "
SELECT 
ut.id AS setVehicleId, 
ut.nombre AS setVehicleName, 
ut.placa_transporte AS setVehiclePlate, 
ut.marca_transporte AS setVehicleBrand 

FROM unidad_transporte ut 
WHERE ut.estado = 1 AND ut.empresa = ? 
";
$query_unidad_transporte = mysqli_prepare($con, $sql_unidad_transporte);
mysqli_stmt_bind_param($query_unidad_transporte, 'i', $company_id);
mysqli_stmt_execute($query_unidad_transporte);
$result_unidad_transporte = mysqli_stmt_get_result($query_unidad_transporte);

//CONDUCTORES
$sql_conductor = "
SELECT 
lc.id AS setDriverId, 
td.nombre AS setDriverDocType, 
lc.numero_documento AS setDriverDocNumber, 
lc.nombre AS setDriverName, 
lc.licencia_conductor AS setDriverLicense, 
lc.certificado_inscripcion AS setDriverCertificate 

FROM conductor lc 
INNER JOIN tipo_documento td ON td.id = lc.tipo_documento

WHERE lc.estado = 1 AND lc.empresa = ? 
";
$query_conductor = mysqli_prepare($con, $sql_conductor);
mysqli_stmt_bind_param($query_conductor, 'i', $company_id);
mysqli_stmt_execute($query_conductor);
$result_conductor = mysqli_stmt_get_result($query_conductor);

//ITEMS - CATEGORIAS
$sql_items_categorias = "
SELECT 
lfp.id AS setItemCategoryId, 
lfp.nombre AS setItemCategoryName 
FROM familia_productom lfp 
WHERE lfp.estado = 1 AND lfp.empresa = ? 
";
$query_items_categorias = mysqli_prepare($con, $sql_items_categorias);
mysqli_stmt_bind_param($query_items_categorias, 'i', $company_id);
mysqli_stmt_execute($query_items_categorias);
$result_items_categorias = mysqli_stmt_get_result($query_items_categorias);

//ITEMS - MARCAS
$sql_items_marcas = "
SELECT 
lmp.id AS setItemBrandId, 
lmp.nombre AS setItemBrandName 
FROM marca lmp 
WHERE lmp.estado = 1 AND lmp.empresa = ? 
";
$query_items_marcas = mysqli_prepare($con, $sql_items_marcas);
mysqli_stmt_bind_param($query_items_marcas, 'i', $company_id);
mysqli_stmt_execute($query_items_marcas);
$result_items_marcas = mysqli_stmt_get_result($query_items_marcas);

//ASIGNACION DE BANCOS A MEDIOS DE PAGO
$sql_apb = "
SELECT 
fp.id_apipos AS setPaymentId, 
fpb.id_banco AS setBankId 
FROM forma_pago_banco fpb 
INNER JOIN forma_pago fp ON fp.id = fpb.id_forma_pago 
WHERE fpb.empresa = ? 
";
$query_apb = mysqli_prepare($con, $sql_apb);
mysqli_stmt_bind_param($query_apb, 'i', $company_id);
mysqli_stmt_execute($query_apb);
$result_apb = mysqli_stmt_get_result($query_apb);

//SISTEMAS DE PUNTAJE
$sql_puntaje = "
SELECT 
lp.id AS setScoringSystemId, 
lp.nombre AS setScoringSystemName, 
lp.id_tipo_vigencia AS setScoringSystemValidityType, 
lp.fecha_desde AS setScoringSystemValidFrom, 
lp.fecha_hasta AS setScoringSystemValidUntil, 
lp.equivalencia_soles AS setScoringSystemConvertingPointsToCurrencyP, 
lp.equivalencia_puntos AS setScoringSystemConvertingPointsToCurrencyC, 
lp.equivalencia_canje_puntos AS setScoringSystemConvertingCurrencyToPointsC, 
lp.equivalencia_canje_soles AS setScoringSystemConvertingCurrencyToPointsP 
FROM puntaje lp 
WHERE lp.empresa = ? 
";
$query_puntaje = mysqli_prepare($con, $sql_puntaje);
mysqli_stmt_bind_param($query_puntaje, 'i', $company_id);
mysqli_stmt_execute($query_puntaje);
$result_puntaje = mysqli_stmt_get_result($query_puntaje);

//ARRAY RETURN
$array_return = [];

//DATOS COMPANY
$array_return['setCompanyLogo'] = $setCompanyLogo;
$array_return['setCompanyDoc'] = $setCompanyDoc;
$array_return['setCompanyName'] = $setCompanyName;
$array_return['setCompanyNameTrade'] = $setCompanyNameTrade;
$array_return['setCompanyAddress'] = $setCompanyAddress;
$array_return['setCompanyPhone'] = $setCompanyPhone;
$array_return['setCompanyEmail'] = $setCompanyEmail;
$array_return['setCompanyWebsite'] = $setCompanyWebsite;

//ALMACENES
$branches = [];
while($row_almacen = mysqli_fetch_assoc($result_almacenes)){
$idSucursal = $row_almacen['setBranchId'];

// Convertir los JSON almacenados en arrays PHP
$printers = json_decode($row_almacen['printers'], true) ?? [];
$warehouses = json_decode($row_almacen['warehouses'], true) ?? [];

// Guardar la sucursal en el array
$branches[$idSucursal] = [
'setBranchId' => $row_almacen['setBranchId'],
'setBranchCode' => $row_almacen['setBranchCode'],
'setBranchName' => $row_almacen['setBranchName'],
'setBranchAddress' => $row_almacen['setBranchAddress'],
'setPrinters' => $printers,
'setWarehouses' => $warehouses,
];
}

//IMPRESORAS
$impresoras = [];
while($row_printer = mysqli_fetch_assoc($result_printers)){
$impresoras[] = $row_printer;
}

//USUARIOS
$usuarios = [];
while($row_user = mysqli_fetch_assoc($result_usuarios)){
$userId = $row_user['setUserCodigo'];

//FIRMA EN BASE64
$rawSignature = $row_user['setUserSignature'] ?? '';
if($rawSignature !== ''){
// URL original en S3
$imageUrl = $rawSignature;
$imageData = @file_get_contents($imageUrl);
if ($imageData !== false) {
$mimeType = mime_content_type($imageUrl);
// Creamos recurso GD
$srcImg = imagecreatefromstring($imageData);
if ($srcImg !== false) {
// Redimensionar si lo necesitas (opcional)
$maxW = 200; $maxH = 200;
list($origW, $origH) = getimagesizefromstring($imageData);
$ratio = min($maxW/$origW, $maxH/$origH, 1);
$newW  = intval($origW * $ratio);
$newH  = intval($origH * $ratio);
$dstImg = imagecreatetruecolor($newW, $newH);
if ($mimeType === 'image/png') {
imagealphablending($dstImg, false);
imagesavealpha($dstImg, true);
} else {
$white = imagecolorallocate($dstImg, 255,255,255);
imagefill($dstImg, 0,0, $white);
}
imagecopyresampled($dstImg, $srcImg, 0,0, 0,0, $newW, $newH, $origW, $origH);

// Capturamos la imagen final
ob_start();
if ($mimeType === 'image/png') {
imagepng($dstImg);
} else {
imagejpeg($dstImg, null, 80);
}
$resizedData = ob_get_clean();
imagedestroy($srcImg);
imagedestroy($dstImg);

// Codificamos a Base64
$base64 = base64_encode($resizedData);
$setSignature = "data:$mimeType;base64,$base64";
} else {
// No se pudo crear recurso GD
$setSignature = '';
}
} else {
// No se pudo descargar
$setSignature = '';
}
} else {
// No hay firma definida
$setSignature = '';
}

// Si aún no se ha agregado el usuario, creamos su estructura básica.
if(!isset($usuarios[$userId])){
$usuarios[$userId] = [
'setUserCodigo'          => $row_user['setUserCodigo'],
'setUserDocNumber'       => $row_user['setUserDocNumber'],
'setUserNombres'         => $row_user['setUserNombres'],
'setUserName'            => $row_user['setUserName'],
'setUserPassw'           => $row_user['setUserPassw'],
'setUserSignature'        => $setSignature,
'setBranches'             => [] 
];
}

// Si existe información de sucursal para este usuario, la agregamos
if(isset($row_user['setBranchId']) && !empty($row_user['setBranchId'])){
$usuarios[$userId]['setBranches'][] = [
'setBranchId' => $row_user['setBranchId']
];
}
}

//NUMEROS DE SERIE
$series = [];
while($row_serie = mysqli_fetch_assoc($result_series)){
$series[] = $row_serie;
}

//BANCOS Y CAJAS
$bancos = [];
while($row_bancos = mysqli_fetch_assoc($result_bancos)){
$row['setBankBalance'] = (float)$row['setBankBalance'];
$bancos[] = $row_bancos;
}

//CLIENTES
$clientes = [];
while($row_cliente = mysqli_fetch_assoc($result_clientes)){
$clientes[] = $row_cliente;
}

//PROVEEDORES
$proveedores = [];
while($row_proveedor = mysqli_fetch_assoc($result_proveedores)){
$proveedores[] = $row_proveedor;
}

//VALIDAR SI HAY USER ID
if($setUserId != ''){

//TURNOS ABIERTOS
$turnos = [];
while($row_turnos = mysqli_fetch_assoc($result_turnos)){
$turnos[] = $row_turnos;
}
}

//COLABORADORES
$colaboradores = [];
while($row_colaborador = mysqli_fetch_assoc($result_colaboradores)){
$colaboradores[] = $row_colaborador;
}

//PUNTOS DE PARTIDA - GUIA DE REMISION REMITENTE DE VENTAS
$puntos_llegadas = [];
while($row_puntos_llegadas = mysqli_fetch_assoc($result_punto_partida)){
$puntos_llegadas[] = $row_puntos_llegadas;
}

//VEHICULOS / UNIDADES DE TRANSPORTE
$vehiculos = [];
while($row_vehiculos = mysqli_fetch_assoc($result_unidad_transporte)){
$vehiculos[] = $row_vehiculos;
}

//CONDUCTORES
$conductores = [];
while($row_conductores = mysqli_fetch_assoc($result_conductor)){
$conductores[] = $row_conductores;
}

//ITEMS - CATEGORIAS
$item_categories = [];
while($row_categories = mysqli_fetch_assoc($result_items_categorias)){
$item_categories[] = $row_categories;
}

//ITEMS - MARCAS
$item_brands = [];
while($row_brands = mysqli_fetch_assoc($result_items_marcas)){
$item_brands[] = $row_brands;
}

//ASIGNACION DE PAGOS A BANCOS
$apb = [];
while($row_apb = mysqli_fetch_assoc($result_apb)){
$apb[] = $row_apb;
}

//SISTEMAS DE PUNTAJE
$item_puntaje = [];
while($row_puntaje = mysqli_fetch_assoc($result_puntaje)){
$item_puntaje[] = $row_puntaje;
}

//ALMACENES
$array_return['setBranches'] = array_values($branches);

//IMPRESORAS
$array_return['setPrinters'] = $impresoras;

//USUARIOS
$array_return['setUsuarios'] = array_values($usuarios);

//SERIES
$array_return['setSeries'] = $series;

//BANCOS
$array_return['setBankAccounts'] = $bancos;

//CLIENTES
$array_return['setCustomers'] = $clientes;

//PROVEEDORES
$array_return['setSuppliers'] = $proveedores;

//VALIDAR SI HAY USER ID
if($setUserId != ''){

//TURNOS
$array_return['setShifts'] = $turnos;
}

//COLABORADORES
$array_return['setCollaborators'] = $colaboradores;

//PUNTOS DE PARTIDA - GUIA DE REMISION REMITENTE DE VENTAS
$array_return['setPlacesStart'] = $puntos_llegadas;

//VEHICULOS / UNIDADES DE TRANSPORTE
$array_return['setVehicles'] = $vehiculos;

//CONDUCTORES
$array_return['setDrivers'] = $conductores;

//ITEMS - CATEGORIAS
$array_return['setItemCategories'] = $item_categories;

//ITEMS - MARCAS
$array_return['setItemBrands'] = $item_brands;

//ASIGNACION DE PAGOS A BANCOS
$array_return['setPaymentMethodBanks'] = $apb;

//MONEDA DEFAULT
$array_return['setCurrencyDefault'] = $currency_default;

//SISTEMAS DE PUNTAJE
$array_return['setScoringSystem'] = $item_puntaje;

//RESPUESTA EXITOSA
echo lp_json_utf8(true, $array_return);
}

//GET ITEMS
if($apiPOS_getItems == 1){

//PARAMS
$limit  = isset($data['limit'])  ? (int)$data['limit']  : 1000;
$offset = isset($data['offset']) ? (int)$data['offset'] : 0;
$setItemIsEcommerce = (int)$data['setItemIsEcommerce'];
$setItemId = (int)$data['setItemId'];

//FILTRO ECOMMERCE
if($setItemIsEcommerce == 1){
$query_filter_setItemIsEcommerce = " AND p.venta = 1 AND p.slug != ''";
} else {
$query_filter_setItemIsEcommerce = '';
}

//FILTRO ITEM ID
if(!empty($setItemId)){
$query_filter_setItemId = " AND ue.id = '$setItemId'";
} else {
$query_filter_setItemId = '';
}

//GETPRODUCTOS
$sql_base = "
SELECT 
ue.id AS setId, 
p.id_producto AS setIdProducto, 
p.tipo_producto AS setItemType, 
p.codigo_producto AS setCodProducto, 
CASE WHEN ue.codigo_barras != '' THEN ue.codigo_barras ELSE p.codigo_barras END AS setItemBarCode, 
p.nombre_producto AS setDescripcion, 
p.descripcion AS setItemDescription, 
u.codigo AS setUnidadCodigo, 
u.abreviatura AS setUnidad, 
ue.nombre AS setItemUnitAlias, 
0 AS setMtoValorUnitario, 
ue.precio_venta AS setMtoPrecioUnitario, 
0 AS setMtoValorCostoUnitario, 
ue.precio_compra AS setMtoPrecioCostoUnitario, 
0 AS setMtoValorUnitario_us, 
ue.precio_venta_dolares AS setMtoPrecioUnitario_us, 
0 AS setMtoValorCostoUnitario_us, 
ue.precio_compra_dolares AS setMtoPrecioCostoUnitario_us, 
CAST(ue.un AS DECIMAL(10,6)) / CAST(ue.do AS DECIMAL(10,6)) AS setConversionFactor, 

CASE WHEN ? = 1 THEN 

CASE
WHEN p.igv = 1 THEN 1 
WHEN p.igv = 0 THEN 2 
WHEN p.igv = 2 THEN 3 
WHEN p.igv IN (3, 4, 5, 6, 7) THEN 4 
WHEN p.igv IN (9, 10, 11, 12, 13, 14) THEN 5 
WHEN p.igv = 17 THEN 7 
WHEN p.igv = 18 THEN 8 

ELSE 1
END

ELSE 

CASE
WHEN p.igv = 1 THEN 6 
WHEN p.igv = 0 THEN 2 
WHEN p.igv = 2 THEN 3 
WHEN p.igv IN (3, 4, 5, 6, 7) THEN 9 
WHEN p.igv IN (9, 10, 11, 12, 13, 14) THEN 5 
WHEN p.igv = 17 THEN 7 
WHEN p.igv = 18 THEN 8 

ELSE 6
END

END AS setTaxId, 

p.inventariable AS setInventariable, 
CASE WHEN p.foto = '' THEN '' ELSE CONCAT('https://neg-img-producto.s3.amazonaws.com/', p.foto) END AS setImg, 
p.compra AS setItemIsBought, 
p.venta AS setItemIsSold, 
c.nombre AS setCategoria, 
m.nombre AS setMarca,
CASE WHEN p.slug = '' THEN '' ELSE CONCAT (ue.id, '-', p.slug) END AS setItemSlug 

FROM products p FORCE INDEX (key_products_empresa_status_improvisado_codigo) 
INNER JOIN unidad_equivalencia ue FORCE INDEX (key_unidad_equivalencia_empresa_estado_producto) 
ON ue.empresa = p.empresa AND ue.estado = 1 AND ue.producto = p.id_producto
INNER JOIN unidadm u ON u.id = ue.unidad_equivalente

LEFT JOIN familia_productom c 
ON c.estado = 1 
AND c.empresa = ue.empresa 
AND c.id = p.familia_producto
LEFT JOIN marca m 
ON m.estado = 1 
AND m.empresa = ue.empresa 
AND m.id = p.marca

WHERE p.empresa = ? AND p.status_producto = 1 AND p.products_improvisado = 0 AND p.codigo_producto <> '' 

$query_filter_setItemIsEcommerce
$query_filter_setItemId

LIMIT ? OFFSET ?
";
$stmt_base = mysqli_prepare($con, $sql_base);
mysqli_stmt_bind_param($stmt_base, 'iiii', $setTaxIdDefault, $company_id, $limit, $offset);
mysqli_stmt_execute($stmt_base);
$res_base = mysqli_stmt_get_result($stmt_base);

//EMPAQUETAR RESULTADOS Y OBTENER ID
$productos       = [];
$ids_ue          = [];
$ids_prod        = [];
while($row = mysqli_fetch_assoc($res_base)){
$setId = (int)$row['setId'];
$productos[$setId] = array_merge($row, [
'currency_data'        => [
[
'currency'                  => 'PEN',
'setMtoValorUnitario'       => $row['setMtoValorUnitario'],
'setMtoPrecioUnitario'      => $row['setMtoPrecioUnitario'],
'setMtoValorCostoUnitario'  => $row['setMtoValorCostoUnitario'],
'setMtoPrecioCostoUnitario' => $row['setMtoPrecioCostoUnitario'],
],
[
'currency'                  => 'USD',
'setMtoValorUnitario'       => $row['setMtoValorUnitario_us'],
'setMtoPrecioUnitario'      => $row['setMtoPrecioUnitario_us'],
'setMtoValorCostoUnitario'  => $row['setMtoValorCostoUnitario_us'],
'setMtoPrecioCostoUnitario' => $row['setMtoPrecioCostoUnitario_us'],
],
],
'sucursales'           => [],
'setPrinters'          => [],
'setImages'            => [],
'setPriceLists'        => [],
'setItemVariantValues' => [],
]);
$ids_ue[]   = $setId;
$ids_prod[] = (int)$row['setIdProducto'];
}

//SANEAR UNA SOLA VEZ
$ids_prod     = array_map('intval', $ids_prod);
$in_prod      = implode(',', $ids_prod);
$ids_ue       = array_map('intval', $ids_ue);
$in_ue        = implode(',', $ids_ue);

//PRODUCTO ALMACEN
if(!empty($ids_prod)){
$sqlProdAlm  = "
SELECT id_producto, id_almacen AS setIdSucursal, stock_actual AS setSucursalStock, stock_minimo AS setWarehouseStockMin
FROM producto_almacen
WHERE empresa = {$company_id}
AND estado = 1
AND id_producto IN ({$in_prod})
";
$res = mysqli_query($con, $sqlProdAlm);
while($r = mysqli_fetch_assoc($res)){
foreach($productos as &$prod){
if($prod['setIdProducto'] == $r['id_producto']){
$prod['sucursales'][] = [
'setIdSucursal'        => $r['setIdSucursal'],
'setSucursalStock'     => $r['setSucursalStock'],
'setWarehouseStockMin' => $r['setWarehouseStockMin'],
];
}
}
unset($prod);
}
}

//PRODUCTO IMPRESION
if(!empty($ids_prod)){
$sqlProdImp  = "
SELECT pi.id_producto,
imp.id   AS setPrinterId,
imp.nombre_impresora AS setPrinterName,
imp.alias_impresora  AS setPrinterAlias
FROM products_impresion pi
JOIN impresion imp
ON imp.id = pi.id_impresion
AND imp.estado = 1
AND imp.empresa= pi.empresa
WHERE pi.estado = 1
AND pi.empresa = {$company_id}
AND pi.id_producto IN ({$in_prod})
";
$res = mysqli_query($con, $sqlProdImp);
while($r = mysqli_fetch_assoc($res)){
foreach($productos as &$prod){
if($prod['setIdProducto'] == $r['id_producto']){
$prod['setPrinters'][] = [
'setPrinterId'   => $r['setPrinterId'],
'setPrinterName' => $r['setPrinterName'],
'setPrinterAlias'=> $r['setPrinterAlias'],
];
}
}
unset($prod);
}
}

//PRODUCTO IMAGENES
if(!empty($ids_prod)){
$sqlProdImgs  = "
SELECT id_producto, imagenes
FROM ecommerce_detalle_img_productos
WHERE empresa = {$company_id}
AND id_producto IN ({$in_prod})
";
$res = mysqli_query($con, $sqlProdImgs);
while($r = mysqli_fetch_assoc($res)){
foreach($productos as &$prod){
if($prod['setIdProducto'] == $r['id_producto']){
$prod['setImages'][] = [
'setImageUrl' => 'https://neg-img-ec-detalle-producto.s3.amazonaws.com/'.$r['imagenes']
];
}
}
unset($prod);
}
}

//PRODUCTO LISTA DE PRECIOS
if(!empty($ids_ue)){
$sqlPriceList  = "
SELECT lpd.idequivalencia  AS ueId,
lp.id_categoria     AS setPriceListType,
lp.nombre           AS setPriceListName,
lp.fecha_desde      AS setPriceListDateStart,
lp.fecha_hasta      AS setPriceListDateEnd,
lpd.id_elemento     AS setPriceListProductId,
lpd.idequivalencia  AS setPriceListItemId,
lpd.rango_desde     AS setPriceListRangeStart,
lpd.rango_hasta     AS setPriceListRangeEnd,
lpd.tipo_ajuste     AS setPriceListAdjustmentType,
lpd.monto           AS setPriceListAdjustmentAmount
FROM lista_precio_detalle lpd
JOIN lista_precio lp
ON lp.id = lpd.id_lista_precio
AND lp.estado = 1
AND lp.id_categoria = 2
AND lp.empresa = lpd.empresa
WHERE lpd.empresa = {$company_id}
AND lpd.idequivalencia IN ({$in_ue})
";
$res = mysqli_query($con, $sqlPriceList);
while($r = mysqli_fetch_assoc($res)){
$ueId = $r['ueId'];
foreach($productos[$ueId]['currency_data'] as &$cd){
if($cd['currency'] === 'PEN'){
$cd['setPriceLists'][] = [
'setPriceListType'             => $r['setPriceListType'],
'setPriceListName'             => $r['setPriceListName'],
'setPriceListDateStart'        => $r['setPriceListDateStart'],
'setPriceListDateEnd'          => $r['setPriceListDateEnd'],
'setPriceListProductId'        => $r['setPriceListProductId'],
'setPriceListItemId'           => $r['setPriceListItemId'],
'setPriceListRangeStart'       => $r['setPriceListRangeStart'],
'setPriceListRangeEnd'         => $r['setPriceListRangeEnd'],
'setPriceListAdjustmentType'   => $r['setPriceListAdjustmentType'],
'setPriceListAdjustmentAmount' => $r['setPriceListAdjustmentAmount'],
];
}
}
unset($cd);
}
}

//PRODUCTO VARIANTES
if(!empty($ids_prod)){
$sqlVariants  = "
SELECT mpcv.id_producto,
mc.nombre   AS variantKey,
mcv.nombre  AS variantValue
FROM matriz_producto_caracteristica_valor mpcv
JOIN matriz_caracteristica_valor mcv
ON mcv.id = mpcv.id_caracteristica_valor
AND mcv.estado = 1  AND mcv.empresa = mpcv.empresa
JOIN matriz_caracteristica mc
ON mc.id = mcv.id_caracteristica
AND mc.estado = 1  AND mc.empresa = mcv.empresa
WHERE mpcv.estado = 1
AND mpcv.empresa = {$company_id}
AND mpcv.id_producto IN ({$in_prod})
";
$res = mysqli_query($con, $sqlVariants);
while($r = mysqli_fetch_assoc($res)){
foreach($productos as &$prod){
if($prod['setIdProducto'] == $r['id_producto']){
$prod['setItemVariantValues'][$r['variantKey']] = $r['variantValue'];
}
}
unset($prod);
}
}

//PRODUCTO LOTES
if(!empty($ids_ue)){
$sqlBatches  = "
SELECT id_producto,
MAX(nombre_caracteristica)    AS setBatchName,
MAX(valor_caracteristica)     AS setBatchExpirationDate
FROM caracteristica_producto
WHERE empresa = {$company_id}
AND id_producto IN ({$in_ue})
GROUP BY id_producto
";
$res = mysqli_query($con, $sqlBatches);
while($r = mysqli_fetch_assoc($res)){
foreach($productos as &$prod){
if($prod['setId'] == $r['id_producto']){
$prod['setBatchName']           = $r['setBatchName'];
$prod['setBatchExpirationDate'] = $r['setBatchExpirationDate'];
}
}
unset($prod);
}
}

//ARRAY RETURN
$array_return = [
'setProductos'   => array_values($productos),
'receivedCount'  => count($productos),
'requestedLimit' => $limit,
];

//RESPUESTA EXITOSA
echo lp_json_utf8(true, $array_return);
}

//ENVIAR DOCUMENTO PARA ANEXAR
if($apiPOS_getAttach == 1){

//PARAMETROS RECIBIDOS
$setAttachType = $data['setAttachType'];
$setAttachSeries = $data['setAttachSeries'];
$setAttachNumber = (int)$data['setAttachNumber'];

//VALIDAR TIPO DE COMPROBANTE
if($setAttachType == 1){
$setAttachType_syseb = 22;
} elseif($setAttachType == 2){
$setAttachType_syseb = 5;
} elseif($setAttachType == 41){
$setAttachType_syseb = 21;
} else {
$setAttachType_syseb = $setAttachType;
}

//OBTENER ID SERIE
$query_serie = mysqli_prepare($con, "
SELECT ls.id 
FROM numero_serie ls
INNER JOIN tipo_numero_serie lst ON lst.id = ls.tipo
WHERE ls.tipo = ? AND ls.numero = ? AND ls.empresa = ? 
LIMIT 1 
");
mysqli_stmt_bind_param($query_serie, 'isi', $setAttachType_syseb, $setAttachSeries, $company_id);
mysqli_stmt_execute($query_serie);
$result_serie = mysqli_stmt_get_result($query_serie);
if(mysqli_num_rows($result_serie) > 0){
$row_serie = mysqli_fetch_array($result_serie);
$id_serie = $row_serie['id'];
} else {
echo lp_json_utf8(false, "Serie Incorrecta.");
exit;
}

//OBTENER ID COMPROBANTE
if($setAttachType == 1){
$query_operation = "
SELECT 
lv.id_factura AS setReceiptId 
FROM cotizaciones lv 
WHERE lv.id_serie = '$id_serie' AND lv.numero_factura = '$setAttachNumber' AND lv.empresa = ? AND tipo_operacion = '2' 
LIMIT 1
";
} elseif($setAttachType == 41){
$query_operation = "
SELECT 
lv.id_factura AS setReceiptId 
FROM cotizaciones lv 
WHERE lv.id_serie = '$id_serie' AND lv.numero_factura = '$setAttachNumber' AND lv.empresa = ? AND tipo_operacion = '1' 
LIMIT 1
";
} else {
$query_operation = "
SELECT 
lv.id_factura AS setReceiptId 
FROM facturas lv 
WHERE lv.tipodoc = '$setAttachType_syseb' AND lv.serie = '$id_serie' AND lv.numero_factura = '$setAttachNumber' AND lv.tipo_resumen != 9999 AND lv.estado_sunat != 9999 AND lv.empresa = ? 
LIMIT 1
";
}
$query_receiptId = mysqli_prepare($con, $query_operation);
mysqli_stmt_bind_param($query_receiptId, 'i', $company_id);
mysqli_stmt_execute($query_receiptId);
$result_receiptId = mysqli_stmt_get_result($query_receiptId);
if(mysqli_num_rows($result_receiptId) > 0){
$row_receiptId = mysqli_fetch_array($result_receiptId);
$setReceiptId = $row_receiptId['setReceiptId'];
} else {
echo lp_json_utf8(false, "No se encontró el comprobante.");
exit;
}

//CONSULTA
if($setAttachType == 1 || $setAttachType == 41){

//SI ES PEDIDO
if($setAttachType == 1){
$sql_tipo_operacion = 2;
} else {
$sql_tipo_operacion = 1;
}

//SQL
$sqlAttach = " 
SELECT 
lv.id_factura AS setReceiptId, 
'$setAttachType' AS setReceiptType, 
'$setAttachSeries' AS setReceiptSeries, 
'$setAttachNumber' AS setReceiptNumber, 
lm.codigo AS setReceiptCurrency, 
CASE WHEN ltd.nombre = 'DNI' OR ltd.nombre = 'RUC' THEN ltd.nombre ELSE 'DNI' END AS setReceiptClientDocType, 
lc.dni AS setReceiptClientDocNumber, 
lc.nombre_cliente AS setReceiptClientName, 
lc.direccion_cliente AS setReceiptClientAddress, 
lv.descuento_global AS setReceiptDescGlobal, 
lv.notas AS setNotes, 
1 AS setDispatchType, 
lv.id_almacen AS setWarehouseId, 

lvd.idequivalencia AS setIdProducto, 
lvd.cantidad AS setValorCantidad, 

CASE WHEN lvd.tipo_igv = 1 OR lvd.tipo_igv = 8 OR lvd.tipo_igv = 15 OR lvd.tipo_igv = 18 
THEN 
((lvd.valor_compra + lvd.igv_compra + lvd.isc_compra) / lvd.cantidad) + (lvd.total_desc / lvd.cantidad)

WHEN lvd.tipo_igv = 2 OR lvd.tipo_igv = 3 OR lvd.tipo_igv = 4 OR lvd.tipo_igv = 5 OR lvd.tipo_igv = 6 OR lvd.tipo_igv = 7 
THEN 
CASE WHEN lv.id_impuesto = 1 THEN (lvd.precio_venta / 1.18) ELSE (lvd.precio_venta / 1.10) END 

ELSE 
lvd.precio_venta 
END 

AS setPrecioUnit, 

CASE WHEN lv.id_impuesto = 1 THEN 

CASE
WHEN lvd.tipo_igv = 1 THEN 1 
WHEN lvd.tipo_igv = 2 OR lvd.tipo_igv = 3 OR lvd.tipo_igv = 4 OR lvd.tipo_igv = 5 OR lvd.tipo_igv = 6 OR lvd.tipo_igv = 7 THEN 4 
WHEN lvd.tipo_igv = 8 THEN 3 
WHEN lvd.tipo_igv = 9 OR lvd.tipo_igv = 10 OR lvd.tipo_igv = 11 OR lvd.tipo_igv = 12 OR lvd.tipo_igv = 13 OR lvd.tipo_igv = 14 THEN 5 
WHEN lvd.tipo_igv = 15 THEN 2 
WHEN lvd.tipo_igv = 17 THEN 7 
WHEN lvd.tipo_igv = 18 THEN 8 
ELSE 1
END

ELSE 

CASE
WHEN lvd.tipo_igv = 1 THEN 6 
WHEN lvd.tipo_igv = 2 OR lvd.tipo_igv = 3 OR lvd.tipo_igv = 4 OR lvd.tipo_igv = 5 OR lvd.tipo_igv = 6 OR lvd.tipo_igv = 7 THEN 9 
WHEN lvd.tipo_igv = 8 THEN 3 
WHEN lvd.tipo_igv = 9 OR lvd.tipo_igv = 10 OR lvd.tipo_igv = 11 OR lvd.tipo_igv = 12 OR lvd.tipo_igv = 13 OR lvd.tipo_igv = 14 THEN 5 
WHEN lvd.tipo_igv = 15 THEN 2 
WHEN lvd.tipo_igv = 17 THEN 7 
WHEN lvd.tipo_igv = 18 THEN 8 
ELSE 6
END

END AS setTaxId, 
li.codigo AS setTipAfeIgv, 
lvd.descuento AS setPorcentajeDesc, 
0 AS setMtoPrecioCostoUnitario, 
lvd.observacion AS setNotas 

FROM cotizaciones lv 
INNER JOIN detalle_cotizacion lvd ON lvd.id_compra = lv.id_factura AND lvd.empresa = lv.empresa 
INNER JOIN igv li ON li.id = lvd.tipo_igv 
INNER JOIN numero_serie ls ON ls.id = '$id_serie' AND ls.id = lv.id_serie AND ls.empresa = lv.empresa 
INNER JOIN moneda lm ON lm.id = lv.moneda 
LEFT JOIN clientes lc ON lc.id_cliente = lv.id_cliente AND lc.empresa = lv.empresa 
LEFT JOIN tipo_documento ltd ON ltd.id = lc.tipo_documento 
WHERE lv.tipo_operacion = '$sql_tipo_operacion' AND lv.id_factura = ? AND lv.empresa = ? 
";

} else {
$sqlAttach = " 
SELECT 
lv.id_factura AS setReceiptId, 
'$setAttachType' AS setReceiptType, 
'$setAttachSeries' AS setReceiptSeries, 
'$setAttachNumber' AS setReceiptNumber, 
lm.codigo AS setReceiptCurrency, 
CASE WHEN ltd.nombre = 'DNI' OR ltd.nombre = 'RUC' THEN ltd.nombre ELSE 'DNI' END AS setReceiptClientDocType, 
lc.dni AS setReceiptClientDocNumber, 
lc.nombre_cliente AS setReceiptClientName, 
lc.direccion_cliente AS setReceiptClientAddress, 
lv.descuento_global AS setReceiptDescGlobal, 
lv.notas AS setNotes, 
lv.id_despacho AS setDispatchType, 
lv.almacen_id AS setWarehouseId, 

lvd.idequivalencia AS setIdProducto, 
lvd.cantidad AS setValorCantidad, 

CASE WHEN lv.tipodoc = 5 THEN

CASE WHEN lvd.tipo_igv = 1 OR lvd.tipo_igv = 8 OR lvd.tipo_igv = 15 OR lvd.tipo_igv = 18 
THEN 
((lvd.valor_venta + lvd.igv_venta + lvd.isc_compra) / lvd.cantidad) + (lvd.total_desc / lvd.cantidad)

ELSE 
lvd.precio_venta 
END 

ELSE

CASE WHEN lvd.tipo_igv = 1 OR lvd.tipo_igv = 8 OR lvd.tipo_igv = 15 OR lvd.tipo_igv = 18
THEN 
((lvd.valor_venta + lvd.igv_venta + lvd.isc_compra) / lvd.cantidad) + (lvd.total_desc / lvd.cantidad)

WHEN lvd.tipo_igv = 2 OR lvd.tipo_igv = 3 OR lvd.tipo_igv = 4 OR lvd.tipo_igv = 5 OR lvd.tipo_igv = 6 OR lvd.tipo_igv = 7 
THEN 
CASE WHEN lv.id_impuesto = 1 THEN (lvd.precio_venta / 1.18) ELSE (lvd.precio_venta / 1.10) END 

ELSE 
lvd.precio_venta 
END 


END AS setPrecioUnit, 

CASE WHEN lv.id_impuesto = 1 THEN 

CASE
WHEN lvd.tipo_igv = 1 THEN 1 
WHEN lvd.tipo_igv = 2 OR lvd.tipo_igv = 3 OR lvd.tipo_igv = 4 OR lvd.tipo_igv = 5 OR lvd.tipo_igv = 6 OR lvd.tipo_igv = 7 THEN 4 
WHEN lvd.tipo_igv = 8 THEN 3 
WHEN lvd.tipo_igv = 9 OR lvd.tipo_igv = 10 OR lvd.tipo_igv = 11 OR lvd.tipo_igv = 12 OR lvd.tipo_igv = 13 OR lvd.tipo_igv = 14 THEN 5 
WHEN lvd.tipo_igv = 15 THEN 2 
WHEN lvd.tipo_igv = 17 THEN 7 
WHEN lvd.tipo_igv = 18 THEN 8 
ELSE 1
END

ELSE 

CASE
WHEN lvd.tipo_igv = 1 THEN 6 
WHEN lvd.tipo_igv = 2 OR lvd.tipo_igv = 3 OR lvd.tipo_igv = 4 OR lvd.tipo_igv = 5 OR lvd.tipo_igv = 6 OR lvd.tipo_igv = 7 THEN 9 
WHEN lvd.tipo_igv = 8 THEN 3 
WHEN lvd.tipo_igv = 9 OR lvd.tipo_igv = 10 OR lvd.tipo_igv = 11 OR lvd.tipo_igv = 12 OR lvd.tipo_igv = 13 OR lvd.tipo_igv = 14 THEN 5 
WHEN lvd.tipo_igv = 15 THEN 2 
WHEN lvd.tipo_igv = 17 THEN 7 
WHEN lvd.tipo_igv = 18 THEN 8 
ELSE 6
END

END AS setTaxId, 
li.codigo AS setTipAfeIgv, 
lvd.descuento AS setPorcentajeDesc, 
lvd.costo AS setMtoPrecioCostoUnitario, 
lvd.observacion AS setNotas 

FROM facturas lv 
INNER JOIN detalle_factura lvd ON lvd.id_factura = lv.id_factura AND lvd.empresa = lv.empresa 
INNER JOIN igv li ON li.id = lvd.tipo_igv 
INNER JOIN numero_serie ls ON ls.id = '$id_serie' AND ls.id = lv.serie AND ls.empresa = lv.empresa 
INNER JOIN moneda lm ON lm.id = lv.moneda 
LEFT JOIN clientes lc ON lc.id_cliente = lv.id_cliente AND lc.empresa = lv.empresa 
LEFT JOIN tipo_documento ltd ON ltd.id = lc.tipo_documento 
WHERE lv.id_factura = ? AND lv.empresa = ? 
";
}

//EJECUTAR CONSULTA
$queryAttach = mysqli_prepare($con, $sqlAttach);
mysqli_stmt_bind_param($queryAttach, 'ii', $setReceiptId, $company_id);
mysqli_stmt_execute($queryAttach);
$resultAttach = mysqli_stmt_get_result($queryAttach);

//VARIABLES
$setReceipt = null;
$setReceiptProducts = [];

//AGREGAR DATOS AL ARRAY FINAL
while($row = mysqli_fetch_assoc($resultAttach)){
if(!$setReceipt){
$setReceipt = [
'setReceiptId'         => $row['setReceiptId'],
'setReceiptType'         => $row['setReceiptType'],
'setReceiptSeries'       => $row['setReceiptSeries'],
'setReceiptNumber'       => $row['setReceiptNumber'],
'setReceiptCurrency'     => $row['setReceiptCurrency'],
'setReceiptClientDocType'=> $row['setReceiptClientDocType'],
'setReceiptClientDocNumber'=> $row['setReceiptClientDocNumber'],
'setReceiptClientName'   => $row['setReceiptClientName'],
'setReceiptClientAddress'=> $row['setReceiptClientAddress'],
'setReceiptDescGlobal'=>    $row['setReceiptDescGlobal'],
'setNotes'=>                $row['setNotes'],
'setDispatchType'=>         $row['setDispatchType'],
'setWarehouseId'=>          $row['setWarehouseId'],
];
}
// Guardamos los datos de producto
$setReceiptProducts[] = [
'setIdProducto' => $row['setIdProducto'],
'setValorCantidad' => $row['setValorCantidad'],
'setPrecioUnit' => $row['setPrecioUnit'],
'setTaxId' => $row['setTaxId'],
'setTipAfeIgv' => $row['setTipAfeIgv'],
'setPorcentajeDesc' => $row['setPorcentajeDesc'],
'setMtoPrecioCostoUnitario' => $row['setMtoPrecioCostoUnitario'],
'setNotas' => $row['setNotas'],
];
}

//RETORNAR ATTACH
$arrayAttach = [
'setReceipt' => $setReceipt,
'setReceiptProducts' => $setReceiptProducts
];
echo lp_json_utf8(true, $arrayAttach);

//FINALIZAR PROCESO
exit;
}

//ENVIAR ULTIMO CORRELATIVO
if($apiPOS_getLastCorrelative == 1){

//PARAMETROS RECIBIDOS
$setReceiptType = $data['setReceiptType'];
$setReceiptSeries = $data['setReceiptSeries'];

//VALIDAR TIPO DE COMPROBANTE
if($setReceiptType == 1){
$setReceiptTypeSyseb = 22;
} elseif($setReceiptType == 2){
$setReceiptTypeSyseb = 5;
} elseif($setReceiptType == 41){
$setReceiptTypeSyseb = 21;
} elseif($setReceiptType == 48){
$setReceiptTypeSyseb = 34;
} elseif($setReceiptType == 55){
$setReceiptTypeSyseb = 13;
} else {
$setReceiptTypeSyseb = $setReceiptType;
}

//VALIDAR SERIE
$query_serie = mysqli_prepare($con, "
SELECT ls.id 
FROM numero_serie ls
INNER JOIN tipo_numero_serie lst ON lst.id = ls.tipo
WHERE ls.tipo = ? AND ls.numero = ? AND ls.empresa = ? 
LIMIT 1 
");
mysqli_stmt_bind_param($query_serie, 'isi', $setReceiptTypeSyseb, $setReceiptSeries, $company_id);
mysqli_stmt_execute($query_serie);
$result_serie = mysqli_stmt_get_result($query_serie);
if(mysqli_num_rows($result_serie) > 0){
$row_serie = mysqli_fetch_array($result_serie);
$id_serie = $row_serie['id'];
} else {
echo lp_json_utf8(false, "Serie Incorrecta.");
exit;
}

//CONSULTA SI ES PEDIDO
if($setReceiptType == 1){
$query_correlativo = mysqli_prepare($con, query: "
SELECT
COALESCE(
(
SELECT c.numero_factura + 1
FROM cotizaciones AS c
FORCE INDEX (key_empresa_serie_estado_tipo_numero)
WHERE c.empresa       = ?
AND c.id_serie        = ?
AND c.estado         = 1
AND c.tipo_operacion = 2
ORDER BY c.numero_factura DESC
LIMIT 1
),
1
) AS lastCorrelative
");
}

//CONSULTA SI ES COTIZACION
if($setReceiptType == 41){
$query_correlativo = mysqli_prepare($con, query: "
SELECT
COALESCE(
(
SELECT c.numero_factura + 1
FROM cotizaciones AS c
FORCE INDEX (key_empresa_serie_estado_tipo_numero)
WHERE c.empresa       = ?
AND c.id_serie        = ?
AND c.estado         = 1
AND c.tipo_operacion = 1
ORDER BY c.numero_factura DESC
LIMIT 1
),
1
) AS lastCorrelative
");
}

//CONSULTA SI ES VENTA
if($setReceiptType == 2 || $setReceiptType == 3 || $setReceiptType == 4){
$query_correlativo = mysqli_prepare($con, query: "
SELECT
COALESCE(
(
SELECT f.numero_factura + 1
FROM facturas AS f
FORCE INDEX (key_empresa_serie_resumen_sunat_numero)
WHERE f.empresa = ?
AND f.serie = ?
AND f.tipo_resumen <> 9999
AND f.estado_sunat  <> 9999
ORDER BY f.numero_factura DESC
LIMIT 1
),
1
) AS lastCorrelative
");
}

//CONSULTA SI ES ORDEN DE COMPRA
if($setReceiptType == 48){
$query_correlativo = mysqli_prepare($con, query: "
SELECT
COALESCE(
(
SELECT c.numero_factura + 1
FROM orden_compras AS c
FORCE INDEX (key_empresa_serie_estado_numero)
WHERE c.empresa       = ?
AND c.id_serie        = ?
AND c.estado         = 1
ORDER BY c.numero_factura DESC
LIMIT 1
),
1
) AS lastCorrelative
");
}

//CONSULTA SI ES GUIA DE REMISION REMITENTE DE VENTA
if($setReceiptType == 55){
$query_correlativo = mysqli_prepare($con, query: "
SELECT
COALESCE(
(
SELECT c.correlativo + 1
FROM guia_remision AS c
FORCE INDEX (key_empresa_serie_estado_numero)
WHERE c.empresa = ?
AND c.serie = ?
AND c.estado = 1
ORDER BY c.correlativo DESC
LIMIT 1
),
1
) AS lastCorrelative
");
}

mysqli_stmt_bind_param($query_correlativo, 'ii', $company_id, $id_serie);
mysqli_stmt_execute($query_correlativo);
$result_correlativo = mysqli_stmt_get_result($query_correlativo);
$row_correlativo = mysqli_fetch_array($result_correlativo);
$setLastCorrelative = (int)$row_correlativo['lastCorrelative'];

//ARRAY RETURN
$arrayAttach = [
'setLastCorrelative' => $setLastCorrelative
];

//RETORNAR
echo lp_json_utf8(true, $arrayAttach);

//FINALIZAR
exit;
}

//APERTURAR TURNO
if($apiPOS_shift == 1){

//DATA APERTURA
$setBranchId = (int)$data['setBranchId'];
$setCashierId = (int)$data['setCashierId'];
$setCashInitial = (float)$data['setCashInitial'];
$setShiftDateStart = date('Y-m-d H:i:s', strtotime($data['setShiftDateStart']));

//DATA CIERRE
$setShiftId = (int)$data['setShiftId'];
$setShiftDateEnd = date('Y-m-d H:i:s', strtotime($data['setShiftDateEnd']));
$setShiftCount = (float)$data['setShiftCount'];
$setShiftCashAmount = (float)$data['setShiftCashAmount'];
$setShiftNotes = $data['setShiftNotes'];

//REGISTRAR TURNO
if(empty($setShiftId)){

//VALIDAR SI NO HAY UN TURNO ABIERTO
$query_setShift = mysqli_prepare($con, "
SELECT ac.id 
FROM apertura_caja ac 
WHERE ac.id_usuario = ? AND ac.id_sucursal = ? AND ac.fecha_fin = '' AND ac.empresa = ? 
LIMIT 1 
");
mysqli_stmt_bind_param($query_setShift, 'iii', $setCashierId, $setBranchId, $company_id);
mysqli_stmt_execute($query_setShift);
$result_setShift = mysqli_stmt_get_result($query_setShift);
if(mysqli_num_rows($result_setShift) > 0){
echo lp_json_utf8(false, "YA EXISTE UN TURNO ABIERTO, VUELVE A INICIAR SESIÓN.");
exit;
}

//APERTURAR TURNO
$stmt_shift = "INSERT INTO apertura_caja (id_usuario, id_sucursal, fecha_inicio, fecha_fin, monto_soles, monto_dolares, empresa, conteo, 
estado_reponer, id_banco, total_caja_pos, estado) VALUES ('$setCashierId', '$setBranchId', '$setShiftDateStart', '', '$setCashInitial', '0', '$company_id', '0', 
'0', '0', '0', '1')";
if(!mysqli_query($con, $stmt_shift)){
echo lp_json_utf8(false, "Error al Aperturar Turno.");
exit();
}
// OBTENER ID DEL REGISTRO INSERTADO
$setShiftId = mysqli_insert_id($con);

//ARRAY RETURN
$arrayReturn = [
'setShiftId' => $setShiftId
];

//RETORNAR
echo lp_json_utf8(true, $arrayReturn);
} else {

//VALIDAR SI HAY DIFERENCIAL
$estado_reponer = 0;
if($setShiftCount < $setShiftCashAmount){
$estado_reponer = 2;
}

//CERRAR TURNO
$stmt_shift = "UPDATE apertura_caja SET fecha_fin = '$setShiftDateEnd', estado = '1', conteo = '$setShiftCount', total_caja_pos = '$setShiftCashAmount', estado_reponer = '$estado_reponer' WHERE id = '$setShiftId' AND empresa = '$company_id'";
if(!mysqli_query($con, $stmt_shift)){
echo lp_json_utf8(false, "Error al Cerrar Turno.");
exit();
}

//ARRAY RETURN
$arrayReturn = [
'setShiftId' => $setShiftId
];

//RETORNAR
echo lp_json_utf8(true, $arrayReturn);
}

//FINALIZAR
exit;
}

//DETALLE DE TURNO
if($apiPOS_getShiftDetails == 1){

//PARAMS
$setShiftId = (int)$data['setShiftId'];

//DETALLE DEL TURNO
$query_shiftDetails = mysqli_prepare($con, "
SELECT 
'1' AS setPaymentType, 
lmp.codigo AS setPaymentCode, 
lc.monto AS setPaymentAmount
FROM cobros lc
INNER JOIN forma_pago lmp ON lmp.id = lc.forma_pago
WHERE lc.id IN (SELECT id_cobro_pago FROM apertura_cuota_operacion WHERE id_apertura = ? AND empresa = ? AND tipo = 1)

UNION ALL

SELECT 
'2' AS setPaymentType, 
lmp.codigo AS setPaymentCode, 
lp.monto AS setPaymentAmount
FROM pagos lp
INNER JOIN forma_pago lmp ON lmp.id = lp.forma_pago
WHERE lp.id IN (SELECT id_cobro_pago FROM apertura_cuota_operacion WHERE id_apertura = ? AND empresa = ? AND tipo = 2)
");
mysqli_stmt_bind_param($query_shiftDetails, 'iiii', $setShiftId, $company_id, $setShiftId, $company_id);
mysqli_stmt_execute($query_shiftDetails);
$result_shiftDetails = mysqli_stmt_get_result($query_shiftDetails);
$array_return = [];
$shiftDetails = [];
while($row_shiftDetails = mysqli_fetch_assoc($result_shiftDetails)){
$shiftDetails[] = $row_shiftDetails;
}
$array_return['shiftDetails'] = $shiftDetails;

//RESPUESTA EXITOSA
echo lp_json_utf8(true, $array_return);

//FINALIZAR
exit;
}

//INGRESOS
if($apiPOS_getIncome == 1){

//PARAMETROS
$fil_date_start     = $data['fil_date_start'];
$fil_date_end       = $data['fil_date_end'];
$fil_CustomersId    = $data['fil_CustomersId'];
$fil_OperationsType = $data['fil_OperationsType'];
$fil_BranchesId     = $data['fil_BranchesId'];
$fil_OperationsId   = $data['fil_OperationsId'];

//FORMATO: 0000-00-00 00:00:00
$pattern = '/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}$/';

//VALIDAR FORMATO DE FECHAS
if(!preg_match($pattern, $fil_date_start) || !preg_match($pattern, $fil_date_end)){
echo lp_json_utf8(false, "Formato de fecha y hora inválido.");
exit;
}

//VALIDAR TIPO DE OPERACION SYSEB
$mapOp     = [2 => 5, 3 => 3, 4 => 4];
$filtersOp = array_values(array_filter(
array_map(fn($v) => $mapOp[$v] ?? null, $fil_OperationsType),
fn($v) => $v !== null
));

//WHERE DINAMICO
$conditions = [];
$params     = [];
$types      = '';

//FILTRO OBLIGATORIO - ID EMPRESA
$conditions[] = 'lv.empresa = ? AND lv.tipo_resumen != 9999 AND lv.estado_sunat != 9999';
$types       .= 'i';
$params[]     = $company_id;

//FILTRO OBLIGATORIO - FECHAS
$conditions[] = 'DATE(lv.fecha_factura) BETWEEN ? AND ?';
$types       .= 'ss';
$params[]     = $fil_date_start;
$params[]     = $fil_date_end;

//FILTRO OPCIONAL - ID CLIENTE
if(!empty($fil_CustomersId)){
$placeholdersClients = implode(',', array_fill(0, count($fil_CustomersId), '?'));
$conditions[]        = "lv.id_cliente IN ($placeholdersClients)";
$types              .= str_repeat('i', count($fil_CustomersId));
foreach($fil_CustomersId as $id){
$params[] = $id;
}
}

//FILTRO OPCIONAL - TIPO OPERACIONES
if(!empty($filtersOp)){
$placeholdersOp = implode(',', array_fill(0, count($filtersOp), '?'));
$conditions[]   = "lv.tipodoc IN ($placeholdersOp)";
$types         .= str_repeat('i', count($filtersOp));
foreach($filtersOp as $op){
$params[] = $op;
}
}

//FILTRO OPCIONAL - ID SUCURSAL
if(!empty($fil_BranchesId)){
$placeholdersBranches = implode(',', array_fill(0, count($fil_BranchesId), '?'));
$conditions[]        = "lv.id_almacen IN ($placeholdersBranches)";
$types              .= str_repeat('i', count($fil_BranchesId));
foreach($fil_BranchesId as $branchid){
$params[] = $branchid;
}
}

//FILTRO OPCIONAL - ID OPERACION
if(!empty($fil_OperationsId)){
$placeholdersOperationsId = implode(',', array_fill(0, count($fil_OperationsId), '?'));
$conditions[]        = "lv.id_factura IN ($placeholdersOperationsId)";
$types              .= str_repeat('i', count($fil_OperationsId));
foreach($fil_OperationsId as $operationId){
$params[] = $operationId;
}
}

//CONSULTA PRINCIPAL - OPERACIONES
$sql = "
SELECT
lv.id_factura             AS setOperationId,
lv.tipodoc                AS setOperationType,
lv.serie                  AS setOperationSeries,
lv.numero_factura         AS setOperationNumber,
lv.fecha_factura          AS setOperationDateTime,
lm.codigo                 AS setOperationCurrency,
lv.id_cliente             AS setOperationCustomerId,
lv.descuento_global       AS setOperationDiscountGlobal,
lv.notas                  AS setOperationNotes,
lv.id_despacho            AS setOperationDispatchType,
lv.id_almacen             AS setOperationBranchId,
lv.almacen_id             AS setOperationWarehouseId, 
lv.estado_factura         AS setOperationPaymentStatus 
FROM facturas lv
INNER JOIN moneda lm ON lm.id = lv.moneda
WHERE
" . implode("\n    AND ", $conditions) . "
ORDER BY lv.fecha_factura DESC
";

//PREPARAR
$stmt = mysqli_prepare($con, $sql);
if($stmt === false){
echo lp_json_utf8(false, "Error al preparar la consulta: " . mysqli_error($con));
exit;
}

//BIND Y EJECUTAR
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$incomes = [];
while($row = mysqli_fetch_assoc($res)){
$reverseMap = array_flip($mapOp);
$docReturn  = $reverseMap[$row['setOperationType']] ?? $row['setOperationType'];
$incomes[$row['setOperationId']] = [
'setOperationId'                 => $row['setOperationId'],
'setOperationType'               => $docReturn,
'setOperationSeries'             => $row['setOperationSeries'],
'setOperationNumber'             => $row['setOperationNumber'],
'setOperationDateTime'           => $row['setOperationDateTime'],
'setOperationCurrency'           => $row['setOperationCurrency'],
'setOperationCustomerId'         => $row['setOperationCustomerId'],
'setOperationDiscountGlobal'     => $row['setOperationDiscountGlobal'],
'setOperationNotes'              => $row['setOperationNotes'],
'setOperationDispatchType'       => $row['setOperationDispatchType'],
'setOperationBranchId'           => $row['setOperationBranchId'],
'setOperationWarehouseId'        => $row['setOperationWarehouseId'],
'setOperationPaymentStatus'      => $row['setOperationPaymentStatus'],
'setOperationItems'              => []
];
}
mysqli_free_result($res);
mysqli_stmt_close($stmt);

//CONSULTA PRINCIPAL - DETALLE DE OPERACIONES
if(!empty($incomes)){
$ids   = array_keys($incomes);
$phIds = implode(',', array_fill(0, count($ids), '?'));
$sqlDet = "
SELECT
lvd.id_factura                   AS setOperationId, 
lvd.idequivalencia               AS setOperationDetailUnitPresentationId, 
lvd.cantidad                     AS setOperationDetailQuantity, 
CASE WHEN lv.tipodoc = 5 THEN 

CASE WHEN lvd.tipo_igv IN (1, 8, 15, 18) 
THEN 
((lvd.valor_venta + lvd.igv_venta + lvd.isc_compra) / lvd.cantidad) + (lvd.total_desc / lvd.cantidad) 
ELSE 
lvd.precio_venta 
END 

ELSE

CASE WHEN lvd.tipo_igv IN (1, 8, 15, 18) 
THEN 
((lvd.valor_venta + lvd.igv_venta + lvd.isc_compra) / lvd.cantidad) + (lvd.total_desc / lvd.cantidad) 
WHEN lvd.tipo_igv IN (2, 3, 4, 5, 6, 7) 
THEN 
CASE WHEN lv.id_impuesto = 1 THEN (lvd.precio_venta / 1.18) ELSE (lvd.precio_venta / 1.10) END 
ELSE 
lvd.precio_venta 
END 

END                                                             AS setOperationDetailUnitPrice, 
CASE WHEN lv.id_impuesto = 1 THEN 

CASE 
WHEN lvd.tipo_igv = 1 THEN 1 
WHEN lvd.tipo_igv IN (2, 3, 4, 5, 6, 7) THEN 4 
WHEN lvd.tipo_igv = 8 THEN 3 
WHEN lvd.tipo_igv IN (9, 10, 11, 12, 13, 14) THEN 5 
WHEN lvd.tipo_igv = 15 THEN 2 
WHEN lvd.tipo_igv = 17 THEN 7 
WHEN lvd.tipo_igv = 18 THEN 8 
ELSE 1 
END 

ELSE 

CASE
WHEN lvd.tipo_igv = 1 THEN 6 
WHEN lvd.tipo_igv IN (2, 3, 4, 5, 6, 7) THEN 9 
WHEN lvd.tipo_igv = 8 THEN 3 
WHEN lvd.tipo_igv IN (9, 10, 11, 12, 13, 14) THEN 5 
WHEN lvd.tipo_igv = 15 THEN 2 
WHEN lvd.tipo_igv = 17 THEN 7 
WHEN lvd.tipo_igv = 18 THEN 8 
ELSE 6 
END 

END                                                             AS setOperationDetailTaxId, 
lvd.descuento                                                   AS setOperationDetailDiscountPercentage, 
lvd.costo                                                       AS setOperationDetailUnitCost, 
lvd.observacion                                                 AS setOperationDetailNotes, 
lvd.valor_venta                                                 AS setOperationDetailSubTotal, 
(lvd.igv_venta + isc_compra)                                    AS setOperationDetailTotalTax, 
(lvd.valor_venta + lvd.igv_venta + isc_compra)                  AS setOperationDetailTotal 
FROM detalle_factura lvd 
INNER JOIN facturas lv ON lv.id_factura = lvd.id_factura AND lv.empresa = lvd.empresa 
WHERE lvd.empresa = lv.empresa 
AND lvd.id_factura IN ($phIds) 
";
$stmt2 = mysqli_prepare($con, $sqlDet);
mysqli_stmt_bind_param($stmt2, str_repeat('i', count($ids)), ...$ids);
mysqli_stmt_execute($stmt2);
$res2 = mysqli_stmt_get_result($stmt2);
while($d = mysqli_fetch_assoc($res2)){
$incomes[$d['setOperationId']]['setOperationItems'][] = [
'setOperationDetailUnitPresentationId'       => $d['setOperationDetailUnitPresentationId'],
'setOperationDetailQuantity'                 => $d['setOperationDetailQuantity'],
'setOperationDetailUnitPrice'                => $d['setOperationDetailUnitPrice'],
'setOperationDetailTaxId'                    => $d['setOperationDetailTaxId'],
'setOperationDetailDiscountPercentage'       => $d['setOperationDetailDiscountPercentage'],
'setOperationDetailUnitCost'                 => $d['setOperationDetailUnitCost'],
'setOperationDetailNotes'                    => $d['setOperationDetailNotes'],
'setOperationDetailSubTotal'                 => $d['setOperationDetailSubTotal'],
'setOperationDetailTotalTax'                 => $d['setOperationDetailTotalTax'],
'setOperationDetailTotal'                    => $d['setOperationDetailTotal']
];
}
mysqli_free_result($res2);
mysqli_stmt_close($stmt2);
}

//RETORNAR JSON
$message_return = ['incomes' => array_values($incomes)];
echo lp_json_utf8(true, $message_return);
exit;
}

//CERRAR CONEXION
mysqli_close($con);
?>