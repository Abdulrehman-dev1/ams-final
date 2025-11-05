"""
HCC Browser Debug Script - Sab kuch visible mode mein
"""
import asyncio
from playwright.async_api import async_playwright
import requests
import json
from datetime import datetime, timedelta

async def main():
    print("🎬 Starting HCC Browser Debug...")
    print("=" * 60)
    
    # Read config
    import sys
    sys.path.append('.')
    from hcc_config import HccConfig
    
    config = HccConfig()
    
    # Date range (Today)
    today = datetime.now()
    from_date = today.strftime('%Y-%m-%d')
    to_date = today.strftime('%Y-%m-%d')
    
    print(f"📅 Fetching data from {from_date} to {to_date}")
    print(f"🔗 API URL: {config.base_url}")
    print("=" * 60)
    
    async with async_playwright() as p:
        # Launch browser (use config setting)
        print("\n🌐 Opening browser...")
        browser = await p.chromium.launch(
            headless=config.headless,  # From .env
            slow_mo=config.slow_mo     # From .env
        )
        
        context = await browser.new_context()
        page = await context.new_page()
        page.set_default_timeout(60000)  # 60 seconds timeout
        
        # Step 1: Go to login page directly
        login_url = "https://www.hik-connect.com/views/login/index.html#/login"
        print(f"\n🌐 Opening login page: {login_url}")
        await page.goto(login_url, wait_until='domcontentloaded', timeout=60000)
        print("   ✅ Page loaded!")
        await asyncio.sleep(4)
        
        # Step 2: Click Phone tab (switch from Account/Email to Phone)
        print("\n📱 Selecting 'Phone' tab...")
        try:
            # Try multiple selectors for Phone tab
            phone_tab_selectors = [
                'text=Phone',
                'label:has-text("Phone")',
                '.el-radio-button:has-text("Phone")',
                'span:has-text("Phone")',
                '[type="radio"][value="phone"]'
            ]
            
            for selector in phone_tab_selectors:
                try:
                    await page.click(selector, timeout=2000)
                    print("   ✅ Phone tab selected!")
                    break
                except:
                    continue
            
            await asyncio.sleep(1)
        except Exception as e:
            print(f"   ⚠️ Phone tab not found: {e}")
        
        # Step 3: Fill phone number
        print(f"\n📝 Entering phone number: {config.username}")
        phone_input_selectors = [
            'input[placeholder*="phone" i]',
            'input[type="tel"]',
            'input[name="phone"]',
            '.phone-input input',
            'input:not([readonly]):not([disabled])[type="text"]'
        ]
        
        phone_filled = False
        for selector in phone_input_selectors:
            try:
                elements = await page.query_selector_all(selector)
                for elem in elements:
                    is_readonly = await elem.get_attribute('readonly')
                    is_disabled = await elem.get_attribute('disabled')
                    if not is_readonly and not is_disabled:
                        await elem.fill(config.username)
                        print(f"   ✅ Phone number entered!")
                        phone_filled = True
                        break
                if phone_filled:
                    break
            except:
                continue
        
        if not phone_filled:
            print("   ❌ Could not find phone input field!")
        
        await asyncio.sleep(1)
        
        # Step 4: Fill password
        print(f"\n🔐 Entering password...")
        try:
            password_input = 'input[type="password"]'
            await page.fill(password_input, config.password)
            print("   ✅ Password entered!")
        except Exception as e:
            print(f"   ❌ Password field error: {e}")
        
        await asyncio.sleep(1)
        
        # Step 5: Click login button
        print("\n🚀 Clicking login button...")
        try:
            login_button_selectors = [
                'button[type="submit"]',
                'button:has-text("Log")',
                'button:has-text("Sign")',
                '.login-btn',
                '.submit-btn'
            ]
            
            for selector in login_button_selectors:
                try:
                    await page.click(selector, timeout=2000)
                    print("   ✅ Login button clicked!")
                    break
                except:
                    continue
        except Exception as e:
            print(f"   ⚠️ Login button error: {e}")
        
        # Step 6: Wait for login to complete
        print("\n⏳ Waiting for login to complete...")
        await asyncio.sleep(5)
        
        current_url = page.url
        print(f"   Current URL: {current_url}")
        
        if 'login' not in current_url.lower():
            print("   ✅ Login successful! (redirected away from login page)")
        else:
            print("   ⚠️ Still on login page - check credentials or form")
        
        # Step 7: Get cookies after login
        print("\n🍪 Extracting cookies...")
        cookies = await context.cookies()
        cookie_str = '; '.join([f"{c['name']}={c['value']}" for c in cookies])
        print(f"   Got {len(cookies)} cookies")
        
        # Step 8: Click Attendance tab in header
        print("\n📊 Clicking Attendance tab (header)...")
        try:
            attendance_tab_selectors = [
                '#tab-HCBAttendance',
                'text=Attendance',
                '[id*="Attendance"]',
                'div:has-text("Attendance")',
                '.nav-tab:has-text("Attendance")'
            ]
            
            for selector in attendance_tab_selectors:
                try:
                    await page.click(selector, timeout=3000)
                    print("   ✅ Attendance tab clicked!")
                    break
                except:
                    continue
            
            await asyncio.sleep(3)
        except Exception as e:
            print(f"   ⚠️ Attendance tab error: {e}")
        
        # Step 9: Click hamburger menu to open/expand sidebar
        print("\n☰ Opening sidebar (hamburger menu - logo ke niche)...")
        
        # Take screenshot before
        await page.screenshot(path='before_hamburger.png')
        print("   📸 Screenshot: before_hamburger.png")
        
        # EXACT SELECTOR from user's HTML
        hamburger_clicked = False
        
        # Method 1: Exact class selector
        exact_selectors = [
            '.nav-base-collapse-top.collapse-s',
            '.nav-base-collapse-top',
            'div[title="Attendance"].nav-base-collapse-top',
            'div.collapse-s',
            '.nav-base-collapse-left',
            'i.nav-base-collapse-left',
        ]
        
        print("   Trying exact selectors from HTML...")
        for selector in exact_selectors:
            try:
                await page.click(selector, timeout=3000)
                print(f"   ✅ Hamburger clicked: {selector}")
                hamburger_clicked = True
                break
            except Exception as e:
                print(f"      ❌ Failed: {selector}")
                continue
        
        # Method 2: JavaScript - Direct click on exact element
        if not hamburger_clicked:
            print("   Trying JavaScript with exact class...")
            result = await page.evaluate('''
                () => {
                    // Exact selector from HTML
                    const hamburger = document.querySelector('.nav-base-collapse-top.collapse-s');
                    if (hamburger) {
                        hamburger.click();
                        return '✅ Clicked .nav-base-collapse-top.collapse-s';
                    }
                    
                    // Try child icon
                    const icon = document.querySelector('.nav-base-collapse-left');
                    if (icon && icon.parentElement) {
                        icon.parentElement.click();
                        return '✅ Clicked parent of .nav-base-collapse-left';
                    }
                    
                    // Try by title
                    const byTitle = document.querySelector('div[title="Attendance"].collapse-s');
                    if (byTitle) {
                        byTitle.click();
                        return '✅ Clicked div[title="Attendance"]';
                    }
                    
                    return '❌ Hamburger not found';
                }
            ''')
            print(f"   {result}")
            hamburger_clicked = 'Clicked' in result
        
        await asyncio.sleep(3)
        
        # Take screenshot after
        await page.screenshot(path='after_hamburger.png')
        print("   📸 Screenshot: after_hamburger.png")
        
        # Step 10: Click Attendance Records dropdown in sidebar
        print("\n📋 Opening 'Attendance Records' dropdown (sidebar)...")
        try:
            records_selectors = [
                'text=Attendance Records',
                'div:has-text("Attendance Records")',
                '.el-submenu:has-text("Attendance Records")',
                '[class*="submenu"]:has-text("Attendance Records")',
                'li:has-text("Attendance Records")',
                '.menu-item:has-text("Attendance Records")'
            ]
            
            for selector in records_selectors:
                try:
                    await page.click(selector, timeout=3000)
                    print("   ✅ Attendance Records dropdown opened!")
                    break
                except:
                    continue
            
            await asyncio.sleep(2)
        except Exception as e:
            print(f"   ⚠️ Attendance Records error: {e}")
        
        # Step 11: Click Transaction in submenu
        print("\n📑 Clicking 'Transaction' tab (dropdown ke andar)...")
        try:
            transaction_selectors = [
                'text=Transaction',
                'div:has-text("Transaction")',
                '.el-menu-item:has-text("Transaction")',
                '[class*="menu-item"]:has-text("Transaction")',
                'li:has-text("Transaction")',
                'a:has-text("Transaction")'
            ]
            
            for selector in transaction_selectors:
                try:
                    await page.click(selector, timeout=3000)
                    print("   ✅ Transaction tab clicked!")
                    break
                except:
                    continue
            
            await asyncio.sleep(5)  # Wait for table to load
        except Exception as e:
            print(f"   ⚠️ Transaction tab error: {e}")
        
        print(f"\n📍 Current page: {page.url}")
        
        # Step 12: Select "Today" from dropdown filter
        print("\n📅 Selecting 'Today' from filter dropdown...")
        
        # Take screenshot of transaction page
        await page.screenshot(path='transaction_page_before_filter.png')
        print("   📸 Screenshot: transaction_page_before_filter.png")
        
        try:
            # Click dropdown icon to open
            print("   Clicking dropdown...")
            dropdown_clicked = False
            
            # Try clicking the angle down icon
            dropdown_selectors = [
                'i.h-icon-angle_down_sm',
                '.el-input__icon.h-icon-angle_down_sm',
                '.el-input--suffix .el-input__icon',
                '.el-input__suffix',
            ]
            
            for selector in dropdown_selectors:
                try:
                    await page.click(selector, timeout=3000)
                    print(f"   ✅ Dropdown opened: {selector}")
                    dropdown_clicked = True
                    break
                except:
                    continue
            
            # JavaScript fallback
            if not dropdown_clicked:
                print("   Trying JavaScript...")
                await page.evaluate('''
                    () => {
                        const icon = document.querySelector('i.h-icon-angle_down_sm');
                        if (icon) icon.click();
                    }
                ''')
            
            await asyncio.sleep(2)
            
            # Select "Today" option
            print("   Selecting 'Today' option...")
            today_selected = False
            
            today_selectors = [
                'text=Today',
                '.el-select-dropdown__item:has-text("Today")',
                'li:has-text("Today")',
                '[role="option"]:has-text("Today")',
            ]
            
            for selector in today_selectors:
                try:
                    await page.click(selector, timeout=3000)
                    print(f"   ✅ 'Today' selected: {selector}")
                    today_selected = True
                    break
                except:
                    continue
            
            # JavaScript fallback for Today
            if not today_selected:
                print("   Trying JavaScript for Today...")
                await page.evaluate('''
                    () => {
                        const options = Array.from(document.querySelectorAll('li, [role="option"]'));
                        const todayOption = options.find(opt => opt.innerText.trim() === 'Today');
                        if (todayOption) todayOption.click();
                    }
                ''')
            
            await asyncio.sleep(2)
            
        except Exception as e:
            print(f"   ⚠️ Dropdown error: {e}")
        
        # Step 13: Click Filter button
        print("\n🔍 Clicking Filter button...")
        try:
            filter_clicked = False
            
            filter_selectors = [
                'button:has-text("Filter")',
                'button:has-text("Search")',
                'button:has-text("Query")',
                '.filter-btn',
                '.search-btn',
                'button[type="submit"]',
            ]
            
            for selector in filter_selectors:
                try:
                    await page.click(selector, timeout=3000)
                    print(f"   ✅ Filter clicked: {selector}")
                    filter_clicked = True
                    break
                except:
                    continue
            
            # JavaScript fallback
            if not filter_clicked:
                print("   Trying JavaScript...")
                await page.evaluate('''
                    () => {
                        const buttons = Array.from(document.querySelectorAll('button'));
                        const filterBtn = buttons.find(btn => 
                            btn.innerText.includes('Filter') || 
                            btn.innerText.includes('Search') ||
                            btn.innerText.includes('Query')
                        );
                        if (filterBtn) filterBtn.click();
                    }
                ''')
            
            await asyncio.sleep(4)  # Wait for table to reload
            
        except Exception as e:
            print(f"   ⚠️ Filter button error: {e}")
        
        # Take screenshot after filter
        await page.screenshot(path='after_filter.png')
        print("   📸 Screenshot: after_filter.png")
        
        # Step 14: Extract data from table
        print("\n📊 Extracting data from table...")
        try:
            # Wait for table to be visible
            await page.wait_for_selector('table tbody tr', timeout=10000)
            
            # Extract ALL table data with COMPLETE fields (including location details)
            table_data = await page.evaluate('''
                () => {
                    const rows = Array.from(document.querySelectorAll('table tbody tr'));
                    return rows.map(row => {
                        const cells = Array.from(row.querySelectorAll('td'));
                        
                        // Extract cell data with special handling for location
                        const cellData = cells.map((cell, index) => {
                            // Column 11: Location (has map icon, may contain object or address)
                            if (index === 11) {
                                // Method 1: Get visible text first
                                let visibleText = cell.innerText.trim();
                                
                                // Method 2: Title attribute (full address might be here)
                                const cellTitle = cell.getAttribute('title');
                                if (cellTitle && cellTitle.trim() && cellTitle !== '--') {
                                    visibleText = cellTitle.trim();
                                }
                                
                                // Method 3: Check nested elements with title
                                const elemWithTitle = cell.querySelector('[title]');
                                if (elemWithTitle) {
                                    const nestedTitle = elemWithTitle.getAttribute('title');
                                    if (nestedTitle && nestedTitle.trim() && nestedTitle !== '--') {
                                        visibleText = nestedTitle.trim();
                                    }
                                }
                                
                                // Method 4: If map icon exists, get parent's title
                                const mapIcon = cell.querySelector('i.h-icon-map_locatin_sm, i[class*="map"], .map-icon');
                                if (mapIcon && mapIcon.parentElement) {
                                    const parentTitle = mapIcon.parentElement.getAttribute('title');
                                    if (parentTitle && parentTitle.trim()) {
                                        visibleText = parentTitle.trim();
                                    }
                                }
                                
                                // Return visible text (not object)
                                return visibleText || '--';
                            }
                            
                            // For other cells, standard text extraction
                            const text = cell.innerText.trim();
                            return text || '--';
                        });
                        
                        return cellData;
                    });
                }
            ''')
            
            print(f"   ✅ Found {len(table_data)} rows in table")
            
            if len(table_data) > 0:
                print(f"\n🔍 First 3 rows (with ALL fields):")
                for i, row in enumerate(table_data[:3]):
                    print(f"      Row {i+1}:")
                    print(f"         Name: {row[0]} {row[1]}")
                    print(f"         Code: {row[2]}")
                    print(f"         Dept: {row[3]}")
                    print(f"         Date/Time: {row[4]} {row[5]}")
                    print(f"         Source: {row[7]}")
                    print(f"         Device: {row[8]} ({row[9]})")
                    print(f"         Location: {row[11]}")
                    print(f"         Remark: {row[12] if len(row) > 12 else ''}")
                    print()
                
                # Save raw table data
                with open('table_scraped_data.json', 'w', encoding='utf-8') as f:
                    json.dump(table_data, f, indent=2, ensure_ascii=False)
                print(f"\n   💾 Raw table saved: table_scraped_data.json")
                
                # Convert to attendance records format
                # Complete mapping with ALL fields from image
                records = []
                for row_data in table_data:
                    if len(row_data) >= 6:  # At least basic required fields
                        # Map ALL columns based on HCC table structure
                        record = {
                            'firstName': row_data[0] if len(row_data) > 0 else '',
                            'lastName': row_data[1] if len(row_data) > 1 else '',
                            'personCode': row_data[2] if len(row_data) > 2 else '',
                            'fullPath': row_data[3] if len(row_data) > 3 else '',        # Department
                            'clockDate': row_data[4] if len(row_data) > 4 else '',
                            'clockTime': row_data[5] if len(row_data) > 5 else '',
                            'week': row_data[6] if len(row_data) > 6 else '',
                            'dataSource': row_data[7] if len(row_data) > 7 else '',      # Mobile App / Device
                            'deviceName': row_data[8] if len(row_data) > 8 else '',
                            'deviceSerial': row_data[9] if len(row_data) > 9 else '',    # Device Serial No.
                            'punchState': row_data[10] if len(row_data) > 10 else '',
                            'location': row_data[11] if len(row_data) > 11 else '',      # Full address
                            'remark': row_data[12] if len(row_data) > 12 else '',
                            
                            # Computed fields
                            'fullName': f"{row_data[0] if len(row_data) > 0 else ''} {row_data[1] if len(row_data) > 1 else ''}".strip(),
                            'clockStamp': f"{row_data[4] if len(row_data) > 4 else ''} {row_data[5] if len(row_data) > 5 else ''}",
                        }
                        
                        # Only add if has required fields
                        if record['personCode'] and record['fullName']:
                            records.append(record)
                
                print(f"\n📦 Converted {len(records)} valid records")
                
                # Save to database via Laravel API
                if len(records) > 0:
                    print("\n💾 Saving to Laravel database...")
                    laravel_api = f"{config.app_url}/api/playwright/save-attendance"
                    
                    try:
                        save_response = requests.post(
                            laravel_api,
                            json={'records': records},
                            timeout=30
                        )
                        
                        if save_response.status_code == 200:
                            result = save_response.json()
                            saved = result.get('saved', 0)
                            print(f"   ✅ Saved {saved} records to database!")
                            print(f"   📊 Check: php artisan tinker")
                            print(f"   >>> \\App\\Models\\HccAttendanceTransaction::count()")
                        else:
                            print(f"   ❌ API Error: {save_response.status_code}")
                            print(f"   Response: {save_response.text}")
                    except Exception as e:
                        print(f"   ❌ Save error: {e}")
                else:
                    print("   ⚠️ No valid records to save")
            else:
                print("   ⚠️ No data in table")
                
        except Exception as e:
            print(f"   ⚠️ Table extraction error: {e}")
            import traceback
            traceback.print_exc()
        
        # Go to HCC site
        url = f"{config.base_url}/hcc/hccattendance/report/v1/list"
        print(f"\n📍 API Endpoint: {url}")
        
        try:
            # Prepare API request
            endpoint = f"{config.base_url}/hcc/hccattendance/report/v1/list"
            
            # Build date range (ISO format)
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
                    {"columnName": "deviceId", "operation": "IN", "value": ""}
                ]
            }
            
            print("\n📤 Sending API request...")
            print(f"   Payload: {json.dumps(payload, indent=2)}")
            
            # Make API call with cookie (use fresh cookies from browser)
            headers = {
                'Cookie': cookie_str,  # Fresh cookies from login
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            }
            
            response = requests.post(endpoint, json=payload, headers=headers, timeout=30)
            
            print(f"\n📥 Response Status: {response.status_code}")
            
            if response.status_code == 200:
                data = response.json()
                print(f"\n✅ Response received!")
                print(f"   Keys: {list(data.keys())}")
                
                # Print full response for debugging
                print(f"\n🔍 Full Response:")
                print(json.dumps(data, indent=2)[:1000])  # First 1000 chars
                
                # Extract records - HCC uses 'reportDataList'
                records = []
                if 'data' in data and 'reportDataList' in data['data']:
                    records = data['data']['reportDataList']
                    print(f"   ✅ Found in data.reportDataList!")
                elif 'reportDataList' in data:
                    records = data['reportDataList']
                    print(f"   ✅ Found in reportDataList!")
                elif 'data' in data and 'list' in data['data']:
                    records = data['data']['list']
                    print(f"   ✅ Found in data.list")
                elif 'data' in data and isinstance(data['data'], list):
                    records = data['data']
                    print(f"   ✅ Found in data (array)")
                elif 'list' in data:
                    records = data['list']
                    print(f"   ✅ Found in list")
                
                print(f"\n📊 Found {len(records)} records")
                
                if len(records) > 0:
                    print("\n🔍 Sample Record:")
                    print(json.dumps(records[0], indent=2))
                    
                    # Save API data to JSON file (Best - has complete location data!)
                    print("\n💾 Saving API data to JSON file...")
                    with open('api_reportDataList.json', 'w', encoding='utf-8') as f:
                        json.dump(records, f, indent=2, ensure_ascii=False)
                    print(f"   ✅ Saved {len(records)} records to: api_reportDataList.json")
                    
                    # Try Laravel API (if server running)
                    print("\n💾 Trying Laravel API...")
                    try:
                        laravel_api = f"{config.app_url}/api/playwright/save-attendance"
                        save_response = requests.post(
                            laravel_api,
                            json={'records': records},
                            timeout=5
                        )
                        
                        if save_response.status_code == 200:
                            result = save_response.json()
                            saved = result.get('saved', 0)
                            print(f"   ✅ Saved {saved} records via API!")
                        else:
                            print(f"   ⚠️  API returned {save_response.status_code}")
                            print(f"\n   💡 Import manually:")
                            print(f"      php scripts/save_api_data.php")
                    except:
                        print(f"   ⚠️  Laravel API not available")
                        print(f"\n   💡 Import with:")
                        print(f"      php scripts/save_api_data.php")
                else:
                    print("\n⚠️  No records found for this date range")
                    print("   Possible reasons:")
                    print("   1. No attendance data in this date range")
                    print("   2. Cookie might be expired")
                    print("   3. API response structure changed")
                    print("\n💡 Next steps:")
                    print("   - Open HCC website manually and check if data exists")
                    print("   - Try last 30 days (change in script)")
                    print("   - Check response structure above")
                
            else:
                print(f"\n❌ API Error: {response.status_code}")
                print(f"   Response: {response.text[:500]}")
                
                if response.status_code == 401:
                    print("\n🔑 Cookie expired! Get new cookie:")
                    print("   1. Login to HCC in this browser")
                    print("   2. Press F12 → Console")
                    print("   3. Run: document.cookie")
                    print("   4. Copy output to .env HCC_COOKIE")
            
            # Keep browser open for 20 seconds
            print("\n⏳ Keeping browser open for 20 seconds...")
            print("   (You can inspect the table and data)")
            await asyncio.sleep(20)
            
        except Exception as e:
            print(f"\n❌ Exception: {e}")
            import traceback
            traceback.print_exc()
        
        finally:
            print("\n🔚 Closing browser...")
            await browser.close()
    
    print("\n" + "=" * 60)
    print("✅ Debug complete!")
    print("=" * 60)

if __name__ == '__main__':
    asyncio.run(main())

