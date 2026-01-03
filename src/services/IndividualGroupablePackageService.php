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
    private $weightCalc;

    /**
     * Constructor
     */
    public function __construct($weightCalc = null)
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
        
        // Inyectar WeightCalculatorService
        if ($weightCalc) {
            $this->weightCalc = $weightCalc;
        } else {
            require_once _PS_MODULE_DIR_ . 'shipping_calculator/src/services/WeightCalculatorService.php';
            $this->weightCalc = new WeightCalculatorService();
        }
    }

    /**
     * Procesa productos individuales agrupables y genera paquetes.
     * Los paquetes se arman con restricción de max_units_per_package Y peso máximo.
     * Se usa el factor volumétrico MÁXIMO para que los paquetes sean suficientemente pequeños
     * para que TODAS las transportadoras puedan cotizarlos.
     *
     * @param array $individualGroupableProducts - Lista de productos
     * @param int $maxVolumetricFactor - Factor volumétrico máximo (más alto)
     *
     * @return array - Estructura con 'individual_packages' y 'oversized_products'
     */
    public function buildIndividualPackages(array $individualGroupableProducts, $maxVolumetricFactor)
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

            // Calcular peso volumétrico unitario usando WeightCalculatorService y factor MÁXIMO
            $volumetricWeightUnit = $this->weightCalc->volumetricWeight($depth, $width, $height, $maxVolumetricFactor);
            
            // Peso facturable unitario (el mayor entre real y volumétrico)
            $billableWeightUnit = $this->weightCalc->billableWeight($weightUnit, $volumetricWeightUnit);
            
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
                // Calcular cuántas unidades caben por PESO (usando factor máximo)
                $unitsByWeight = (int)floor($this->maxWeightPerPackage / $billableWeightUnit);
                
                // Aplicar restricción de max_units_per_package
                $unitsForThisPackage = min($unitsByWeight, $maxUnitsPerPackage, $remainingUnits);
                
                // Crear paquete
                $packages[] = [
                    'package_id' => 'individual_grouped_' . $packageIdCounter++,
                    'package_type' => 'individual_grouped',
                    'id_product' => $id_product,
                    'product_name' => $name,
                    'units_in_package' => $unitsForThisPackage,
                    'real_weight_unit' => $weightUnit,
                    'volumetric_weight_unit' => $volumetricWeightUnit,
                    'total_weight_real' => $weightUnit * $unitsForThisPackage,
                    'total_weight_volumetric' => $volumetricWeightUnit * $unitsForThisPackage,
                    'total_weight' => $billableWeightUnit * $unitsForThisPackage,
                    'height' => $height,
                    'width' => $width,
                    'depth' => $depth,
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

}
