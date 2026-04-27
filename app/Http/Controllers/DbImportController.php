<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class DbImportController extends Controller
{
    public function show()
    {
        return view('db-import');
    }

    public function import(Request $request)
    {
        $request->validate([
            'sql_file' => ['required', 'file', 'mimes:sql,txt'],
        ]);

        $sql = file_get_contents($request->file('sql_file')->getRealPath());

        if ($sql === false || trim($sql) === '') {
            return back()->with('error', 'SQL failas tuščias arba neperskaitomas.');
        }

        try {
            // Temporary raw SQL import endpoint.
            DB::unprepared($sql);
        } catch (Throwable $exception) {
            return back()->with('error', 'Importas nepavyko: ' . $exception->getMessage());
        }

        return back()->with('success', 'Duomenų bazė sėkmingai importuota.');
    }
}
