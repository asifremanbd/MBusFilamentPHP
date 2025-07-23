# Configuration Guide - Energy Monitor

## Environment Variables

Add these variables to your `.env` file:

### Basic Configuration
```env
APP_NAME="Energy Monitor"
APP_ENV=local
APP_KEY=base64:your-app-key-here
APP_DEBUG=true
APP_URL=http://localhost
```

### Database Configuration
```env
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

### Queue Configuration
```env
QUEUE_CONNECTION=database
```

### Email Configuration
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="${APP_NAME}"
```

### SMS Configuration (Vonage)
```env
VONAGE_API_KEY=your_vonage_api_key
VONAGE_API_SECRET=your_vonage_api_secret
VONAGE_SMS_FROM="EnergyMonitor"
```

## SMS Setup Instructions

1. **Sign up for Vonage**:
   - Go to https://dashboard.nexmo.com/sign-up
   - Create a new account

2. **Get API Credentials**:
   - Navigate to Settings > API Settings
   - Copy your API Key and API Secret

3. **Configure in Laravel**:
   - Add the credentials to your `.env` file
   - Update the `VONAGE_SMS_FROM` with your sender ID

## Queue Worker

To process notifications in the background:

```bash
# Start the queue worker
php artisan queue:work

# For production, use supervisor or similar process manager
```

## User Notification Preferences

Users can configure their notification preferences:

- **Email Notifications**: Enable/disable email alerts
- **SMS Notifications**: Enable/disable SMS alerts (requires phone number)
- **Critical Only**: Only receive critical alerts

## Alert Severity Levels

- **Critical**: System failures, extreme values (always sent)
- **Warning**: Values outside normal range
- **Info**: Off-hours activity, general notifications

## Testing Notifications

```bash
# Test email configuration
php artisan tinker
Mail::raw('Test email', function($message) {
    $message->to('test@example.com')->subject('Test');
});

# Test SMS configuration (requires valid phone number)
php artisan tinker
use App\Models\User;
$user = User::first();
$user->phone = '+1234567890';
$user->save();
```

## Production Considerations

1. **Use Redis for Queues**: Better performance than database queues
2. **Configure Horizon**: For queue monitoring (Laravel Horizon)
3. **Set up Supervisor**: To keep queue workers running
4. **Configure Logging**: Proper log rotation and monitoring
5. **SSL Certificate**: Enable HTTPS for production 