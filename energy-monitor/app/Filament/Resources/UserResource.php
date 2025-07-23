<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use App\Models\Gateway;
use App\Models\Device;
use App\Models\UserDeviceAssignment;
use App\Models\UserGatewayAssignment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    
    protected static ?string $navigationGroup = 'Users & Access';
    
    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('User Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(20),
                        Forms\Components\Select::make('role')
                            ->options([
                                'admin' => 'Administrator',
                                'operator' => 'Operator',
                            ])
                            ->required()
                            ->default('operator'),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->required(fn (string $context): bool => $context === 'create')
                            ->minLength(8)
                            ->dehydrated(fn ($state) => filled($state))
                            ->dehydrateStateUsing(fn ($state) => bcrypt($state)),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('Notification Preferences')
                    ->schema([
                        Forms\Components\Toggle::make('email_notifications')
                            ->label('Enable Email Notifications')
                            ->default(true)
                            ->helperText('Receive alert notifications via email'),
                        Forms\Components\Toggle::make('sms_notifications')
                            ->label('Enable SMS Notifications')
                            ->default(false)
                            ->helperText('Receive alert notifications via SMS'),
                        Forms\Components\Toggle::make('notification_critical_only')
                            ->label('Critical Alerts Only')
                            ->default(false)
                            ->helperText('Only receive critical severity alerts'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Gateway Permissions')
                    ->schema([
                        Forms\Components\CheckboxList::make('gateway_assignments')
                            ->label('Assigned Gateways')
                            ->options(function () {
                                return Gateway::all()->pluck('name', 'id')->toArray();
                            })
                            ->descriptions(function () {
                                return Gateway::with('devices')
                                    ->get()
                                    ->mapWithKeys(function ($gateway) {
                                        $deviceCount = $gateway->devices->count();
                                        $location = $gateway->gnss_location ? " - {$gateway->gnss_location}" : '';
                                        return [$gateway->id => "{$deviceCount} devices{$location}"];
                                    })
                                    ->toArray();
                            })
                            ->columns(2)
                            ->helperText('Select gateways this user can access. This will automatically grant access to all devices in selected gateways.')
                            ->afterStateHydrated(function (Forms\Components\CheckboxList $component, $state, $record) {
                                if ($record) {
                                    $assignedGateways = UserGatewayAssignment::where('user_id', $record->id)
                                        ->pluck('gateway_id')
                                        ->toArray();
                                    $component->state($assignedGateways);
                                }
                            })
                            ->dehydrated(false),
                        
                        Forms\Components\Toggle::make('include_gateway_devices')
                            ->label('Auto-assign Gateway Devices')
                            ->default(true)
                            ->helperText('Automatically assign all devices within selected gateways')
                            ->live(),
                    ])
                    ->visible(fn (string $context) => $context !== 'create' || auth()->user()?->isAdmin()),

                Forms\Components\Section::make('Device Permissions')
                    ->schema([
                        Forms\Components\Select::make('gateway_filter')
                            ->label('Filter by Gateway')
                            ->options(function () {
                                return ['' => 'All Gateways'] + Gateway::all()->pluck('name', 'id')->toArray();
                            })
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                $set('device_assignments', []);
                            }),
                            
                        Forms\Components\CheckboxList::make('device_assignments')
                            ->label('Assigned Devices')
                            ->options(function (Forms\Get $get) {
                                $gatewayFilter = $get('gateway_filter');
                                $query = Device::with('gateway');
                                
                                if ($gatewayFilter) {
                                    $query->where('gateway_id', $gatewayFilter);
                                }
                                
                                return $query->get()
                                    ->mapWithKeys(function ($device) {
                                        $gatewayName = $device->gateway->name ?? 'Unknown Gateway';
                                        $label = "{$device->name} ({$gatewayName})";
                                        return [$device->id => $label];
                                    })
                                    ->toArray();
                            })
                            ->descriptions(function (Forms\Get $get) {
                                $gatewayFilter = $get('gateway_filter');
                                $query = Device::with(['gateway', 'registers']);
                                
                                if ($gatewayFilter) {
                                    $query->where('gateway_id', $gatewayFilter);
                                }
                                
                                return $query->get()
                                    ->mapWithKeys(function ($device) {
                                        $registerCount = $device->registers->count();
                                        $slaveId = $device->slave_id ? "Slave ID: {$device->slave_id}" : '';
                                        $location = $device->location_tag ? " - {$device->location_tag}" : '';
                                        return [$device->id => "{$registerCount} registers{$location} {$slaveId}"];
                                    })
                                    ->toArray();
                            })
                            ->columns(1)
                            ->searchable()
                            ->helperText('Select specific devices this user can access')
                            ->afterStateHydrated(function (Forms\Components\CheckboxList $component, $state, $record) {
                                if ($record) {
                                    $assignedDevices = UserDeviceAssignment::where('user_id', $record->id)
                                        ->pluck('device_id')
                                        ->toArray();
                                    $component->state($assignedDevices);
                                }
                            })
                            ->dehydrated(false),
                    ])
                    ->visible(fn (string $context) => $context !== 'create' || auth()->user()?->isAdmin()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'admin' => 'success',
                        'operator' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\IconColumn::make('email_notifications')
                    ->label('Email')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('sms_notifications')
                    ->label('SMS')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('notification_critical_only')
                    ->label('Critical Only')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('assigned_gateways_count')
                    ->label('Gateways')
                    ->getStateUsing(function (User $record) {
                        return UserGatewayAssignment::where('user_id', $record->id)->count();
                    })
                    ->badge()
                    ->color('info')
                    ->sortable(false)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('assigned_devices_count')
                    ->label('Devices')
                    ->getStateUsing(function (User $record) {
                        return UserDeviceAssignment::where('user_id', $record->id)->count();
                    })
                    ->badge()
                    ->color('success')
                    ->sortable(false)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options([
                        'admin' => 'Administrator',
                        'operator' => 'Operator',
                    ]),
                Tables\Filters\TernaryFilter::make('email_notifications')
                    ->label('Email Notifications')
                    ->boolean()
                    ->trueLabel('Enabled')
                    ->falseLabel('Disabled'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateRecordDataUsing(function (array $data): array {
                        // Store original data for comparison
                        session(['edit_user_original_data' => $data]);
                        return $data;
                    })
                    ->using(function (User $record, array $data): User {
                        return DB::transaction(function () use ($record, $data) {
                            $originalData = session('edit_user_original_data', []);
                            
                            // Check if role has changed
                            if (isset($originalData['role']) && $originalData['role'] !== $data['role']) {
                                \App\Services\SecurityLogService::logRoleChange(
                                    $record,
                                    $originalData['role'],
                                    $data['role'],
                                    auth()->user()
                                );
                            }
                            
                            // Check if notification preferences have changed
                            $oldPreferences = [
                                'email_notifications' => $originalData['email_notifications'] ?? $record->email_notifications,
                                'sms_notifications' => $originalData['sms_notifications'] ?? $record->sms_notifications,
                                'notification_critical_only' => $originalData['notification_critical_only'] ?? $record->notification_critical_only,
                            ];
                            
                            $newPreferences = [
                                'email_notifications' => $data['email_notifications'],
                                'sms_notifications' => $data['sms_notifications'],
                                'notification_critical_only' => $data['notification_critical_only'],
                            ];
                            
                            if ($oldPreferences !== $newPreferences) {
                                \App\Services\SecurityLogService::logNotificationPreferenceChange(
                                    $record,
                                    $oldPreferences,
                                    $newPreferences,
                                    auth()->user()
                                );
                            }
                            
                            // Update the record
                            $record->update($data);
                            
                            // Handle gateway assignments
                            if (isset($data['gateway_assignments'])) {
                                $currentGateways = UserGatewayAssignment::where('user_id', $record->id)
                                    ->pluck('gateway_id')
                                    ->toArray();
                                $newGateways = $data['gateway_assignments'];
                                
                                // Remove unselected gateways
                                $gatewaysToRemove = array_diff($currentGateways, $newGateways);
                                if (!empty($gatewaysToRemove)) {
                                    UserGatewayAssignment::where('user_id', $record->id)
                                        ->whereIn('gateway_id', $gatewaysToRemove)
                                        ->delete();
                                }
                                
                                // Add new gateways
                                $gatewaysToAdd = array_diff($newGateways, $currentGateways);
                                foreach ($gatewaysToAdd as $gatewayId) {
                                    UserGatewayAssignment::create([
                                        'user_id' => $record->id,
                                        'gateway_id' => $gatewayId,
                                        'assigned_at' => now(),
                                        'assigned_by' => auth()->user()->id,
                                    ]);
                                }
                                
                                // Auto-assign gateway devices if enabled
                                if ($data['include_gateway_devices'] ?? true) {
                                    foreach ($gatewaysToAdd as $gatewayId) {
                                        $gatewayDevices = Device::where('gateway_id', $gatewayId)->pluck('id');
                                        foreach ($gatewayDevices as $deviceId) {
                                            UserDeviceAssignment::firstOrCreate([
                                                'user_id' => $record->id,
                                                'device_id' => $deviceId,
                                            ], [
                                                'assigned_at' => now(),
                                                'assigned_by' => auth()->user()->id,
                                            ]);
                                        }
                                    }
                                }
                            }
                            
                            // Handle device assignments
                            if (isset($data['device_assignments'])) {
                                $currentDevices = UserDeviceAssignment::where('user_id', $record->id)
                                    ->pluck('device_id')
                                    ->toArray();
                                $newDevices = $data['device_assignments'];
                                
                                // Remove unselected devices
                                $devicesToRemove = array_diff($currentDevices, $newDevices);
                                if (!empty($devicesToRemove)) {
                                    UserDeviceAssignment::where('user_id', $record->id)
                                        ->whereIn('device_id', $devicesToRemove)
                                        ->delete();
                                }
                                
                                // Add new devices
                                $devicesToAdd = array_diff($newDevices, $currentDevices);
                                foreach ($devicesToAdd as $deviceId) {
                                    UserDeviceAssignment::create([
                                        'user_id' => $record->id,
                                        'device_id' => $deviceId,
                                        'assigned_at' => now(),
                                        'assigned_by' => auth()->user()->id,
                                    ]);
                                }
                            }
                            
                            return $record;
                        });
                    })
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('User updated')
                            ->body('User permissions have been updated successfully.')
                    ),
                    
                Tables\Actions\Action::make('manage_permissions')
                    ->label('Permissions')
                    ->icon('heroicon-o-key')
                    ->color('info')
                    ->form([
                        Forms\Components\Section::make('Quick Gateway Assignment')
                            ->schema([
                                Forms\Components\CheckboxList::make('gateway_assignments')
                                    ->label('Assigned Gateways')
                                    ->options(Gateway::all()->pluck('name', 'id')->toArray())
                                    ->columns(2),
                            ]),
                        Forms\Components\Section::make('Quick Device Assignment')
                            ->schema([
                                Forms\Components\Select::make('gateway_filter')
                                    ->label('Filter by Gateway')
                                    ->options(['' => 'All Gateways'] + Gateway::all()->pluck('name', 'id')->toArray())
                                    ->live(),
                                Forms\Components\CheckboxList::make('device_assignments')
                                    ->label('Assigned Devices')
                                    ->options(function (Forms\Get $get) {
                                        $gatewayFilter = $get('gateway_filter');
                                        $query = Device::with('gateway');
                                        
                                        if ($gatewayFilter) {
                                            $query->where('gateway_id', $gatewayFilter);
                                        }
                                        
                                        return $query->get()
                                            ->mapWithKeys(function ($device) {
                                                $gatewayName = $device->gateway->name ?? 'Unknown Gateway';
                                                return [$device->id => "{$device->name} ({$gatewayName})"];
                                            })
                                            ->toArray();
                                    })
                                    ->columns(1),
                            ]),
                    ])
                    ->fillForm(function (User $record) {
                        return [
                            'gateway_assignments' => UserGatewayAssignment::where('user_id', $record->id)
                                ->pluck('gateway_id')
                                ->toArray(),
                            'device_assignments' => UserDeviceAssignment::where('user_id', $record->id)
                                ->pluck('device_id')
                                ->toArray(),
                        ];
                    })
                    ->action(function (User $record, array $data) {
                        DB::transaction(function () use ($record, $data) {
                            // Update gateway assignments
                            UserGatewayAssignment::where('user_id', $record->id)->delete();
                            foreach ($data['gateway_assignments'] ?? [] as $gatewayId) {
                                UserGatewayAssignment::create([
                                    'user_id' => $record->id,
                                    'gateway_id' => $gatewayId,
                                    'assigned_at' => now(),
                                    'assigned_by' => auth()->user()->id,
                                ]);
                            }
                            
                            // Update device assignments
                            UserDeviceAssignment::where('user_id', $record->id)->delete();
                            foreach ($data['device_assignments'] ?? [] as $deviceId) {
                                UserDeviceAssignment::create([
                                    'user_id' => $record->id,
                                    'device_id' => $deviceId,
                                    'assigned_at' => now(),
                                    'assigned_by' => auth()->user()->id,
                                ]);
                            }
                        });
                        
                        Notification::make()
                            ->success()
                            ->title('Permissions updated')
                            ->body('User permissions have been updated successfully.')
                            ->send();
                    }),
                    
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (User $record) => auth()->user()->id !== $record->id),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_assign_gateways')
                        ->label('Assign Gateways')
                        ->icon('heroicon-o-server-stack')
                        ->color('info')
                        ->form([
                            Forms\Components\CheckboxList::make('gateway_assignments')
                                ->label('Gateways to Assign')
                                ->options(Gateway::all()->pluck('name', 'id')->toArray())
                                ->descriptions(function () {
                                    return Gateway::with('devices')
                                        ->get()
                                        ->mapWithKeys(function ($gateway) {
                                            $deviceCount = $gateway->devices->count();
                                            $location = $gateway->gnss_location ? " - {$gateway->gnss_location}" : '';
                                            return [$gateway->id => "{$deviceCount} devices{$location}"];
                                        })
                                        ->toArray();
                                })
                                ->columns(2)
                                ->required(),
                            Forms\Components\Toggle::make('include_gateway_devices')
                                ->label('Auto-assign Gateway Devices')
                                ->default(true)
                                ->helperText('Automatically assign all devices within selected gateways'),
                            Forms\Components\Toggle::make('replace_existing')
                                ->label('Replace Existing Assignments')
                                ->default(false)
                                ->helperText('Remove existing gateway assignments before adding new ones'),
                        ])
                        ->action(function ($records, array $data) {
                            DB::transaction(function () use ($records, $data) {
                                foreach ($records as $user) {
                                    // Skip current user to prevent self-modification issues
                                    if ($user->id === auth()->user()->id) {
                                        continue;
                                    }
                                    
                                    if ($data['replace_existing']) {
                                        UserGatewayAssignment::where('user_id', $user->id)->delete();
                                    }
                                    
                                    foreach ($data['gateway_assignments'] as $gatewayId) {
                                        UserGatewayAssignment::firstOrCreate([
                                            'user_id' => $user->id,
                                            'gateway_id' => $gatewayId,
                                        ], [
                                            'assigned_at' => now(),
                                            'assigned_by' => auth()->user()->id,
                                        ]);
                                        
                                        // Auto-assign gateway devices if enabled
                                        if ($data['include_gateway_devices']) {
                                            $gatewayDevices = Device::where('gateway_id', $gatewayId)->pluck('id');
                                            foreach ($gatewayDevices as $deviceId) {
                                                UserDeviceAssignment::firstOrCreate([
                                                    'user_id' => $user->id,
                                                    'device_id' => $deviceId,
                                                ], [
                                                    'assigned_at' => now(),
                                                    'assigned_by' => auth()->user()->id,
                                                ]);
                                            }
                                        }
                                    }
                                }
                            });
                            
                            $userCount = $records->count();
                            $gatewayCount = count($data['gateway_assignments']);
                            
                            Notification::make()
                                ->success()
                                ->title('Bulk assignment completed')
                                ->body("Assigned {$gatewayCount} gateways to {$userCount} users.")
                                ->send();
                        }),
                        
                    Tables\Actions\BulkAction::make('bulk_assign_devices')
                        ->label('Assign Devices')
                        ->icon('heroicon-o-cpu-chip')
                        ->color('success')
                        ->form([
                            Forms\Components\Select::make('gateway_filter')
                                ->label('Filter by Gateway')
                                ->options(['' => 'All Gateways'] + Gateway::all()->pluck('name', 'id')->toArray())
                                ->live(),
                            Forms\Components\CheckboxList::make('device_assignments')
                                ->label('Devices to Assign')
                                ->options(function (Forms\Get $get) {
                                    $gatewayFilter = $get('gateway_filter');
                                    $query = Device::with('gateway');
                                    
                                    if ($gatewayFilter) {
                                        $query->where('gateway_id', $gatewayFilter);
                                    }
                                    
                                    return $query->get()
                                        ->mapWithKeys(function ($device) {
                                            $gatewayName = $device->gateway->name ?? 'Unknown Gateway';
                                            return [$device->id => "{$device->name} ({$gatewayName})"];
                                        })
                                        ->toArray();
                                })
                                ->columns(1)
                                ->searchable()
                                ->required(),
                            Forms\Components\Toggle::make('replace_existing')
                                ->label('Replace Existing Assignments')
                                ->default(false)
                                ->helperText('Remove existing device assignments before adding new ones'),
                        ])
                        ->action(function ($records, array $data) {
                            DB::transaction(function () use ($records, $data) {
                                foreach ($records as $user) {
                                    // Skip current user to prevent self-modification issues
                                    if ($user->id === auth()->user()->id) {
                                        continue;
                                    }
                                    
                                    if ($data['replace_existing']) {
                                        UserDeviceAssignment::where('user_id', $user->id)->delete();
                                    }
                                    
                                    foreach ($data['device_assignments'] as $deviceId) {
                                        UserDeviceAssignment::firstOrCreate([
                                            'user_id' => $user->id,
                                            'device_id' => $deviceId,
                                        ], [
                                            'assigned_at' => now(),
                                            'assigned_by' => auth()->user()->id,
                                        ]);
                                    }
                                }
                            });
                            
                            $userCount = $records->count();
                            $deviceCount = count($data['device_assignments']);
                            
                            Notification::make()
                                ->success()
                                ->title('Bulk assignment completed')
                                ->body("Assigned {$deviceCount} devices to {$userCount} users.")
                                ->send();
                        }),
                        
                    Tables\Actions\BulkAction::make('bulk_remove_permissions')
                        ->label('Remove Permissions')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Remove User Permissions')
                        ->modalDescription('This will remove all gateway and device assignments from the selected users.')
                        ->modalSubmitActionLabel('Remove Permissions')
                        ->action(function ($records) {
                            DB::transaction(function () use ($records) {
                                foreach ($records as $user) {
                                    // Skip current user to prevent self-modification issues
                                    if ($user->id === auth()->user()->id) {
                                        continue;
                                    }
                                    
                                    UserGatewayAssignment::where('user_id', $user->id)->delete();
                                    UserDeviceAssignment::where('user_id', $user->id)->delete();
                                }
                            });
                            
                            $userCount = $records->count();
                            
                            Notification::make()
                                ->success()
                                ->title('Permissions removed')
                                ->body("Removed all permissions from {$userCount} users.")
                                ->send();
                        }),
                        
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function ($records) {
                            $currentUserId = auth()->user()->id;
                            $records->reject(fn ($record) => $record->id === $currentUserId)->each->delete();
                        }),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
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
