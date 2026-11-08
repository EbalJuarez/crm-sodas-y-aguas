<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\User;
use App\Support\ZonaGuatemala;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reporte del mes (L6, R7) y lista de oportunidades. Lo usan el reporte y, en L5, la página del equipo.
 *
 * Reglas (spec sección 4, «Reglas transversales»):
 * - Total de una oportunidad = Σ cantidad × precio_unitario de sus LÍNEAS (precio guardado al
 *   cotizar), nunca el precio del catálogo. Si se usara el catálogo, Valeria daría Q9,551.00.
 * - Ventas del mes = ganadas cuyo fecha_cierre cae en el mes de Guatemala, como rango UTC
 *   [día 1 06:00, día 1 del mes siguiente 06:00). Agrupar en UTC daría a Valeria Q5,650.00.
 * - Pronóstico = ROUND(Σ total × probabilidad / 100, 2) de las abiertas, sin filtro de mes; se
 *   redondea una sola vez, en la base, con DECIMAL (en float, 6751.125 puede quedar en 6751.12).
 * - Supervisor: su equipo (vendedores con supervisor_id = él). Administrador: toda la empresa.
 *
 * Los únicos fragmentos SQL escritos a mano son las constantes de abajo; fechas e ids van
 * siempre como parámetros enlazados (nunca DB::raw con datos del usuario).
 */
final class ReporteMes
{
    public const ETAPAS = ['prospecto', 'calificado', 'propuesta', 'negociacion', 'ganada', 'perdida'];

    public const ETAPAS_ABIERTAS = ['prospecto', 'calificado', 'propuesta', 'negociacion'];

    public const PROBABILIDADES = [
        'prospecto' => 10, 'calificado' => 25, 'propuesta' => 50,
        'negociacion' => 75, 'ganada' => 100, 'perdida' => 0,
    ];

    private const SQL_TOTAL_LINEAS = 'SUM(cantidad * precio_unitario) AS total';

    private const SQL_VENTAS = 'COUNT(*) AS ventas, COALESCE(SUM(l.total), 0) AS total';

