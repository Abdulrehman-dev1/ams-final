"""
HCC Auto Sync - Complete Automation
Scrapes data from browser + saves to database automatically
Perfect for cron jobs
"""
import asyncio
from playwright.async_api import async_playwright
import requests
import json
import subprocess
import os
from datetime import datetime, timedelta

# Fix Windows encoding
import sys
if sys.platform == 'win32':
    try:
        sys.stdout.reconfigure(encoding='utf-8')
        sys.stderr.reconfigure(encoding='utf-8')
    except:
        os.environ['PYTHONIOENCODING'] = 'utf-8'

from hcc_config import HccConfig

async def auto_sync():
    """Complete automation - scrape + save"""
    
    print("=" * 70)
    print("🤖 HCC Auto Sync - Starting...")
    print("=" * 70)
    
    config = HccConfig()
    
    # Date range (Today by default)
    today = datetime.now()
    from_date = today.strftime('%Y-%m-%d')
    to_date = today.strftime('%Y-%m-%d')
    
    print(f"\n📅 Date: {from_date}")
    print(f"🔗 API: {config.base_url}")
    
    async with async_playwright() as p:
        try:
            # Launch browser (headless for production, visible for debug)
            headless = config.headless
            print(f"\n🌐 Opening browser (headless={headless})...")
            
            browser = await p.chromium.launch(
                headless=headless,
                slow_mo=500 if not headless else 0
            )
            
            context = await browser.new_context()
            page = await context.new_page()
            page.set_default_timeout(60000)
            
            # ===== LOGIN =====
            login_url = "https://www.hik-connect.com/views/login/index.html#/login"
            print(f"\n🔐 [1/8] Logging in...")
            await page.goto(login_url, wait_until='domcontentloaded', timeout=60000)
            await asyncio.sleep(3)
            
            # Phone tab
            try:
                await page.click('text=Phone', timeout=3000)
            except:
                await page.evaluate("Array.from(document.querySelectorAll('label')).find(l => l.innerText.includes('Phone'))?.click()")
            
            await asyncio.sleep(1)
            
            # Fill credentials
            await page.evaluate(f'''
                () => {{
                    const phoneNum = "{config.username}";
                    const password = "{config.password}";
                    
                    const inputs = Array.from(document.querySelectorAll('input[type="text"]'));
                    for (let input of inputs) {{
                        if (!input.hasAttribute('readonly')) {{
                            input.value = phoneNum;
                            input.dispatchEvent(new Event('input', {{ bubbles: true }}));
                            break;
                        }}
                    }}
                    const passInput = document.querySelector('input[type="password"]');
                    if (passInput) {{
                        passInput.value = password;
                        passInput.dispatchEvent(new Event('input', {{ bubbles: true }}));
                    }}
                }}
            ''')
            
            await asyncio.sleep(1)
            
            # Click login
            await page.evaluate("document.querySelector('button[type=\"submit\"]')?.click()")
            await asyncio.sleep(6)
            
            print(f"   ✅ Logged in")
            
            # Get cookies
            cookies = await context.cookies()
            cookie_str = '; '.join([f"{c['name']}={c['value']}" for c in cookies])
            
            # ===== NAVIGATE TO ATTENDANCE =====
            print(f"\n📊 [2/8] Opening Attendance...")
            await page.evaluate("document.querySelector('#tab-HCBAttendance')?.click()")
            await asyncio.sleep(3)
            print(f"   ✅ Attendance opened")
            
            # ===== HAMBURGER MENU =====
            print(f"\n☰ [3/8] Expanding sidebar...")
            await page.evaluate("document.querySelector('.nav-base-collapse-top')?.click()")
            await asyncio.sleep(2)
            print(f"   ✅ Sidebar expanded")
            
            # ===== ATTENDANCE RECORDS =====
            print(f"\n📋 [4/8] Opening Attendance Records...")
            await page.evaluate('''
                () => {
                    const els = Array.from(document.querySelectorAll('*'));
                    const target = els.find(el => el.innerText?.includes('Attendance Records'));
                    if (target) target.click();
                }
            ''')
            await asyncio.sleep(2)
            print(f"   ✅ Attendance Records opened")
            
            # ===== TRANSACTION TAB =====
            print(f"\n📑 [5/8] Opening Transaction...")
            await page.evaluate('''
                () => {
                    const els = Array.from(document.querySelectorAll('*'));
                    const target = els.find(el => el.innerText?.trim() === 'Transaction');
                    if (target) target.click();
                }
            ''')
            await asyncio.sleep(4)
            print(f"   ✅ Transaction page loaded")
            
            # ===== SELECT TODAY FILTER =====
            print(f"\n📅 [6/8] Filtering for Today...")
            
            # Open dropdown
            await page.evaluate("document.querySelector('i.h-icon-angle_down_sm')?.click()")
            await asyncio.sleep(2)
            
            # Select Today
            await page.evaluate('''
                () => {
                    const options = Array.from(document.querySelectorAll('li, [role="option"]'));
                    const todayOption = options.find(opt => opt.innerText.trim() === 'Today');
                    if (todayOption) todayOption.click();
                }
            ''')
            await asyncio.sleep(1)
            
            # Click Filter
            await page.evaluate('''
                () => {
                    const buttons = Array.from(document.querySelectorAll('button'));
                    const filterBtn = buttons.find(btn => btn.innerText.includes('Filter'));
                    if (filterBtn) filterBtn.click();
                }
            ''')
            await asyncio.sleep(4)
            print(f"   ✅ Today filter applied")
            
            # ===== SET PAGINATION TO 100 =====
            print(f"\n📄 Setting pagination to 100 per page...")
            
            # Click pagination dropdown (bottom left - shows 20, 30, etc)
            result = await page.evaluate('''
                () => {
                    try {
                        // Method 1: Find by el-pagination class
                        const pagination = document.querySelector('.el-pagination__sizes');
                        if (pagination) {
                            const input = pagination.querySelector('input, .el-input__inner');
                            if (input) {
                                input.click();
                                return 'Pagination clicked (el-pagination)';
                            }
                        }
                        
                        // Method 2: Find dropdown showing numbers (20, 30, 50)
                        const dropdowns = Array.from(document.querySelectorAll('.el-select, select'));
                        for (let dropdown of dropdowns) {
                            const text = (dropdown.innerText || dropdown.value || '').toString();
                            if (text && (text.includes('20') || text.includes('30') || text.includes('50'))) {
                                dropdown.click();
                                return 'Pagination dropdown found and clicked';
                            }
                        }
                        
                        // Method 3: Click first el-select in bottom area
                        const bottomSelects = document.querySelectorAll('.el-pagination .el-select');
                        if (bottomSelects.length > 0) {
                            bottomSelects[0].click();
                            return 'Clicked first pagination select';
                        }
                        
                        return 'Pagination not found (might already be 100)';
                    } catch (e) {
                        return 'Error: ' + e.message;
                    }
                }
            ''')
            print(f"   {result}")
            
            await asyncio.sleep(2)
            
            # Select 100 option
            result2 = await page.evaluate('''
                () => {
                    const options = Array.from(document.querySelectorAll('li, [role="option"], .el-select-dropdown__item'));
                    const option100 = options.find(opt => opt.innerText.trim() === '100');
                    if (option100) {
                        option100.click();
                        return '✅ Selected 100 per page';
                    }
                    return '⚠️ 100 option not found';
                }
            ''')
            print(f"   {result2}")
            
            await asyncio.sleep(3)
            print(f"   ✅ Pagination set to 100")
            
            # ===== WAIT & INTERCEPT API CALLS =====
            print(f"\n📡 [7/8] Intercepting API data...")
            
            # Setup API response capture
            api_data_captured = []
            
            async def capture_response(response):
                url = response.url
                if 'hccattendance/report/v1/list' in url:
                    try:
                        data = await response.json()
                        api_data_captured.append(data)
                        print(f"   ✅ Intercepted API response!")
                    except:
                        pass
            
            page.on('response', capture_response)
            
            # Wait for API call to happen (after filter was clicked)
            print(f"   Waiting for API data...")
            await asyncio.sleep(5)
            
            # Extract records
            records = []
            if api_data_captured:
                for response_data in api_data_captured:
                    if 'data' in response_data and 'reportDataList' in response_data['data']:
                        records.extend(response_data['data']['reportDataList'])
            
            print(f"   ✅ Captured {len(records)} records from browser")
            
            # If no data intercepted, try direct API call with fresh cookies
            if len(records) == 0:
                print(f"   Trying direct API call with cookies...")
                
                endpoint = f"{config.base_url}/hcc/hccattendance/report/v1/list"
                from_dt = f"{from_date}T00:00:00+05:00"
                to_dt = f"{to_date}T23:59:59+05:00"
                
                payload = {
                    "page": 1,
                    "pageSize": 100,
                    "language": "en",
                    "reportTypeId": 1,
                    "columnIdList": [],
                    "filterList": [
                        {"columnName": "fullName", "operation": "LIKE", "value": ""},
                        {"columnName": "personCode", "operation": "LIKE", "value": ""},
                        {"columnName": "groupId", "operation": "IN", "value": ""},
                        {"columnName": "clockStamp", "operation": "BETWEEN", "value": f"{from_dt},{to_dt}"},
                        {"columnName": "deviceId", "operation": "IN", "value": ""},
                    ]
                }
                
                headers = {
                    'Cookie': cookie_str,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'
                }
                
                response = requests.post(endpoint, json=payload, headers=headers, timeout=30)
                
                if response.status_code == 200:
                    data = response.json()
                    records = data.get('data', {}).get('reportDataList', [])
                    print(f"   ✅ Direct API: {len(records)} records")
            
            # ===== SAVE DATA =====
            if len(records) > 0:
                print(f"\n💾 [8/8] Saving {len(records)} records to database...")
                
                # Save to JSON
                with open('api_reportDataList.json', 'w', encoding='utf-8') as f:
                    json.dump(records, f, indent=2, ensure_ascii=False)
                
                print(f"   💾 Saved to: api_reportDataList.json")
                
                # Run PHP import script
                script_dir = os.path.dirname(os.path.abspath(__file__))
                php_script = os.path.join(script_dir, 'save_api_data.php')
                
                result = subprocess.run(
                    ['php', php_script],
                    cwd=script_dir,
                    capture_output=True,
                    text=True,
                    encoding='utf-8'
                )
                
                print(result.stdout)
                
                if result.returncode == 0:
                    print(f"\n   ✅ Database updated successfully!")
                else:
                    print(f"\n   ⚠️  Import error:")
                    print(result.stderr)
            else:
                print(f"\n⚠️  No records found for {from_date}")
            
            await browser.close()
            
            print("\n" + "=" * 70)
            print("✅ Auto Sync Complete!")
            print("=" * 70)
            
        except Exception as e:
            print(f"\n❌ Error: {e}")
            import traceback
            traceback.print_exc()
            
            if 'browser' in locals():
                await browser.close()

if __name__ == '__main__':
    asyncio.run(auto_sync())

