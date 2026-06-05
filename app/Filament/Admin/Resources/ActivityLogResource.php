<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ActivityLogResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    public static function getNavigationGroup(): ?string
    {
        return __('SYSTEM');
    }

    public static function getModelLabel(): string
    {
        return __('Activity Log');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Activity Logs');
    }

    public static function getSubjectLabel(?Model $subject): ?string
    {
        if (! $subject) {
            return null;
        }

        // Try common naming fields in order of preference
        foreach (['document_number', 'receiving_number', 'doc_no', 'name', 'username', 'bank_name', 'account_number', 'title', 'initial'] as $field) {
            if (!empty($subject->{$field})) {
                return $subject->{$field};
            }
        }

        return (string) $subject->getKey();
    }

    public static function getSubjectLabelFromRecord($record): ?string
    {
        if (!$record) {
            return null;
        }

        if ($record->subject) {
            return static::getSubjectLabel($record->subject);
        }

        // Fallback for soft-deleted models or unloaded relations
        if ($record->subject_type && $record->subject_id) {
            $type = $record->subject_type;
            if (class_exists($type)) {
                try {
                    $query = $type::query();
                    if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($type))) {
                        $query->withTrashed();
                    }
                    $model = $query->find($record->subject_id);
                    if ($model) {
                        return static::getSubjectLabel($model);
                    }
                } catch (\Exception $e) {
                    // Ignore query or database errors
                }
            }
        }

        return $record->subject_id;
    }

    public static function resolveIdToName(string $key, $value): ?string
    {
        $relation = str_replace('_id', '', $key);
        
        // Map causer/user/idusers to User model
        if ($relation === 'causer' || $relation === 'user' || $relation === 'idusers') {
            $fullModelClass = "App\\Models\\User";
        } else {
            $modelName = Str::studly($relation);
            $fullModelClass = "App\\Models\\{$modelName}";
        }

        if (class_exists($fullModelClass)) {
            try {
                $query = $fullModelClass::query();
                if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($fullModelClass))) {
                    $query->withTrashed();
                }
                $model = $query->find($value);
                if ($model) {
                    return static::getSubjectLabel($model) . " (ID: {$value})";
                }
            } catch (\Exception $e) {
                // Ignore query or database errors
            }
        }
        return null;
    }

    public static function formatProperties(array $properties): array
    {
        $formatted = [];
        foreach ($properties as $key => $value) {
            // Humanize the key name (e.g. supplier_id -> Supplier)
            $humanKey = str_replace('_', ' ', $key);
            $humanKey = Str::title($humanKey);
            
            // Translate the key label if translation is available
            $humanKey = __($humanKey);

            if (is_null($value)) {
                $formatted[$humanKey] = '-';
                continue;
            }

            // If it's a foreign key ID, resolve it to a friendly name
            if (str_ends_with($key, '_id') && is_numeric($value)) {
                $resolved = static::resolveIdToName($key, $value);
                if ($resolved) {
                    $formatted[$humanKey] = $resolved;
                    continue;
                }
            }

            if (is_array($value) || is_object($value)) {
                $formatted[$humanKey] = json_encode($value);
            } elseif (is_bool($value)) {
                $formatted[$humanKey] = $value ? __('Yes') : __('No');
            } else {
                $formatted[$humanKey] = (string) $value;
            }
        }
        return $formatted;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Log Information'))
                    ->columns(2)
                    ->schema([
                        Forms\Components\Placeholder::make('created_at')
                            ->label(__('Timestamp'))
                            ->content(fn ($record) => $record?->created_at?->format('d-M-Y H:i:s')),
                        Forms\Components\Placeholder::make('causer')
                            ->label(__('User/Triggered By'))
                            ->content(fn ($record) => $record?->causer?->name ?? 'System'),
                        Forms\Components\Placeholder::make('description')
                            ->label(__('Event/Action'))
                            ->content(fn ($record) => $record?->description),
                        Forms\Components\Placeholder::make('log_name')
                            ->label(__('Log Name'))
                            ->content(fn ($record) => $record?->log_name),
                        Forms\Components\Placeholder::make('subject_type')
                            ->label(__('Subject Type'))
                            ->content(fn ($record) => class_basename($record?->subject_type)),
                        Forms\Components\Placeholder::make('subject_label')
                            ->label(__('Target / Document No'))
                            ->content(fn ($record) => static::getSubjectLabelFromRecord($record)),
                    ]),

                Forms\Components\Section::make(__('Data Changes'))
                    ->columns(2)
                    ->schema([
                        Forms\Components\KeyValue::make('properties.old')
                            ->label(__('Old Data'))
                            ->keyLabel(__('Field'))
                            ->valueLabel(__('Value'))
                            ->disabled()
                            ->afterStateHydrated(function (Forms\Components\KeyValue $component, $state) {
                                $component->state(static::formatProperties($state ?? []));
                            })
                            ->visible(fn ($record) => !empty($record?->properties['old']))
                            ->columnSpan(fn ($record) => empty($record?->properties['attributes']) ? 2 : 1),
                        Forms\Components\KeyValue::make('properties.attributes')
                            ->label(__('New Data'))
                            ->keyLabel(__('Field'))
                            ->valueLabel(__('Value'))
                            ->disabled()
                            ->afterStateHydrated(function (Forms\Components\KeyValue $component, $state) {
                                $component->state(static::formatProperties($state ?? []));
                            })
                            ->visible(fn ($record) => !empty($record?->properties['attributes']))
                            ->columnSpan(fn ($record) => empty($record?->properties['old']) ? 2 : 1),
                    ])
                    ->visible(fn ($record) => !empty($record?->properties['old']) || !empty($record?->properties['attributes'])),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Timestamp'))
                    ->dateTime('d-M-Y H:i:s')
                    ->sortable(),
                Tables\Columns\TextColumn::make('causer.name')
                    ->label(__('User'))
                    ->default('System')
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->label(__('Event'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('subject_type')
                    ->label(__('Subject'))
                    ->formatStateUsing(fn ($state) => class_basename($state))
                    ->searchable(),
                Tables\Columns\TextColumn::make('subject_label')
                    ->label(__('Target / Document No'))
                    ->getStateUsing(fn ($record) => static::getSubjectLabelFromRecord($record))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function ($q) use ($search) {
                            $q->where('subject_id', 'like', "%{$search}%")
                              ->orWhereHasMorph('subject', [
                                  \App\Models\CattleReceiving::class,
                                  \App\Models\PurchaseCattle::class,
                                  \App\Models\Supplier::class,
                                  \App\Models\User::class,
                                  \App\Models\Customer::class,
                                  \App\Models\CustomerGroup::class,
                                  \App\Models\CustomerSegment::class,
                                  \App\Models\BankAccount::class,
                                  \App\Models\Product::class,
                                  \App\Models\ProductCategory::class,
                                  \App\Models\Material::class,
                                  \App\Models\MaterialCategory::class,
                                  \App\Models\MaterialUnit::class,
                                  \App\Models\CattleClass::class,
                              ], function (Builder $morphQuery, string $type) use ($search) {
                                  if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($type))) {
                                      $morphQuery->withTrashed();
                                  }
                                  
                                  if ($type === \App\Models\CattleReceiving::class) {
                                      $morphQuery->where('receiving_number', 'like', "%{$search}%")
                                                 ->orWhere('doc_no', 'like', "%{$search}%");
                                  } elseif ($type === \App\Models\PurchaseCattle::class) {
                                      $morphQuery->where('document_number', 'like', "%{$search}%");
                                  } elseif ($type === \App\Models\BankAccount::class) {
                                      $morphQuery->where('bank_name', 'like', "%{$search}%")
                                                 ->orWhere('account_number', 'like', "%{$search}%")
                                                 ->orWhere('initial', 'like', "%{$search}%");
                                  } elseif ($type === \App\Models\User::class) {
                                      $morphQuery->where('name', 'like', "%{$search}%")
                                                 ->orWhere('username', 'like', "%{$search}%");
                                  } else {
                                      // All other common models have a 'name' field
                                      $morphQuery->where('name', 'like', "%{$search}%");
                                  }
                              });
                        });
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(
                fn (Model $record): string => Pages\ViewActivityLog::getUrl([$record->getKey()]),
            )
            ->filters([
                Tables\Filters\SelectFilter::make('description')
                    ->label(__('Event'))
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label(__('From Date'))
                            ->default(now()->startOfMonth()),
                        Forms\Components\DatePicker::make('created_until')
                            ->label(__('Until Date'))
                            ->default(now()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                // Clean UI: Clickable rows instead of action buttons
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
            'view' => Pages\ViewActivityLog::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereNotIn('subject_type', [
                'App\Models\PurchaseCattleItem',
                'App\Models\CattleReceivingItem',
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->isProgrammer() || auth()->user()->hasPermission('view_activity_logs');
    }
}