    private const SQL_PRONOSTICO = "ROUND(COALESCE(SUM(COALESCE(l.total, 0) * CASE o.etapa
        WHEN 'prospecto' THEN 10 WHEN 'calificado' THEN 25 WHEN 'propuesta' THEN 50
        WHEN 'negociacion' THEN 75 WHEN 'ganada' THEN 100 ELSE 0 END / 100), 0), 2) AS pronostico";

    /**
     * Vendedores que ve el usuario (activos o no: Iván sale con ceros en el reporte de la empresa).
     *
     * @return Collection<int, User>
     */
    public function vendedoresVisibles(User $usuario): Collection
    {
        return User::query()->equipoDe($usuario)->orderBy('name')->orderBy('id')
            ->get(['id', 'name', 'activo']);
    }

    /**
     * L5 «Mi equipo»: por vendedor visible, su cartera actual (x de 10) y sus ventas del mes.
     * Un vendedor desactivado aparece con su cartera intacta (D4) y marcado.
     *
     * @return list<array{id: int, nombre: string, activo: bool, cartera: int, maximo: int,
     *                    ventas: int, total: string, pronostico: string}>
     */
    public function equipo(User $usuario, string $mes): array
    {
        $vendedores = $this->vendedoresVisibles($usuario);
        $ids = $vendedores->pluck('id')->all();
        $resumen = $this->resumenPorVendedor($ids, $mes);

        $carteras = $ids === [] ? [] : DB::table('clientes')
            ->whereIn('vendedor_id', $ids)
            ->groupBy('vendedor_id')
            ->select('vendedor_id')->selectRaw('COUNT(*) AS clientes')
            ->pluck('clientes', 'vendedor_id')
            ->all();

        return $vendedores->map(fn (User $vendedor) => [
            'id' => $vendedor->id,
            'nombre' => $vendedor->name,
            'activo' => (bool) $vendedor->activo,
            'cartera' => (int) ($carteras[$vendedor->id] ?? 0),
            'maximo' => Cliente::MAXIMO_CARTERA,
            ...$resumen[$vendedor->id],
        ])->values()->all();
    }

    /**
     * Ids de vendedor que limitan las consultas: null = toda la empresa (administrador).
     *
     * @return list<int>|null
     */
    public function alcance(User $usuario): ?array
    {
        if ($usuario->esAdministrador()) {
            return null;
        }

        return $this->vendedoresVisibles($usuario)->pluck('id')->all();
    }

    /**
     * Reporte completo del mes para lo que ve el usuario.
     *
     * @return array{mes: string, nombre_mes: string, totales: array{ventas: int, total: string, pronostico: string},
     *               vendedores: list<array{id: int, nombre: string, activo: bool, ventas: int, total: string, pronostico: string}>,
     *               productos: list<array{id: int, nombre: string, cantidad: int, total: string}>}
     */
    public function generar(User $usuario, string $mes): array
    {
        $alcance = $this->alcance($usuario);
        $vendedores = $this->vendedoresVisibles($usuario);
        $porVendedor = $this->resumenPorVendedor($vendedores->pluck('id')->all(), $mes);

        return [
            'mes' => $mes,
            'nombre_mes' => ZonaGuatemala::nombreDelMes($mes),
            'totales' => $this->totales($alcance, $mes),
            'vendedores' => $vendedores->map(fn (User $vendedor) => [
                'id' => $vendedor->id,
                'nombre' => $vendedor->name,
                'activo' => (bool) $vendedor->activo,
                ...$porVendedor[$vendedor->id],
            ])->values()->all(),
            'productos' => $this->porProducto($alcance, $mes),
        ];
    }

    /**
     * Ventas, total y pronóstico de cada vendedor indicado (ceros si no tiene). Para L5.
     *
     * @param  list<int>  $vendedorIds
     * @return array<int, array{ventas: int, total: string, pronostico: string}>
     */
    public function resumenPorVendedor(array $vendedorIds, string $mes): array
    {
        $resultado = [];
        foreach ($vendedorIds as $id) {
            $resultado[$id] = ['ventas' => 0, 'total' => '0.00', 'pronostico' => '0.00'];
        }

        if ($vendedorIds === []) {
            return $resultado;
        }

        $ventas = $this->ventasDelMes($this->oportunidadesConTotal($vendedorIds), $mes)
            ->groupBy('o.vendedor_id')
            ->select('o.vendedor_id')->selectRaw(self::SQL_VENTAS)
            ->get();
        foreach ($ventas as $fila) {
            $resultado[(int) $fila->vendedor_id]['ventas'] = (int) $fila->ventas;
            $resultado[(int) $fila->vendedor_id]['total'] = (string) $fila->total;
        }

        $pronosticos = $this->abiertas($this->oportunidadesConTotal($vendedorIds))
            ->groupBy('o.vendedor_id')
            ->select('o.vendedor_id')->selectRaw(self::SQL_PRONOSTICO)
            ->get();
        foreach ($pronosticos as $fila) {
            $resultado[(int) $fila->vendedor_id]['pronostico'] = (string) $fila->pronostico;
        }

        return $resultado;
    }

    /**
     * Totales del alcance, calculados en la base en una sola suma (no sumando filas en PHP).
     *
     * @param  list<int>|null  $alcance
     * @return array{ventas: int, total: string, pronostico: string}
     */
    public function totales(?array $alcance, string $mes): array
    {
        $ventas = $this->ventasDelMes($this->oportunidadesConTotal($alcance), $mes)
            ->selectRaw(self::SQL_VENTAS)->first();
        $pronostico = $this->abiertas($this->oportunidadesConTotal($alcance))
            ->selectRaw(self::SQL_PRONOSTICO)->first();

        return [
            'ventas' => (int) $ventas->ventas,
            'total' => (string) $ventas->total,
            'pronostico' => (string) $pronostico->pronostico,
        ];
    }

    /**
     * Unidades y total vendido por producto en el mes (precio guardado en la línea).
     *
     * @param  list<int>|null  $alcance
     * @return list<array{id: int, nombre: string, cantidad: int, total: string}>
     */
    public function porProducto(?array $alcance, string $mes): array
    {
        [$inicio, $fin] = ZonaGuatemala::limitesDelMes($mes);

        $consulta = DB::table('oportunidad_productos as op')
            ->join('oportunidades as o', 'o.id', '=', 'op.oportunidad_id')
            ->join('productos as p', 'p.id', '=', 'op.producto_id')
            ->where('o.etapa', 'ganada')
            ->where('o.fecha_cierre', '>=', $inicio)
            ->where('o.fecha_cierre', '<', $fin);

        if ($alcance !== null) {
            $consulta->whereIn('o.vendedor_id', $alcance);
        }

        return $consulta
            ->groupBy('p.id', 'p.nombre')
            ->orderBy('p.nombre')
            ->select('p.id', 'p.nombre')
            ->selectRaw('SUM(op.cantidad) AS cantidad, SUM(op.cantidad * op.precio_unitario) AS total')
            ->get()
            ->map(fn ($fila) => [
                'id' => (int) $fila->id,
                'nombre' => $fila->nombre,
                'cantidad' => (int) $fila->cantidad,
                'total' => (string) $fila->total,
            ])
            ->all();
    }

    /**
     * Lista de oportunidades (solo lectura) con su total, filtrable por etapa y vendedor.
     * El filtro de vendedor se aplica dentro del alcance: un id de otro equipo no devuelve nada.
     */
    public function listaDeOportunidades(User $usuario, ?string $etapa = null, ?int $vendedorId = null): Builder
    {
        $consulta = $this->oportunidadesConTotal($this->alcance($usuario))
            ->join('clientes as c', 'c.id', '=', 'o.cliente_id')
            ->join('users as u', 'u.id', '=', 'o.vendedor_id')
            ->select('o.id', 'o.titulo', 'o.etapa', 'o.fecha_cierre', 'c.nombre as cliente',
                'u.name as vendedor', 'u.activo as vendedor_activo')
            ->selectRaw('COALESCE(l.total, 0) AS total')
            ->orderBy('o.id');

        if ($etapa !== null) {
            $consulta->where('o.etapa', $etapa);
        }

        if ($vendedorId !== null) {
            $consulta->where('o.vendedor_id', $vendedorId);
        }

        return $consulta;
    }

    /** Oportunidades con el total de sus líneas (l.total; NULL si no tiene líneas). */
    private function oportunidadesConTotal(?array $alcance): Builder
    {
        $lineas = DB::table('oportunidad_productos')
            ->select('oportunidad_id')
            ->selectRaw(self::SQL_TOTAL_LINEAS)
            ->groupBy('oportunidad_id');

        $consulta = DB::table('oportunidades as o')
            ->leftJoinSub($lineas, 'l', 'l.oportunidad_id', '=', 'o.id');

        if ($alcance !== null) {
            $consulta->whereIn('o.vendedor_id', $alcance);
        }

        return $consulta;
    }

    private function ventasDelMes(Builder $consulta, string $mes): Builder
    {
        [$inicio, $fin] = ZonaGuatemala::limitesDelMes($mes);

        return $consulta->where('o.etapa', 'ganada')
            ->where('o.fecha_cierre', '>=', $inicio)
            ->where('o.fecha_cierre', '<', $fin);
    }

    private function abiertas(Builder $consulta): Builder
    {
        return $consulta->whereIn('o.etapa', self::ETAPAS_ABIERTAS);
    }
}
