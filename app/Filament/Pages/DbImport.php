<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DbImport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';

    protected static string $view = 'filament.pages.db-import';

    protected static ?string $slug = 'db';

    protected static ?string $title = 'Database import';

    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->check() && filter_var(env('DB_IMPORT_ENABLED', false), FILTER_VALIDATE_BOOL);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Import SQL dump')
                    ->description('Temporary tool for authenticated users only. Disable with DB_IMPORT_ENABLED=false when finished.')
                    ->schema([
                        Textarea::make('sql')
                            ->label('Paste SQL')
                            ->rows(16)
                            ->nullable(),

                        FileUpload::make('sql_file')
                            ->label('Or upload .sql file')
                            ->acceptedFileTypes([
                                'text/plain',
                                'application/sql',
                                'application/octet-stream',
                            ])
                            ->maxSize(51200)
                            ->directory('db-imports')
                            ->nullable(),
                    ]),
            ])
            ->statePath('data');
    }

    public function import(): void
    {
        $sql = trim($this->data['sql'] ?? '');

        if ($sql === '' && ! empty($this->data['sql_file'])) {
            $files = is_array($this->data['sql_file']) ? $this->data['sql_file'] : [$this->data['sql_file']];
            $path = $files[0] ?? null;

            if ($path && Storage::disk('public')->exists($path)) {
                $sql = Storage::disk('public')->get($path);
                Storage::disk('public')->delete($path);
            }
        }

        if ($sql === '') {
            Notification::make()
                ->danger()
                ->title('No SQL provided')
                ->body('Paste SQL or upload a .sql file.')
                ->send();

            return;
        }

        try {
            DB::unprepared($sql);
        } catch (Throwable $exception) {
            Notification::make()
                ->danger()
                ->title('Import failed')
                ->body($exception->getMessage())
                ->send();

            return;
        }

        $this->data = ['sql' => null, 'sql_file' => null];
        $this->form->fill();

        Notification::make()
            ->success()
            ->title('Database imported')
            ->body($this->buildReportMessage())
            ->send();
    }

    private function buildReportMessage(): string
    {
        $lines = ['Import completed. Row counts:'];

        foreach (['users', 'partners', 'garages', 'claims', 'settings'] as $table) {
            if (! Schema::hasTable($table)) {
                $lines[] = "- {$table}: missing";

                continue;
            }

            $lines[] = '- '.$table.': '.DB::table($table)->count();
        }

        return implode("\n", $lines);
    }
}
