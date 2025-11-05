"""
Direct database save - Laravel API ke bina
Table data ko seedhe database mein save karta hai
"""
import json
import sys
import os

# Add parent directory to path for importing Laravel helpers
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

def save_to_database(json_file='table_scraped_data.json'):
    """Read JSON and save via Laravel tinker"""
    
    print("=" * 60)
    print("💾 Saving scraped data to database...")
    print("=" * 60)
    
    # Read JSON file
    if not os.path.exists(json_file):
        print(f"❌ File not found: {json_file}")
        return
    
    with open(json_file, 'r', encoding='utf-8') as f:
        table_data = json.load(f)
    
    print(f"📊 Found {len(table_data)} rows in JSON")
    
    # Convert to INSERT statements
    insert_statements = []
    
    for row in table_data:
        if len(row) >= 5:
            # Extract data from table columns
            first_name = row[0] if len(row) > 0 else ''
            last_name = row[1] if len(row) > 1 else ''
            person_code = row[2] if len(row) > 2 else ''
            full_path = row[3] if len(row) > 3 else ''
            clock_date = row[4] if len(row) > 4 else ''
            clock_time = row[5] if len(row) > 5 else ''
            
            full_name = f"{first_name} {last_name}".strip()
            
            if person_code and clock_date:
                insert_statements.append({
                    'person_code': person_code,
                    'full_name': full_name,
                    'department': full_path,
                    'attendance_date': clock_date,
                    'attendance_time': clock_time,
                })
    
    print(f"✅ Converted {len(insert_statements)} valid records")
    
    # Generate PHP tinker code
    php_code = """
use App\\Models\\HccAttendanceTransaction;

$records = """ + json.dumps(insert_statements, indent=2) + """;

$saved = 0;
foreach ($records as $record) {
    try {
        HccAttendanceTransaction::updateOrCreate(
            [
                'person_code' => $record['person_code'],
                'attendance_date' => $record['attendance_date'],
                'attendance_time' => $record['attendance_time'],
            ],
            [
                'full_name' => $record['full_name'],
                'department' => $record['department'],
                'source_data' => $record,
            ]
        );
        $saved++;
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\\n";
    }
}

echo "✅ Saved {$saved} records!\\n";
echo "📊 Total: " . HccAttendanceTransaction::count() . " records in database\\n";
"""
    
    # Save PHP code to file
    with open('save_to_db.php', 'w', encoding='utf-8') as f:
        f.write(php_code)
    
    print("\n" + "=" * 60)
    print("✅ PHP code generated: save_to_db.php")
    print("=" * 60)
    print("\n📝 Run this command to save:")
    print("   php artisan tinker < save_to_db.php")
    print("\nOr manually in tinker:")
    print("   php artisan tinker")
    print("   Then paste the code from save_to_db.php")
    print("=" * 60)

if __name__ == '__main__':
    json_file = sys.argv[1] if len(sys.argv) > 1 else 'table_scraped_data.json'
    save_to_database(json_file)

