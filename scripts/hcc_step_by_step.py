"""
HCC Step-by-step Helper - Har step pe PAUSE hoga, tum manually click kar sakte ho
"""
import asyncio
from playwright.async_api import async_playwright
from datetime import datetime, timedelta
import json

async def wait_for_user(message="Press ENTER to continue..."):
    """Wait for user to press enter"""
    print(f"\n⏸️  {message}")
    await asyncio.sleep(0.5)
    # Auto-continue after showing message
    
async def main():
    print("=" * 70)
    print("🎯 HCC Step-by-Step Helper (Browser will stay open)")
    print("=" * 70)
    
    # Read config
    import sys
    sys.path.append('.')
    from hcc_config import HccConfig
    config = HccConfig()
    
    async with async_playwright() as p:
        # Launch visible browser
        print("\n🌐 Opening browser...")
        browser = await p.chromium.launch(
            headless=False,
            slow_mo=500
        )
        
        context = await browser.new_context(
            viewport={'width': 1920, 'height': 1080}
        )
        page = await context.new_page()
        
        # Enable console logging
        page.on('console', lambda msg: print(f"   [Browser] {msg.text}"))
        
        try:
            # STEP 1: Login
            login_url = "https://www.hik-connect.com/views/login/index.html#/login"
            print(f"\n🔐 STEP 1: Opening login page...")
            await page.goto(login_url, wait_until='domcontentloaded')
            await asyncio.sleep(3)
            await page.screenshot(path='step1_login_page.png')
            print("   📸 Screenshot: step1_login_page.png")
            
            # STEP 2: Phone tab
            print(f"\n📱 STEP 2: Selecting Phone tab...")
            phone_clicked = False
            
            # Method 1: Text
            try:
                await page.click('text=Phone', timeout=3000)
                phone_clicked = True
                print("   ✅ Phone tab clicked (text selector)!")
            except:
                pass
            
            # Method 2: JavaScript
            if not phone_clicked:
                print("   Trying JavaScript click...")
                result = await page.evaluate('''
                    () => {
                        // Find all labels
                        const labels = Array.from(document.querySelectorAll('label'));
                        const phoneLabel = labels.find(l => l.innerText.includes('Phone'));
                        if (phoneLabel) {
                            phoneLabel.click();
                            return 'Clicked Phone label';
                        }
                        return 'Phone label not found';
                    }
                ''')
                print(f"   {result}")
            
            await asyncio.sleep(2)
            
            # STEP 3: Fill phone
            print(f"\n📝 STEP 3: Filling phone number...")
            
            # Find editable input
            filled = await page.evaluate(f'''
                (phoneNum) => {{
                    const inputs = Array.from(document.querySelectorAll('input[type="text"]'));
                    for (let input of inputs) {{
                        if (!input.hasAttribute('readonly') && !input.hasAttribute('disabled')) {{
                            input.value = phoneNum;
                            input.dispatchEvent(new Event('input', {{ bubbles: true }}));
                            input.dispatchEvent(new Event('change', {{ bubbles: true }}));
                            return 'Filled: ' + input.placeholder;
                        }}
                    }}
                    return 'No editable input found';
                }}
            ''', config.username)
            print(f"   {filled}")
            
            await asyncio.sleep(1)
            await page.screenshot(path='step3_phone_filled.png')
            
            # STEP 4: Fill password
            print(f"\n🔐 STEP 4: Filling password...")
            await page.evaluate(f'''
                (pwd) => {{
                    const passInput = document.querySelector('input[type="password"]');
                    if (passInput) {{
                        passInput.value = pwd;
                        passInput.dispatchEvent(new Event('input', {{ bubbles: true }}));
                        passInput.dispatchEvent(new Event('change', {{ bubbles: true }}));
                    }}
                }}
            ''', config.password)
            print(f"   ✅ Password filled!")
            
            await asyncio.sleep(1)
            
            # STEP 5: Click login
            print(f"\n🚀 STEP 5: Clicking login button...")
            await page.evaluate('''
                () => {
                    const btn = document.querySelector('button[type="submit"]');
                    if (btn) btn.click();
                }
            ''')
            print(f"   ✅ Login clicked!")
            
            await asyncio.sleep(7)
            print(f"   Current URL: {page.url}")
            await page.screenshot(path='step5_after_login.png')
            
            # STEP 6: Attendance tab
            print(f"\n📊 STEP 6: Clicking Attendance tab...")
            await page.evaluate('''
                () => {
                    const tab = document.querySelector('#tab-HCBAttendance');
                    if (tab) tab.click();
                }
            ''')
            await asyncio.sleep(4)
            await page.screenshot(path='step6_attendance_page.png')
            print(f"   📸 Screenshot: step6_attendance_page.png")
            
            # STEP 7: HAMBURGER - Critical step!
            print(f"\n☰ STEP 7: Opening sidebar (HAMBURGER MENU)...")
            print(f"   🔍 Searching for hamburger icon...")
            
            # Find all buttons and icons
            all_buttons = await page.evaluate('''
                () => {
                    const btns = Array.from(document.querySelectorAll('button, i, svg, [role="button"]'));
                    return btns.slice(0, 30).map((el, i) => ({
                        index: i,
                        tag: el.tagName,
                        class: el.className,
                        id: el.id,
                        parent_class: el.parentElement?.className,
                        text: el.innerText?.substring(0, 20)
                    }));
                }
            ''')
            
            print(f"\n   📋 Found {len(all_buttons)} clickable elements:")
            for btn in all_buttons[:15]:
                print(f"      [{btn['index']}] {btn['tag']}.{btn['class'][:30]} | parent: {btn['parent_class'][:30]}")
            
            # Try ALL possible hamburger clicks
            hamburger_methods = [
                # CSS selectors
                ("CSS: .hamburger", ".hamburger"),
                ("CSS: button:first-of-type", "button:first-of-type"),
                ("CSS: .el-icon-menu", ".el-icon-menu"),
                ("CSS: i.el-icon-s-unfold", "i.el-icon-s-unfold"),
                
                # JavaScript clicks
                ("JS: First button", "document.querySelectorAll('button')[0]?.click()"),
                ("JS: Menu icon", "document.querySelector('[class*=\"menu\"]')?.click()"),
                ("JS: Unfold icon", "document.querySelector('.el-icon-s-unfold')?.click()"),
            ]
            
            for method_name, selector_or_js in hamburger_methods:
                try:
                    print(f"\n   Trying: {method_name}")
                    
                    if selector_or_js.startswith('document.'):
                        # JavaScript
                        await page.evaluate(selector_or_js)
                    else:
                        # CSS selector
                        await page.click(selector_or_js, timeout=2000)
                    
                    await asyncio.sleep(2)
                    await page.screenshot(path=f'step7_hamburger_attempt.png')
                    print(f"   ✅ Attempted! Check screenshot")
                    break
                    
                except Exception as e:
                    print(f"      ❌ Failed")
            
            # MANUAL PAUSE
            print(f"\n" + "="*70)
            print(f"⏸️  PAUSED - Check browser window!")
            print(f"   👉 Manually click hamburger menu (☰) if not clicked")
            print(f"   👉 Then click 'Attendance Records' dropdown")
            print(f"   👉 Then click 'Transaction'")
            print(f"   Browser will auto-continue in 15 seconds...")
            print(f"="*70)
            await asyncio.sleep(15)
            
            # STEP 8: Attendance Records
            print(f"\n📋 STEP 8: Clicking Attendance Records...")
            await page.evaluate('''
                () => {
                    const els = Array.from(document.querySelectorAll('*'));
                    const target = els.find(el => 
                        el.innerText?.includes('Attendance Records') && 
                        el.tagName !== 'BODY'
                    );
                    if (target) {
                        target.click();
                        return true;
                    }
                    return false;
                }
            ''')
            
            await asyncio.sleep(3)
            
            # STEP 9: Transaction
            print(f"\n📑 STEP 9: Clicking Transaction...")
            await page.evaluate('''
                () => {
                    const els = Array.from(document.querySelectorAll('*'));
                    const target = els.find(el => 
                        el.innerText?.trim() === 'Transaction'
                    );
                    if (target) {
                        target.click();
                        return true;
                    }
                    return false;
                }
            ''')
            
            await asyncio.sleep(5)
            await page.screenshot(path='step9_transaction_page.png', full_page=True)
            print(f"   📸 Screenshot: step9_transaction_page.png")
            
            # STEP 10: Extract table
            print(f"\n📊 STEP 10: Extracting table...")
            
            table_data = await page.evaluate('''
                () => {
                    const rows = Array.from(document.querySelectorAll('table tbody tr'));
                    return rows.map(row => {
                        const cells = Array.from(row.querySelectorAll('td'));
                        return cells.map(cell => cell.innerText.trim());
                    });
                }
            ''')
            
            print(f"   Found {len(table_data)} rows")
            
            if len(table_data) > 0:
                print(f"\n📋 First 3 rows:")
                for i, row in enumerate(table_data[:3]):
                    print(f"      {i+1}. {row}")
                
                # Save to file
                with open('extracted_data.json', 'w', encoding='utf-8') as f:
                    json.dump(table_data, f, indent=2, ensure_ascii=False)
                
                print(f"\n   💾 Data saved to: extracted_data.json")
                print(f"   📊 Total: {len(table_data)} records")
            
            # Keep open
            print(f"\n" + "="*70)
            print(f"✅ EXTRACTION COMPLETE!")
            print(f"   📸 Screenshots: step*.png")
            print(f"   📋 Data: extracted_data.json, table_data.json")
            print(f"\n⏳ Browser staying open for 30 seconds for inspection...")
            print(f"="*70)
            
            await asyncio.sleep(30)
            
        except Exception as e:
            print(f"\n❌ Error: {e}")
            import traceback
            traceback.print_exc()
            
            print(f"\n⏳ Browser staying open for inspection...")
            await asyncio.sleep(60)
        
        finally:
            await browser.close()

if __name__ == '__main__':
    asyncio.run(main())

