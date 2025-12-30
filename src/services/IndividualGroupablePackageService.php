<?php

/**
 * IndividualGroupablePackageService
 *
 * Maneja la lógica de agrupación de productos INDIVIDUALES que pueden agruparse entre sí mismos.
 * 
 * Reglas:
 * 1. Solo procesa productos con is_grouped=0 Y max_units_per_package > 0
 * 2. Cada producto se agrupa SOLO con unidades del MISMO producto (no con otros)
 * 3. Restricción de peso: Peso total del paquete ≤ peso máximo configurado
 * 4. Restricción de unidades: Máximo max_units_per_package unidades por paquete
 * 5. Si una unidad excede el peso máximo, se marca para cotización especial
 */
class IndividualGroupablePackageService
{
    private $maxWeightPerPackage = 60.0;  // kg (se obtiene de config)

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
     * Procesa productos individuales agrupables y genera paquetes.
     *
     * @param array $individualGroupableProducts - Lista de productos con estructura:
     *   [
     *     {
     *       'id_product': int,
     *       'quantity': int,
     *       'max_units_per_package': int,
     *       'weight_unit': float (kg),
     *       'height': float (cm),
     *       'width': float (cm),
     *       'depth': float (cm),
     *       'name': string,
     *       'price': float
     *     },
     *     ...
     *   ]
     * @param float $volumetricFactor - Factor volumétrico máximo
     *
     * @return array - Estructura:
     *   {
     *     'individual_packages': [
     *       {
     *         'package_id': string,
     *         'package_type': 'individual_grouped',
     *         'id_product': int,
     *         'product_name': string,
     *         'total_weight': float,
     *         'units_in_package': int,
     *         'price_per_unit': float
     *       },
     *       ...
     *     ],
     *     'oversized_products': [
     *       {
     *         'id_product': int,
     *         'quantity': int,
     *         'reason': 'unit_exceeds_max_weight',
     *         'unit_weight': float
     *       },
     *       ...
     *     ]
     *   }
     */
    public function buildIndividualPackages(array $individualGroupableProducts, $volumetricFactor = 5000.0)
    {
        $packages = [];
        $packageIdCounter = 1;
        $oversizedProducts = [];
        
        foreach ($individualGroupableProducts as $product) {
            $id_product = (int)$product['id_product'];
            $quantity = (int)$product['quantity'];
            $maxUnitsPerPackage = (int)$product['max_units_per_package'];
            $weightUnit = (float)$product['weight_unit'];
            $height = (float)$product['height'];
            $width = (float)$product['width'];
            $depth = (float)$product['depth'];
            $name = $product['name'];
            $price = isset($product['price']) ? (float)$product['price'] : 0.0;

            // Calcular peso volumétrico unitario
            $volumetricWeightUnit = $this->calculateVolumetricWeight($height, $width, $depth, $volumetricFactor);

            // Peso máximo por unidad (el mayor entre real y volumétrico)
            $billableWeightUnit = max($weightUnit, $volumetricWeightUnit);
            
            // CASO ESPECIAL: Si una sola unidad excede el peso máximo
            if ($billableWeightUnit > $this->maxWeightPerPackage) {
                $oversizedProducts[] = [
                    'id_product' => $id_product,
                    'quantity' => $quantity,
                    'reason' => 'unit_exceeds_max_weight',
                    'unit_weight' => $billableWeightUnit
                ];
                continue;
            }

            // Dividir unidades en paquetes según restricciones
            $remainingUnits = $quantity;

            while ($remainingUnits > 0) {
                // Calcular cuántas unidades caben por peso
                $unitsByWeight = (int)floor($this->maxWeightPerPackage / $billableWeightUnit);
                
                // Aplicar restricción de max_units_per_package
                $unitsForThisPackage = min($unitsByWeight, $maxUnitsPerPackage, $remainingUnits);
                
                // Crear paquete
                $packages[] = [
                    'package_id' => 'individual_grouped_' . $packageIdCounter++,
                    'package_type' => 'individual_grouped',
                    'id_product' => $id_product,
                    'product_name' => $name,
                    'total_weight' => $unitsForThisPackage * $billableWeightUnit,
                    'units_in_package' => $unitsForThisPackage,
                    'real_weight_unit' => $weightUnit,
                    'volumetric_weight_unit' => $volumetricWeightUnit,
                    'price_per_unit' => $price
                ];

                $remainingUnits -= $unitsForThisPackage;
            }
        }

        return [
            'individual_packages' => $packages,
            'oversized_products' => $oversizedProducts
        ];
    }

    /**
     * Calcula el peso volumétrico de un producto
     */
    private function calculateVolumetricWeight($height, $width, $depth, $factor)
    {
        if ($height <= 0 || $width <= 0 || $depth <= 0 || $factor <= 0) {
            return 0.0;
        }

        // (cm³) / factor = kg
        return (($height/100) * ($width/100) * ($depth/100)) / $factor;
    }
}
