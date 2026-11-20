import pyodbc
from datetime import date, timedelta

conn_str = (
    "DRIVER={ODBC Driver 17 for SQL Server};"
    "SERVER=127.0.0.1,1434;"
    "DATABASE=PharmaPro_v2;"
    "UID=sa;"
    "PWD=YourStrong@Passw0rd123;"
    "TrustServerCertificate=yes;"
)

conn = pyodbc.connect(conn_str, autocommit=False)
cur = conn.cursor()

def get_or_insert(select_sql, select_params, insert_sql, insert_params):
    cur.execute(select_sql, select_params)
    rows = cur.fetchall()
    if rows:
        return int(rows[0][0])
    cur.execute(insert_sql, insert_params)
    out = cur.fetchall()
    return int(out[0][0])

try:
    # 1. Khoa chuyên môn & Dạng bào chế (DosageForms dùng FormName + RouteOfAdmin)
    dept_hohap = get_or_insert(
        "SELECT DepartmentID FROM Departments WHERE DeptCode = ?", ("HOHAP",),
        "SET NOCOUNT ON; DECLARE @O TABLE(ID INT); INSERT INTO Departments(DeptCode, DeptName) OUTPUT INSERTED.DepartmentID INTO @O VALUES (?, ?); SELECT ID FROM @O;",
        ("HOHAP", "Khoa Hô hấp")
    )
    dept_ungbuou = get_or_insert(
        "SELECT DepartmentID FROM Departments WHERE DeptCode = ?", ("UNGBUOU",),
        "SET NOCOUNT ON; DECLARE @O TABLE(ID INT); INSERT INTO Departments(DeptCode, DeptName) OUTPUT INSERTED.DepartmentID INTO @O VALUES (?, ?); SELECT ID FROM @O;",
        ("UNGBUOU", "Khoa Ung bướu")
    )
    form_tab = get_or_insert(
        "SELECT DosageFormID FROM DosageForms WHERE FormName = ?", ("Viên nén",),
        "SET NOCOUNT ON; DECLARE @O TABLE(ID INT); INSERT INTO DosageForms(FormName, RouteOfAdmin) OUTPUT INSERTED.DosageFormID INTO @O VALUES (?, ?); SELECT ID FROM @O;",
        ("Viên nén", "Đường uống")
    )
    form_inj = get_or_insert(
        "SELECT DosageFormID FROM DosageForms WHERE FormName = ?", ("Dung dịch tiêm",),
        "SET NOCOUNT ON; DECLARE @O TABLE(ID INT); INSERT INTO DosageForms(FormName, RouteOfAdmin) OUTPUT INSERTED.DosageFormID INTO @O VALUES (?, ?); SELECT ID FROM @O;",
        ("Dung dịch tiêm", "Tiêm tĩnh mạch / Dưới da")
    )

    # 2. Hoạt chất chuẩn INN
    ing_para = get_or_insert(
        "SELECT IngredientID FROM ActiveIngredients WHERE IngredientName = ?", ("Paracetamol",),
        "SET NOCOUNT ON; DECLARE @O TABLE(ID INT); INSERT INTO ActiveIngredients(IngredientName) OUTPUT INSERTED.IngredientID INTO @O VALUES (?); SELECT ID FROM @O;",
        ("Paracetamol",)
    )
    ing_clari = get_or_insert(
        "SELECT IngredientID FROM ActiveIngredients WHERE IngredientName = ?", ("Clarithromycin",),
        "SET NOCOUNT ON; DECLARE @O TABLE(ID INT); INSERT INTO ActiveIngredients(IngredientName) OUTPUT INSERTED.IngredientID INTO @O VALUES (?); SELECT ID FROM @O;",
        ("Clarithromycin",)
    )
    ing_simva = get_or_insert(
        "SELECT IngredientID FROM ActiveIngredients WHERE IngredientName = ?", ("Simvastatin",),
        "SET NOCOUNT ON; DECLARE @O TABLE(ID INT); INSERT INTO ActiveIngredients(IngredientName) OUTPUT INSERTED.IngredientID INTO @O VALUES (?); SELECT ID FROM @O;",
        ("Simvastatin",)
    )
    ing_morphin = get_or_insert(
        "SELECT IngredientID FROM ActiveIngredients WHERE IngredientName = ?", ("Morphin Sulfat",),
        "SET NOCOUNT ON; DECLARE @O TABLE(ID INT); INSERT INTO ActiveIngredients(IngredientName) OUTPUT INSERTED.IngredientID INTO @O VALUES (?); SELECT ID FROM @O;",
        ("Morphin Sulfat",)
    )

    # 3. Cặp tương tác DDI Chống chỉ định (Hard Stop)
    cur.execute("SELECT 1 FROM Interactions WHERE (IngredientA_ID=? AND IngredientB_ID=?) OR (IngredientA_ID=? AND IngredientB_ID=?)",
                (ing_clari, ing_simva, ing_simva, ing_clari))
    if not cur.fetchone():
        cur.execute("""
            INSERT INTO Interactions (IngredientA_ID, IngredientB_ID, SeverityLevel, MechanismDescription)
            VALUES (?, ?, 'Contraindicated', N'Ức chế mạnh CYP3A4 làm tăng nồng độ Simvastatin, nguy cơ tiêu cơ vân cấp và kéo dài khoảng QT')
        """, (ing_clari, ing_simva))

    # 4. Biệt dược cho Substitution Engine (±20% giá vs ngoài biên độ) + DDI + Kiểm soát đặc biệt
    drug_hapacol = get_or_insert(
        "SELECT DrugID FROM Drugs WHERE DrugCode = ?", ("HAPACOL500",),
        """SET NOCOUNT ON; DECLARE @O TABLE(ID INT);
           INSERT INTO Drugs(DrugCode, DrugName, IngredientID, DosageFormID, Concentration, Manufacturer, DepartmentID, IsPrescriptionOnly, IsControlled, MinSafeCrCl, CurrentPrice, CurrentStock)
           OUTPUT INSERTED.DrugID INTO @O VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, 15.00, 38000, 0); SELECT ID FROM @O;""",
        ("HAPACOL500", "Hapacol 500mg DHG", ing_para, form_tab, "500mg", "DHG Pharma", dept_hohap)
    )
    drug_panadol_exp = get_or_insert(
        "SELECT DrugID FROM Drugs WHERE DrugCode = ?", ("PANADOL_VIP",),
        """SET NOCOUNT ON; DECLARE @O TABLE(ID INT);
           INSERT INTO Drugs(DrugCode, DrugName, IngredientID, DosageFormID, Concentration, Manufacturer, DepartmentID, IsPrescriptionOnly, IsControlled, MinSafeCrCl, CurrentPrice, CurrentStock)
           OUTPUT INSERTED.DrugID INTO @O VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, 15.00, 65000, 0); SELECT ID FROM @O;""",
        ("PANADOL_VIP", "Panadol Extra Nhập Khẩu", ing_para, form_tab, "500mg", "GSK", dept_hohap)
    )
    drug_clari = get_or_insert(
        "SELECT DrugID FROM Drugs WHERE DrugCode = ?", ("KLACID500",),
        """SET NOCOUNT ON; DECLARE @O TABLE(ID INT);
           INSERT INTO Drugs(DrugCode, DrugName, IngredientID, DosageFormID, Concentration, Manufacturer, DepartmentID, IsPrescriptionOnly, IsControlled, MinSafeCrCl, CurrentPrice, CurrentStock)
           OUTPUT INSERTED.DrugID INTO @O VALUES (?, ?, ?, ?, ?, ?, ?, 1, 0, 30.00, 120000, 0); SELECT ID FROM @O;""",
        ("KLACID500", "Klacid 500mg", ing_clari, form_tab, "500mg", "Abbott", dept_hohap)
    )
    drug_simva = get_or_insert(
        "SELECT DrugID FROM Drugs WHERE DrugCode = ?", ("ZOCOR20",),
        """SET NOCOUNT ON; DECLARE @O TABLE(ID INT);
           INSERT INTO Drugs(DrugCode, DrugName, IngredientID, DosageFormID, Concentration, Manufacturer, DepartmentID, IsPrescriptionOnly, IsControlled, MinSafeCrCl, CurrentPrice, CurrentStock)
           OUTPUT INSERTED.DrugID INTO @O VALUES (?, ?, ?, ?, ?, ?, ?, 1, 0, 30.00, 95000, 0); SELECT ID FROM @O;""",
        ("ZOCOR20", "Zocor 20mg", ing_simva, form_tab, "20mg", "MSD", dept_hohap)
    )
    drug_morphin = get_or_insert(
        "SELECT DrugID FROM Drugs WHERE DrugCode = ?", ("MORPHIN10",),
        """SET NOCOUNT ON; DECLARE @O TABLE(ID INT);
           INSERT INTO Drugs(DrugCode, DrugName, IngredientID, DosageFormID, Concentration, Manufacturer, DepartmentID, IsPrescriptionOnly, IsControlled, MinSafeCrCl, CurrentPrice, CurrentStock)
           OUTPUT INSERTED.DrugID INTO @O VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1, 30.00, 45000, 0); SELECT ID FROM @O;""",
        ("MORPHIN10", "Morphin Hydroclorid 10mg/1ml", ing_morphin, form_inj, "10mg/1ml", "Vidipha", dept_ungbuou)
    )

    # 5. Nhập lô hàng
    today = date.today()
    batches = [
        ("LOT-HAPA-NEAR", drug_hapacol, today - timedelta(days=300), today + timedelta(days=25), 10, 28000),
        ("LOT-HAPA-FAR",  drug_hapacol, today - timedelta(days=30),  today + timedelta(days=720), 50, 28000),
        ("LOT-PANA-01",   drug_panadol_exp, today - timedelta(days=60), today + timedelta(days=500), 40, 50000),
        ("LOT-CLARI-01",  drug_clari, today - timedelta(days=40), today + timedelta(days=400), 30, 90000),
        ("LOT-SIMVA-01",  drug_simva, today - timedelta(days=40), today + timedelta(days=400), 30, 70000),
        ("LOT-MORPH-01",  drug_morphin, today - timedelta(days=20), today + timedelta(days=365), 20, 32000),
    ]

    for b_code, d_id, mfg, exp, qty, price in batches:
        cur.execute("SELECT BatchID FROM InventoryBatches WHERE BatchCode = ?", (b_code,))
        if not cur.fetchone():
            cur.execute("""
                SET NOCOUNT ON; DECLARE @O TABLE(ID INT);
                INSERT INTO InventoryBatches(BatchCode, DrugID, ManufactureDate, ExpiryDate, CurrentStock, ImportPrice, IsLocked, ModifiedByUserID)
                OUTPUT INSERTED.BatchID INTO @O VALUES (?, ?, ?, ?, ?, ?, 0, 1); SELECT ID FROM @O;
            """, (b_code, d_id, mfg, exp, qty, price))
            b_id = int(cur.fetchone()[0])
            cur.execute("UPDATE Drugs SET CurrentStock = CurrentStock + ? WHERE DrugID = ?", (qty, d_id))

            if d_id == drug_morphin:
                cur.execute("""
                    INSERT INTO ControlledDrugLogs(DrugID, BatchID, TransactionType, QuantityChange, CabinetPhysicalStock, SystemStock, HandoverReportCode, ModifiedByUserID)
                    VALUES (?, ?, 'IMPORT', ?, 20, 20, 'BBGN-PL9-2026-001', 1)
                """, (d_id, b_id, qty))

    conn.commit()
    print("SEED RICH CLINICAL DATA V2 SUCCESS!")
except Exception as e:
    conn.rollback()
    print("SEED ERROR:", e)
    raise
finally:
    cur.close()
    conn.close()
