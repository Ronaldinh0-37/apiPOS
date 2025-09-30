<?php
// Activar reporte de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configurar encabezados CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Manejar solicitud OPTIONS de preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("HTTP/1.1 200 OK");
    exit();
}

set_time_limit(180);
ini_set('max_execution_time', 180);
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Lima');

// Validar método POST

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    echo json_encode([
        'success' => false,
        'message' => 'Método No Permitido.'
    ]);
    exit();
}

// Función para formatear respuestas JSON
function lp_json_utf8($success, $message) {
    $response = [
        'success' => $success,
        'message' => $message
    ];
    return json_encode($response, JSON_UNESCAPED_UNICODE);
}

// Obtener datos de entrada
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validar JSON
if (json_last_error() !== JSON_ERROR_NONE) {
    echo lp_json_utf8(false, 'JSON Inválido.');
    exit();
}

// Token estático válido
$validToken = 'HV5Teaj66n6sOdpy_z_x0uA9qQCJqV3PIm152yiWp5s';

// Validar token
if(!isset($data['setToken']) || $data['setToken'] !== $validToken) {
    echo lp_json_utf8(false, "TOKEN INCORRECTO.");
    exit();
}

// Parámetros de la solicitud
$apiPOS_getAccessData = isset($data['apiPOS_getAccessData']) ? (int)$data['apiPOS_getAccessData'] : 0;
$apiPOS_getItems = isset($data['apiPOS_getItems']) ? (int)$data['apiPOS_getItems'] : 0;
$apiPOS_shift = isset($data['apiPOS_shift']) ? (int)$data['apiPOS_shift'] : 0;

// --- Funciones para manejo de turnos ---
function readShiftsFromFile() {
    $filePath = 'turnos.json';
    if (!file_exists($filePath)) {
        file_put_contents($filePath, json_encode([], JSON_PRETTY_PRINT));
        return [];
    }
    $json = file_get_contents($filePath);
    $data = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Error decoding turnos.json: " . json_last_error_msg());
        return [];
    }
    return is_array($data) ? $data : [];
}

