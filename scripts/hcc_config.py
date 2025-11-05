"""
Configuration handler for HCC Playwright Scraper
Reads from Laravel .env file
"""
import os
from pathlib import Path
from dotenv import load_dotenv

class HccConfig:
    def __init__(self):
        # Load .env from parent directory (Laravel root)
        env_path = Path(__file__).parent.parent / '.env'
        load_dotenv(env_path)
        
        # HCC Credentials
        self.username = os.getenv('HCC_USERNAME')
        self.password = os.getenv('HCC_PASSWORD')
        self.login_url = os.getenv('HCC_LOGIN_URL', 'https://www.hik-connect.com/views/login/index.html#/login')
        
        # HCC API
        self.base_url = os.getenv('HCC_BASE_URL', 'https://isgp-team.hikcentralconnect.com')
        self.timezone = os.getenv('HCC_TIMEZONE', 'Asia/Karachi')
        
        # Playwright Settings
        self.headless = os.getenv('PLAYWRIGHT_HEADLESS', 'true').lower() == 'true'
        self.timeout = int(os.getenv('PLAYWRIGHT_TIMEOUT', '30000'))
        self.slow_mo = int(os.getenv('PLAYWRIGHT_SLOW_MO', '0'))
        
        # Laravel API
        self.app_url = os.getenv('APP_URL', 'http://localhost')
        self.laravel_api = f"{self.app_url}/api/playwright"
        
        # Cookie for API calls
        self.cookie = os.getenv('HCC_COOKIE')
        
    def validate(self):
        """Validate required config"""
        if not self.username or not self.password:
            raise ValueError("HCC_USERNAME and HCC_PASSWORD are required in .env file")
        return True
    
    def __repr__(self):
        return f"HccConfig(username={self.username[:3]}***, headless={self.headless})"

