<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'USERS';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identitas User')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Full Name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('username')
                            ->label('Username')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->rules(['alpha_dash'])
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set) {
                                if (filled($state)) {
                                    $set('email', strtolower($state) . '@wijayameat.co.id');
                                }
                            }),

                        Forms\Components\TextInput::make('email')
                            ->label('Email Address')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->readOnly(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Akun Aktif')
                            ->default(true)
                            ->inline(false)
                            ->helperText('Matikan jika karyawan resign/nonaktif.'),

                        Forms\Components\Hidden::make('password')
                            ->default('1234')
                            ->dehydrated(fn(string $context): bool => $context === 'create')
                            ->dehydrateStateUsing(fn($state) => Hash::make($state)),

                        Forms\Components\Hidden::make('must_change_password')
                            ->default(true)
                            ->dehydrated(fn(string $context): bool => $context === 'create'),

                        Forms\Components\Hidden::make('must_change_password')
                            ->default(true),

                        /* Grup Jabatan Utama */
                        Forms\Components\CheckboxList::make('roles')
                            ->label('Role Utama')
                            ->relationship('roles', 'name')
                            ->columns(2)
                            ->columnSpanFull(),
                    ])->columns(2),

                /* Matriks Spesifik per Modul yang Sudah Dirapikan */
                Forms\Components\Section::make('Hak Akses Ekstra (Direct Permissions)')
                    ->description('Atur hak akses spesifik untuk user ini secara terperinci.')
                    ->schema(static::getPermissionMatrix())
                    ->columns(1),
            ]);
    }

    /**
     * Fungsi ajaib buat ngebangun Matriks Permission secara dinamis dan SUPER RAPI
     */
    public static function getPermissionMatrix(): array
    {
        $permissions = \Spatie\Permission\Models\Permission::all();

        // 1. Definisikan action bawaan Shield dan custom lu
        $orderedActions = [
            'view_any',
            'view',
            'create',
            'update',
            'delete',
            'lock',     // Tambahan baru
            'unlock',   // Tambahan baru
            'review',
            'approve',
            'page',     // Shield default
            'widget',   // Shield default
        ];

        // Sortir dari terpanjang ke terpendek agar pencocokan akurat (view_any tidak terpotong jadi view)
        $searchActions = $orderedActions;
        usort($searchActions, fn($a, $b) => strlen($b) <=> strlen($a));

        $groupedPermissions = [];

        // 2. Kelompokkan & Rapihkan String
        foreach ($permissions as $permission) {
            $name = $permission->name;
            $actionMatch = null;
            $entityMatch = null;

            foreach ($searchActions as $action) {
                if (Str::startsWith($name, $action . '_')) {
                    $actionMatch = $action;
                    $entityMatch = substr($name, strlen($action) + 1);
                    break;
                }
            }

            if ($actionMatch && $entityMatch) {
                // Hilangkan ::, _, -, lalu jadikan Title Case
                $cleanEntityName = (string) Str::of($entityMatch)->replace(['::', '_', '-'], ' ')->headline();
                $cleanLabel = (string) Str::of($actionMatch)->replace(['::', '_', '-'], ' ')->headline();

                // Pisahkan Page dan Widget ke grup tersendiri biar gak nyampur sama tabel data
                if (in_array($actionMatch, ['page', 'widget'])) {
                    $groupedPermissions['Halaman & Widget'][] = [
                        'name' => $permission->name,
                        'action' => $actionMatch,
                        'label' => $cleanEntityName . ' (' . $cleanLabel . ')', // Cth: Dashboard (Page)
                    ];
                } else {
                    $groupedPermissions[$cleanEntityName][] = [
                        'name' => $permission->name,
                        'action' => $actionMatch,
                        'label' => $cleanLabel, // Cth: View Any
                    ];
                }
            } else {
                // Fallback untuk permission yang strukturnya tidak standar
                $cleanName = (string) Str::of($name)->replace(['::', '_', '-'], ' ')->headline();

                $groupedPermissions['Hak Akses Lainnya'][] = [
                    'name' => $permission->name,
                    'action' => $name,
                    'label' => $cleanName,
                ];
            }
        }

        // 3. Bangun UI Section per entitas
        $schema = [];
        foreach ($groupedPermissions as $entity => $perms) {
            // Urutkan ulang checkbox sesuai dengan hierarki $orderedActions
            usort($perms, function ($a, $b) use ($orderedActions) {
                $posA = array_search($a['action'], $orderedActions);
                $posB = array_search($b['action'], $orderedActions);
                if ($posA === false) $posA = 999;
                if ($posB === false) $posB = 999;
                return $posA <=> $posB;
            });

            $options = [];
            foreach ($perms as $perm) {
                $options[$perm['name']] = $perm['label'];
            }

            $schema[] = Forms\Components\Section::make($entity)
                ->schema([
                    Forms\Components\CheckboxList::make('custom_permissions_' . Str::slug($entity))
                        ->hiddenLabel()
                        ->options($options)
                        ->columns(4)
                        ->bulkToggleable()
                        ->dehydrated(false) // Penting: cegah error missing column saat save
                        ->afterStateHydrated(function ($component, $record) use ($options) {
                            if ($record) {
                                $hasPerms = $record->permissions()
                                    ->whereIn('name', array_keys($options))
                                    ->pluck('name')
                                    ->toArray();
                                $component->state($hasPerms);
                            }
                        })
                ])
                ->collapsible()
                ->compact();
        }

        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),

                Tables\Columns\TextColumn::make('username')->badge()->color('info')->searchable(),

                Tables\Columns\TextColumn::make('roles.name')->label('Roles')->badge()->color('success'),

                Tables\Columns\IconColumn::make('must_change_password')
                    ->label('Force Reset?')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->color(fn($state) => $state ? 'warning' : 'success'),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active')
                    ->sortable(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
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
