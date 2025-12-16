<?php

require_once _PS_MODULE_DIR_ . 'shipping_calculator/src/services/ShippingGroupedPackageService.php';
require_once _PS_MODULE_DIR_ . 'shipping_calculator/src/services/CarrierRateTypeService.php';
require_once _PS_MODULE_DIR_ . 'shipping_calculator/src/services/WeightCalculatorService.php';


class ShippingQuoteService
{
    private $carrierRegistry;
    private $weightCalc;
    private $groupedPackageService;

    public function __construct(
        CarrierRegistryService $carrierRegistry,
        WeightCalculatorService $weightCalc
    ) {
        $this->carrierRegistry = $carrierRegistry;
        $this->weightCalc = $weightCalc;
        $this->groupedPackageService = new ShippingGroupedPackageService();
    }

    public function quote($id_product, $id_city, $qty = 1)
    {
        if ((int)$id_city <= 0) {
            return [];
        }

        $id_product = (int) $id_product;
        $id_city = (int) $id_city;
        $qty = max(1, (int) $qty);

        $product = new Product($id_product);

        if (!Validate::isLoadedObject($product)) {
            throw new Exception("Producto no encontrado.");
        }

        // --------------- 2. pesos producto -----------------
        $p_weight = (float) $product->weight * $qty;   // kg
        $p_width = (float) $product->width;          // cm
        $p_height = (float) $product->height;         // cm
        $p_length = (float) $product->depth;          // cm
        
        // --------------- 1. carriers con cobertura -----------------
        $carriersWithCoverage = $this->getCarriersWithCityCoverage($id_city);

        if (!$carriersWithCoverage) {
            return [];
        }

        // --------------- 3 y 4. cotizar -----------------
        $quotes = [];

        foreach ($carriersWithCoverage as $carrier) {
            try {
                $id_carrier = (int) $carrier['id_carrier'];
                $type = $carrier['type'];
                
                $kgVol = Db::getInstance()->getRow("
                    SELECT value_number
                    FROM " . _DB_PREFIX_ . "shipping_config
                    WHERE name = 'Peso volumetrico'
                    AND id_carrier = " . (int) $id_carrier . "
                ");

                if (!$kgVol || !isset($kgVol['value_number'])) {
                    $kgVol = ['value_number' => 5000];
                } else {
                    $kgVol['value_number'] = (int) $kgVol['value_number'];
                }

                $unitVolWeight = $this->weightCalc->volumetricWeight($p_length, $p_width, $p_height, $kgVol['value_number']);
                
                $volWeight = $unitVolWeight * $qty;
                
                $billable = $this->weightCalc->billableWeight($p_weight, $volWeight);
                
                $shippingCost = null;
                
                // CAMBIO: Usar comparación de strings directa en lugar de constantes
                if ($type === 'per_kg') {
                    $shippingCost = $this->calculatePerKg($id_carrier, $id_city, $billable);
                    
                } elseif ($type === 'range') {
                    $unitBillable = $this->weightCalc->billableWeight((float)$product->weight, $unitVolWeight);
                    
                    $unitCost = $this->calculateRange($id_carrier, $id_city, $unitBillable);
                    
                    if ($unitCost !== null) {
                        $shippingCost = $unitCost * $qty;
                    }
                }
                
                if ($shippingCost !== null && $shippingCost > 0) {
                    $shippingCost = (float) $shippingCost;
                    
                    $packagingCost = $this->calculatePackagingCost($shippingCost);

                    // Calcular valor total del paquete para seguro
                    $totalValue = (float) $product->price * $qty;
                    
                    // Para el seguro, usar peso unitario
                    $unitBillableForInsurance = isset($unitBillable) ? $unitBillable : $this->weightCalc->billableWeight((float)$product->weight, $unitVolWeight);
                    
                    $insuranceCost = $this->calculateInsuranceCost($id_carrier, $type, $unitBillableForInsurance, $totalValue);

                    $totalPrice = $shippingCost + $packagingCost + $insuranceCost;

                    $quotes[] = [
                        'carrier' => $carrier['name'],
                        'type' => $type,
                        'price' => $totalPrice,
                        'shipping_cost' => $shippingCost,
                        'packaging_cost' => $packagingCost,
                        'insurance_cost' => $insuranceCost,
                        'weight_real' => $p_weight,
                        'weight_vol' => $volWeight,
                        'weight_billable' => $billable,
                    ];
                }
                
            } catch (Exception $e) {
                continue;
            }
        }

        usort($quotes, function ($a, $b) {
            return $a['price'] <=> $b['price'];
        });

        return $quotes;
    }
    /**
     * Cotiza múltiples productos (array de items ['id_product'=>, 'qty'=>])
     * Retorna detalle por producto con la cotización más barata y el total acumulado.
     */
    public function quoteMultiple(array $items, $id_city)
    {
        $lang_id = Context::getContext()->language->id;
        $results = [];
        $total = 0.0;

        foreach ($items as $it) {
            $id_product = isset($it['id_product']) ? (int) $it['id_product'] : 0;
            $qty = isset($it['qty']) ? max(1, (int) $it['qty']) : 1;

            if (!$id_product) {
                continue;
            }

            // verificar si es agrupado
            $row = Db::getInstance()->getRow("SELECT is_grouped FROM " . _DB_PREFIX_ . "shipping_product WHERE id_product = " . (int) $id_product);
            $is_grouped = $row ? (int) $row['is_grouped'] : 0;

            $productRow = Db::getInstance()->getRow("SELECT id_product, name FROM " . _DB_PREFIX_ . "product_lang WHERE id_product = " . (int) $id_product . " AND id_lang = " . (int) $lang_id);

            if ($is_grouped === 1) {
                $results[] = [
                    'id_product' => $id_product,
                    'name' => $productRow ? $productRow['name'] : '',
                    'qty' => $qty,
                    'is_grouped' => 1,
                    'cheapest' => null,
                    'quotes' => [],
                ];
                continue;
            }

            // reutilizar la cotización por producto
            $quotes = $this->quote($id_product, $id_city, $qty);

            $cheapest = null;
            if ($quotes && count($quotes) > 0) {
                $cheapest = [
                    'carrier' => $quotes[0]['carrier'],
                    'price' => (float) $quotes[0]['price'],
                ];
                $total += (float) $quotes[0]['price'];
            }

            $results[] = [
                'id_product' => $id_product,
                'name' => $productRow ? $productRow['name'] : '',
                'qty' => $qty,
                'is_grouped' => 0,
                'cheapest' => $cheapest,
                'quotes' => $quotes,
            ];
        }

        return ['items' => $results, 'total' => (float) $total];
    }

    /**
     * Cotiza múltiples productos, manejando 3 tipos:
     * 1. Agrupables (is_grouped=1)
     * 2. Individuales Agrupables (is_grouped=0, max_units_per_package > 0)
     * 3. Individuales NO Agrupables (is_grouped=0, max_units_per_package = 0/NULL)
     * 
     * Entrada: array de items con estructura:
     *   [
     *     {
     *       'id_product': int,
     *       'qty': int,
     *       'is_grouped': int (0 o 1),
     *       'max_units_per_package': int
     *     },
     *     ...
     *   ]
     */
    public function quoteMultipleWithGrouped(array $items, $id_city)
    {
        if ((int)$id_city <= 0 || empty($items)) {
            return [
                'grouped_packages' => [],
                'individual_grouped_packages' => [],
                'individual_non_grouped_items' => [],
                'total_grouped' => 0.0,
                'total_individual_grouped' => 0.0,
                'total_individual_non_grouped' => 0.0,
                'grand_total' => 0.0
            ];
        }
        
        $lang_id = Context::getContext()->language->id;
        $id_city = (int) $id_city;

        $groupedItems = [];              // is_grouped = 1
        $individualGroupableItems = [];  // is_grouped = 0, max_units > 0
        $individualNonGroupableItems = []; // is_grouped = 0, max_units = 0/NULL

        // Paso 1: Separar por tipo
        foreach ($items as $item) {
            $id_product = isset($item['id_product']) ? (int) $item['id_product'] : 0;
            $qty = isset($item['qty']) ? max(1, (int) $item['qty']) : 1;
            $is_grouped = isset($item['is_grouped']) ? (int) $item['is_grouped'] : 0;
            $max_units = isset($item['max_units_per_package']) ? (int) $item['max_units_per_package'] : 0;

            if (!$id_product)
                continue;

            $product = new Product($id_product);
            if (!Validate::isLoadedObject($product)) {
                continue;
            }

            // Obtener nombre en el idioma actual
            $productName = $this->getProductName($id_product, $lang_id);
            $productPrice = (float) $product->price;

            if ($is_grouped === 1) {
                // Productos AGRUPABLES
                $groupedItems[] = [
                    'id_product' => $id_product,
                    'quantity' => $qty,
                    'max_units_per_package' => $max_units,
                    'weight_unit' => (float) $product->weight,
                    'height' => (float) $product->height,
                    'width' => (float) $product->width,
                    'depth' => (float) $product->depth,
                    'name' => $productName,
                    'price' => $productPrice
                ];
            } else {
                // Productos INDIVIDUALES
                if ($max_units > 0) {
                    // INDIVIDUAL AGRUPABLE (se agrupa consigo mismo)
                    $individualGroupableItems[] = [
                        'id_product' => $id_product,
                        'quantity' => $qty,
                        'max_units_per_package' => $max_units,
                        'weight_unit' => (float) $product->weight,
                        'height' => (float) $product->height,
                        'width' => (float) $product->width,
                        'depth' => (float) $product->depth,
                        'name' => $productName,
                        'price' => $productPrice
                    ];
                } else {
                    // INDIVIDUAL NO AGRUPABLE (cada unidad separada)
                    $individualNonGroupableItems[] = [
                        'id_product' => $id_product,
                        'qty' => $qty,
                        'name' => $productName
                    ];
                }
            }
        }

        // Paso 2: Obtener factor volumétrico máximo
        $maxVolumetricFactor = $this->getMaxVolumetricFactor();

        $results = [
            'grouped_packages' => [],
            'individual_grouped_packages' => [],
            'individual_non_grouped_items' => [],
            'total_grouped' => 0.0,
            'total_individual_grouped' => 0.0,
            'total_individual_non_grouped' => 0.0,
            'total_individual' => 0.0,
            'grand_total' => 0.0
        ];

        // Paso 3: Procesar productos agrupados
        if (!empty($groupedItems)) {
            $packageResult = $this->groupedPackageService->buildGroupedPackages(
                $groupedItems,
                $maxVolumetricFactor
            );

            $results['grouped_packages'] = $this->quotGroupedPackages(
                $packageResult['grouped_packages'],
                $id_city,
                $lang_id
            );

            // Sumar precios más baratos de paquetes agrupados
            foreach ($results['grouped_packages'] as $pkgResult) {
                if (isset($pkgResult['cheapest']) && $pkgResult['cheapest']) {
                    $price = (float) $pkgResult['cheapest']['price'];
                    $results['total_grouped'] += $price;
                }
            }

            // Productos agrupados que exceden peso deben tratarse como individuales NO agrupables
            foreach ($packageResult['individual_products'] as $indvProduct) {
                $individualNonGroupableItems[] = [
                    'id_product' => $indvProduct['id_product'],
                    'qty' => $indvProduct['quantity'],
                    'name' => $this->getProductName($indvProduct['id_product'], $lang_id),
                    'reason' => 'from_grouped_exceeds_max'
                ];
            }
        }

        // Paso 4: Procesar productos individuales AGRUPABLES
        if (!empty($individualGroupableItems)) {
            
            require_once dirname(__FILE__) . '/IndividualGroupablePackageService.php';
            $individualGroupableService = new IndividualGroupablePackageService();

            $individualPackageResult = $individualGroupableService->buildIndividualPackages(
                $individualGroupableItems,
                $maxVolumetricFactor
            );
            
            // Cotizar paquetes individuales agrupables
            foreach ($individualPackageResult['individual_packages'] as $pkg) {
                $packageWeight = (float)$pkg['total_weight'];
                $unitsInPackage = (int)$pkg['units_in_package'];
                $pricePerUnit = (float)$pkg['price_per_unit'];
                $packageValue = $pricePerUnit * $unitsInPackage;

                $quotes = $this->quoteByWeight($packageWeight, $id_city, $packageValue);

                $cheapest = null;
                if ($quotes && count($quotes) > 0) {
                    $cheapest = [
                        'carrier' => $quotes[0]['carrier'],
                        'price' => (float)$quotes[0]['price'],
                    ];
                    $results['total_individual_grouped'] += (float)$quotes[0]['price'];
                }

                $results['individual_grouped_packages'][] = [
                    'package_id' => $pkg['package_id'],
                    'package_type' => 'individual_grouped',
                    'id_product' => $pkg['id_product'],
                    'product_name' => $pkg['product_name'],
                    'total_weight' => $packageWeight,
                    'units_in_package' => $unitsInPackage,
                    'cheapest' => $cheapest,
                    'quotes' => $quotes
                ];
            }

            // Productos oversized van como NO agrupables
            foreach ($individualPackageResult['oversized_products'] as $oversized) {
                $individualNonGroupableItems[] = [
                    'id_product' => $oversized['id_product'],
                    'qty' => $oversized['quantity'],
                    'name' => $this->getProductName($oversized['id_product'], $lang_id),
                    'reason' => 'unit_exceeds_max_weight'
                ];
            }
        }

        // Paso 5: Procesar productos individuales NO AGRUPABLES (cada unidad por separado)
        if (!empty($individualNonGroupableItems)) {
            
            foreach ($individualNonGroupableItems as $item) {
                $id_product = $item['id_product'];
                $qty = $item['qty'];
                
                $quotes = $this->quote($id_product, $id_city, $qty);

                $cheapest = null;
                if ($quotes && count($quotes) > 0) {
                    $cheapest = [
                        'carrier' => $quotes[0]['carrier'],
                        'price' => (float) $quotes[0]['price'],
                    ];
                    $results['total_individual_non_grouped'] += (float) $quotes[0]['price'];
                }

                $results['individual_non_grouped_items'][] = [
                    'id_product' => $id_product,
                    'name' => $item['name'],
                    'qty' => $qty,
                    'is_grouped' => 0,
                    'cheapest' => $cheapest,
                    'quotes' => $quotes,
                    'reason' => isset($item['reason']) ? $item['reason'] : null
                ];
            }
        }

        // Paso 6: Calcular totales
        $results['grand_total'] = $results['total_grouped'] + $results['total_individual_grouped'] + $results['total_individual_non_grouped'];

        return $results;
    }

    /**
     * Cotiza paquetes agrupados: para cada paquete, calcula todas las tarifas disponibles
     */
    private function quotGroupedPackages(array $packages, $id_city, $lang_id)
    {
        $results = [];

        foreach ($packages as $package) {
            $packageWeight = (float) $package['total_weight'];

            // Calcular valor total del paquete
            $packageValue = 0.0;
            foreach ($package['items'] as $item) {
                $price = isset($item['price']) ? (float) $item['price'] : 0.0;
                $packageValue += $price * $item['units_in_package'];
            }

            // Cotizar este paquete como si fuera un producto individual
            $quotes = $this->quoteByWeight($packageWeight, $id_city, $packageValue);

            $cheapest = null;
            if ($quotes && count($quotes) > 0) {
                $cheapest = [
                    'carrier' => $quotes[0]['carrier'],
                    'price' => (float) $quotes[0]['price'],
                ];
            }

            // Construir descripción de contenido del paquete con detalles de pesos
            $itemsSummary = [];
            $itemsDetailed = [];

            foreach ($package['items'] as $item) {
                $itemName = $this->getProductName($item['id_product'], $lang_id);
                $itemsSummary[] = sprintf(
                    "%s (%d unidades)",
                    $itemName,
                    $item['units_in_package']
                );

                // Crear estructura detallada para cada item con pesos
                $itemsDetailed[] = [
                    'id_product' => $item['id_product'],
                    'name' => $itemName,
                    'units_in_package' => $item['units_in_package'],
                    'real_weight_unit' => isset($item['real_weight_unit']) ? (float) $item['real_weight_unit'] : 0,
                    'volumetric_weight_unit' => isset($item['volumetric_weight_unit']) ? (float) $item['volumetric_weight_unit'] : 0,
                    'total_real_weight' => isset($item['real_weight_unit']) ? (float) $item['real_weight_unit'] * $item['units_in_package'] : 0,
                    'total_volumetric_weight' => isset($item['volumetric_weight_unit']) ? (float) $item['volumetric_weight_unit'] * $item['units_in_package'] : 0,
                    'price_unit' => isset($item['price']) ? (float) $item['price'] : 0.0
                ];
            }

            $results[] = [
                'package_id' => $package['package_id'],
                'package_type' => 'grouped',
                'total_weight' => $packageWeight,
                'items_summary' => implode(", ", $itemsSummary),
                'items_detail' => $itemsDetailed,
                'cheapest' => $cheapest,
                'quotes' => $quotes
            ];
        }

        return $results;
    }

    /**
     * Cotiza un peso específico (para paquetes agrupados)
     */
    private function quoteByWeight($weight, $id_city, $declaredValue = 0.0)
    {
        if ((int)$id_city <= 0) {
            return [];
        }

        $id_city = (int) $id_city;
        $weight = (float) $weight;
        $declaredValue = (float) $declaredValue;

        $carriersWithCoverage = $this->getCarriersWithCityCoverage($id_city);

        if (!$carriersWithCoverage) {
            return [];
        }

        $quotes = [];

        foreach ($carriersWithCoverage as $carrier) {
            $id_carrier = (int) $carrier['id_carrier'];
            $type = $carrier['type'];

            if ($type === CarrierRateTypeService::RATE_TYPE_PER_KG) {
                $shippingCost = $this->calculatePerKg($id_carrier, $id_city, $weight);
            } else {
                $shippingCost = $this->calculateRange($id_carrier, $id_city, $weight);
            }

            if ($shippingCost !== null) {
                $shippingCost = (float) $shippingCost;
                $packagingCost = $this->calculatePackagingCost($shippingCost);
                $insuranceCost = $this->calculateInsuranceCost($id_carrier, $type, $weight, $declaredValue);

                $totalPrice = $shippingCost + $packagingCost + $insuranceCost;

                $quotes[] = [
                    'carrier' => $carrier['name'],
                    'type' => $type,
                    'price' => $totalPrice,
                    'shipping_cost' => $shippingCost,
                    'packaging_cost' => $packagingCost,
                    'insurance_cost' => $insuranceCost,
                    'weight_billable' => $weight,
                ];
            }
        }

        usort($quotes, function ($a, $b) {
            return $a['price'] <=> $b['price'];
        });

        return $quotes;
    }

    /**
     * Calcula costo de empaque: % del valor del envío
     */
    private function calculatePackagingCost($shippingCost)
    {
        $row = Db::getInstance()->getRow("
            SELECT value_number
            FROM " . _DB_PREFIX_ . "shipping_config
            WHERE name = 'Empaque'
              AND (id_carrier = 0 OR id_carrier IS NULL)
        ");

        if ($row && isset($row['value_number'])) {
            $percentage = (float) $row['value_number'];
            return $shippingCost * ($percentage / 100);
        }
        return 0.0;
    }

    /**
     * Calcula costo de seguro
     * 
     * Por Rango: Busca en rangos de PESO y aplica porcentaje sobre valor declarado
     * Por Kg: Busca en rangos de VALOR DECLARADO y aplica valor fijo o porcentaje
     */
    private function calculateInsuranceCost($id_carrier, $carrierType, $weight, $declaredValue)
    {
        if ($carrierType === CarrierRateTypeService::RATE_TYPE_RANGE) {
            // TRANSPORTADORAS POR RANGO: Seguro por rangos de PESO
            $rangeRow = Db::getInstance()->getRow("
                SELECT value_number
                FROM " . _DB_PREFIX_ . "shipping_config
                WHERE id_carrier = " . (int) $id_carrier . "
                  AND name = 'Seguro'
                  AND min <= " . (float) $weight . "
                  AND (max >= " . (float) $weight . " OR max IS NULL OR max = 0)
                ORDER BY min DESC
            ");

            if ($rangeRow) {
                $percentage = (float) $rangeRow['value_number'];
                return $declaredValue * ($percentage / 100);
            }
        } else {
            // TRANSPORTADORAS POR KG: Seguro por rangos de VALOR DECLARADO
            $rangeRow = Db::getInstance()->getRow("
                SELECT value_number, min, max
                FROM " . _DB_PREFIX_ . "shipping_config
                WHERE id_carrier = " . (int) $id_carrier . "
                  AND name = 'Seguro'
                  AND min <= " . (float) $declaredValue . "
                  AND (max >= " . (float) $declaredValue . " OR max IS NULL OR max = 0)
                ORDER BY min DESC
            ");

            if ($rangeRow) {
                $valueNumber = (float) $rangeRow['value_number'];
                
                // Si value_number >= 100, es valor fijo en pesos
                // Si value_number < 100, es porcentaje
                if ($valueNumber >= 100) {
                    return $valueNumber;  // Valor fijo
                } else {
                    return $declaredValue * ($valueNumber / 100);  // Porcentaje
                }
            }
        }

        return 0.0;
    }

    /**
     * Obtiene el factor volumétrico MÍNIMO de todos los carriers
     * (Factor menor = peso volumétrico mayor = cálculo más conservador)
     */
    private function getMaxVolumetricFactor()
    {
        $result = Db::getInstance()->getRow("
            SELECT MIN(CAST(value_number AS UNSIGNED)) as min_factor
            FROM " . _DB_PREFIX_ . "shipping_config
            WHERE name = 'Peso volumetrico' AND CAST(value_number AS UNSIGNED) > 0
        ");

        if ($result && isset($result['min_factor']) && $result['min_factor'] > 0) {
            return (int) $result['min_factor'];
        }

        return 5000; // Default
    }

    /**
     * Obtiene el nombre de un producto por ID
     */
    private function getProductName($id_product, $lang_id)
    {
        $result = Db::getInstance()->getRow("
            SELECT name
            FROM " . _DB_PREFIX_ . "product_lang
            WHERE id_product = " . (int) $id_product . "
            AND id_lang = " . (int) $lang_id . "
        ");

        return $result ? $result['name'] : 'Producto #' . $id_product;
    }

    /**
     * Devuelve SOLO carriers registrados que tienen filas
     * para esta ciudad en alguna tabla de tarifas.
     */
    private function getCarriersWithCityCoverage($id_city)
    {
        $registered = $this->carrierRegistry->getAllRegistered();
        if (!$registered)
            return [];

        // ids con cobertura per kg
        $perKg = Db::getInstance()->executeS("
            SELECT DISTINCT id_carrier
            FROM " . _DB_PREFIX_ . "shipping_per_kg_rate
            WHERE id_city = " . (int) $id_city . "
              AND active = 1
        ");

        // ids con cobertura rangos
        $ranges = Db::getInstance()->executeS("
            SELECT DISTINCT id_carrier
            FROM " . _DB_PREFIX_ . "shipping_range_rate
            WHERE id_city = " . (int) $id_city . "
              AND active = 1
        ");

        $coveredIds = [];
        foreach ($perKg as $r)
            $coveredIds[(int) $r['id_carrier']] = true;
        foreach ($ranges as $r)
            $coveredIds[(int) $r['id_carrier']] = true;

        // filtrar solo los registrados + con cobertura
        $result = [];
        foreach ($registered as $c) {
            $id_carrier = (int) $c['id_carrier'];
            if (isset($coveredIds[$id_carrier])) {
                $result[] = $c;
            }
        }

        return $result;
    }

    /**
     * Precio por KG * peso facturable (kg)
     */
    private function calculatePerKg($id_carrier, $id_city, $weight)
    {
        $row = Db::getInstance()->getRow("
            SELECT price
            FROM " . _DB_PREFIX_ . "shipping_per_kg_rate
            WHERE id_carrier = " . (int) $id_carrier . "
              AND id_city = " . (int) $id_city . "
              AND active = 1
        ");

        if (!$row) {
            return null;
        }

        $min = Db::getInstance()->getRow("
            SELECT value_number
            FROM " . _DB_PREFIX_ . "shipping_config
            WHERE name = 'Flete minimo'
            AND id_carrier = " . (int) $id_carrier . "
        ");

        $price = (float) $row['price'] * (float) $weight;

        if ($min && isset($min['value_number']) && $price < (float) $min['value_number']) {
            $price = (float) $min['value_number'];
        }

        return $price;
    }

    private function calculateRange($id_carrier, $id_city, $weight)
    {
        try {
            // Validaciones mínimas
            $id_carrier = (int)$id_carrier;
            $id_city    = (int)$id_city;
            $weight     = (float)$weight;

            if ($id_carrier <= 0 || $id_city <= 0 || $weight <= 0) {
                return null;
            }
            
            // PASO 1: Verificar si existe cobertura para esta ciudad
            
            // ✅ CORRECCIÓN: usar id_range_rate en lugar de id_range
            $coverageCheck = Db::getInstance()->executeS("
                SELECT id_range_rate, min_weight, max_weight, price, active
                FROM "._DB_PREFIX_."shipping_range_rate
                WHERE id_carrier = ".$id_carrier."
                AND id_city = ".$id_city."
                ORDER BY min_weight ASC
            ");
            
            if ($coverageCheck === false) {
                return null;
            }
            
            if (empty($coverageCheck)) {
                return null;
            }

            // PASO 2: Buscar el rango activo que contenga el peso
            $rows = Db::getInstance()->executeS("
                SELECT price, min_weight, max_weight
                FROM "._DB_PREFIX_."shipping_range_rate
                WHERE id_carrier = ".$id_carrier."
                AND id_city = ".$id_city."
                AND active = 1
                AND min_weight <= ".$weight."
                AND (max_weight = 0 OR max_weight >= ".$weight.")
                ORDER BY min_weight DESC
            ");
            
            if ($rows === false) {
                return null;
            }

            $row = $rows[0] ?? null;

            if (!$row) {
                return null;
            }

            $price = (float)$row['price'];

            if ($price <= 0) {
                return null;
            }

            return $price;
            
        } catch (Exception $e) {
            return null;
        }
    }
}