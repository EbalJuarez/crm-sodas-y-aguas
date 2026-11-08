<?php

namespace App\Http\Controllers;

use App\Services\ReporteMes;
use App\Support\ZonaGuatemala;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class ReporteController extends Controller
{
    private const MENSAJE_MES = 'Elija un mes válido con el formato AAAA-MM (por ejemplo, 2026-10).';

    /** L6: reporte del mes. ?mes=AAAA-MM; sin él, el mes actual en hora de Guatemala. */
    public function mes(Request $request, ReporteMes $reporte): View|RedirectResponse
    {
        // Un mes inválido (2026-13, octubre, un arreglo) vuelve al mes actual con el mensaje, nunca un 500.
        $validacion = Validator::make($request->query(), [
            'mes' => ['bail', 'nullable', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! ZonaGuatemala::esMesValido($value)) {
                    $fail(self::MENSAJE_MES);
                }
            }],
        ], ['mes.string' => self::MENSAJE_MES]);

        if ($validacion->fails()) {
            return redirect()->route('reportes.mes')->withErrors($validacion);
        }

        $mes = $request->query('mes') ?? ZonaGuatemala::mesActual();

        return view('reportes.mes', [
            'reporte' => $reporte->generar($request->user(), $mes),
            'esAdministrador' => $request->user()->esAdministrador(),
        ]);
    }
}
