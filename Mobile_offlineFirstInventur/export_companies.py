
import sqlite3
import json

def export_companies(db_path):
    try:
        conn = sqlite3.connect(db_path)
        cursor = conn.cursor()
        cursor.execute("SELECT gfghid, name_of FROM gfgh ORDER BY name_of")
        rows = cursor.fetchall()
        companies = [{"id": r[0], "name": r[1]} for r in rows]
        print(json.dumps(companies, indent=2))
        conn.close()
    except Exception as e:
        print(f"Error: {e}")

if __name__ == "__main__":
    export_companies("asset.db3")
