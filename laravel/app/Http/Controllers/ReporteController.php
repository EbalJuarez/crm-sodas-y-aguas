<?php

namespace App\Http\Controllers;

use App\Http\Requests\MesRequest;
use App\Services\ReporteMes;
use Illuminate\View\View;

class ReporteController extends Controller
{
    /** L6: reporte del mes. ?mes=AAAA-MM; sin él, el mes actual en hora de Guatemala. */
    public function mes(MesRequest $request, ReporteMes $reporte): View
    {
        return view('reportes.mes', [
            'reporte' => $reporte->generar($request->user(), $request->mes()),
            'esAdministrador' => $request->user()->esAdministrador(),
        ]);
    }
}
