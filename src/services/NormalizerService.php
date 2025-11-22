<?php

class NormalizerService
{
    public function normalizeNumber($value)
    {
        $value = trim((string)$value);
        if ($value === '') {
            return 0;
        }

        $value = str_replace(' ', '', $value);

        // Si tiene , y . asumimos formato 1.234,56
        if (strpos($value, ',') !== false && strpos($value, '.') !== false) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } else {
            // si no, quitamos separadores de miles y dejamos punto decimal
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        return (float)$value;
    }

    /** si no es numérico => null */
    public function normalizeDelivery($raw)
    {
        $raw = trim(Tools::strtoupper((string)$raw));
        return is_numeric($raw) ? (int)$raw : null;
    }

    /**
     * regla pedida:
     *  - "APLICA" => true(1)
     *  - "NO APLICA" => false(0)
     *  - diferente => null
     */
    public function normalizeBooleanFlag($raw)
    {
        $raw = trim(Tools::strtoupper((string)$raw));
        if ($raw === 'APLICA') {
            return 1;
        }
        if ($raw === 'NO APLICA') {
            return 0;
        }
        return null;
    }
}