function writeShiftsToFile($shifts) {
    $filePath = 'turnos.json';
    $result = file_put_contents($filePath, json_encode($shifts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    if ($result === false) {
        error_log("Failed to write shifts to file: $filePath");
    }
    return $result !== false;
}

// --- Manejo de apertura/cierre de turnos ---
if($apiPOS_shift == 1) {
    // Validar datos mínimos requeridos
    $requiredFields = ['setBranchId', 'setCashierId'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            echo lp_json_utf8(false, "Falta el campo requerido: $field");
            exit();
        }
    }

    $branchId = (int)$data['setBranchId'];
    $cashierId = (int)$data['setCashierId'];
    $currentDateTime = date('Y-m-d H:i:s');

    // Leer turnos existentes
    $shifts = readShiftsFromFile();

    // Verificar si ya existe un turno abierto para este usuario
    $openShift = null;
    foreach ($shifts as $shift) {
        if ($shift['id_usuario'] == $cashierId && empty($shift['fecha_fin'])) {
            $openShift = $shift;
            break;
        }
    }

    // Determinar si es apertura o cierre
    if (isset($data['setShiftId']) && !empty($data['setShiftId'])) {
        // --- CERRAR TURNO ---
        $shiftId = $data['setShiftId'];
        
        // Validar datos para cierre
        if (!isset($data['setShiftDateEnd'], $data['setShiftCount'], $data['setShiftCashAmount'])) {
            echo lp_json_utf8(false, "Datos incompletos para cerrar turno");
            exit();
        }

        // Buscar el turno a cerrar
        $shiftFound = false;
        foreach ($shifts as &$shift) {
            if ($shift['shift_id'] == $shiftId && $shift['id_usuario'] == $cashierId) {
                $shiftFound = true;
                
                // Actualizar datos de cierre
                $shift['fecha_fin'] = date('Y-m-d H:i:s', strtotime($data['setShiftDateEnd']));
                $shift['conteo'] = (float)$data['setShiftCount'];
                $shift['total_caja_pos'] = (float)$data['setShiftCashAmount'];
                $shift['estado'] = 0; // Cerrado
                
                // Calcular estado_reponer
                $shift['estado_reponer'] = ($shift['conteo'] < $shift['total_caja_pos']) ? 2 : 0;
                
                break;
            }
        }

        if (!$shiftFound) {
            echo lp_json_utf8(false, "Turno no encontrado o no pertenece al usuario");
            exit();
        }

        // Guardar cambios
        if (writeShiftsToFile($shifts)) {
            echo lp_json_utf8(true, [
                'message' => 'Turno cerrado correctamente',
                'setShiftId' => $shiftId
            ]);
        } else {
            echo lp_json_utf8(false, "Error al guardar el cierre del turno");
        }
        
    } else {
        // --- APERTURAR TURNO ---
        if ($openShift) {
            echo lp_json_utf8(false, "Ya existe un turno abierto para este usuario");
            exit();
        }

        // Validar monto inicial
        if (!isset($data['setCashInitial']) || !is_numeric($data['setCashInitial'])) {
            echo lp_json_utf8(false, "Monto inicial inválido");
            exit();
        }
        $cashInitial = (float)$data['setCashInitial'];

        // Crear nuevo turno
        $newShift = [
            'shift_id' => uniqid('shift_', true),
            'id_usuario' => $cashierId,
            'id_sucursal' => $branchId,
            'fecha_inicio' => isset($data['setShiftDateStart']) ? date('Y-m-d H:i:s', strtotime($data['setShiftDateStart'])) : $currentDateTime,
            'fecha_fin' => '',
            'monto_soles' => $cashInitial,
            'monto_dolares' => 0,
            'conteo' => 0,
            'estado_reponer' => 0,
            'id_banco' => 0,
            'total_caja_pos' => 0,
            'estado' => 1 // Abierto
        ];

        $shifts[] = $newShift;
        
        if (writeShiftsToFile($shifts)) {
            echo lp_json_utf8(true, [
                'message' => 'Turno aperturado correctamente',
                'setShiftId' => $newShift['shift_id'],
                'setShiftDateStart' => $newShift['fecha_inicio']
            ]);
        } else {
            echo lp_json_utf8(false, "Error al guardar el nuevo turno");
        }
    }
    
    exit();
}

// Respuesta para ACCESS DATA
if($apiPOS_getAccessData == 1) {
    $response = [
        'setCompanyLogo' => 'data:image/png;base64,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',
        'setCompanyDoc' => '10724753099',
        'setCompanyName' => 'AUTOMOTRIZ PERUANA S.A.',
        'setCompanyNameTrade' => 'AUTOMOTRIZ PERU',
        'setCompanyAddress' => 'Av.  123, Lima, Perú',
        'setCompanyPhone' => '+51916173721',
        'setCompanyEmail' => 'ventas@automotrizperu.com', 
        'setCompanyWebsite' => 'www.automotrizperu.com',

        'setBranches' => [
            [
                'setBranchId' => 130,
                'setBranchCode' => '001',
                'setBranchName' => 'Sucursal Principal',
                'setBranchAddress' => 'Av. Principal 456, Lima',
                'setPrinters' => [
                    [
                        'setPrinterId' => 1,
                        'setPrinterName' => 'Impresora Principal',
                        'setPrinterAlias' => 'Ticket'
                    ]
                ],
                'setWarehouses' => [
                    [
                        'setWarehouseId' => 1,
                        'setWarehouseName' => 'Almacen Principal'
                    ]
                ]
            ]
        ],

        'setPrinters' => [
            [
                'setPrinterId' => 1,
                'setPrinterName' => 'Impresora Principal',
                'setPrinterAlias' => 'Ticket',
                'setPrinterBranchId' => 1
            ]
        ],

        'setUsuarios' => [
            [
                'setUserCodigo' => 1,
                'setUserDocNumber' => '12345678',
                'setUserNombres' => 'Administrador Demo',
                'setUserName' => 'admin',
                'setUserPassw' => 'demo123',
                'permSellNoStock' => 1,
                'permViewBranchProducts' => 1,
                'setBranches' => [
                    ['setBranchId' => 130]
                ]
            ]
        ],

        'setSeries' => [
            [
                'ID_SERIE' => 1,
                'setSerie' => 'F001',
                'setSerieTipo' => '3',
                'setCorrelativoInicial' => 1234,
                'setIdSucursal' => 130,
                'setSeriesNumberInitial' => 1
            ]
        ],

        'setBankAccounts' => [
            [
                'setBankId' => 1,
                'setBankType' => 1,
                'setBankName' => 'Banco de Crédito',
                'setBankNumber' => '193-12345678-1-23',
                'setBankCCI' => '00219300123456781234',
                'setBankCurrency' => 'PEN',
                'setBankBalance' => 15000.50
            ]
        ],
        'setCustomers' => [

            [
                'setCustomerId' => 1,
                'setCustomerDocumentNumber' => '87654321',
                'setCustumerPhone' => '916173721',
                'setCustumerEmail' => 'rpintadoaguilar@gmail.com',
                'setCustomerName' => 'leonardo ',
                'setCustomerAddress' => 'Jr. Cliente 789, Lima',
                'setCustomerUbigeo' => '150101',
                'setCustomerBirthdate' => '1980-07-25',
                'setCustomerScoringSystemId' => 1,
                'setCustomerScore'=> 0
            ],
            [
                'setCustomerId' => 2,
                'setCustomerDocumentNumber' => '87654321',
                'setCustumerPhone' => '920392176',
                'setCustumerEmail' => 'jouseueehwhbf@gmail.com',
                'setCustomerName' => 'josue',
                'setCustomerAddress' => 'Jr. Cliente 789, Lima',
                'setCustomerUbigeo' => '150101',
                'setCustomerBirthdate' => '2000-07-26',
                'setCustomerScoringSystemId' => 1,
                'setCustomerScore'=> 0
            ],
            [
                'setCustomerId' =>3,
                'setCustomerDocumentNumber' => '878477477',
                'setCustumerPhone' => '920392176',
                'setCustumerEmail' => 'Erwuiebddff@gmail.com',
                'setCustomerName' => 'josue',
                'setCustomerAddress' => 'Jr. Cliente 789, Lima',
                'setCustomerUbigeo' => '150101',
                'setCustomerBirthdate' => '2000-07-26',
                'setCustomerScoringSystemId' => 2,
                'setCustomerScore'=> 10
            ]

            ],

    
            'setSuppliers' => [
            [
                'setSupplierId' => 3,
                'setSupplierDocumentNumber' => '20198765432',
                'setSupplierName' => 'Proveedor Demo SAC',
                'setSupplierAddress' => 'Av. Proveedor 321, Lima',
                'setSupplierUbigeo' => '150102',
                'setSupplierBirthdate' => '1990-01-01',
            ]
        ],

        'setShifts' => [
            [
                'setShiftId' => 1,
                'setCashInitial' => 1000.00,
                'setShiftDateStart' => '2028-01-01 08:00:00',
                'setShiftStatus' => '1',
                'setBranchId' => 130
            ]
        ],

        'setCollaborators' => [
            [
                'setCollaboratorId' => 1,
                'setCollaboratorDocNumber' => '76543210',
                'setCollaboratorFullName' => 'Colaborador Demo',
                'setCollaboratorAddress' => 'Jr. Colaborador 654, Lima',
                'setCollaboratorUbigeo' => '150103',
                'setCollaboratorBirthdate' => '1985-01-01'
            ]
        ],

        'setPlacesStart' => [
            [
                'setPlaceStartId' => 1,
                'setPlaceStartBranchId' => 1,
                'setPlaceStartCode' => '001',
                'setPlaceStartAddress' => 'Av. Partida 123, Lima',
                'setPlaceStartUbigeo' => '150101'
            ]
        ],

        'setVehicles' => [
            [
                'setVehicleId' => 1,
                'setVehicleName' => 'Camión de reparto',
                'setVehiclePlate' => 'ABC-123',
                'setVehicleBrand' => 'Toyota'
            ]
        ],

        'setDrivers' => [
            [
                'setDriverId' => 1,
                'setDriverDocType' => 'DNI',
                'setDriverDocNumber' => '65432109',
                'setDriverName' => 'Conductor Demo',
                'setDriverLicense' => 'B12345678',
                'setDriverCertificate' => 'CERT-12345'
            ]
        ],
        'setItemBrands' => [
            [
                'setItemBrandId' => 1,
                'setItemBrandName' =>'KARPIX',
            ],
            [
                'setItemBrandId' => 2,
                'setItemBrandName' => 'KARPIX',
            ],
            [
                'setItemBrandId' => 3,
                'setItemBrandName' => 'KARPIX',
            ],
            [
                'setItemBrandId' => 4,
                'setItemBrandName' => 'KARPIX',
            ],
            [
                'setItemBrandId' => 5,
                'setItemBrandName' => 'KARPIX',
            ],
        ],

        'setItemCategories' => [
            [
                'setItemCategoryId'=> 1,
                'setItemCategoryName' =>'Tratamientos'
            ],
            [
                'setItemCategoryId'=> 2,
                'setItemCategoryName' =>'limpieza'
            ],
            [
                'setItemCategoryId'=> 3,
                'setItemCategoryName' =>'Tratamientos'
            ],
            [
                'setItemCategoryId'=> 4,
                'setItemCategoryName' =>'limpieza'
            ],
            [
                'setItemCategoryId'=> 5,
                'setItemCategoryName' =>'limpieza'
            ]
        ],

        'setCurrencyDefault' => 'PEN',


        'setPaymentMethodBanks' => [
            [
                'setPaymentId'=> 2,
                'setBankId '=> 1,
            ]

    
        ],
        
      'setScoringSystem' => [
            [
                'setScoringSystemId'=> 1,
                'setScoringSystemName '=> 'cliente clasico',
                'setScoringSystemValidityType'=> 1,
                'setScoringSystemValidFrom '=> '',
                'setScoringSystemValidUntil'=> '',
                'setScoringSystemConvertingPointsToCurrencyP '=> 10,
                'setScoringSystemConvertingPointsToCurrencyC'=> 1,
                'setScoringSystemConvertingCurrencyToPointsC '=> 100,
                'setScoringSystemConvertingCurrencyToPointsP '=> 10
                

            ],
             [
                'setScoringSystemId'=> 2,
                'setScoringSystemName '=> 'cliente oro',
                'setScoringSystemValidityType'=> 1,
                'setScoringSystemValidFrom '=> '',
                'setScoringSystemValidUntil'=> '',
                'setScoringSystemConvertingPointsToCurrencyP '=> 10,
                'setScoringSystemConvertingPointsToCurrencyC'=> 1,
                'setScoringSystemConvertingCurrencyToPointsC '=> 100,
                'setScoringSystemConvertingCurrencyToPointsP '=> 20
                

            ]

    
        ],

        
    ];

    echo lp_json_utf8(true, $response);
    exit();
}

// Respuesta para GET ITEMS
if($apiPOS_getItems == 1) {
    $limit = isset($data['limit']) ? (int)$data['limit'] : 1000;
    $offset = isset($data['offset']) ? (int)$data['offset'] : 0;

    $products = [
        [
            'setId' => 1,
            'setIdProducto' => 101,
            'setItemType' => 1,
            'setCodProducto' => 'REP001',
            'setItemBarCode' => '7891234560123',
            'setDescripcion' => 'REPELENTE ANTI-LLUVIA KARPIX 250ML',
            'setItemDescription' => 'REPELENTE DE LLUVIA ANTI-LLUVIAS, solución diseñada para aplicarse sobre superficies exteriores con el fin de protegerlas de la humedad y evitar que se empapen durante las lluvias. Crea una capa impermeable que repele el agua, ayudando a mantener las superficies secas y protegidas.',
            'setUnidadCodigo' => 'NIU',
            'setUnidad' => 'UND',
            'setItemUnitAlias' => 'Unidad',
            'setMtoValorUnitario' => 25.00,
            'setMtoPrecioUnitario' => 35.00,
            'setMtoValorCostoUnitario' => 18.00,
            'setMtoPrecioCostoUnitario' => 20.00,
            'setMtoValorUnitario_us' => 7.50,
            'setMtoPrecioUnitario_us' => 10.00,
            'setMtoValorCostoUnitario_us' => 5.50,
            'setMtoPrecioCostoUnitario_us' => 6.00,
            'setConversionFactor' => 1.0,
            'setTaxId' => 1,
            'setInventariable' => 1,
            'setImg' => 'https://valca.com.pe/public/frontend/images/producto/1205202217574895.jpg',
            'setItemIsBought' => 1,
            'setItemIsSold' => 1,
            'setCategoria' => 'Tratamientos',
            'setMarca' => 'Karpix',
            'currency_data' => [
                [
                    'currency' => 'PEN',
                    'setMtoValorUnitario' => 25.00,
                    'setMtoPrecioUnitario' => 35.00,
                    'setMtoValorCostoUnitario' => 18.00,
                    'setMtoPrecioCostoUnitario' => 20.00
                ],
                [
                    'currency' => 'USD',
                    'setMtoValorUnitario' => 7.50,
                    'setMtoPrecioUnitario' => 10.00,
                    'setMtoValorCostoUnitario' => 5.50,
                    'setMtoPrecioCostoUnitario' => 6.00
                ]
            ],
            'sucursales' => [
                [
                    'setIdSucursal' => 1,
                    'setSucursalStock' => 50,
                    'setWarehouseStockMin' => 10
                ]
            ],
            'setPrinters' => [
                [
                    'setPrinterId' => 1,
                    'setPrinterName' => 'Impresora Taller',
                    'setPrinterAlias' => 'Ticket'
                ]
            ],
            'setImages' => [
                [
                    'setImageUrl' => 'https://valca.com.pe/public/frontend/images/producto/1205202217574895.jpg'
                ]
            ],
            'setPriceLists' => [
                [
                    'setPriceListType' => 2,
                    'setPriceListName' => 'Lista Talleres',
                    'setPriceListDateStart' => '2023-01-01',
                    'setPriceListDateEnd' => '2023-12-31',
                    'setPriceListProductId' => 101,
                    'setPriceListItemId' => 1,
                    'setPriceListRangeStart' => 1,
                    'setPriceListRangeEnd' => 999,
                    'setPriceListAdjustmentType' => 1,
                    'setPriceListAdjustmentAmount' => 30.00
                ]
            ],
            'setItemVariantValues' => [
                'Tipo' => 'Repelente',
                'Aplicación' => 'Exterior'
            ],
            'setBatchName' => 'Lote-KP2023',
            'setBatchExpirationDate' => '2029-12-31'
        ],
        [
           'setItemSlug'=>'1,REPELENTE-ANTI-LLUVIA-KARPIX-250ML.html'
        ],
        [
            'setId' => 2,
            'setIdProducto' => 102,
            'setItemType' => 1,
            'setCodProducto' => 'REP002',
            'setItemBarCode' => '7891234560456',
            'setDescripcion' => 'LIMPIADOR DE MOTOR KARPIX CLEAN 1LT',
            'setItemDescription' => 'LIMPIADOR DE MOTOR KARPIX CLEAN, diseñado específicamente para eliminar la acumulación de suciedad, grasa y residuos en el motor de un vehículo.',
            'setUnidadCodigo' => 'NIU',
            'setUnidad' => 'UND',
            'setItemUnitAlias' => 'Unidad',
            'setMtoValorUnitario' => 45.00,
            'setMtoPrecioUnitario' => 65.00,
            'setMtoValorCostoUnitario' => 35.00,
            'setMtoPrecioCostoUnitario' => 38.00,
            'setMtoValorUnitario_us' => 13.50,
            'setMtoPrecioUnitario_us' => 18.50,
            'setMtoValorCostoUnitario_us' => 10.50,
            'setMtoPrecioCostoUnitario_us' => 11.50,
            'setConversionFactor' => 1.0,
            'setTaxId' => 1,
            'setInventariable' => 1,
            'setImg' => 'https://www.valca.com.pe/public/frontend/images/producto/1707202422333191.jpg',
            'setItemIsBought' => 1,
            'setItemIsSold' => 1,
            'setCategoria' => 'Limpieza',
            'setMarca' => 'Karpix',
            'currency_data' => [
                [
                    'currency' => 'PEN',
                    'setMtoValorUnitario' => 45.00,
                    'setMtoPrecioUnitario' => 65.00,
                    'setMtoValorCostoUnitario' => 35.00,
                    'setMtoPrecioCostoUnitario' => 38.00
                ],
                [
                    'currency' => 'USD',
                    'setMtoValorUnitario' => 13.50,
                    'setMtoPrecioUnitario' => 18.50,
                    'setMtoValorCostoUnitario' => 10.50,
                    'setMtoPrecioCostoUnitario' => 11.50
                ]
            ],
            'sucursales' => [
                [
                    'setIdSucursal' => 1,
                    'setSucursalStock' => 30,
                    'setWarehouseStockMin' => 5
                ]
            ],
            'setPrinters' => [
                [
                    'setPrinterId' => 1,
                    'setPrinterName' => 'Impresora Taller',
                    'setPrinterAlias' => 'Ticket'
                ]
            ],
            'setImages' => [
                [
                    'setImageUrl' => 'https://www.valca.com.pe/public/frontend/images/producto/1707202422333191.jpg'
                ]
            ],
            'setPriceLists' => [],
            'setItemVariantValues' => [
                'Tipo' => 'Líquido',
                'Uso' => 'Motor'
            ],
            'setBatchName' => 'Lote-KC2023',
            'setBatchExpirationDate' => '2029-06-30'
        ],
        [
            'setItemSlug'=>'2,LIMPIADOR-DE-MOTOR KARPIX-CLEAN-1LT.html'

        ],
        [
            'setId' => 3,
            'setIdProducto' => 103,
            'setItemType' => 1,
            'setCodProducto' => 'REP003',
            'setItemBarCode' => '7891234560789',
            'setDescripcion' => 'CERAS PARA AUTOS KARPIX BRILLO EXTREMO 500ML',
            'setItemDescription' => 'Cera de alta calidad que proporciona un brillo extremo y protección duradera para la pintura de tu vehículo. Fórmula avanzada que repele el agua y protege contra rayos UV.',
            'setUnidadCodigo' => 'NIU',
            'setUnidad' => 'UND',
            'setItemUnitAlias' => 'Unidad',
            'setMtoValorUnitario' => 55.00,
            'setMtoPrecioUnitario' => 80.00,
            'setMtoValorCostoUnitario' => 40.00,
            'setMtoPrecioCostoUnitario' => 45.00,
            'setMtoValorUnitario_us' => 16.50,
            'setMtoPrecioUnitario_us' => 23.00,
            'setMtoValorCostoUnitario_us' => 12.00,
            'setMtoPrecioCostoUnitario_us' => 13.50,
            'setConversionFactor' => 1.0,
            'setTaxId' => 1,
            'setInventariable' => 1,
            'setImg' => 'https://www.valca.com.pe/public/frontend/images/producto/1311202314573377.png',
            'setItemIsBought' => 1,
            'setItemIsSold' => 1,
            'setCategoria' => 'Tratamientos',
            'setMarca' => 'Karpix',
            'currency_data' => [
                [
                    'currency' => 'PEN',
                    'setMtoValorUnitario' => 55.00,
                    'setMtoPrecioUnitario' => 80.00,
                    'setMtoValorCostoUnitario' => 40.00,
                    'setMtoPrecioCostoUnitario' => 45.00
                ],
                [
                    'currency' => 'USD',
                    'setMtoValorUnitario' => 16.50,
                    'setMtoPrecioUnitario' => 23.00,
                    'setMtoValorCostoUnitario' => 12.00,
                    'setMtoPrecioCostoUnitario' => 13.50
                ]
            ],
            'sucursales' => [
                [
                    'setIdSucursal' => 1,
                    'setSucursalStock' => 25,
                    'setWarehouseStockMin' => 5
                ]
            ],
            'setPrinters' => [
                [
                    'setPrinterId' => 1,
                    'setPrinterName' => 'Impresora Taller',
                    'setPrinterAlias' => 'Ticket'
                ]
            ],
            'setImages' => [
                [
                    'setImageUrl' => 'https://example.com/images/cera-brillo-detalle.jpg'
                ]
            ],
            'setPriceLists' => [
                [
                    'setPriceListType' => 2,
                    'setPriceListName' => 'Promo Verano',
                    'setPriceListDateStart' => '2023-01-01',
                    'setPriceListDateEnd' => '2029-03-31',
                    'setPriceListProductId' => 103,
                    'setPriceListItemId' => 3,
                    'setPriceListRangeStart' => 1,
                    'setPriceListRangeEnd' => 999,
                    'setPriceListAdjustmentType' => 1,
                    'setPriceListAdjustmentAmount' => 70.00
                ]
            ],
            'setItemVariantValues' => [
                'Tipo' => 'Pasta',
                'Duración' => '3 meses'
            ],
            'setBatchName' => 'Lote-KB2023',
            'setBatchExpirationDate' => '2029-12-31'
        ],
        [
            'setId' => 4,
            'setIdProducto' => 104,
            'setItemType' => 1,
            'setCodProducto' => 'REP004',
            'setItemBarCode' => '7891234560124',
            'setDescripcion' => 'SHAMPOO AUTOMOTRIZ KARPIX ACTIVE 5LT',
            'setItemDescription' => 'Shampoo concentrado para lavado de autos con tecnología Active Foam que levanta la suciedad sin dañar la pintura. Biodegradable y seguro para todos los tipos de pintura.',
            'setUnidadCodigo' => 'NIU',
            'setUnidad' => 'UND',
            'setItemUnitAlias' => 'Unidad',
            'setMtoValorUnitario' => 75.00,
            'setMtoPrecioUnitario' => 110.00,
            'setMtoValorCostoUnitario' => 55.00,
            'setMtoPrecioCostoUnitario' => 60.00,
            'setMtoValorUnitario_us' => 22.50,
            'setMtoPrecioUnitario_us' => 32.00,
            'setMtoValorCostoUnitario_us' => 16.50,
            'setMtoPrecioCostoUnitario_us' => 18.00,
            'setConversionFactor' => 1.0,
            'setTaxId' => 1,
            'setInventariable' => 1,
            'setImg' => 'https://www.valca.com.pe/public/frontend/images/producto/1302202414023185.png',
            'setItemIsBought' => 1,
            'setItemIsSold' => 1,
            'setCategoria' => 'Limpieza',
            'setMarca' => 'Karpix',
            'currency_data' => [
                [
                    'currency' => 'PEN',
                    'setMtoValorUnitario' => 75.00,
                    'setMtoPrecioUnitario' => 110.00,
                    'setMtoValorCostoUnitario' => 55.00,
                    'setMtoPrecioCostoUnitario' => 60.00
                ],
                [
                    'currency' => 'USD',
                    'setMtoValorUnitario' => 22.50,
                    'setMtoPrecioUnitario' => 32.00,
                    'setMtoValorCostoUnitario' => 16.50,
                    'setMtoPrecioCostoUnitario' => 18.00
                ]
            ],
            'sucursales' => [
                [
                    'setIdSucursal' => 1,
                    'setSucursalStock' => 15,
                    'setWarehouseStockMin' => 3
                ]
            ],
            'setPrinters' => [
                [
                    'setPrinterId' => 1,
                    'setPrinterName' => 'Impresora Taller',
                    'setPrinterAlias' => 'Ticket'
                ]
            ],
            'setImages' => [
                [
                    'setImageUrl' => 'https://example.com/images/shampoo-auto-detalle.jpg'
                ]
            ],
            'setPriceLists' => [],
            'setItemVariantValues' => [
                'Tipo' => 'Concentrado',
                'Rendimiento' => '50 lavados'
            ],
            'setBatchName' => 'Lote-KS2023',
            'setBatchExpirationDate' => '2026-06-30'
        ],
        [
            'setItemSlug'=>'4,SHAMPOO-AUTOMOTRIZ-KARPIX-ACTIVE-5LT.html'
        ],
        [
            'setId' => 5,
            'setIdProducto' => 105,
            'setItemType' => 1,
            'setCodProducto' => 'REP005',
            'setItemBarCode' => '7891234560457',
            'setDescripcion' => 'DESENGRASANTE INDUSTRIAL KARPIX POWER 1LT',
            'setItemDescription' => 'Desengrasante de alto poder para motores y partes mecánicas. Elimina grasa, aceite y suciedad pesada. Fórmula biodegradable y no corrosiva.',
            'setUnidadCodigo' => 'NIU',
            'setUnidad' => 'UND',
            'setItemUnitAlias' => 'Unidad',
            'setMtoValorUnitario' => 40.00,
            'setMtoPrecioUnitario' => 60.00,
            'setMtoValorCostoUnitario' => 30.00,
            'setMtoPrecioCostoUnitario' => 35.00,
            'setMtoValorUnitario_us' => 12.00,
            'setMtoPrecioUnitario_us' => 17.50,
            'setMtoValorCostoUnitario_us' => 9.00,
            'setMtoPrecioCostoUnitario_us' => 10.50,
            'setConversionFactor' => 1.0,
            'setTaxId' => 1,
            'setInventariable' => 1,
            'setImg' => 'https://www.valca.com.pe/public/frontend/images/producto/1311202314442963.png ',
            'setItemIsBought' => 1,
            'setItemIsSold' => 1,
            'setCategoria' => 'Limpieza',
            'setMarca' => 'Karpix',
            'currency_data' => [
                [
                    'currency' => 'PEN',
                    'setMtoValorUnitario' => 40.00,
                    'setMtoPrecioUnitario' => 60.00,
                    'setMtoValorCostoUnitario' => 30.00,
                    'setMtoPrecioCostoUnitario' => 35.00
                ],
                [
                    'currency' => 'USD',
                    'setMtoValorUnitario' => 12.00,
                    'setMtoPrecioUnitario' => 17.50,
                    'setMtoValorCostoUnitario' => 9.00,
                    'setMtoPrecioCostoUnitario' => 10.50
                ]
            ],
            'sucursales' => [
                [
                    'setIdSucursal' => 1,
                    'setSucursalStock' => 20,
                    'setWarehouseStockMin' => 5
                ]
            ],
            'setPrinters' => [
                [
                    'setPrinterId' => 1,
                    'setPrinterName' => 'Impresora Taller',
                    'setPrinterAlias' => 'Ticket'
                ]
            ],
            'setImages' => [
                [
                    'setImageUrl' => 'https://example.com/images/desengrasante-detalle.jpg'
                ]
            ],
            'setPriceLists' => [
                [
                    'setPriceListType' => 2,
                    'setPriceListName' => 'Oferta Talleres',
                    'setPriceListDateStart' => '2023-01-01',
                    'setPriceListDateEnd' => '2029-12-31',
                    'setPriceListProductId' => 105,
                    'setPriceListItemId' => 5,
                    'setPriceListRangeStart' => 1,
                    'setPriceListRangeEnd' => 999,
                    'setPriceListAdjustmentType' => 1,
                    'setPriceListAdjustmentAmount' => 55.00
                ]
            
            ],
            'setItemVariantValues' => [
                'Tipo' => 'Industrial',
                'Uso' => 'Motores'
            ],
            'setBatchName' => 'Lote-KD2023',
            'setBatchExpirationDate' => '2026-06-30'
            
        ]
        

    ];

    // Aplicar límite y offset
    $products = array_slice($products, $offset, $limit);

    $response = [
        'setProductos' => $products,
        'receivedCount' => count($products),
        'requestedLimit' => $limit
    ];

    echo lp_json_utf8(true, $response);
    exit();
}

// Si no se especificó ninguna acción válida
if($apiPOS_getAccessData == 0 && $apiPOS_getItems == 0 && $apiPOS_shift == 0) {
    echo lp_json_utf8(false, "No se especificó una acción válida.");
}
?>