<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DbImportController extends Controller
{
    private const REPORT_TABLES = [
        'users',
        'partners',
        'garages',
        'claims',
    ];

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

        return back()
            ->with('success', 'Duomenų bazė sėkmingai importuota.')
            ->with('import_report', [
                'file_name' => $request->file('sql_file')->getClientOriginalName(),
                'imported_at' => now()->format('Y-m-d H:i:s'),
                'table_counts' => $this->tableCounts(),
            ]);
    }

    private function tableCounts(): array
    {
        $counts = [];

        foreach (self::REPORT_TABLES as $tableName) {
            if (! Schema::hasTable($tableName)) {
                $counts[$tableName] = null;
                continue;
            }

            $counts[$tableName] = DB::table($tableName)->count();
        }

        return $counts;
    }
}
