<?php

class CsvReader
{
    public function read($filePath)
    {
        $h = fopen($filePath, 'r');
        if (!$h) {
            throw new Exception("No se pudo abrir el archivo CSV.");
        }

        // Leer primera línea como header
        $rawHeader = fgetcsv($h, 0, ';');
        if ($rawHeader === false) {
            fclose($h);
            throw new Exception("El CSV está vacío.");
        }

        // Si solo viene 1 columna con comas, re-parsear
        if (count($rawHeader) == 1) {
            $rawHeader = str_getcsv($rawHeader[0], ',');
        }

        // Quitar BOM del primer header
        if (isset($rawHeader[0])) {
            $rawHeader[0] = preg_replace('/^\xEF\xBB\xBF/', '', $rawHeader[0]);
        }

        $header = array_map(function ($x) {
            return Tools::strtoupper(trim((string)$x));
        }, $rawHeader);

        // Generador streaming de filas
        $rows = (function () use ($h) {
            while (($row = fgetcsv($h, 0, ';')) !== false) {
                if (count($row) == 1) {
                    $row = str_getcsv($row[0], ',');
                }

                // Saltar filas totalmente vacías
                if (!isset($row[0]) || trim(implode('', $row)) === '') {
                    continue;
                }

                yield $row;
            }
            fclose($h);
        })();

        return [$header, $rows];
    }
}