<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserDeviceAssignmentResource\Pages;
use App\Filament\Resources\UserDeviceAssignmentResource\RelationManagers;
use App\Models\UserDeviceAssignment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserDeviceAssignmentResource extends Resource
{
    protected static ?string $model = UserDeviceAssignment::class;

    protected static ?string $navigationIcon = 'heroicon-o-link';
    
    protected static ?string $navigationGroup = 'Users & Access';
    
    protected static ?int $navigationSort = 2;
    
    protected static ?string $navigationLabel = 'Device Assignments';
    
    protected static ?string $modelLabel = 'Device Assignment';
    
    protected static ?string $pluralModelLabel = 'Device Assignments';

    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Assignment Details')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->name} ({$record->email}) - {$record->role}"),
                        Forms\Components\Select::make('device_id')
                            ->relationship('device', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->name} (ID: {$record->slave_id}) - {$record->gateway->name}"),
                        Forms\Components\DateTimePicker::make('assigned_at')
                            ->default(now())
                            ->required(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.role')
                    ->label('Role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'admin' => 'success',
                        'operator' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('device.name')
                    ->label('Device')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('device.gateway.name')
                    ->label('Gateway')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('assigned_at')
                    ->label('Assigned At')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('assignedBy.name')
                    ->label('Assigned By')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('device_id')
                    ->relationship('device', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('bulk_assign')
                        ->label('Bulk Assign to User')
                        ->icon('heroicon-o-user-plus')
                        ->form([
                            Forms\Components\Select::make('user_id')
                                ->label('Assign to User')
                                ->relationship('user', 'name')
                                ->required()
                                ->searchable()
                                ->preload(),
                        ])
                        ->action(function (array $data, $records) {
                            foreach ($records as $record) {
                                $record->update([
                                    'user_id' => $data['user_id'],
                                    'assigned_by' => auth()->id(),
                                    'assigned_at' => now(),
                                ]);
                            }
                        })
                        ->requiresConfirmation(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->defaultSort('assigned_at', 'desc');
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
            'index' => Pages\ListUserDeviceAssignments::route('/'),
            'create' => Pages\CreateUserDeviceAssignment::route('/create'),
            'edit' => Pages\EditUserDeviceAssignment::route('/{record}/edit'),
        ];
    }    
}
