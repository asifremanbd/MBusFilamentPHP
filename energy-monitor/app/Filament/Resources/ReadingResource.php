<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReadingResource\Pages;
use App\Filament\Resources\ReadingResource\RelationManagers;
use App\Models\Reading;
use App\Models\Device;
use App\Models\Register;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ReadingResource extends Resource
{
    protected static ?string $model = Reading::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    
    protected static ?string $navigationGroup = 'Monitoring & Alerts';
    
    protected static ?int $navigationSort = 2;
    
    public static function canCreate(): bool
    {
        return false; // Readings are created via API only
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Read-only form - not used since canCreate() returns false
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
                Tables\Columns\TextColumn::make('register.parameter_name')
                    ->label('Parameter')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('value')
                    ->label('Value')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                Tables\Columns\TextColumn::make('register.unit')
                    ->label('Unit')
                    ->searchable(),
                Tables\Columns\TextColumn::make('timestamp')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
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
                SelectFilter::make('register_id')
                    ->relationship('register', 'parameter_name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('timestamp')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('timestamp', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('timestamp', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                // No bulk actions for read-only resource
            ])
            ->emptyStateActions([
                // No create action for read-only resource
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
            'index' => Pages\ListReadings::route('/'),
            'create' => Pages\CreateReading::route('/create'),
            'edit' => Pages\EditReading::route('/{record}/edit'),
        ];
    }    
}
