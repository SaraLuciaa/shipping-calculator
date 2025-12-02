<?php

/**
 * ShippingGroupedPackageService
 *
 * Maneja la lógica de agrupación de productos marcados como "agrupados"
 * en paquetes de hasta 60 kg usando una estrategia de mejor ajuste (best-fit).
 *
 * Reglas:
 * 1. Solo procesa productos con is_grouped=1
 * 2. Calcula peso_volumetrico por unidad y peso_max_unidad = max(peso_real, peso_volumetrico)
 * 3. Si un producto genera >1 paquete de 60kg, se marca para tratamiento individual
 * 4. Los productos que generan 1 paquete se agrupan usando best-fit en paquetes de 60kg máximo
 * 5. Retorna dos listas: paquetes agrupados y productos que deben ser tratados como individuales
 */
class ShippingGroupedPackageService
{
    private $maxWeightPerPackage = 60.0;  // kg
    private $maxVolumetricFactor = 0.0;  // Se obtiene del máximo factor por transportadora

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * Procesa una lista de productos agrupados y genera paquetes.
     *
     * @param array $groupedProducts - Lista de productos con estructura:
     *   [
     *     {
     *       'id_product': int,
     *       'quantity': int,
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
     * Implementa best-fit packing permitiendo dividir productos
     * 
     * Regla: Las unidades de un mismo producto se pueden dividir en múltiples paquetes.
     * Se colocan tantas unidades como quepan en cada paquete hasta completar la cantidad.
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

            // Distribuir unidades en paquetes
            $remainingUnits = $quantity;

            while ($remainingUnits > 0) {
                // Buscar el mejor paquete donde quepan al menos 1 unidad
                $bestPackageIdx = $this->findBestFitPackageForUnits(
                    $packages,
                    $unitWeight
                );

                if ($bestPackageIdx !== null) {
                    // Calcular cuántas unidades caben en el espacio disponible
                    $availableSpace = $this->maxWeightPerPackage - $packages[$bestPackageIdx]['total_weight'];
                    $unitsThatFit = (int) floor($availableSpace / $unitWeight);
                    $unitsToAdd = min($unitsThatFit, $remainingUnits);

                    // Verificar si ya existe este producto en el paquete
                    $existingItemIdx = null;
                    foreach ($packages[$bestPackageIdx]['items'] as $idx => $item) {
                        if ($item['id_product'] === $id_product) {
                            $existingItemIdx = $idx;
                            break;
                        }
                    }

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
                    // Crear nuevo paquete
                    $unitsForNewPackage = min(
                        (int) floor($this->maxWeightPerPackage / $unitWeight),
                        $remainingUnits
                    );

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

        return [
            'grouped_packages' => $packages,
            'individual_products' => $forIndividualTreatment
        ];
    }

    /**
     * Busca el mejor paquete donde quepa al menos una unidad del producto
     *
     * @param array $packages - Lista de paquetes actuales
     * @param float $unitWeight - Peso de una unidad del producto
     *
     * @return int|null - Índice del mejor paquete, o null si no cabe en ninguno
     */
    private function findBestFitPackageForUnits(array $packages, $unitWeight)
    {
        $bestIdx = null;
        $bestFreeSpace = PHP_INT_MAX;

        foreach ($packages as $idx => $package) {
            $availableSpace = $this->maxWeightPerPackage - $package['total_weight'];

            // ¿Cabe al menos una unidad?
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
