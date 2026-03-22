
import sqlite3

def inspect_specific_tables(db_path, tables_to_check):
    try:
        conn = sqlite3.connect(db_path)
        cursor = conn.cursor()
        
        for t_name in tables_to_check:
            print(f"\nTable: {t_name}")
            try:
                cursor.execute(f"PRAGMA table_info({t_name})")
                columns = cursor.fetchall()
                if not columns:
                    print("  (Table not found or empty schema)")
                for col in columns:
                    print(f"  - {col[1]} ({col[2]})")
            except Exception as e:
                print(f"  Error inspecting table: {e}")
                
        # Also list all tables to serve as a double check
        print("\nAll Tables:")
        cursor.execute("SELECT name FROM sqlite_master WHERE type='table';")
        all_tables = cursor.fetchall()
        for t in all_tables:
            print(f"  - {t[0]}")

        conn.close()
    except Exception as e:
        print(f"Error: {e}")

if __name__ == "__main__":
    inspect_specific_tables("asset.db3", ["gfgh", "asset", "sn", "inventur"])
