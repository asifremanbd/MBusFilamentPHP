@echo off
echo === Energy Monitor Quick Start ===
echo.

cd energy-monitor

echo Starting Laravel server...
echo.
echo ╔══════════════════════════════════════════════════════════════╗
echo ║                    🎉 QUICK START GUIDE 🎉                  ║
echo ║                                                              ║
echo ║  1. Open your browser to: http://localhost:8000/setup        ║
echo ║     This will create the admin user and OEMIA gateway        ║
echo ║                                                              ║
echo ║  2. Then go to: http://localhost:8000/admin                  ║
echo ║     Login with: admin@example.com / password                 ║
echo ║                                                              ║
echo ║  3. Open a new terminal and run:                             ║
echo ║     cd python-modbus-service                                 ║
echo ║     python scheduler.py                                      ║
echo ║                                                              ║
echo ║  Press Ctrl+C to stop the server when done                  ║
echo ╚══════════════════════════════════════════════════════════════╝
echo.

php artisan serve

echo.
echo Server stopped. Goodbye!
cd ..
pause