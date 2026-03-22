
import sqlite3

def inspect_db(db_path):
    try:
        conn = sqlite3.connect(db_path)
        cursor = conn.cursor()
        
        # Get all tables
        cursor.execute("SELECT name FROM sqlite_master WHERE type='table';")
        tables = cursor.fetchall()
        
        print(f"Tables found: {len(tables)}")
        for table_name in tables:
            t_name = table_name[0]
            print(f"\nTable: {t_name}")
            
            # Get columns for each table
            cursor.execute(f"PRAGMA table_info({t_name})")
            columns = cursor.fetchall()
            for col in columns:
                print(f"  - {col[1]} ({col[2]})")
                
        conn.close()
    except Exception as e:
        print(f"Error: {e}")

if __name__ == "__main__":
    inspect_db("asset.db3")
