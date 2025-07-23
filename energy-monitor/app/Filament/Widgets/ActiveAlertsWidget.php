<?php

namespace App\Filament\Widgets;

use App\Models\Alert;
use App\Models\Gateway;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Actions\Action;
use Filament\Support\Enums\FontWeight;

class ActiveAlertsWidget extends BaseWidget
{
    protected static ?string $heading = 'Active Alerts';
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';
    
    public ?int $gatewayId = null;

    public function mount($gatewayId = null): void
    {
        $this->gatewayId = $gatewayId;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('device.name')
                    ->label('Device')
                    ->weight(FontWeight::Medium)
                    ->sortable(),
                    
                TextColumn::make('parameter_name')
                    ->label('Parameter')
                    ->sortable(),
                    
                TextColumn::make('value')
                    ->label('Value')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(fn (Alert $record): string => ' ' . ($record->device->registers()
                        ->where('parameter_name', $record->parameter_name)
                        ->first()?->unit ?? ''))
                    ->sortable(),
                    
                TextColumn::make('severity')
                    ->label('Severity')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'critical' => 'danger',
                        'high' => 'danger', 
                        'warning' => 'warning',
                        'medium' => 'warning',
                        'info' => 'info',
                        'low' => 'success',
                        null => 'gray',
                        default => 'gray',
                    })
                    ->icon(fn (?string $state): string => match ($state) {
                        'critical', 'high' => 'heroicon-o-exclamation-triangle',
                        'warning', 'medium' => 'heroicon-o-exclamation-circle', 
                        'info', 'low' => 'heroicon-o-information-circle',
                        null => 'heroicon-o-question-mark-circle',
                        default => 'heroicon-o-question-mark-circle',
                    }),
                    
                TextColumn::make('timestamp')
                    ->label('Time')
                    ->dateTime('M d, H:i')
                    ->sortable(),
                    
                TextColumn::make('message')
                    ->label('Message')
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    }),
            ])
            ->actions([
                Action::make('resolve')
                    ->label('Resolve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Alert $record): void {
                        $alertService = app(\App\Services\AlertService::class);
                        $alertService->resolveAlert($record->id, auth()->id());
                        $this->dispatch('$refresh');
                    })
                    ->visible(fn (Alert $record): bool => !$record->resolved),
            ])
            ->defaultSort('timestamp', 'desc')
            ->emptyStateHeading('No Active Alerts')
            ->emptyStateDescription('All systems are operating normally.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->poll('30s'); // Auto-refresh every 30 seconds
    }

    protected function getTableQuery(): ?\Illuminate\Database\Eloquent\Builder
    {
        $gateway = $this->gatewayId ? Gateway::find($this->gatewayId) : Gateway::first();
        
        if (!$gateway) {
            return Alert::query()->whereRaw('1 = 0'); // Return empty query
        }

        return Alert::query()
            ->whereIn('device_id', $gateway->devices()->pluck('id'))
            ->where('resolved', false)
            ->with(['device', 'device.registers']);
    }
}
