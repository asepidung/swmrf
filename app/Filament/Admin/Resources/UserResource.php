<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function getNavigationGroup(): ?string
    {
        return __('SYSTEM');
    }

    public static function getModelLabel(): string
    {
        return __('User');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Users');
    }

    public static function getNavigationLabel(): string
    {
        return __('User Management');
    }

    public static function getEloquentQuery(): Builder
    {
        // Exclude absolute programmer account from the UI completely
        return parent::getEloquentQuery()->where('role', '!=', 'programmer');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('User Details'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('Name'))
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('username')
                            ->label(__('Username'))
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\Toggle::make('is_active')
                            ->label(__('Active Status'))
                            ->default(true)
                            ->required()
                            ->visibleOn('edit'),
                    ])->columns(2),

                Forms\Components\Section::make(__('Permissions (Hak Akses)'))
                    ->description(__('Define custom permissions for this employee.'))
                    ->schema([
                        // Dikelompokkan ke TAB mengikuti grup sidebar.
                        //
                        // Sebelumnya 46 seksi modul ditumpuk vertikal dalam satu
                        // halaman, dan admin harus menggulir melewati semuanya.
                        // Itu bukan sekadar tidak nyaman: memilih satu per satu
                        // dari 46 seksi melelahkan, sehingga lebih gampang
                        // mencentang semua -- dan begitulah akun uji berakhir
                        // dengan 181 permission. Form yang menyulitkan pemberian
                        // hak secara selektif melahirkan hak yang serampangan.
                        //
                        // Urutan tabnya sama persis dengan sidebar, supaya admin
                        // bisa membayangkan menu yang nanti dilihat pengguna.
                        Forms\Components\Tabs::make('permission_groups')
                            ->columnSpanFull()
                            ->tabs(function () {
                                $tabs = [];

                                foreach (\App\Models\Permission::groupedByModuleGroup() as $group => $modules) {
                                    $sections = [];

                                    foreach ($modules as $moduleName => $permissions) {
                                        $sections[] = Forms\Components\Section::make(__($moduleName))
                                            ->collapsed()
                                            ->compact()
                                            ->collapsible()
                                            ->schema([
                                                static::permissionCheckboxList($moduleName, $permissions),
                                            ]);
                                    }

                                    $tabs[] = Forms\Components\Tabs\Tab::make(__($group))
                                        ->badge(count($modules))
                                        ->schema($sections);
                                }

                                return $tabs;
                            }),
                    ]),
            ]);
    }

    /**
     * Daftar centang hak akses untuk satu modul.
     *
     * Label sengaja dipendekkan menjadi kata kerjanya saja (View, Create,
     * Edit, ...) karena nama modulnya sudah menjadi judul seksi -- menulis
     * "View sales orders" di dalam seksi "Sales Orders" hanya mengulang.
     */
    protected static function permissionCheckboxList(string $moduleName, $permissions): Forms\Components\CheckboxList
    {
        return Forms\Components\CheckboxList::make('permissions_' . $moduleName)
            ->hiddenLabel()
            ->options($permissions->mapWithKeys(function ($p) {
                $desc = $p->description;
                $label = __($desc);
                $prefixes = ['View deleted', 'View', 'Create', 'Edit', 'Delete', 'Review', 'Approve', 'Print', 'Lock/Unlock', 'Lock', 'Reset password'];
                foreach ($prefixes as $prefix) {
                    if (str_starts_with($desc, $prefix)) {
                        $label = __($prefix);
                        break;
                    }
                }
                if (str_starts_with($desc, 'Perform invoice exchange')) {
                    $label = __('Invoice exchange');
                }

                return [$p->id => $label];
            })->toArray())
            ->bulkToggleable()
            ->columns(4)
            ->dehydrated(false)
            ->afterStateHydrated(function (Forms\Components\CheckboxList $component, ?User $record) use ($moduleName) {
                if ($record) {
                    $component->state(
                        $record->permissions()
                            ->where('module_name', $moduleName)
                            ->pluck('permissions.id')
                            ->toArray()
                    );
                }
            });
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('username')
                    ->label(__('Username'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Active Status'))
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('Active Status')),
            ])
            ->actions([
                Tables\Actions\Action::make('reset_password')
                    ->label(__('Reset Password'))
                    ->requiresConfirmation()
                    ->action(function (User $record) {
                        $record->update([
                            'password' => Hash::make('1234')
                        ]);
                        Notification::make()
                            ->title(__('Password Reset Successfully'))
                            ->body(__("The password for user ':username' has been reset to '1234'.", ['username' => $record->username]))
                            ->success()
                            ->send();
                    })
                    ->visible(fn () => auth()->user()->hasPermission('reset_password'))
                    ->color('warning')
                    ->icon('heroicon-o-arrow-path'),
            ])
            // TIDAK ADA aksi hapus, baik satuan maupun massal.
            //
            // Pengguna dinonaktifkan, tidak dihapus -- lihat alasan lengkapnya
            // di `UserPolicy::delete()`. Membiarkan tombolnya berdiri sambil
            // ditolak policy hanya menawarkan sesuatu yang tidak akan pernah
            // berhasil.
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                ]),
            ])
            ->recordUrl(fn (User $record): string => Pages\EditUser::getUrl(['record' => $record]));
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
