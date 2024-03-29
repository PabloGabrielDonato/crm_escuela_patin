<?php

namespace App\Http\Controllers;

use App\Models\Alumno;
use App\Models\Cuota;
use App\Models\Disciplina;
use App\Models\Discipline;
// use App\Notifications\SweetAlertNotification;
use Illuminate\Http\Request;

class AlumnoController extends Controller
{
    public function index()
    {

        if (isset($_GET['filter']))
        {
            $filtro = $_GET['filter'];
            // quiero un where con 2 clausulas


            $alumnos = Alumno::where('nombre', 'like', '%'.$filtro.'%')
            ->orWhere('apellido', 'like', '%'.$filtro.'%')
            ->get();
        }else
        {
            $alumnos = Alumno::all();
        }
        // Utiliza una consulta SQL para calcular el ingreso bruto anual
        $ingresoBrutoAnual = Alumno::join('cuotas', 'alumnos.id', '=', 'cuotas.alumno_id')
            ->where('cuotas.estado_pago', 'pagada')
            ->sum('cuotas.total');


        return view('alumnos.index', ['alumnos' => $alumnos, 'ingresoBrutoAnual' => $ingresoBrutoAnual]);
    }

    public function create()
    {
        $disciplinas = Discipline::all();
        return view('alumnos.create', compact('disciplinas'));
    }

    public function store(Request $request)
{
    $request->validate([
        'nombre' => 'required',
        'apellido' => 'required',
        'dni' => 'required',
        // ... otras validaciones
    ]);

    $alumno = Alumno::create($request->all());

    // Asociar las disciplinas al alumno
    if ($request->has('disciplinas')) {
        $alumno->disciplinas()->sync($request->input('disciplinas'));
    }

    // Crear cuotas para el alumno
    $alumno->crearCuotas();

    return redirect()->route('alumnos.index')->with('success', 'Alumno creado exitosamente');
}

    public function show(Alumno $alumno)
    {
        return view('alumnos.show', compact('alumno'));
    }

    public function edit(Alumno $alumno)
    {
        $disciplinas = Discipline::all();
        return view('alumnos.edit', compact('alumno', 'disciplinas'));
    }




public function update(Request $request, Alumno $alumno)
{
    $request->validate([
        'nombre' => 'required',
        'apellido' => 'required',
        'dni' => 'required',
        'disciplinas' => 'array',
    ]);

    // Obtén el total actual de las cuotas ya pagadas
    $totalCuotasPagadas = $alumno->cuotas()->where('estado_pago', 'pagada')->sum('total');

    // Antes de la actualización, imprime solo los nombres de las disciplinas
    $disciplinasNombres = $alumno->disciplinas->pluck('nombre')->toArray();
    info("Disciplinas antes de la actualización: " . implode(', ', $disciplinasNombres));

    // Imprime información esencial de las cuotas pagadas
    $cuotasPagadas = $alumno->cuotas()->where('estado_pago', 'pagada')->get();
    foreach ($cuotasPagadas as $cuota) {
        info("Cuota pagada: Mes={$cuota->mes}, Total={$cuota->total}");
    }

    // Actualiza el alumno
    $alumno->update([
        'nombre' => $request->nombre,
        'apellido' => $request->apellido,
        'dni' => $request->dni,
    ]);

    // Sincroniza las disciplinas del alumno
    $alumno->disciplinas()->sync($request->disciplinas);
    $alumno->refresh();

    $alumno->cuotas->where('estado_pago', '!=', 'pagada')->each(function ($cuota) {
        $cuota->disciplinas()->sync($cuota->alumno->disciplinas);
    });






    // Actualiza el total de cuotas pagadas solo si no hay cuotas pagadas
    if ($totalCuotasPagadas == 0.00) {
        // Recalcula el total solo para las cuotas no pagadas del mes actual
        $alumno->cuotas()->where('estado_pago', '!=', 'pagada')->whereMonth('created_at', now()->month)->update(['total' => 0]);
    }

    return redirect()->route('alumnos.index')->with('success', 'Alumno actualizado exitosamente');
}


    public function destroy(Alumno $alumno)
    {
        $alumno->delete();
        // $this->guardarNotificacionSweetAlert('Éxito', 'El alumno fue eliminado correctamente.', 'success');

        return redirect()->route('alumnos.index')->with('success', 'Alumno eliminado exitosamente');
    }


    public function showCuotas(Alumno $alumno)
{
    $cuotas = Cuota::where('alumno_id', $alumno->id)->get();

    return view('alumnos.show', compact('alumno', 'cuotas'));
}


}
