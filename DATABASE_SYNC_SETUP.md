# Database Synchronization Setup Guide

This guide will help you set up database synchronization between your local development environment and your Ubuntu production server.

## Quick Start

1. **Test your setup**:
   ```powershell
   .\quick-db-sync.ps1 -Test
   ```

2. **Push local database to server**:
   ```powershell
   .\quick-db-sync.ps1 -Push
   ```

3. **Pull server database to local**:
   ```powershell
   .\quick-db-sync.ps1 -Pull
   ```

## Available Scripts

### 1. `setup-database-sync.ps1` - Main Setup Script
- `-Setup`: Initial configuration and testing
- `-TestLocal`: Test local database connection
- `-TestServer`: Test server database connection
- `-SyncToServer`: Push local database to server
- `-SyncFromServer`: Pull server database to local

### 2. `quick-db-sync.ps1` - Simple Interface
- `-Test`: Test both database connections
- `-Push`: Push local database to server
- `-Pull`: Pull server database to local

### 3. `production-sync.ps1` - Complete Production Sync
- `-SyncCode`: Sync code only
- `-SyncDatabase`: Sync database to server
- `-DeployAll`: Complete deployment
- `-Status`: Check server status
- `-Logs`: View server logs

## Current Configuration

Based on your `.env` files, here's your current setup:

### Local Database
- **Host**: 127.0.0.1
- **Database**: energy_monitor
- **Username**: root
- **Password**: (empty)

### Server Database
- **Host**: 165.22.112.94
- **Database**: energy_monitor
- **Username**: root
- **Password**: 2tDEoBWefYLp.PYyPF

## Initial Setup Steps

### 1. Run Initial Setup
```powershell
.\setup-database-sync.ps1 -Setup
```

This will:
- Test SSH connection to server
- Test local database connection
- Test server database connection
- Create backup directory
- Verify database existence

### 2. Test Individual Components
```powershell
# Test local database
.\setup-database-sync.ps1 -TestLocal

# Test server database
.\setup-database-sync.ps1 -TestServer
```

### 3. Create Database if Needed

If databases don't exist, create them:

**Local (MySQL command line)**:
```sql
CREATE DATABASE energy_monitor;
```

**Server (SSH to server, then MySQL)**:
```bash
ssh root@165.22.112.94
mysql -u root -p
```
```sql
CREATE DATABASE energy_monitor;
```

## Daily Workflow

### Development Workflow
1. Make changes to your local database (migrations, data changes)
2. Test locally
3. Push to server:
   ```powershell
   .\quick-db-sync.ps1 -Push
   ```

### Getting Latest Data
If you need fresh data from production:
```powershell
.\quick-db-sync.ps1 -Pull
```

### Complete Deployment
For major updates (code + database):
```powershell
.\production-sync.ps1 -DeployAll
```

## Safety Features

### Automatic Backups
All sync operations create backups:
- Local backups stored in `backups/` directory
- Server backups stored on server as `backup_server_[timestamp].sql`

### Backup Files
- `backup_local_[timestamp].sql` - Local database backup
- `backup_server_[timestamp].sql` - Server database backup
- `local_dump_[timestamp].sql` - Local export for server import
- `server_dump_[timestamp].sql` - Server export for local import

## Troubleshooting

### Common Issues

1. **SSH Connection Failed**
   - Verify server IP: `165.22.112.94`
   - Test SSH: `ssh root@165.22.112.94`
   - Check SSH keys or password authentication

2. **Local Database Connection Failed**
   - Ensure MySQL is running locally
   - Check if root user has no password (as per .env)
   - Verify database exists: `SHOW DATABASES;`

3. **Server Database Connection Failed**
   - Verify server database password
   - Check if MySQL is running on server
   - Ensure database exists on server

4. **Permission Issues**
   - Local: Ensure MySQL user has proper permissions
   - Server: Check file permissions for backup operations

### Manual Database Operations

**Create local backup**:
```powershell
mysqldump -u root energy_monitor > manual_backup.sql
```

**Restore from backup**:
```powershell
mysql -u root energy_monitor < manual_backup.sql
```

**SSH to server for manual operations**:
```bash
ssh root@165.22.112.94
cd /MBusFilamentPHP
```

## Security Notes

- Database passwords are configured in scripts (not ideal for production)
- Consider using environment variables for sensitive data
- Regular backups are created automatically
- Server backups are cleaned up after transfer

## File Structure

```
├── setup-database-sync.ps1    # Main setup and sync script
├── quick-db-sync.ps1          # Simple interface
├── production-sync.ps1        # Complete production sync
├── full-sync.ps1             # Full synchronization
├── sync-database.sh          # Bash version
├── backups/                  # Local backup directory
└── DATABASE_SYNC_SETUP.md    # This guide
```

## Next Steps

1. Run the initial setup: `.\setup-database-sync.ps1 -Setup`
2. Test connections: `.\quick-db-sync.ps1 -Test`
3. Try a sync operation: `.\quick-db-sync.ps1 -Push` or `.\quick-db-sync.ps1 -Pull`
4. Integrate into your daily workflow

For questions or issues, check the troubleshooting section or examine the script output for specific error messages.