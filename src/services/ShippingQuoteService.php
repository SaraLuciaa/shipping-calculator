<?php

require_once _PS_MODULE_DIR_ . 'shipping_calculator/src/services/ShippingGroupedPackageService.php';

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

        $this->checkAndSeedConfig();
    }

    private function checkAndSeedConfig()
    {
        $count = (int) Db::getInstance()->getValue("SELECT COUNT(*) FROM " . _DB_PREFIX_ . "shipping_config");

        if ($count === 0) {
            // Seed Packaging (Global)
            Db::getInstance()->execute("
                INSERT INTO " . _DB_PREFIX_ . "shipping_config (id_carrier, name, value_number)
                VALUES (0, 'Empaque', 5.00)
            ");

            // Seed Volumetric Factor (Global)
            Db::getInstance()->execute("
                INSERT INTO " . _DB_PREFIX_ . "shipping_config (id_carrier, name, value_number)
                VALUES (0, 'Peso volumetrico', 5000)
            ");

            // Get active carriers to seed insurance
            $carriers = $this->carrierRegistry->getAllRegistered();
            foreach ($carriers as $carrier) {
                $id_carrier = (int) $carrier['id_carrier'];

                // Seed Insurance (Range based example)
                Db::getInstance()->execute("
                    INSERT INTO " . _DB_PREFIX_ . "shipping_config (id_carrier, name, min, max, value_number)
                    VALUES 
                    ($id_carrier, 'Seguro', 0, 10000, 1.00)
                ");

                // Seed Insurance Min (Mixed case example)
                Db::getInstance()->execute("
                    INSERT INTO " . _DB_PREFIX_ . "shipping_config (id_carrier, name, min, max, value_number)
                    VALUES 
                    ($id_carrier, 'Seguro', 0, 0, 1000.00)
                ");
            }
        }
    }

    /**
     * Cotiza envío para un producto y ciudad.
     * Pasos:
     * 1. Buscar carriers con cobertura para la ciudad
     * 2. Calcular peso volumétrico y real
     * 3. Tomar el mayor y cotizar según tipo
     * 4. Retornar listado
     */
    public function quote($id_product, $id_city, $qty = 1)
    {
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
            return []; // nadie cubre Bogotá (o el id no coincide en tarifas)
        }

        // --------------- 3 y 4. cotizar -----------------
        $quotes = [];

        foreach ($carriersWithCoverage as $carrier) {

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

            // volumétrico por separado (regla estándar por ahora)
            $unitVolWeight = $this->weightCalc->volumetricWeight($p_length, $p_width, $p_height, $kgVol['value_number']);
            $volWeight = $unitVolWeight * $qty;
            
            $billable = $this->weightCalc->billableWeight($p_weight, $volWeight);

            if ($type === CarrierRateTypeService::RATE_TYPE_PER_KG) {
                $shippingCost = $this->calculatePerKg($id_carrier, $id_city, $billable);
            } else {
                // Calculo por rango: Precio unitario * Cantidad
                $unitBillable = $this->weightCalc->billableWeight((float)$product->weight, $unitVolWeight);
                $unitCost = $this->calculateRange($id_carrier, $id_city, $unitBillable);
                
                if ($unitCost !== null) {
                    $shippingCost = $unitCost * $qty;
                } else {
                    $shippingCost = null;
                }
            }

            if ($shippingCost !== null) {
                $shippingCost = (float) $shippingCost;
                $packagingCost = $this->calculatePackagingCost($shippingCost);

                // Calcular valor total del paquete para seguro
                $totalValue = (float) $product->price * $qty;
                
                // Para el seguro, usar peso unitario
                $unitBillable = $this->weightCalc->billableWeight((float)$product->weight, $unitVolWeight);
                $insuranceCost = $this->calculateInsuranceCost($id_carrier, $type, $unitBillable, $totalValue);

                $totalPrice = $shippingCost + $packagingCost + $insuranceCost;

                $quotes[] = [
                    'carrier' => $carrier['name'],
                    'type' => $type,
                    'price' => $totalPrice, // Precio total para ordenamiento
                    'shipping_cost' => $shippingCost,
                    'packaging_cost' => $packagingCost,
                    'insurance_cost' => $insuranceCost,
                    'weight_real' => $p_weight,
                    'weight_vol' => $volWeight,
                    'weight_billable' => $billable,
                ];
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
     * Cotiza múltiples productos, manejando AMBOS tipos: agrupados e individuales.
     * 
     * Entrada: array de items con estructura:
     *   [
     *     {
     *       'id_product': int,
     *       'qty': int,
     *       'is_grouped': int (0 o 1)
     *     },
     *     ...
     *   ]
     * 
     * Proceso:
     * 1. Separa productos agrupados de individuales
     * 2. Para agrupados: usa ShippingGroupedPackageService para empaquetar
     * 3. Para individuales: cotiza directamente
     * 4. Retorna estructura unificada con paquetes agrupados e items individuales
     */
    public function quoteMultipleWithGrouped(array $items, $id_city)
    {
        $lang_id = Context::getContext()->language->id;
        $id_city = (int) $id_city;

        $groupedItems = [];
        $individualItems = [];

        // Paso 1: Separar por tipo
        foreach ($items as $item) {
            $id_product = isset($item['id_product']) ? (int) $item['id_product'] : 0;
            $qty = isset($item['qty']) ? max(1, (int) $item['qty']) : 1;
            $is_grouped = isset($item['is_grouped']) ? (int) $item['is_grouped'] : 0;

            if (!$id_product)
                continue;

            $product = new Product($id_product);
            if (!Validate::isLoadedObject($product))
                continue;

            // Obtener nombre en el idioma actual
            $productName = $this->getProductName($id_product, $lang_id);
            $productPrice = (float) $product->price;

            if ($is_grouped === 1) {
                $groupedItems[] = [
                    'id_product' => $id_product,
                    'quantity' => $qty,
                    'weight_unit' => (float) $product->weight,
                    'height' => (float) $product->height,
                    'width' => (float) $product->width,
                    'depth' => (float) $product->depth,
                    'name' => $productName,
                    'price' => $productPrice
                ];
            } else {
                $individualItems[] = [
                    'id_product' => $id_product,
                    'qty' => $qty,
                    'name' => $productName
                ];
            }
        }

        // Paso 2: Obtener factor volumétrico máximo
        $maxVolumetricFactor = $this->getMaxVolumetricFactor();

        $results = [
            'grouped_packages' => [],
            'individual_items' => [],
            'total_grouped' => 0.0,
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

            // Agregar productos que deben tratarse como individuales (>1 paquete)
            foreach ($packageResult['individual_products'] as $indvProduct) {
                $individualItems[] = [
                    'id_product' => $indvProduct['id_product'],
                    'qty' => $indvProduct['quantity'],
                    'name' => $this->getProductName($indvProduct['id_product'], $lang_id),
                    'reason' => 'from_grouped_exceeds_max'
                ];
            }
        }

        // Paso 4: Procesar productos individuales
        if (!empty($individualItems)) {
            foreach ($individualItems as $item) {
                $id_product = $item['id_product'];
                $qty = $item['qty'];

                $quotes = $this->quote($id_product, $id_city, $qty);

                $cheapest = null;
                if ($quotes && count($quotes) > 0) {
                    $cheapest = [
                        'carrier' => $quotes[0]['carrier'],
                        'price' => (float) $quotes[0]['price'],
                    ];
                    $results['total_individual'] += (float) $quotes[0]['price'];
                }

                $results['individual_items'][] = [
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

        // Paso 5: Calcular totales
        $results['grand_total'] = $results['total_grouped'] + $results['total_individual'];

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
     * Obtiene el factor volumétrico máximo configurado entre todas las transportadoras
     */
    private function getMaxVolumetricFactor()
    {
        $result = Db::getInstance()->getRow("
            SELECT MAX(CAST(value_number AS UNSIGNED)) as max_factor
            FROM " . _DB_PREFIX_ . "shipping_config
            WHERE name = 'Peso volumetrico'
        ");

        if ($result && isset($result['max_factor'])) {
            return (int) $result['max_factor'] ?: 5000;
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

        $min = Db::getInstance()->getRow("
            SELECT value_number
            FROM " . _DB_PREFIX_ . "shipping_config
            WHERE name = 'Flete minimo'
            AND id_carrier = " . (int) $id_carrier . "
        ");

        if (!$row)
            return null;

        $price = (float) $row['price'] * (float) $weight;

        if ($min && isset($min['value_number']) && $price < (float) $min['value_number']) {
            $price = (float) $min['value_number'];
        }

        return $price;
    }

    /**
     * Busca el rango donde cae el peso facturable.
     */
    private function calculateRange($id_carrier, $id_city, $weight)
    {
        $row = Db::getInstance()->getRow("
            SELECT price
            FROM " . _DB_PREFIX_ . "shipping_range_rate
            WHERE id_carrier = " . (int) $id_carrier . "
              AND id_city = " . (int) $id_city . "
              AND active = 1
              AND (min_weight < " . (float) $weight . " OR min_weight = 0)
              AND (max_weight = 0 OR max_weight >= " . (float) $weight . ")
            ORDER BY min_weight DESC
        ");

        if (!$row)
            return null;

        return (float) $row['price'];
    }
}