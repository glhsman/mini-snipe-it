
import sqlite3

def inspect_gfgh_asset(db_path):
    try:
        conn = sqlite3.connect(db_path)
        cursor = conn.cursor()
        
        for t_name in ["gfgh", "asset"]: # Note: verify table name 'asset' or 'assets'? The previous list showed 'assets'.
            print(f"\nTable: {t_name}")
            try:
                # Try singular first
                cursor.execute(f"PRAGMA table_info({t_name})")
                columns = cursor.fetchall()
                if not columns:
                     # Try plural 'assets' if 'asset' fails
                    print(f"  (Table '{t_name}' empty/not found, trying plural 'assets' if applicable)")
                    if t_name == 'asset':
                         cursor.execute(f"PRAGMA table_info(assets)")
                         columns = cursor.fetchall()
                
                for col in columns:
                    print(f"  - {col[1]} ({col[2]})")
            except Exception as e:
                print(f"  Error: {e}")
                
        conn.close()
    except Exception as e:
        print(f"Error: {e}")

if __name__ == "__main__":
    inspect_gfgh_asset("asset.db3")
