"""
HCC Final Auto - Uses the WORKING debug script logic
Simplified & reliable for cron jobs
"""
import subprocess
import os
import sys

# Fix Windows encoding
if sys.platform == 'win32':
    try:
        sys.stdout.reconfigure(encoding='utf-8')
        sys.stderr.reconfigure(encoding='utf-8')
    except:
        os.environ['PYTHONIOENCODING'] = 'utf-8'

def main():
    print("=" * 70)
    print("🤖 HCC Final Auto Sync")
    print("=" * 70)
    
    script_dir = os.path.dirname(os.path.abspath(__file__))
    
    # Step 1: Run browser scraper (the one that WORKS!)
    print("\n📡 Step 1/2: Fetching data via browser...")
    print("-" * 70)
    
    debug_script = os.path.join(script_dir, 'hcc_debug_browser.py')
    
    result1 = subprocess.run(
        ['python', debug_script],
        cwd=script_dir,
        capture_output=False,  # Show output live
        text=True
    )
    
    if result1.returncode != 0:
        print("\n❌ Browser scraping failed!")
        return 1
    
    # Step 2: Import to database
    print("\n" + "=" * 70)
    print("💾 Step 2/2: Saving to database...")
    print("-" * 70)
    
    php_script = os.path.join(script_dir, 'save_api_data.php')
    
    # Detect OS and use correct PHP path
    if os.path.exists('/usr/local/lsws/lsphp82/bin/php'):
        php_path = '/usr/local/lsws/lsphp82/bin/php'  # Linux server
    elif os.path.exists('/usr/bin/php'):
        php_path = '/usr/bin/php'
    else:
        php_path = 'php'  # Windows fallback
    
    result2 = subprocess.run(
        [php_path, php_script],
        cwd=script_dir,
        capture_output=False,  # Show output live
        text=True,
        encoding='utf-8'
    )
    
    if result2.returncode == 0:
        print("\n" + "=" * 70)
        print("✅ Auto Sync Complete!")
        print("=" * 70)
        return 0
    else:
        print("\n❌ Database import failed!")
        return 1

if __name__ == '__main__':
    exit_code = main()
    sys.exit(exit_code)

