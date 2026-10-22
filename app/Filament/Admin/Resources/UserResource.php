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

    // Sebelumnya tidak ada -- menu "User Management" tampil di sidebar
    // SEMUA orang yang login, termasuk yang tidak punya `view_users`.
    // `Resource::shouldRegisterNavigation()` bawaan Filament mengembalikan
    // true tanpa memeriksa policy apa pun; baru ditolak (403) begitu
    // diklik. Meniru pola persis `WarehouseResource`, disamakan dengan
    // syarat `UserPolicy::viewAny()`.
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check()
            && (auth()->user()->isProgrammer() || auth()->user()->hasPermission('view_users'));
    }

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
                    /*
                     * `edit_users` dulu menanggung dua hal sekaligus: data &
                     * status aktif, DAN izin modul lain lewat checkbox yang
                     * sama -- siapa pun yang bisa mengedit user otomatis bisa
                     * mengangkat dirinya sendiri ke akses penuh. Seksi ini
                     * sekarang perlu `manage_user_permissions` (izin
                     * terpisah, tidak diberikan otomatis ke siapa pun -- lihat
                     * migrasi `2026_09_15_100000_...` dan `tertunda.md` §G),
                     * dan disembunyikan sama sekali saat mengedit AKUN
                     * SENDIRI -- tidak peduli izinnya apa.
                     */
                    ->visible(function (?User $record): bool {
                        if (! auth()->user()?->hasPermission('manage_user_permissions')) {
                            return false;
                        }

                        return ! $record || $record->id !== auth()->id();
                    })
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
    /**
     * Label khusus untuk izin di luar kosakata baku (View, Create, Edit, Delete, View deleted, Approve, Review,
     * Print, Lock/Unlock). Satu tempat, supaya semua modul memakai kata yang sama: nama modulnya sudah menjadi judul
     * seksi, jadi label cukup kata kerjanya (+ objek bila perlu). Izin baru yang tidak baku WAJIB didaftarkan di sini
     * (dijaga tes).
     *
     * @var array<string, string>
     */
    public const SPECIFIC_LABELS = [
        'reopen_carcass_for_boning' => 'Open for boning',
        'reopen_settled_invoices' => 'Reopen settled',
        'reopen_settled_payables' => 'Reopen settled',
        'manage_customer_specs' => 'Manage',
        'pay_purchase_products' => 'Pay down payment',
        'pay_purchase_materials' => 'Pay down payment',
        'cancel_purchase_products' => 'Cancel PO',
        'cancel_purchase_materials' => 'Cancel PO',
        'set_repack_yield_limit' => 'Set shrinkage limit',
        'override_repack_yield' => 'Lock above shrinkage limit',
        'finish_stock_takes' => 'Finish and apply to stock',
        'finish_material_stock_takes' => 'Finish and apply to stock',
        'record_found_items' => 'Record found goods',
        'record_material_findings' => 'Record findings',
        'view_delivery_receipts' => 'View receipts',
        'unlock_sales_returns' => 'Unlock approved',
        'tukar_faktur' => 'Invoice exchange',
        'manage_expense_categories' => 'Manage categories',
        'pay_payables' => 'Pay',
        'record_payable_compensations' => 'Record compensation',
        'cancel_receivable_payments' => 'Cancel payment',
        'receive_receivables' => 'Receive payment',
        'set_opening_balance' => 'Set opening balance',
        'adjust_cash_balance' => 'Cash adjustment',
        'view_cost_reports' => 'View reports',
        'view_sales_report' => 'View sales report',
        'view_fast_moving_products' => 'View fast moving products',
        'manage_user_permissions' => 'Manage permissions',
        'reset_password' => 'Reset password',
    ];

    /** Kosakata baku: label yang boleh muncul tanpa didaftarkan di SPECIFIC_LABELS. */
    public const STANDARD_LABELS = ['View deleted', 'View', 'Create', 'Edit', 'Delete', 'Review', 'Approve', 'Print', 'Lock/Unlock'];

    /**
     * Label tiap centang dalam satu modul: id izin => teks.
     *
     * Urutan: (1) SPECIFIC_LABELS menurut NAMA izin; (1b) semua `lock_*` = Lock/Unlock; (2) kosakata baku dari awal deskripsi (View deleted, View,
     * Create, Edit, Delete, Review, Approve, Print, Lock/Unlock -- tanpa membedakan huruf besar/kecil, jadi
     * "Lock/unlock" dan "Lock/Unlock" sama); (3) deskripsi lengkap sebagai cadangan. Bila dua izin dalam modul yang
     * sama tetap berlabel sama, keduanya memakai deskripsi LENGKAP (dua centang berlabel sama tidak bisa dibedakan).
     *
     * @param  \Illuminate\Support\Collection<int, Permission>  $permissions
     * @return array<int, string>
     */
    public static function permissionLabels($permissions): array
    {
        $short = $permissions->mapWithKeys(function ($p) {
            if (isset(self::SPECIFIC_LABELS[$p->name])) {
                return [$p->id => __(self::SPECIFIC_LABELS[$p->name])];
            }

            // Setiap izin `lock_*` menahan Lock DAN Unlock (satu izin untuk keduanya), apa pun deskripsinya.
            if (str_starts_with($p->name, 'lock_')) {
                return [$p->id => __('Lock/Unlock')];
            }

            $desc = (string) $p->description;

            foreach (self::STANDARD_LABELS as $label) {
                if (stripos($desc, $label) === 0) {
                    return [$p->id => __($label)];
                }
            }

            return [$p->id => __($desc)];
        });

        $counts = $short->countBy();

        return $permissions->mapWithKeys(fn ($p) => [
            $p->id => $counts[$short[$p->id]] > 1 ? __($p->description) : $short[$p->id],
        ])->toArray();
    }

    protected static function permissionCheckboxList(string $moduleName, $permissions): Forms\Components\CheckboxList
    {
        return Forms\Components\CheckboxList::make('permissions_' . $moduleName)
            ->hiddenLabel()
            ->options(static::permissionLabels($permissions))
            ->bulkToggleable()
            ->columns(4)
            ->dehydrated(false)
            ->afterStateHydrated(function (Forms\Components\CheckboxList $component, ?User $record) use ($moduleName, $permissions) {
                if ($record) {
                    // Hanya izin yang TAMPIL di daftar ini: yang disembunyikan (mis. hapus di master
                    // data) tidak ikut terbawa di state, sehingga menyimpan form tidak menyisakan hak
                    // yang tidak terlihat dan tidak bisa dicabut siapa pun.
                    $component->state(
                        $record->permissions()
                            ->where('module_name', $moduleName)
                            ->whereIn('permissions.id', $permissions->pluck('id'))
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
            ->defaultSort('name')
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

