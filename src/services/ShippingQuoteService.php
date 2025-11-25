<?php

class ShippingQuoteService
{
    private $carrierRegistry;
    private $weightCalc;

    public function __construct(
        CarrierRegistryService $carrierRegistry,
        WeightCalculatorService $weightCalc
    ) {
        $this->carrierRegistry = $carrierRegistry;
        $this->weightCalc      = $weightCalc;
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
        $id_product = (int)$id_product;
        $id_city    = (int)$id_city;
        $qty        = max(1, (int)$qty);

        $product = new Product($id_product);

        if (!Validate::isLoadedObject($product)) {
            throw new Exception("Producto no encontrado.");
        }

        // --------------- 2. pesos producto -----------------
        $p_weight = (float)$product->weight * $qty;   // kg
        $p_width  = (float)$product->width;          // cm
        $p_height = (float)$product->height;         // cm
        $p_length = (float)$product->depth;          // cm

        // --------------- 1. carriers con cobertura -----------------
        $carriersWithCoverage = $this->getCarriersWithCityCoverage($id_city);

        if (!$carriersWithCoverage) {
            return []; // nadie cubre Bogotá (o el id no coincide en tarifas)
        }

        // --------------- 3 y 4. cotizar -----------------
        $quotes = [];

        foreach ($carriersWithCoverage as $carrier) {
            
            $id_carrier = (int)$carrier['id_carrier'];
            $type       = $carrier['type'];

            $kgVol = Db::getInstance()->getRow("
                SELECT value_number
                FROM "._DB_PREFIX_."shipping_config
                WHERE name = 'Peso volumetrico'
                  AND id_carrier = ".(int)$id_carrier."
            ");

            if (!$kgVol || !isset($kgVol['value_number'])) {
                $kgVol = ['value_number' => 5000];
            } else {
                $kgVol['value_number'] = (int)$kgVol['value_number'];
            }

            // volumétrico por separado (regla estándar por ahora)
            $volWeight   = $this->weightCalc->volumetricWeight($p_length, $p_width, $p_height, $kgVol['value_number']) * $qty;
            $billable    = $this->weightCalc->billableWeight($p_weight, $volWeight);

            if ($type === CarrierRateTypeService::RATE_TYPE_PER_KG) {
                $price = $this->calculatePerKg($id_carrier, $id_city, $billable);
            } else {
                $price = $this->calculateRange($id_carrier, $id_city, $billable);
            }

            if ($price !== null) {
                $quotes[] = [
                    'carrier'        => $carrier['name'],
                    'type'           => $type,
                    'price'          => (float)$price,
                    'weight_real'    => $p_weight,
                    'weight_vol'     => $volWeight,
                    'weight_billable'=> $billable,
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
            $id_product = isset($it['id_product']) ? (int)$it['id_product'] : 0;
            $qty = isset($it['qty']) ? max(1, (int)$it['qty']) : 1;

            if (!$id_product) {
                continue;
            }

            // verificar si es agrupado
            $row = Db::getInstance()->getRow("SELECT is_grouped FROM "._DB_PREFIX_."shipping_product WHERE id_product = ".(int)$id_product);
            $is_grouped = $row ? (int)$row['is_grouped'] : 0;

            $productRow = Db::getInstance()->getRow("SELECT id_product, name FROM "._DB_PREFIX_."product_lang WHERE id_product = ".(int)$id_product." AND id_lang = ".(int)$lang_id);

            if ($is_grouped === 1) {
                $results[] = [
                    'id_product' => $id_product,
                    'name'       => $productRow ? $productRow['name'] : '',
                    'qty'        => $qty,
                    'is_grouped' => 1,
                    'cheapest'   => null,
                    'quotes'     => [],
                ];
                continue;
            }

            // reutilizar la cotización por producto
            $quotes = $this->quote($id_product, $id_city, $qty);

            $cheapest = null;
            if ($quotes && count($quotes) > 0) {
                $cheapest = [
                    'carrier' => $quotes[0]['carrier'],
                    'price'   => (float)$quotes[0]['price'],
                ];
                $total += (float)$quotes[0]['price'];
            }

            $results[] = [
                'id_product' => $id_product,
                'name'       => $productRow ? $productRow['name'] : '',
                'qty'        => $qty,
                'is_grouped' => 0,
                'cheapest'   => $cheapest,
                'quotes'     => $quotes,
            ];
        }

        return ['items' => $results, 'total' => (float)$total];
    }

    /**
     * Devuelve SOLO carriers registrados que tienen filas
     * para esta ciudad en alguna tabla de tarifas.
     */
    private function getCarriersWithCityCoverage($id_city)
    {
        $registered = $this->carrierRegistry->getAllRegistered(); 
        if (!$registered) return [];

        // ids con cobertura per kg
        $perKg = Db::getInstance()->executeS("
            SELECT DISTINCT id_carrier
            FROM "._DB_PREFIX_."shipping_per_kg_rate
            WHERE id_city = ".(int)$id_city."
              AND active = 1
        ");

        // ids con cobertura rangos
        $ranges = Db::getInstance()->executeS("
            SELECT DISTINCT id_carrier
            FROM "._DB_PREFIX_."shipping_range_rate
            WHERE id_city = ".(int)$id_city."
              AND active = 1
        ");

        $coveredIds = [];
        foreach ($perKg as $r)   $coveredIds[(int)$r['id_carrier']] = true;
        foreach ($ranges as $r)  $coveredIds[(int)$r['id_carrier']] = true;

        // filtrar solo los registrados + con cobertura
        $result = [];
        foreach ($registered as $c) {
            $id_carrier = (int)$c['id_carrier'];
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
            FROM "._DB_PREFIX_."shipping_per_kg_rate
            WHERE id_carrier = ".(int)$id_carrier."
              AND id_city = ".(int)$id_city."
              AND active = 1
        ");

        if (!$row) return null;

        return (float)$row['price'] * (float)$weight;
    }

    /**
     * Busca el rango donde cae el peso facturable.
     */
    private function calculateRange($id_carrier, $id_city, $weight)
    {
        $row = Db::getInstance()->getRow("
            SELECT price
            FROM "._DB_PREFIX_."shipping_range_rate
            WHERE id_carrier = ".(int)$id_carrier."
              AND id_city = ".(int)$id_city."
              AND active = 1
              AND min_weight <= ".(float)$weight."
              AND (max_weight = 0 OR max_weight >= ".(float)$weight.")
            ORDER BY min_weight DESC
        ");

        if (!$row) return null;

        return (float)$row['price'];
    }
}