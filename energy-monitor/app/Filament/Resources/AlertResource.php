<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AlertResource\Pages;
use App\Filament\Resources\AlertResource\RelationManagers;
use App\Models\Alert;
use App\Models\Device;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AlertResource extends Resource
{
    protected static ?string $model = Alert::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';
    
    protected static ?string $navigationGroup = 'Monitoring & Alerts';
    
    protected static ?int $navigationSort = 1;
    
    public static function canCreate(): bool
    {
        return false; // Alerts are created automatically by the system
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        
        if (auth()->user()?->isOperator()) {
            // Operators can only see alerts for their assigned devices
            $assignedDeviceIds = auth()->user()->getAssignedDevices()->pluck('id');
            $query->whereIn('device_id', $assignedDeviceIds);
        }
        
        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('device_id')
                    ->relationship('device', 'name')
                    ->required()
                    ->disabled(),
                Forms\Components\TextInput::make('parameter_name')
                    ->required()
                    ->disabled(),
                Forms\Components\TextInput::make('value')
                    ->required()
                    ->numeric()
                    ->disabled(),
                Forms\Components\Select::make('severity')
                    ->required()
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                        'critical' => 'Critical',
                    ])
                    ->disabled(),
                Forms\Components\DateTimePicker::make('timestamp')
                    ->required()
                    ->disabled(),
                Forms\Components\Toggle::make('resolved')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('device.name')
                    ->label('Device')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('parameter_name')
                    ->label('Parameter')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('value')
                    ->label('Value')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                Tables\Columns\TextColumn::make('severity')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'low' => 'success',
                        'medium' => 'warning',
                        'high' => 'danger',
                        'critical' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('timestamp')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\IconColumn::make('resolved')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('device_id')
                    ->relationship('device', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('severity')
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                        'critical' => 'Critical',
                    ]),
                TernaryFilter::make('resolved')
                    ->label('Resolved')
                    ->boolean()
                    ->trueLabel('Resolved')
                    ->falseLabel('Unresolved')
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->form([
                        Forms\Components\Toggle::make('resolved')
                            ->label('Mark as Resolved')
                            ->required(),
                    ]),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('resolve')
                        ->label('Mark as Resolved')
                        ->icon('heroicon-o-check')
                        ->action(function ($records) {
                            $records->each->update(['resolved' => true]);
                        })
                        ->requiresConfirmation(),
                ]),
            ])
            ->emptyStateActions([
                // No create action for system-generated alerts
            ])
            ->defaultSort('timestamp', 'desc');
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
            'index' => Pages\ListAlerts::route('/'),
            'create' => Pages\CreateAlert::route('/create'),
            'edit' => Pages\EditAlert::route('/{record}/edit'),
        ];
    }    
}
