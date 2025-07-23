<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GatewayResource\Pages;
use App\Filament\Resources\GatewayResource\RelationManagers;
use App\Models\Gateway;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class GatewayResource extends Resource
{
    protected static ?string $model = Gateway::class;

    protected static ?string $navigationIcon = 'heroicon-o-signal';
    
    protected static ?string $navigationGroup = 'Configuration';
    
    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        
        if (auth()->user()?->isOperator()) {
            // Operators can only see gateways that have assigned devices
            $assignedDeviceIds = auth()->user()->getAssignedDevices()->pluck('id');
            $query->whereHas('devices', function ($q) use ($assignedDeviceIds) {
                $q->whereIn('id', $assignedDeviceIds);
            });
        }
        
        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('fixed_ip')
                    ->required()
                    ->label('Fixed IP Address')
                    ->placeholder('192.168.1.100')
                    ->maxLength(255),
                Forms\Components\TextInput::make('sim_number')
                    ->label('SIM Number')
                    ->maxLength(255),
                Forms\Components\TextInput::make('gsm_signal')
                    ->label('GSM Signal Strength')
                    ->numeric()
                    ->suffix('dBm'),
                Forms\Components\TextInput::make('gnss_location')
                    ->label('GNSS Location')
                    ->placeholder('Latitude, Longitude')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('fixed_ip')
                    ->label('Fixed IP')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sim_number')
                    ->label('SIM Number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('gsm_signal')
                    ->label('GSM Signal')
                    ->suffix(' dBm')
                    ->sortable(),
                Tables\Columns\TextColumn::make('gnss_location')
                    ->label('GNSS Location')
                    ->limit(30),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
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
            'index' => Pages\ListGateways::route('/'),
            'create' => Pages\CreateGateway::route('/create'),
            'edit' => Pages\EditGateway::route('/{record}/edit'),
        ];
    }    
}
