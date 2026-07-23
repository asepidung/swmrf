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
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make(__('Permissions (Hak Akses)'))
                    ->description(__('Define custom permissions for this employee.'))
                    ->schema(function () {
                        $schema = [];
                        $modules = \App\Models\Permission::all()->groupBy('module_name');

                        foreach ($modules as $moduleName => $permissions) {
                            $schema[] = Forms\Components\Section::make(__($moduleName))
                                ->schema([
                                    Forms\Components\CheckboxList::make('permissions_' . $moduleName)
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
                                                $label = __('Tukar Faktur');
                                            }
                                            return [$p->id => $label];
                                        })->toArray())
                                        ->bulkToggleable()
                                        ->columns(4)
                                        ->dehydrated(false)
                                        ->afterStateHydrated(function (Forms\Components\CheckboxList $component, ?User $record) use ($moduleName) {
                                            if ($record) {
                                                $hasPermissions = $record->permissions()
                                                    ->where('module_name', $moduleName)
                                                    ->pluck('permissions.id')
                                                    ->toArray();
                                                $component->state($hasPermissions);
                                            }
                                        }),
                                ])
                                ->compact()
                                ->collapsible();
                        }
                        return $schema;
                    }),
            ]);
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
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
