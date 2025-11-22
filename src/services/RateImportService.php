<?php

class RateImportService
{
    private $csv;
    private $norm;
    private $cityLookup;
    private $carrierType;

    public function __construct(
        CsvReader $csv,
        NormalizerService $norm,
        CityLookupService $cityLookup,
        CarrierRateTypeService $carrierType
    ) {
        $this->csv         = $csv;
        $this->norm        = $norm;
        $this->cityLookup  = $cityLookup;
        $this->carrierType = $carrierType;
    }

    /**
     * @return array{inserted:int, summary:string, stats:array}
     */
    public function import($id_carrier, $filePath)
    {
        $id_carrier = (int)$id_carrier;

        // 1. Validar que esté registrado
        if (!$this->carrierType->isRegistered($id_carrier)) {
            throw new Exception("Este carrier no está registrado en el módulo.");
        }

        // 2. Consultar su tipo registrado
        $expectedType = $this->carrierType->getType($id_carrier);

        if ($expectedType === CarrierRateTypeService::RATE_TYPE_PER_KG) {
            return $this->importPerKg($id_carrier, $filePath);
        }

        if ($expectedType === CarrierRateTypeService::RATE_TYPE_RANGE) {
            return $this->importRanges($id_carrier, $filePath);
        }

        throw new Exception("El carrier tiene un tipo de tarifa desconocido.");
    }


    private function importPerKg($id_carrier, $filePath)
    {
        // borrar previos (siempre debes borrar antes)
        Db::getInstance()->delete('shipping_per_kg_rate', 'id_carrier='.(int)$id_carrier);

        list($header, $rows) = $this->csv->read($filePath);

        $stats = [
            'rows_total'        => 0,
            'rows_city_missing' => 0,
            'price_zero'        => 0,
            'inserted'          => 0,
            'ignored'           => 0,
        ];
        $omittedCities = [];

        $cityIdx     = array_search('CIUDAD', $header);
        $stateIdx    = array_search('DEPARTAMENTO', $header);
        $priceIdx    = array_search('TARIFA X KG', $header);
        if ($priceIdx === false) $priceIdx = 3;

        $deliveryIdx = array_search('TIEMPOS DE ENTREGA', $header);

        foreach ($rows as $row) {
            $stats['rows_total']++;

            if (!isset($row[$cityIdx], $row[$stateIdx], $row[$priceIdx])) {
                $stats['ignored']++;
                continue;
            }

            $city  = trim($row[$cityIdx]);
            $state = trim($row[$stateIdx]);
            $price = $this->norm->normalizeNumber($row[$priceIdx]);

            $id_city = $this->cityLookup->getIdCityByNameAndState($city, $state);
            if (!$id_city) {
                $stats['rows_city_missing']++;
                $omittedCities[] = $city;
                continue;
            }

            if ($price <= 0) {
                $stats['price_zero']++;
                continue;
            }

            $delivery_raw  = ($deliveryIdx !== false && isset($row[$deliveryIdx])) ? $row[$deliveryIdx] : null;
            $delivery_time = $this->norm->normalizeDelivery($delivery_raw);

            $ok = Db::getInstance()->insert('shipping_per_kg_rate', [
                'id_carrier'    => $id_carrier,
                'id_city'       => (int)$id_city,
                'price'         => (float)$price,
                'delivery_time' => $delivery_time,
                'active'        => 1,
            ]);

            if ($ok) $stats['inserted']++;
            else     $stats['ignored']++;
        }

        // 🔥 IMPORTANTE: YA NO cambiamos el tipo
        // $this->carrierType->setCarrierRateType() YA NO VA AQUÍ

        $summary = $this->buildSummaryHtml("por kilo", $stats, $omittedCities);

        return ['inserted' => $stats['inserted'], 'summary' => $summary, 'stats' => $stats];
    }

    private function importRanges($id_carrier, $filePath)
    {
        Db::getInstance()->delete('shipping_range_rate', 'id_carrier='.(int)$id_carrier);

        list($header, $rows) = $this->csv->read($filePath);

        $stats = [
            'rows_total'        => 0,
            'rows_city_missing' => 0,
            'range_inserted'    => 0,
            'range_price_zero'  => 0,
            'range_bad_header'  => 0,
            'range_ignored'     => 0,
        ];
        $omittedCities = [];

        $cityIdx     = array_search('CIUDAD', $header);
        $stateIdx    = array_search('DEPARTAMENTO', $header);
        $deliveryIdx = array_search('TIEMPOS DE ENTREGA', $header);
        $packIdx     = array_search('PAQUETEO', $header);
        $massIdx     = array_search('MASIVO', $header);

        foreach ($rows as $row) {
            $stats['rows_total']++;

            if (!isset($row[$cityIdx], $row[$stateIdx])) {
                continue;
            }

            $city  = trim($row[$cityIdx]);
            $state = trim($row[$stateIdx]);

            $id_city = $this->cityLookup->getIdCityByNameAndState($city, $state);
            if (!$id_city) {
                $stats['rows_city_missing']++;
                $omittedCities[] = $city;
                continue;
            }

            $delivery_raw  = ($deliveryIdx !== false && isset($row[$deliveryIdx])) ? $row[$deliveryIdx] : null;
            $delivery_time = $this->norm->normalizeDelivery($delivery_raw);

            $pack_raw = ($packIdx !== false && isset($row[$packIdx])) ? $row[$packIdx] : null;
            $apply_packaging = $this->norm->normalizeBooleanFlag($pack_raw);

            $mass_raw = ($massIdx !== false && isset($row[$massIdx])) ? $row[$massIdx] : null;
            $apply_massive = $this->norm->normalizeBooleanFlag($mass_raw);

            foreach ($header as $colIdx => $colName) {

                if (!preg_match('/^(RANGO_)?(\d+)[\-_](\d+|INF)$/', $colName, $m)) {
                    if ($colIdx > 2) $stats['range_bad_header']++;
                    continue;
                }

                $min = (float)$m[2];
                $max = ($m[3] === 'INF') ? null : (float)$m[3];

                $rawPrice = isset($row[$colIdx]) ? $row[$colIdx] : '';
                $price = $this->norm->normalizeNumber($rawPrice);

                if ($price <= 0) {
                    $stats['range_price_zero']++;
                    continue;
                }

                $ok = Db::getInstance()->insert('shipping_range_rate', [
                    'id_carrier'      => $id_carrier,
                    'id_city'         => (int)$id_city,
                    'min_weight'      => $min,
                    'max_weight'      => $max,
                    'price'           => $price,
                    'delivery_time'   => $delivery_time,
                    'apply_packaging' => $apply_packaging,
                    'apply_massive'   => $apply_massive,
                    'active'          => 1,
                ]);

                if ($ok) $stats['range_inserted']++;
                else     $stats['range_ignored']++;
            }
        }

        $summary = $this->buildSummaryHtml("por rangos", $stats, $omittedCities);

        return ['inserted' => $stats['range_inserted'], 'summary' => $summary, 'stats' => $stats];
    }
}