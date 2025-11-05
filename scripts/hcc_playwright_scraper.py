"""
HikCentral Connect Playwright Scraper
Replaces Laravel Dusk with Python Playwright for better stability and performance
"""
import sys
import json
import asyncio
import os
from datetime import datetime, timedelta
from playwright.async_api import async_playwright, Page, Browser, BrowserContext
import requests
from hcc_config import HccConfig

# Fix Windows console encoding for emojis
if sys.platform == 'win32':
    try:
        sys.stdout.reconfigure(encoding='utf-8')
        sys.stderr.reconfigure(encoding='utf-8')
    except:
        # Fallback: disable emojis
        os.environ['PYTHONIOENCODING'] = 'utf-8'

class HccPlaywrightScraper:
    def __init__(self):
        self.config = HccConfig()
        self.config.validate()
        self.browser = None
        self.context = None
        self.page = None
        self.cookies = []
        self.token = None
        
    async def init_browser(self):
        """Initialize Playwright browser"""
        playwright = await async_playwright().start()
        self.browser = await playwright.chromium.launch(
            headless=self.config.headless,
            slow_mo=self.config.slow_mo
        )
        self.context = await self.browser.new_context(
            viewport={'width': 1920, 'height': 1080},
            user_agent='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        )
        self.page = await self.context.new_page()
        self.page.set_default_timeout(self.config.timeout)
        
    async def login(self):
        """Login to HikCentral Connect"""
        print(f"🔐 Logging in to {self.config.login_url}...")
        
        await self.page.goto(self.config.login_url)
        await self.page.wait_for_load_state('networkidle')
        
        print("📝 Filling login form...")
        
        # Click phone radio button (if email, adjust selector)
        try:
            phone_radio = 'label.el-radio-button:has-text("Phone")'
            await self.page.click(phone_radio, timeout=5000)
            await asyncio.sleep(1)
        except:
            print("⚠️  Phone radio not found, assuming already selected")
        
        # Fill username (phone/email)
        username_selector = 'input[placeholder*="phone" i], input[placeholder*="email" i], input[type="text"]'
        await self.page.fill(username_selector, self.config.username)
        
        # Fill password
        password_selector = 'input[type="password"]'
        await self.page.fill(password_selector, self.config.password)
        
        await asyncio.sleep(1)
        
        # Click submit button
        submit_button = 'button[type="submit"], button:has-text("Log in"), button:has-text("Sign in")'
        await self.page.click(submit_button)
        
        print("⏳ Waiting for redirect...")
        
        # Wait for successful login (URL change)
        try:
            await self.page.wait_for_url('**/overview**', timeout=15000)
            print("✅ Login successful!")
        except:
            try:
                await self.page.wait_for_url('**/main**', timeout=5000)
                print("✅ Login successful!")
            except:
                current_url = self.page.url
                if 'login' not in current_url.lower():
                    print(f"✅ Login successful! (at {current_url})")
                else:
                    print(f"⚠️  Warning: Still on login page. Check credentials.")
        
        await asyncio.sleep(3)
        
    async def navigate_to_attendance(self):
        """Navigate to attendance transaction page"""
        print("🗺️  Navigating to attendance page...")
        
        # Click Attendance tab
        await asyncio.sleep(4)
        attendance_tab = '#tab-HCBAttendance'
        try:
            await self.page.click(attendance_tab)
            print("✅ Clicked Attendance tab")
        except:
            print("⚠️  Attendance tab not found with selector, trying alternate...")
            await self.page.click('text=Attendance')
        
        await asyncio.sleep(2)
        
        # Click Attendance Records submenu
        try:
            submenu = '.el-submenu.is-opened div:has-text("Attendance Records")'
            await self.page.click(submenu, timeout=5000)
            print("✅ Clicked Attendance Records")
        except:
            print("⚠️  Submenu click failed, trying to continue...")
        
        await asyncio.sleep(2)
        
        # Click Transaction submenu
        try:
            transaction = '.el-menu-item:has-text("Transaction")'
            await self.page.click(transaction, timeout=5000)
            print("✅ Clicked Transaction")
        except:
            print("⚠️  Transaction click failed")
        
        await asyncio.sleep(5)
        print(f"📍 Current URL: {self.page.url}")
        
    async def get_cookies(self):
        """Extract authentication cookies"""
        print("🍪 Extracting cookies...")
        
        # Login first
        await self.login()
        await self.navigate_to_attendance()
        
        # Visit API domain to get cross-domain cookies
        try:
            await self.page.goto(self.config.base_url, timeout=10000)
            await asyncio.sleep(2)
        except:
            print("⚠️  Could not visit API domain, using current cookies")
        
        # Get all cookies
        cookies = await self.context.cookies()
        self.cookies = cookies
        
        print(f"✅ Found {len(cookies)} cookies")
        
        # Format as cookie string
        cookie_string = '; '.join([f"{c['name']}={c['value']}" for c in cookies])
        
        print("\n" + "="*60)
        print("COPY THIS TO YOUR .env FILE:")
        print("="*60)
        print(f'HCC_COOKIE="{cookie_string}"')
        print("="*60 + "\n")
        
        # Also try to get token from localStorage
        try:
            local_storage = await self.page.evaluate('() => JSON.stringify(localStorage)')
            storage_data = json.loads(local_storage)
            
            # Look for token in various keys
            token_keys = ['token', 'auth_token', 'access_token', 'authorization', 'bearer_token']
            for key in token_keys:
                if key in storage_data:
                    print(f"🔑 Found token in localStorage['{key}']:")
                    print(f'HCC_BEARER_TOKEN="{storage_data[key]}"')
                    print()
                    break
        except:
            print("ℹ️  No token found in localStorage")
        
        return cookie_string
        
    async def intercept_api_calls(self):
        """Setup network interception to capture API responses"""
        self.api_data = []
        
        async def handle_response(response):
            url = response.url
            # Check if this is the attendance API
            if 'hccattendance/report/v1/list' in url:
                print(f"📡 Intercepted API call: {url}")
                try:
                    data = await response.json()
                    self.api_data.append(data)
                    print(f"✅ Captured response with {len(str(data))} characters")
                except Exception as e:
                    print(f"⚠️  Could not parse response: {e}")
        
        self.page.on('response', handle_response)
        
    async def fetch_attendance(self, from_date, to_date):
        """Fetch attendance data for date range"""
        print(f"📅 Fetching attendance from {from_date} to {to_date}...")
        
        # Login and navigate
        await self.login()
        await self.navigate_to_attendance()
        
        # Setup API interception
        await self.intercept_api_calls()
        
        # Wait for page to be fully loaded
        await asyncio.sleep(3)
        
        # Try to set date filters (UI may vary)
        try:
            print("📆 Setting date range...")
            
            # Look for date inputs
            date_inputs = await self.page.query_selector_all('input[type="text"][placeholder*="date" i]')
            
            if len(date_inputs) >= 2:
                # Fill start date
                await date_inputs[0].fill(from_date)
                await asyncio.sleep(0.5)
                
                # Fill end date
                await date_inputs[1].fill(to_date)
                await asyncio.sleep(0.5)
                
                # Click search/filter button
                search_buttons = ['button:has-text("Search")', 'button:has-text("Filter")', 'button:has-text("Query")']
                for btn_selector in search_buttons:
                    try:
                        await self.page.click(btn_selector, timeout=2000)
                        print("✅ Clicked search button")
                        break
                    except:
                        continue
            
            # Wait for API response
            await asyncio.sleep(5)
            
        except Exception as e:
            print(f"⚠️  Date filtering failed: {e}")
            print("Fetching default data...")
        
        # Extract data from intercepted API calls
        if self.api_data:
            print(f"✅ Intercepted {len(self.api_data)} API response(s)")
            return self.extract_attendance_records(self.api_data)
        else:
            print("⚠️  No API data intercepted, trying to scrape from table...")
            return await self.scrape_table_data()
    
    def extract_attendance_records(self, api_responses):
        """Extract records from API responses"""
        all_records = []
        
        for response in api_responses:
            # Try different paths where records might be
            records = None
            
            if isinstance(response, dict):
                # Try common paths
                paths = [
                    ['data', 'list'],
                    ['data', 'dataList'],
                    ['data', 'records'],
                    ['data'],
                    ['list'],
                    ['records']
                ]
                
                for path in paths:
                    temp = response
                    try:
                        for key in path:
                            temp = temp[key]
                        if isinstance(temp, list) and len(temp) > 0:
                            records = temp
                            break
                    except (KeyError, TypeError):
                        continue
            
            if records:
                all_records.extend(records)
        
        print(f"📊 Extracted {len(all_records)} records")
        return all_records
    
    async def scrape_table_data(self):
        """Fallback: scrape data from HTML table"""
        print("🔍 Scraping table data...")
        records = []
        
        try:
            # Wait for table
            await self.page.wait_for_selector('table tbody tr', timeout=5000)
            
            # Get all rows
            rows = await self.page.query_selector_all('table tbody tr')
            
            for row in rows:
                cells = await row.query_selector_all('td')
                if len(cells) >= 5:
                    record = {
                        'personCode': await cells[0].inner_text(),
                        'fullName': await cells[1].inner_text(),
                        'department': await cells[2].inner_text(),
                        'clockStamp': await cells[3].inner_text(),
                        'deviceId': await cells[4].inner_text() if len(cells) > 4 else None,
                    }
                    records.append(record)
            
            print(f"✅ Scraped {len(records)} records from table")
        except Exception as e:
            print(f"⚠️  Table scraping failed: {e}")
        
        return records
    
    def save_to_laravel(self, records, action='attendance'):
        """Send data to Laravel API"""
        if not records:
            print("⚠️  No records to save")
            return 0
        
        print(f"💾 Saving {len(records)} records to Laravel...")
        
        endpoint = f"{self.config.laravel_api}/save-{action}"
        
        try:
            response = requests.post(
                endpoint,
                json={'records': records},
                timeout=30
            )
            
            if response.status_code == 200:
                result = response.json()
                saved = result.get('saved', 0)
                print(f"✅ Saved {saved} records to database")
                return saved
            else:
                print(f"⚠️  API returned status {response.status_code}")
                print(response.text)
                return 0
                
        except Exception as e:
            print(f"❌ Failed to save to Laravel: {e}")
            return 0
    
    async def close(self):
        """Close browser"""
        if self.browser:
            await self.browser.close()
    
    async def run(self, action, **kwargs):
        """Main dispatcher"""
        try:
            await self.init_browser()
            
            if action == 'get-cookies':
                await self.get_cookies()
                
            elif action == 'fetch-today':
                today = datetime.now().strftime('%Y-%m-%d')
                records = await self.fetch_attendance(today, today)
                self.save_to_laravel(records)
                
            elif action == 'fetch-range':
                from_date = kwargs.get('from_date')
                to_date = kwargs.get('to_date')
                if not from_date or not to_date:
                    print("❌ --from and --to dates required")
                    return
                records = await self.fetch_attendance(from_date, to_date)
                self.save_to_laravel(records)
                
            elif action == 'fetch-recent':
                # Last 24 hours
                today = datetime.now().strftime('%Y-%m-%d')
                yesterday = (datetime.now() - timedelta(days=1)).strftime('%Y-%m-%d')
                records = await self.fetch_attendance(yesterday, today)
                self.save_to_laravel(records)
                
            else:
                print(f"❌ Unknown action: {action}")
                
        except Exception as e:
            print(f"❌ Error: {e}")
            import traceback
            traceback.print_exc()
        finally:
            await self.close()


def main():
    """CLI entry point"""
    if len(sys.argv) < 2:
        print("Usage: python hcc_playwright_scraper.py <action> [options]")
        print("\nActions:")
        print("  get-cookies              - Get authentication cookies")
        print("  fetch-today              - Fetch today's attendance")
        print("  fetch-recent             - Fetch last 24 hours")
        print("  fetch-range --from=DATE --to=DATE")
        return
    
    action = sys.argv[1]
    
    # Parse additional arguments
    kwargs = {}
    for arg in sys.argv[2:]:
        if arg.startswith('--'):
            key, value = arg[2:].split('=', 1)
            kwargs[key] = value
    
    # Run scraper
    scraper = HccPlaywrightScraper()
    asyncio.run(scraper.run(action, **kwargs))


if __name__ == '__main__':
    main()

