<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Hora de Guatemala: UTC-6 fijo, sin horario de verano (CLAUDE.md, «Fechas en UTC»).
 *
 * No se usa CONVERT_TZ con un nombre de zona: en Windows MySQL no tiene las tablas de zonas y
 * devuelve NULL. Los límites del mes se calculan aquí, en UTC, y la base filtra con ese rango.
 */
final class ZonaGuatemala
{
    public const DESFASE_HORAS = 6;

    /** AAAA-MM con mes 01–12. Años 2000–2099: los únicos que tiene sentido elegir en el selector. */
    public const PATRON_MES = '/^20[0-9]{2}-(0[1-9]|1[0-2])$/D';

    /** Mes actual en hora de Guatemala, como AAAA-MM. */
    public static function mesActual(?DateTimeInterface $ahora = null): string
    {
        $utc = CarbonImmutable::instance($ahora ?? CarbonImmutable::now('UTC'))->utc();

        return $utc->subHours(self::DESFASE_HORAS)->format('Y-m');
    }

    public static function esMesValido(mixed $mes): bool
    {
        return is_string($mes) && preg_match(self::PATRON_MES, $mes) === 1;
    }

    /**
     * Rango UTC [inicio, fin) del mes de Guatemala: medianoche del día 1 en Guatemala son las
     * 06:00 UTC. Octubre de 2026 = [2026-10-01 06:00:00, 2026-11-01 06:00:00).
     *
     * @return array{0: string, 1: string} fechas 'Y-m-d H:i:s' en UTC, para comparar con DATETIME
     */
    public static function limitesDelMes(string $mes): array
    {
        $inicio = CarbonImmutable::createFromFormat('!Y-m', $mes, 'UTC');

        return [
            $inicio->addHours(self::DESFASE_HORAS)->format('Y-m-d H:i:s'),
            $inicio->addMonthNoOverflow()->addHours(self::DESFASE_HORAS)->format('Y-m-d H:i:s'),
        ];
    }

    /** Una fecha guardada en UTC, mostrada en hora de Guatemala: 31/10/2026 21:30. */
    public static function formatear(DateTimeInterface|string|null $utc): string
    {
        if ($utc === null || $utc === '') {
            return '—';
        }

        $fecha = is_string($utc) ? CarbonImmutable::parse($utc, 'UTC') : CarbonImmutable::instance($utc)->utc();

        return $fecha->subHours(self::DESFASE_HORAS)->format('d/m/Y H:i');
    }

    /** «octubre de 2026», sin depender del locale del sistema. */
    public static function nombreDelMes(string $mes): string
    {
        $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
            'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        [$anio, $numero] = explode('-', $mes);

        return $meses[(int) $numero - 1].' de '.$anio;
    }
}
