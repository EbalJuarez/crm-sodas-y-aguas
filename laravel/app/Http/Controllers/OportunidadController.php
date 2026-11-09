<?php

namespace App\Http\Controllers;

use App\Services\ReporteMes;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** L6: oportunidades de solo lectura (L6.3: el panel no crea, edita ni cambia de etapa). */
class OportunidadController extends Controller
{
    public function index(Request $request, ReporteMes $reporte): View
    {
        // Filtros que el formulario no puede enviar (arreglos, etapa inexistente, vendedor no
        // numérico): 400, como la búsqueda de clientes (L7) y la API (spec 3.3).
        foreach (['etapa', 'vendedor_id'] as $filtro) {
            abort_if(is_array($request->query($filtro)), 400);
        }

        $etapa = $request->query('etapa');
        abort_if($etapa !== null && ! in_array($etapa, ReporteMes::ETAPAS, true), 400);

        $vendedorId = $request->query('vendedor_id');
        abort_if($vendedorId !== null && ! ctype_digit($vendedorId), 400);

        $usuario = $request->user();

        return view('oportunidades.index', [
            'oportunidades' => $reporte
                ->listaDeOportunidades($usuario, $etapa, $vendedorId === null ? null : (int) $vendedorId)
                ->paginate(20)
                ->withQueryString(),
            'vendedores' => $reporte->vendedoresVisibles($usuario),
            'filtros' => ['etapa' => $etapa, 'vendedor_id' => $vendedorId],
        ]);
    }
}
