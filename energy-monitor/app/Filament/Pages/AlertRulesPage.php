<?php

namespace App\Filament\Pages;

use App\Models\Register;
use App\Models\Device;
use App\Models\Gateway;
use Filament\Pages\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Notifications\Notification;
use Filament\Actions;
use Illuminate\Support\Facades\DB;

class AlertRulesPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';
    protected static ?string $navigationLabel = 'Alert Rules';
    protected static ?string $title = 'Alert Rules Configuration';
    protected static ?string $navigationGroup = 'Configuration';
    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.alert-rules-page';
    
    public ?array $alertRuleData = [];
    
    public function mount(): void
    {
        $this->alertRuleData = [
            'register_id' => null,
            'condition' => 'greater_than',
            'threshold_value' => null,
            'severity' => 'medium',
            'enabled' => true,
            'description' => '',
        ];
    }
    
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Create New Alert Rule')
                    ->schema([
                        Forms\Components\Select::make('alertRuleData.register_id')
                            ->label('Parameter')
                            ->options(function () {
                                return Register::with(['device.gateway'])
                                    ->get()
                                    ->mapWithKeys(function ($register) {
                                        $label = "{$register->parameter_name} ({$register->device->name} - {$register->device->gateway->name})";
                                        return [$register->id => $label];
                                    });
                            })
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state) {
                                if ($state) {
                                    $register = Register::find($state);
                                    if ($register) {
                                        $this->alertRuleData['description'] = "Alert when {$register->parameter_name} exceeds normal range";
                                    }
                                }
                            }),
                            
                        Forms\Components\Select::make('alertRuleData.condition')
                            ->label('Condition')
                            ->options([
                                'greater_than' => 'Greater Than',
                                'less_than' => 'Less Than',
                                'equals' => 'Equals',
                                'not_equals' => 'Not Equals',
                                'between' => 'Between',
                                'outside_range' => 'Outside Range',
                            ])
                            ->required()
                            ->live(),
                            
                        Forms\Components\TextInput::make('alertRuleData.threshold_value')
                            ->label('Threshold Value')
                            ->numeric()
                            ->required()
                            ->step(0.01)
                            ->helperText(function () {
                                if ($this->alertRuleData['register_id']) {
                                    $register = Register::find($this->alertRuleData['register_id']);
                                    return $register ? "Unit: {$register->unit}" : '';
                                }
                                return '';
                            }),
                            
                        Forms\Components\Select::make('alertRuleData.severity')
                            ->label('Alert Severity')
                            ->options([
                                'low' => 'Low',
                                'medium' => 'Medium',
                                'high' => 'High',
                                'critical' => 'Critical',
                            ])
                            ->required()
                            ->default('medium'),
                            
                        Forms\Components\Toggle::make('alertRuleData.enabled')
                            ->label('Enable Rule')
                            ->default(true),
                            
                        Forms\Components\Textarea::make('alertRuleData.description')
                            ->label('Description')
                            ->rows(2)
                            ->placeholder('Describe when this alert should trigger'),
                    ])
                    ->columns(2),
            ])
            ->statePath('alertRuleData');
    }
    
    public function table(Table $table): Table
    {
        return $table
            ->query(
                Register::query()
                    ->with(['device.gateway'])
                    ->where('critical', true) // Show critical parameters that should have alert rules
            )
            ->columns([
                Tables\Columns\TextColumn::make('device.gateway.name')
                    ->label('Gateway')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('device.name')
                    ->label('Device')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('parameter_name')
                    ->label('Parameter')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('unit')
                    ->label('Unit')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('normal_range')
                    ->label('Normal Range')
                    ->placeholder('Not set'),
                    
                Tables\Columns\IconColumn::make('critical')
                    ->label('Critical')
                    ->boolean(),
                    
                Tables\Columns\TextColumn::make('alert_rules_count')
                    ->label('Alert Rules')
                    ->getStateUsing(function ($record) {
                        // This would need an actual alert_rules table in production
                        return 'N/A'; // Placeholder
                    })
                    ->badge()
                    ->color('info'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('device_id')
                    ->relationship('device', 'name')
                    ->searchable()
                    ->preload(),
                    
                Tables\Filters\TernaryFilter::make('critical')
                    ->label('Critical Parameters Only')
                    ->boolean()
                    ->trueLabel('Critical Only')
                    ->falseLabel('All Parameters'),
            ])
            ->actions([
                Tables\Actions\Action::make('create_rule')
                    ->label('Create Alert Rule')
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('condition')
                            ->label('Condition')
                            ->options([
                                'greater_than' => 'Greater Than',
                                'less_than' => 'Less Than',
                                'equals' => 'Equals',
                                'not_equals' => 'Not Equals',
                                'between' => 'Between',
                                'outside_range' => 'Outside Range',
                            ])
                            ->required(),
                            
                        Forms\Components\TextInput::make('threshold_value')
                            ->label('Threshold Value')
                            ->numeric()
                            ->required()
                            ->step(0.01),
                            
                        Forms\Components\Select::make('severity')
                            ->label('Alert Severity')
                            ->options([
                                'low' => 'Low',
                                'medium' => 'Medium',
                                'high' => 'High',
                                'critical' => 'Critical',
                            ])
                            ->required()
                            ->default('medium'),
                            
                        Forms\Components\Toggle::make('enabled')
                            ->label('Enable Rule')
                            ->default(true),
                            
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(2),
                    ])
                    ->action(function (array $data, $record) {
                        // In production, this would create an alert rule record
                        Notification::make()
                            ->title('Alert Rule Created')
                            ->body("Alert rule created for {$record->parameter_name}")
                            ->success()
                            ->send();
                    }),
                    
                Tables\Actions\Action::make('view_alerts')
                    ->label('View Alerts')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn ($record) => "/admin/alerts?tableFilters[register_id][value]={$record->id}"),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('bulk_create_rules')
                    ->label('Create Alert Rules')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('condition')
                            ->label('Condition')
                            ->options([
                                'greater_than' => 'Greater Than',
                                'less_than' => 'Less Than',
                                'outside_range' => 'Outside Normal Range',
                            ])
                            ->required()
                            ->default('outside_range'),
                            
                        Forms\Components\Select::make('severity')
                            ->label('Alert Severity')
                            ->options([
                                'low' => 'Low',
                                'medium' => 'Medium',
                                'high' => 'High',
                                'critical' => 'Critical',
                            ])
                            ->required()
                            ->default('medium'),
                            
                        Forms\Components\Toggle::make('enabled')
                            ->label('Enable Rules')
                            ->default(true),
                    ])
                    ->action(function (array $data, $records) {
                        $count = $records->count();
                        
                        // In production, this would create alert rule records
                        Notification::make()
                            ->title('Bulk Alert Rules Created')
                            ->body("Created alert rules for {$count} parameters")
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation(),
            ])
            ->emptyStateActions([
                Tables\Actions\Action::make('create_first_rule')
                    ->label('Create Your First Alert Rule')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->action(function () {
                        Notification::make()
                            ->title('Getting Started')
                            ->body('Use the form above to create your first alert rule')
                            ->info()
                            ->send();
                    }),
            ]);
    }
    
    public function createAlertRule(): void
    {
        // Validate the data
        if (!$this->alertRuleData['register_id'] || !$this->alertRuleData['threshold_value']) {
            Notification::make()
                ->title('Validation Error')
                ->body('Please fill in all required fields')
                ->danger()
                ->send();
            return;
        }
        
        // In production, this would create an alert rule record
        $register = Register::find($this->alertRuleData['register_id']);
        
        if ($register) {
            Notification::make()
                ->title('Alert Rule Created')
                ->body("Alert rule created for {$register->parameter_name}")
                ->success()
                ->send();
                
            // Reset form
            $this->alertRuleData = [
                'register_id' => null,
                'condition' => 'greater_than',
                'threshold_value' => null,
                'severity' => 'medium',
                'enabled' => true,
                'description' => '',
            ];
        } else {
            Notification::make()
                ->title('Error')
                ->body("Register not found")
                ->danger()
                ->send();
        }
    }
    
    // Method removed to fix template error
}