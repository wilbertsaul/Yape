<?php

namespace Tests\Unit;

use App\Services\NumeroALetras;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NumeroALetrasTest extends TestCase
{
    #[DataProvider('montos')]
    public function test_convierte_montos_a_letras(float $numero, string $esperado): void
    {
        $this->assertSame($esperado, NumeroALetras::convertir($numero));
    }

    public static function montos(): array
    {
        return [
            'cero' => [0.0, 'CERO CON 00/100 SOLES'],
            'entero' => [1.0, 'UN CON 00/100 SOLES'],
            'cien' => [100.0, 'CIEN CON 00/100 SOLES'],
            'veintiuno con decimal' => [21.5, 'VEINTIUN CON 50/100 SOLES'],
            'miles' => [1234.56, 'MIL DOSCIENTOS TREINTA Y CUATRO CON 56/100 SOLES'],
        ];
    }
}
