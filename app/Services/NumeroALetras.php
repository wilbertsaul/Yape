<?php

namespace App\Services;

class NumeroALetras
{
    private static $unidades = [
        '', 'UN', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE',
        'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISEIS', 'DIECISIETE',
        'DIECIOCHO', 'DIECINUEVE', 'VEINTE',
    ];

    private static $decenas = [
        '', '', '', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA',
    ];

    private static $centenas = [
        '', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS',
        'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS',
    ];

    public static function convertir(float $numero): string
    {
        $entero = floor($numero);
        $decimal = round(($numero - $entero) * 100);

        $letras = self::convertirEntero($entero);
        $letras .= ' CON ' . str_pad($decimal, 2, '0', STR_PAD_LEFT) . '/100 SOLES';

        return $letras;
    }

    private static function convertirEntero(int $numero): string
    {
        if ($numero === 0) return 'CERO';
        if ($numero === 1) return 'UN';

        $resultado = '';

        if ($numero >= 1000000) {
            $millones = intdiv($numero, 1000000);
            $resultado .= ($millones === 1) ? 'UN MILLON' : self::convertirEntero($millones) . ' MILLONES';
            $numero %= 1000000;
            if ($numero > 0) $resultado .= ' ';
        }

        if ($numero >= 1000) {
            $miles = intdiv($numero, 1000);
            $resultado .= ($miles === 1) ? 'MIL' : self::convertirEntero($miles) . ' MIL';
            $numero %= 1000;
            if ($numero > 0) $resultado .= ' ';
        }

        if ($numero >= 100) {
            $centena = intdiv($numero, 100);
            $resultado .= ($numero === 100) ? 'CIEN' : self::$centenas[$centena];
            $numero %= 100;
            if ($numero > 0) $resultado .= ' ';
        }

        if ($numero > 0) {
            if ($numero <= 20) {
                $resultado .= self::$unidades[$numero];
            } elseif ($numero < 30) {
                $resultado .= 'VEINTI' . self::$unidades[$numero - 20];
            } else {
                $decena = intdiv($numero, 10);
                $unidad = $numero % 10;
                $resultado .= self::$decenas[$decena];
                if ($unidad > 0) {
                    $resultado .= ' Y ' . self::$unidades[$unidad];
                }
            }
        }

        return trim($resultado);
    }
}
