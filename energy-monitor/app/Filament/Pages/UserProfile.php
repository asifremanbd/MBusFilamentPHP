<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserProfile extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-user-circle';
    protected static ?string $navigationLabel = 'My Profile';
    protected static ?string $title = 'User Profile';
    protected static ?string $navigationGroup = 'Users & Access';
    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.user-profile';
    
    public ?array $userData = [];
    
    public function mount(): void
    {
        $user = Auth::user();
        
        $this->userData = [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'email_notifications' => $user->email_notifications,
            'sms_notifications' => $user->sms_notifications,
            'notification_critical_only' => $user->notification_critical_only,
            'current_password' => '',
            'new_password' => '',
            'new_password_confirmation' => '',
        ];
    }
    
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Personal Information')
                    ->schema([
                        Forms\Components\TextInput::make('userData.name')
                            ->label('Name')
                            ->required(),
                        Forms\Components\TextInput::make('userData.email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique(table: User::class, column: 'email', ignoreRecord: true),
                        Forms\Components\TextInput::make('userData.phone')
                            ->label('Phone Number')
                            ->tel(),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('Notification Preferences')
                    ->schema([
                        Forms\Components\Toggle::make('userData.email_notifications')
                            ->label('Enable Email Notifications')
                            ->helperText('Receive alert notifications via email'),
                        Forms\Components\Toggle::make('userData.sms_notifications')
                            ->label('Enable SMS Notifications')
                            ->helperText('Receive alert notifications via SMS (requires phone number)'),
                        Forms\Components\Toggle::make('userData.notification_critical_only')
                            ->label('Critical Alerts Only')
                            ->helperText('Only receive critical severity alerts'),
                    ])
                    ->columns(3),
                
                Forms\Components\Section::make('Change Password')
                    ->schema([
                        Forms\Components\TextInput::make('userData.current_password')
                            ->label('Current Password')
                            ->password()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('userData.new_password')
                            ->label('New Password')
                            ->password()
                            ->dehydrated(false)
                            ->minLength(8)
                            ->confirmed(),
                        Forms\Components\TextInput::make('userData.new_password_confirmation')
                            ->label('Confirm New Password')
                            ->password()
                            ->dehydrated(false)
                            ->minLength(8),
                    ])
                    ->columns(2),
            ])
            ->statePath('userData');
    }
    
    public function getFormActions(): array
    {
        return [
            Forms\Components\Actions\Action::make('save')
                ->label('Save Changes')
                ->submit('save'),
        ];
    }
    
    public function save(): void
    {
        $user = Auth::user();
        
        // Validate the form data
        $data = $this->form->getState();
        
        // Store old preferences for logging
        $oldPreferences = [
            'email_notifications' => $user->email_notifications,
            'sms_notifications' => $user->sms_notifications,
            'notification_critical_only' => $user->notification_critical_only,
        ];
        
        // Update user information
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->phone = $data['phone'];
        $user->email_notifications = $data['email_notifications'];
        $user->sms_notifications = $data['sms_notifications'];
        $user->notification_critical_only = $data['notification_critical_only'];
        
        // Update password if provided
        if (!empty($data['current_password']) && !empty($data['new_password'])) {
            if (!Hash::check($data['current_password'], $user->password)) {
                Notification::make()
                    ->title('Current password is incorrect')
                    ->danger()
                    ->send();
                
                return;
            }
            
            $user->password = Hash::make($data['new_password']);
        }
        
        $user->save();
        
        // Log notification preference changes
        $newPreferences = [
            'email_notifications' => $user->email_notifications,
            'sms_notifications' => $user->sms_notifications,
            'notification_critical_only' => $user->notification_critical_only,
        ];
        
        if ($oldPreferences !== $newPreferences) {
            \App\Services\SecurityLogService::logNotificationPreferenceChange(
                $user,
                $oldPreferences,
                $newPreferences
            );
        }
        
        Notification::make()
            ->title('Profile updated successfully')
            ->success()
            ->send();
    }
}
