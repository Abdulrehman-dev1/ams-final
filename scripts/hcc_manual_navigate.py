"""
HCC Manual Navigation Helper - Tumhare samne browser khul ke exact selectors find karega
"""
import asyncio
from playwright.async_api import async_playwright
from datetime import datetime, timedelta
import json

async def main():
    print("=" * 70)
    print("🎯 HCC Manual Navigation Helper")
    print("=" * 70)
    
    # Read config
    import sys
    sys.path.append('.')
    from hcc_config import HccConfig
    config = HccConfig()
    
    async with async_playwright() as p:
        # Launch visible browser
        print("\n🌐 Opening browser (VISIBLE MODE)...")
        browser = await p.chromium.launch(
            headless=False,
            slow_mo=800  # 800ms delay
        )
        
        context = await browser.new_context(
            viewport={'width': 1920, 'height': 1080}
        )
        page = await context.new_page()
        
        # ====================
        # STEP 1: LOGIN
        # ====================
        login_url = "https://www.hik-connect.com/views/login/index.html#/login"
        print(f"\n🔐 STEP 1: Opening login page...")
        await page.goto(login_url, wait_until='networkidle')
        await asyncio.sleep(3)
        
        # Click Phone tab
        print("\n📱 STEP 2: Clicking Phone tab...")
        try:
            await page.click('text=Phone', timeout=5000)
            print("   ✅ Phone tab clicked!")
        except:
            print("   ⚠️ Phone tab click failed, trying JavaScript...")
            await page.evaluate("document.querySelector('label:nth-child(2)').click()")
        
        await asyncio.sleep(2)
        
        # Fill phone (find the editable input)
        print(f"\n📝 STEP 3: Entering phone: {config.username}")
        try:
            # Get all text inputs
            inputs = await page.query_selector_all('input[type="text"]')
            for i, inp in enumerate(inputs):
                is_readonly = await inp.get_attribute('readonly')
                placeholder = await inp.get_attribute('placeholder')
                print(f"   Input {i}: readonly={is_readonly}, placeholder={placeholder}")
                
                if not is_readonly:
                    await inp.fill(config.username)
                    print(f"   ✅ Filled in input {i}!")
                    break
        except Exception as e:
            print(f"   ❌ Error: {e}")
        
        await asyncio.sleep(1)
        
        # Fill password
        print(f"\n🔐 STEP 4: Entering password...")
        try:
            await page.fill('input[type="password"]', config.password)
            print("   ✅ Password entered!")
        except Exception as e:
            print(f"   ❌ Error: {e}")
        
        await asyncio.sleep(1)
        
        # Click login
        print(f"\n🚀 STEP 5: Clicking login button...")
        try:
            await page.click('button[type="submit"]')
            print("   ✅ Login button clicked!")
        except:
            await page.evaluate("document.querySelector('button[type=\"submit\"]').click()")
            print("   ✅ Login via JavaScript!")
        
        # Wait for redirect
        print(f"\n⏳ STEP 6: Waiting for login to complete...")
        await asyncio.sleep(6)
        print(f"   Current URL: {page.url}")
        
        # ====================
        # STEP 7: ATTENDANCE TAB
        # ====================
        print(f"\n📊 STEP 7: Clicking Attendance tab (header)...")
        try:
            # Try ID first
            await page.click('#tab-HCBAttendance', timeout=5000)
            print("   ✅ Attendance clicked via ID!")
        except:
            try:
                # Try text
                await page.click('text=Attendance', timeout=3000)
                print("   ✅ Attendance clicked via text!")
            except:
                # JavaScript fallback
                await page.evaluate("document.querySelector('#tab-HCBAttendance').click()")
                print("   ✅ Attendance clicked via JavaScript!")
        
        await asyncio.sleep(4)
        
        # ====================
        # STEP 8: HAMBURGER MENU (Critical!)
        # ====================
        print(f"\n☰ STEP 8: Finding & clicking HAMBURGER MENU...")
        
        # Take screenshot before
        await page.screenshot(path='screenshot_before_hamburger.png')
        print("   📸 Screenshot saved: screenshot_before_hamburger.png")
        
        # List all clickable elements in sidebar area
        print("\n   🔍 Inspecting sidebar elements...")
        sidebar_info = await page.evaluate('''
            () => {
                const elements = document.querySelectorAll('.sidebar *, .nav *, [class*="menu"] *');
                return Array.from(elements).slice(0, 20).map((el, i) => ({
                    index: i,
                    tag: el.tagName,
                    class: el.className,
                    id: el.id,
                    text: el.innerText?.substring(0, 30)
                }));
            }
        ''')
        
        for elem in sidebar_info[:10]:
            print(f"      [{elem['index']}] {elem['tag']}.{elem['class'][:30]} - {elem['text'][:20]}")
        
        # Try multiple hamburger selectors
        hamburger_found = False
        hamburger_selectors = [
            '.hamburger',
            '.menu-toggle',
            '.sidebar-toggle',
            'button.el-icon-menu',
            'i.el-icon-menu',
            '.el-icon-s-unfold',
            '[class*="fold"]',
            '.nav-toggle',
            # Try by position (first icon in sidebar)
            '.sidebar button:first-child',
            '.left-menu button:first-child',
            # Generic
            'svg[class*="menu"]',
        ]
        
        for selector in hamburger_selectors:
            try:
                print(f"   Trying selector: {selector}")
                await page.click(selector, timeout=2000)
                print(f"   ✅ Hamburger clicked with: {selector}")
                hamburger_found = True
                break
            except Exception as e:
                print(f"      ❌ Failed: {str(e)[:50]}")
                continue
        
        if not hamburger_found:
            print("\n   ⚠️ Hamburger not found with selectors, trying JavaScript...")
            # Try JavaScript click on common positions
            js_attempts = [
                "document.querySelector('.hamburger')?.click()",
                "document.querySelector('button[class*=\"menu\"]')?.click()",
                "document.querySelector('.sidebar button')?.click()",
                "document.querySelectorAll('button')[0]?.click()",
            ]
            
            for js_code in js_attempts:
                try:
                    await page.evaluate(js_code)
                    print(f"   ✅ Tried: {js_code}")
                    await asyncio.sleep(1)
                except:
                    pass
        
        await asyncio.sleep(3)
        
        # Take screenshot after
        await page.screenshot(path='screenshot_after_hamburger.png')
        print("   📸 Screenshot saved: screenshot_after_hamburger.png")
        
        # ====================
        # STEP 9: ATTENDANCE RECORDS DROPDOWN
        # ====================
        print(f"\n📋 STEP 9: Clicking 'Attendance Records' dropdown...")
        
        # First, find what's visible
        print("   🔍 Finding 'Attendance Records'...")
        menu_items = await page.evaluate('''
            () => {
                const items = document.querySelectorAll('.el-menu-item, .el-submenu, li, div[role="menuitem"]');
                return Array.from(items).map((el, i) => ({
                    index: i,
                    tag: el.tagName,
                    class: el.className,
                    text: el.innerText?.substring(0, 50)
                }));
            }
        ''')
        
        for item in menu_items[:15]:
            if 'attendance' in item['text'].lower() or 'record' in item['text'].lower():
                print(f"      Found: {item['text']}")
        
        # Try clicking
        records_found = False
        records_selectors = [
            'text=Attendance Records',
            '.el-submenu__title:has-text("Attendance Records")',
            'div:has-text("Attendance Records"):not(:has(div))',
        ]
        
        for selector in records_selectors:
            try:
                await page.click(selector, timeout=3000)
                print(f"   ✅ Clicked: {selector}")
                records_found = True
                break
            except:
                continue
        
        if not records_found:
            print("   ⚠️ Trying JavaScript click...")
            await page.evaluate('''
                () => {
                    const els = Array.from(document.querySelectorAll('*'));
                    const target = els.find(el => el.innerText?.includes('Attendance Records'));
                    if (target) target.click();
                }
            ''')
        
        await asyncio.sleep(3)
        
        # ====================
        # STEP 10: TRANSACTION TAB
        # ====================
        print(f"\n📑 STEP 10: Clicking 'Transaction' tab...")
        
        transaction_found = False
        transaction_selectors = [
            'text=Transaction',
            '.el-menu-item:has-text("Transaction")',
            'li:has-text("Transaction")',
            'a:has-text("Transaction")',
        ]
        
        for selector in transaction_selectors:
            try:
                await page.click(selector, timeout=3000)
                print(f"   ✅ Transaction clicked: {selector}")
                transaction_found = True
                break
            except:
                continue
        
        if not transaction_found:
            print("   ⚠️ Trying JavaScript...")
            await page.evaluate('''
                () => {
                    const els = Array.from(document.querySelectorAll('*'));
                    const target = els.find(el => el.innerText?.trim() === 'Transaction');
                    if (target) target.click();
                }
            ''')
        
        await asyncio.sleep(5)
        
        # ====================
        # STEP 11: EXTRACT TABLE
        # ====================
        print(f"\n📊 STEP 11: Current URL: {page.url}")
        print(f"\n📊 Extracting table data...")
        
        # Take screenshot of table
        await page.screenshot(path='screenshot_table.png', full_page=True)
        print("   📸 Full page screenshot: screenshot_table.png")
        
        # Wait for table
        try:
            await page.wait_for_selector('table tbody tr', timeout=10000)
            
            # Extract ALL table data
            table_html = await page.evaluate('''
                () => {
                    const rows = Array.from(document.querySelectorAll('table tbody tr'));
                    return rows.map(row => {
                        const cells = Array.from(row.querySelectorAll('td'));
                        return cells.map(cell => cell.innerText.trim());
                    });
                }
            ''')
            
            print(f"   ✅ Found {len(table_html)} rows")
            
            if len(table_html) > 0:
                print(f"\n📋 Sample rows:")
                for i, row in enumerate(table_html[:3]):
                    print(f"      Row {i+1}: {row}")
                
                # Save raw data to file
                with open('table_data.json', 'w', encoding='utf-8') as f:
                    json.dump(table_html, f, indent=2, ensure_ascii=False)
                print(f"\n   💾 Raw data saved to: table_data.json")
                print(f"   📊 Total rows: {len(table_html)}")
            else:
                print("   ⚠️ Table is empty")
                
        except Exception as e:
            print(f"   ❌ Table error: {e}")
        
        # Keep browser open
        print(f"\n⏳ Browser will stay open for 30 seconds...")
        print(f"   💡 You can manually inspect the page")
        print(f"   💡 Screenshots saved in scripts/ folder")
        await asyncio.sleep(30)
        
        await browser.close()
        
        print("\n" + "=" * 70)
        print("✅ Navigation complete! Check screenshots folder.")
        print("=" * 70)

if __name__ == '__main__':
    asyncio.run(main())

