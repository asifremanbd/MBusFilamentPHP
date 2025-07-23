<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RegisterResource\Pages;
use App\Filament\Resources\RegisterResource\RelationManagers;
use App\Models\Register;
use App\Models\Device;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RegisterResource extends Resource
{
    protected static ?string $model = Register::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    
    protected static ?string $navigationGroup = 'Configuration';
    
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('device_id')
                    ->relationship('device', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('parameter_name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Voltage (L-N)'),
                Forms\Components\TextInput::make('register_address')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->placeholder('40001'),
                Forms\Components\Select::make('data_type')
                    ->required()
                    ->options([
                        'float' => 'Float',
                        'int' => 'Integer',
                        'uint16' => 'Unsigned 16-bit',
                        'uint32' => 'Unsigned 32-bit',
                    ]),
                Forms\Components\TextInput::make('unit')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('V, A, kW, kWh'),
                Forms\Components\TextInput::make('scale')
                    ->required()
                    ->numeric()
                    ->default(1.0)
                    ->step(0.0001),
                Forms\Components\TextInput::make('normal_range')
                    ->maxLength(255)
                    ->placeholder('220-240'),
                Forms\Components\Toggle::make('critical')
                    ->required(),
                Forms\Components\Textarea::make('notes')
                    ->maxLength(65535)
                    ->columnSpanFull(),
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
                Tables\Columns\TextColumn::make('register_address')
                    ->label('Address')
                    ->sortable(),
                Tables\Columns\TextColumn::make('data_type')
                    ->label('Data Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'float' => 'success',
                        'int' => 'info',
                        'uint16' => 'warning',
                        'uint32' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('unit')
                    ->searchable(),
                Tables\Columns\TextColumn::make('scale')
                    ->sortable(),
                Tables\Columns\TextColumn::make('normal_range')
                    ->label('Normal Range')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('critical')
                    ->boolean(),
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
            'index' => Pages\ListRegisters::route('/'),
            'create' => Pages\CreateRegister::route('/create'),
            'edit' => Pages\EditRegister::route('/{record}/edit'),
        ];
    }    
}
