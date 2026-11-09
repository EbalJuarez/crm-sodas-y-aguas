<?php

namespace App\Http\Controllers;

use App\Http\Requests\MesRequest;
use App\Services\ReporteMes;
use Illuminate\View\View;

/** L5: «Mi equipo» — cartera (x de 10) y ventas del mes de cada vendedor visible. */
class EquipoController extends Controller
{
    public function index(MesRequest $request, ReporteMes $reporte): View
    {
        $mes = $request->mes();

        return view('equipo.index', [
            'equipo' => $reporte->equipo($request->user(), $mes),
            'mes' => $mes,
            'esAdministrador' => $request->user()->esAdministrador(),
        ]);
    }
}
