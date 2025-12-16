<?php

/**
 * ShippingGroupedPackageService
 *
 * Maneja la lógica de agrupación de productos marcados como "agrupados"
 * en paquetes usando una estrategia de mejor ajuste (best-fit).
 *
 * Reglas:
 * 1. Solo procesa productos con is_grouped=1
 * 2. Calcula peso_volumetrico por unidad y peso_max_unidad = max(peso_real, peso_volumetrico)
 * 3. Si un producto excede el peso máximo configurado, se marca para tratamiento individual
 * 4. Los productos que generan 1 paquete se agrupan usando best-fit
 * 5. Retorna dos listas: paquetes agrupados y productos que deben ser tratados como individuales
 */
class ShippingGroupedPackageService
{
    private $maxWeightPerPackage = 60.0;  // kg (default, se sobrescribe con config)
    private $maxVolumetricFactor = 0.0;  // Se obtiene del máximo factor por transportadora

    /**
     * Constructor
     */
    public function __construct()
    {
        // Obtener peso máximo configurado desde BD
        $config = Db::getInstance()->getRow("
            SELECT value_number
            FROM "._DB_PREFIX_."shipping_config
            WHERE name = 'Peso maximo paquete'
        ");

        if ($config && isset($config['value_number'])) {
            $this->maxWeightPerPackage = (float)$config['value_number'];
        }
    }

    /**
     * Procesa una lista de productos agrupados y genera paquetes.
     *
     * @param array $groupedProducts - Lista de productos con estructura:
     *   [
     *     {
     *       'id_product': int,
     *       'quantity': int,
     *       'max_units_per_package': int (0 = sin límite),
     *       'weight_unit': float (kg),
     *       'height': float (cm),
     *       'width': float (cm),
     *       'depth': float (cm),
     *       'price': float
     *     },
     *     ...
     *   ]
     * @param float $volumetricFactor - Factor volumétrico máximo para usar en cálculos
     *
     * @return array - Estructura:
     *   {
     *     'grouped_packages': [
     *       {
     *         'package_id': string,
     *         'package_type': 'grouped',
     *         'total_weight': float,
     *         'items': [
     *           {'id_product': int, 'units_in_package': int, 'price': float},
     *           ...
     *         ]
     *       },
     *       ...
     *     ],
     *     'individual_products': [
     *       {
     *         'id_product': int,
     *         'quantity': int,
     *         'reason': 'exceeds_max_packages'
     *       },
     *       ...
     *     ]
     *   }
     */
    public function buildGroupedPackages(array $groupedProducts, $volumetricFactor = 5000.0)
    {
        $this->maxVolumetricFactor = (float) $volumetricFactor;

        // Paso 1: Calcular peso máximo por unidad para TODOS los productos
        $productMetrics = [];

        foreach ($groupedProducts as $product) {
            $id_product = (int) $product['id_product'];
            $quantity = max(1, (int) $product['quantity']);
            $maxUnitsPerPackage = isset($product['max_units_per_package']) ? (int)$product['max_units_per_package'] : 0;
            $weightUnit = (float) $product['weight_unit'];
            $height = (float) $product['height'];
            $width = (float) $product['width'];
            $depth = (float) $product['depth'];
            $price = isset($product['price']) ? (float) $product['price'] : 0.0;

            // Cálculo de peso volumétrico unitario
            $volumetricWeightUnit = $this->calculateVolumetricWeight($height, $width, $depth);

            // Peso máximo por unidad (el mayor entre real y volumétrico)
            $maxWeightUnit = max($weightUnit, $volumetricWeightUnit);

            // Peso total del producto (todas sus unidades)
            $totalProductWeight = $maxWeightUnit * $quantity;

            $productMetrics[] = [
                'id_product' => $id_product,
                'quantity' => $quantity,
                'max_units_per_package' => $maxUnitsPerPackage,
                'weight_unit' => $maxWeightUnit,
                'total_weight' => $totalProductWeight,
                'volumetric_weight_unit' => $volumetricWeightUnit,
                'real_weight_unit' => $weightUnit,
                'price' => $price
            ];
        }

        // Paso 2: Aplicar estrategia best-fit para agrupar TODOS los productos
        $results = $this->bestFitPackingWithIndividuals($productMetrics);

        return $results;
    }

    /**
     * Implementa best-fit packing con regla híbrida
     * 
     * Regla:
     * - Si el peso total de un producto < 60kg: Todas las unidades van en UN SOLO paquete.
     * - Si el peso total de un producto >= 60kg: Se divide en múltiples paquetes de 60kg.
     */
    private function bestFitPackingWithIndividuals(array $products)
    {
        $packages = [];
        $packageIdCounter = 1;
        $forIndividualTreatment = [];

        // Crear un mapa de id_product => metrics para acceso rápido
        $metricsMap = [];
        foreach ($products as $product) {
            $metricsMap[$product['id_product']] = $product;
        }

        foreach ($products as $product) {
            $id_product = $product['id_product'];
            $quantity = $product['quantity'];
            $unitWeight = $product['weight_unit'];
            $totalWeight = $product['total_weight'];

            // Si una SOLA UNIDAD pesa más de 60kg, marcar para tratamiento individual
            if ($unitWeight > $this->maxWeightPerPackage) {
                $forIndividualTreatment[] = [
                    'id_product' => $id_product,
                    'quantity' => $quantity,
                    'reason' => 'unit_exceeds_max_weight',
                    'unit_weight' => $unitWeight
                ];
                continue;
            }

            // Obtener max_units_per_package para este producto
            $maxUnitsPerPackage = $product['max_units_per_package'];

            // REGLA: Verificar si TODAS las unidades caben respetando peso y max_units
            $weightFitsAll = ($totalWeight < $this->maxWeightPerPackage);
            $unitsFitAll = true;
            if ($maxUnitsPerPackage > 0) {
                $unitsFitAll = ($quantity <= $maxUnitsPerPackage);
            }
            
            $canFitAll = $weightFitsAll && $unitsFitAll;
            
            if ($canFitAll) {
                // Buscar el mejor paquete existente donde quepa el producto completo
                $bestPackageIdx = $this->findBestFitPackageForProduct($packages, $totalWeight, $id_product, $quantity, $maxUnitsPerPackage);

                if ($bestPackageIdx !== null) {
                    // Cabe completo en un paquete existente
                    $packages[$bestPackageIdx]['items'][] = [
                        'id_product' => $id_product,
                        'units_in_package' => $quantity,
                        'real_weight_unit' => $metricsMap[$id_product]['real_weight_unit'],
                        'volumetric_weight_unit' => $metricsMap[$id_product]['volumetric_weight_unit'],
                        'price' => $metricsMap[$id_product]['price']
                    ];
                    $packages[$bestPackageIdx]['total_weight'] += $totalWeight;
                } else {
                    // Crear nuevo paquete para el producto completo
                    $packages[] = [
                        'package_id' => 'grouped_' . $packageIdCounter++,
                        'package_type' => 'grouped',
                        'total_weight' => $totalWeight,
                        'items' => [
                            [
                                'id_product' => $id_product,
                                'units_in_package' => $quantity,
                                'real_weight_unit' => $metricsMap[$id_product]['real_weight_unit'],
                                'volumetric_weight_unit' => $metricsMap[$id_product]['volumetric_weight_unit'],
                                'price' => $metricsMap[$id_product]['price']
                            ]
                        ]
                    ];
                }
            } else {
                // REGLA: Dividir en paquetes respetando peso y max_units_per_package
                $remainingUnits = $quantity;

                while ($remainingUnits > 0) {
                    // Buscar el mejor paquete donde quepan al menos 1 unidad
                    $bestPackageIdx = $this->findBestFitPackageForUnits($packages, $unitWeight, $id_product, $maxUnitsPerPackage);

                    if ($bestPackageIdx !== null) {
                        // Calcular cuántas unidades caben por peso
                        $availableSpace = $this->maxWeightPerPackage - $packages[$bestPackageIdx]['total_weight'];
                        $unitsThatFitByWeight = (int) floor($availableSpace / $unitWeight);

                        // Verificar si ya existe este producto en el paquete
                        $existingItemIdx = null;
                        $currentUnitsInPackage = 0;
                        foreach ($packages[$bestPackageIdx]['items'] as $idx => $item) {
                            if ($item['id_product'] === $id_product) {
                                $existingItemIdx = $idx;
                                $currentUnitsInPackage = $item['units_in_package'];
                                break;
                            }
                        }

                        // Calcular cuántas unidades más caben respetando max_units_per_package
                        $unitsThatFit = $unitsThatFitByWeight;
                        if ($maxUnitsPerPackage > 0) {
                            $maxAdditional = $maxUnitsPerPackage - $currentUnitsInPackage;
                            $unitsThatFit = min($unitsThatFit, $maxAdditional);
                        }

                        $unitsToAdd = min($unitsThatFit, $remainingUnits);

                        if ($unitsToAdd > 0) {
                            if ($existingItemIdx !== null) {
                                // Incrementar unidades del producto existente
                                $packages[$bestPackageIdx]['items'][$existingItemIdx]['units_in_package'] += $unitsToAdd;
                            } else {
                                // Agregar nuevo producto al paquete
                                $packages[$bestPackageIdx]['items'][] = [
                                    'id_product' => $id_product,
                                    'units_in_package' => $unitsToAdd,
                                    'real_weight_unit' => $metricsMap[$id_product]['real_weight_unit'],
                                    'volumetric_weight_unit' => $metricsMap[$id_product]['volumetric_weight_unit'],
                                    'price' => $metricsMap[$id_product]['price']
                                ];
                            }

                            $packages[$bestPackageIdx]['total_weight'] += $unitsToAdd * $unitWeight;
                            $remainingUnits -= $unitsToAdd;
                        } else {
                            // No caben más unidades, forzar creación de nuevo paquete
                            $bestPackageIdx = null;
                        }
                    }
                    
                    if ($bestPackageIdx === null) {
                        // Crear nuevo paquete
                        $unitsByWeight = (int) floor($this->maxWeightPerPackage / $unitWeight);
                        $unitsForNewPackage = min($unitsByWeight, $remainingUnits);
                        
                        // Aplicar restricción de max_units_per_package
                        if ($maxUnitsPerPackage > 0) {
                            $unitsForNewPackage = min($unitsForNewPackage, $maxUnitsPerPackage);
                        }
                        
                        $packages[] = [
                            'package_id' => 'grouped_' . $packageIdCounter++,
                            'package_type' => 'grouped',
                            'total_weight' => $unitsForNewPackage * $unitWeight,
                            'items' => [
                                [
                                    'id_product' => $id_product,
                                    'units_in_package' => $unitsForNewPackage,
                                    'real_weight_unit' => $metricsMap[$id_product]['real_weight_unit'],
                                    'volumetric_weight_unit' => $metricsMap[$id_product]['volumetric_weight_unit'],
                                    'price' => $metricsMap[$id_product]['price']
                                ]
                            ]
                        ];

                        $remainingUnits -= $unitsForNewPackage;
                    }
                }
            }
        }

        return [
            'grouped_packages' => $packages,
            'individual_products' => $forIndividualTreatment
        ];
    }

    /**
     * Busca el mejor paquete donde quepa UN PRODUCTO COMPLETO sin dividirlo
     *
     * @param array $packages - Lista de paquetes actuales
     * @param float $productTotalWeight - Peso TOTAL del producto (todas sus unidades)
     * @param int $id_product - ID del producto a agregar
     * @param int $quantity - Cantidad de unidades
     * @param int $maxUnitsPerPackage - Máximo de unidades permitidas (0 = sin límite)
     *
     * @return int|null - Índice del mejor paquete, o null si no cabe en ninguno
     */
    private function findBestFitPackageForProduct(array $packages, $productTotalWeight, $id_product, $quantity, $maxUnitsPerPackage)
    {
        $bestIdx = null;
        $bestFreeSpace = PHP_INT_MAX;

        foreach ($packages as $idx => $package) {
            $availableSpace = $this->maxWeightPerPackage - $package['total_weight'];

            // Verificar si ya existe este producto en el paquete
            $currentUnits = 0;
            foreach ($package['items'] as $item) {
                if ($item['id_product'] === $id_product) {
                    $currentUnits = $item['units_in_package'];
                    break;
                }
            }

            // Verificar restricción de max_units_per_package
            if ($maxUnitsPerPackage > 0 && ($currentUnits + $quantity) > $maxUnitsPerPackage) {
                continue; // No caben todas las unidades
            }

            // ¿Cabe el PRODUCTO COMPLETO por peso?
            if ($availableSpace >= $productTotalWeight) {
                // Espacio que quedará después de agregar el producto completo
                $spaceAfter = $availableSpace - $productTotalWeight;

                // ¿Es mejor ajuste que el anterior?
                if ($spaceAfter < $bestFreeSpace) {
                    $bestFreeSpace = $spaceAfter;
                    $bestIdx = $idx;
                }
            }
        }

        return $bestIdx;
    }

    /**
     * Busca el mejor paquete donde quepa al menos una unidad del producto
     *
     * @param array $packages - Lista de paquetes actuales
     * @param float $unitWeight - Peso de una unidad del producto
     * @param int $id_product - ID del producto
     * @param int $maxUnitsPerPackage - Máximo de unidades permitidas (0 = sin límite)
     *
     * @return int|null - Índice del mejor paquete, o null si no cabe en ninguno
     */
    private function findBestFitPackageForUnits(array $packages, $unitWeight, $id_product, $maxUnitsPerPackage)
    {
        $bestIdx = null;
        $bestFreeSpace = PHP_INT_MAX;

        foreach ($packages as $idx => $package) {
            $availableSpace = $this->maxWeightPerPackage - $package['total_weight'];

            // Verificar si ya existe este producto en el paquete
            $currentUnits = 0;
            foreach ($package['items'] as $item) {
                if ($item['id_product'] === $id_product) {
                    $currentUnits = $item['units_in_package'];
                    break;
                }
            }

            // Verificar restricción de max_units_per_package
            if ($maxUnitsPerPackage > 0 && $currentUnits >= $maxUnitsPerPackage) {
                continue; // Ya alcanzó el límite de unidades
            }

            // ¿Cabe al menos una unidad por peso?
            if ($availableSpace >= $unitWeight) {
                // ¿Es mejor ajuste que el anterior?
                if ($availableSpace < $bestFreeSpace) {
                    $bestFreeSpace = $availableSpace;
                    $bestIdx = $idx;
                }
            }
        }

        return $bestIdx;
    }

    /**
     * Calcula el peso volumétrico basado en dimensiones.
     *
     * @param float $height - Alto en cm
     * @param float $width - Ancho en cm
     * @param float $depth - Profundidad en cm
     *
     * @return float - Peso volumétrico en kg
     */
    private function calculateVolumetricWeight($height, $width, $depth)
    {
        $volume = ($height / 100) * ($width / 100) * ($depth / 100); // m³
        return $volume * $this->maxVolumetricFactor; // kg
    }
}
