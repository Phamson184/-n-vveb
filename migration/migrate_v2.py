import re, pyodbc

server = "localhost"
conn_str = f"DRIVER={{ODBC Driver 17 for SQL Server}};SERVER={server};DATABASE=master;Trusted_Connection=yes;TrustServerCertificate=yes;"
print(f"Dang ket noi toi SQL Server ({server}) bang Windows Authentication...")

try:
    conn = pyodbc.connect(conn_str, autocommit=True)
except Exception as e:
    conn_str = f"DRIVER={{ODBC Driver 18 for SQL Server}};SERVER={server};DATABASE=master;Trusted_Connection=yes;TrustServerCertificate=yes;"
    conn = pyodbc.connect(conn_str, autocommit=True)

cursor = conn.cursor()

# Sửa lại đường dẫn đọc file trực tiếp từ thư mục hiện tại
with open("schema_v2.sql", "r", encoding="utf-8") as f:
    sql_script = f.read()

batches = [b.strip() for b in re.split(r"^\s*GO\s*$", sql_script, flags=re.MULTILINE | re.IGNORECASE) if b.strip()]
for idx, batch in enumerate(batches, 1):
    cursor.execute(batch)
    print(f"[OK] Da thuc thi batch {idx}/{len(batches)}")

print("==> HOAN TAT KHOI TAO DATABASE PharmaPro_v2 THANH CONG!")
