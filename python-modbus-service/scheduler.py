#!/usr/bin/env python3
"""
Scheduler for Modbus Polling Service
Runs the polling service every 30 minutes
"""

import os
import sys
import logging
from datetime import datetime
from apscheduler.schedulers.blocking import BlockingScheduler
from apscheduler.triggers.cron import CronTrigger
from dotenv import load_dotenv
from poller import ModbusPoller

# Load environment variables
load_dotenv()

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('scheduler.log'),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)

class ModbusScheduler:
    """Scheduler for Modbus polling service"""
    
    def __init__(self):
        self.scheduler = BlockingScheduler()
        self.poller = None
        self.setup_poller()
    
    def setup_poller(self):
        """Initialize the Modbus poller"""
        try:
            config_file = os.getenv('MODBUS_CONFIG', 'config.json')
            api_url = os.getenv('LARAVEL_API_URL', 'http://localhost:8000/api/readings')
            
            self.poller = ModbusPoller(config_file=config_file, api_url=api_url)
            
            if not self.poller.devices:
                logger.error("No devices configured. Please check your config.json file.")
                sys.exit(1)
                
            logger.info(f"Initialized poller with {len(self.poller.devices)} devices")
            
        except Exception as e:
            logger.error(f"Failed to initialize poller: {e}")
            sys.exit(1)
    
    def run_polling_job(self):
        """Execute the polling job"""
        try:
            logger.info("Starting scheduled polling cycle")
            start_time = datetime.now()
            
            success = self.poller.poll_all_devices()
            
            end_time = datetime.now()
            duration = (end_time - start_time).total_seconds()
            
            if success:
                logger.info(f"Polling cycle completed successfully in {duration:.2f} seconds")
            else:
                logger.error(f"Polling cycle failed after {duration:.2f} seconds")
                
        except Exception as e:
            logger.error(f"Error in polling job: {e}")
    
    def start_scheduler(self):
        """Start the scheduler with 30-minute intervals"""
        try:
            # Add the polling job to run every 30 minutes
            self.scheduler.add_job(
                func=self.run_polling_job,
                trigger=CronTrigger(minute='*/30'),  # Every 30 minutes
                id='modbus_polling',
                name='Modbus Polling Job',
                replace_existing=True
            )
            
            # Also run immediately on startup
            self.scheduler.add_job(
                func=self.run_polling_job,
                trigger='date',  # Run once immediately
                id='initial_poll',
                name='Initial Polling Job'
            )
            
            logger.info("Scheduler configured to run every 30 minutes")
            logger.info("Starting scheduler...")
            
            # Start the scheduler
            self.scheduler.start()
            
        except Exception as e:
            logger.error(f"Failed to start scheduler: {e}")
            sys.exit(1)
    
    def stop_scheduler(self):
        """Stop the scheduler gracefully"""
        try:
            logger.info("Stopping scheduler...")
            self.scheduler.shutdown()
            logger.info("Scheduler stopped")
        except Exception as e:
            logger.error(f"Error stopping scheduler: {e}")

def main():
    """Main entry point"""
    logger.info("Starting Modbus Polling Scheduler")
    
    # Create and start scheduler
    scheduler = ModbusScheduler()
    
    try:
        scheduler.start_scheduler()
    except KeyboardInterrupt:
        logger.info("Received interrupt signal")
        scheduler.stop_scheduler()
    except Exception as e:
        logger.error(f"Unexpected error: {e}")
        scheduler.stop_scheduler()
        sys.exit(1)

if __name__ == "__main__":
    main() 