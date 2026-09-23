-- SQLite 迁移文件 (已弃用，由 php/ 目录基于 Migration 类生成的代码替代)
-- 结构同构 MySQL/PostgreSQL

-- 系统配置
CREATE TABLE IF NOT EXISTS system_config (
    config_key TEXT PRIMARY KEY,
    config_value TEXT,
    config_group TEXT,
    description TEXT,
    updated_at TEXT
);

-- 科室
CREATE TABLE IF NOT EXISTS departments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    description TEXT,
    is_active INTEGER DEFAULT 1,
    created_at TEXT DEFAULT (datetime('now'))
);

-- 病区
CREATE TABLE IF NOT EXISTS wards (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    floor TEXT,
    bed_count INTEGER DEFAULT 0,
    is_active INTEGER DEFAULT 1,
    created_at TEXT DEFAULT (datetime('now'))
);

-- 床位
CREATE TABLE IF NOT EXISTS beds (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ward_id INTEGER NOT NULL,
    bed_no TEXT NOT NULL,
    status TEXT DEFAULT 'free',
    patient_id INTEGER,
    FOREIGN KEY (ward_id) REFERENCES wards(id),
    FOREIGN KEY (patient_id) REFERENCES patients(id)
);

-- 用户
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    name TEXT NOT NULL,
    role TEXT NOT NULL,
    department_id INTEGER,
    ward_id INTEGER,
    is_active INTEGER DEFAULT 1,
    created_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (ward_id) REFERENCES wards(id)
);

-- 患者
CREATE TABLE IF NOT EXISTS patients (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    patient_no TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    gender TEXT,
    birth_date TEXT,
    id_card TEXT,
    admission_no TEXT UNIQUE NOT NULL,
    department_id INTEGER,
    ward_id INTEGER,
    bed_no TEXT,
    admission_date TEXT,
    admission_diagnosis TEXT,
    admission_icd_code TEXT,
    status TEXT DEFAULT 'active',
    created_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (ward_id) REFERENCES wards(id)
);

-- 医嘱
CREATE TABLE IF NOT EXISTS orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    patient_id INTEGER NOT NULL,
    admission_no TEXT NOT NULL,
    parent_id INTEGER,
    is_group_main INTEGER DEFAULT 0,
    order_type TEXT,
    content TEXT,
    category TEXT,
    status TEXT DEFAULT 'draft',
    is_auto_generated INTEGER DEFAULT 0,
    dosage TEXT,
    frequency TEXT,
    duration TEXT,
    created_by INTEGER,
    verified_by INTEGER,
    verified_at TEXT,
    start_date TEXT,
    end_date TEXT,
    notes TEXT,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (parent_id) REFERENCES orders(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (verified_by) REFERENCES users(id)
);

-- 电子病历
CREATE TABLE IF NOT EXISTS emr_records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    patient_id INTEGER NOT NULL,
    admission_no TEXT NOT NULL,
    record_type TEXT NOT NULL,
    content_delta TEXT,
    content_html TEXT,
    diagnosis TEXT,
    icd_code TEXT,
    created_by INTEGER,
    created_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- EMR 模板
CREATE TABLE IF NOT EXISTS emr_templates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    record_type TEXT NOT NULL,
    content_delta TEXT,
    content_html TEXT,
    usage_count INTEGER DEFAULT 0,
    created_by INTEGER,
    created_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- 护理记录
CREATE TABLE IF NOT EXISTS nursing_records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL,
    patient_id INTEGER NOT NULL,
    action TEXT NOT NULL,
    executed_by INTEGER NOT NULL,
    executed_at TEXT,
    notes TEXT,
    created_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (executed_by) REFERENCES users(id)
);

-- 生命体征
CREATE TABLE IF NOT EXISTS vital_signs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    patient_id INTEGER NOT NULL,
    admission_no TEXT NOT NULL,
    recorded_by INTEGER,
    recorded_at TEXT,
    temperature REAL,
    pulse REAL,
    blood_pressure_systolic REAL,
    blood_pressure_diastolic REAL,
    respiratory_rate REAL,
    oxygen_saturation REAL,
    height REAL,
    weight REAL,
    notes TEXT,
    created_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (recorded_by) REFERENCES users(id)
);

-- 药品库存
CREATE TABLE IF NOT EXISTS medications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    specification TEXT,
    unit TEXT,
    price REAL,
    stock_quantity INTEGER DEFAULT 0,
    created_at TEXT DEFAULT (datetime('now'))
);

-- 发药记录
CREATE TABLE IF NOT EXISTS dispensing_records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    patient_id INTEGER NOT NULL,
    admission_no TEXT NOT NULL,
    order_id INTEGER,
    medication_id INTEGER NOT NULL,
    quantity INTEGER NOT NULL,
    dispensed_by INTEGER NOT NULL,
    dispensed_at TEXT,
    status TEXT DEFAULT 'dispensed',
    notes TEXT,
    created_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (medication_id) REFERENCES medications(id),
    FOREIGN KEY (dispensed_by) REFERENCES users(id)
);

-- 检验报告
CREATE TABLE IF NOT EXISTS lab_reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    patient_id INTEGER NOT NULL,
    admission_no TEXT NOT NULL,
    report_no TEXT UNIQUE NOT NULL,
    exam_name TEXT NOT NULL,
    specimen_type TEXT,
    status TEXT DEFAULT 'pending',
    is_critical INTEGER DEFAULT 0,
    reported_by INTEGER,
    reported_at TEXT,
    created_by INTEGER,
    created_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (reported_by) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- 检验明细
CREATE TABLE IF NOT EXISTS lab_report_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    report_id INTEGER NOT NULL,
    item_name TEXT NOT NULL,
    result TEXT,
    unit TEXT,
    reference_range TEXT,
    flag TEXT,
    created_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (report_id) REFERENCES lab_reports(id)
);

-- 检查报告
CREATE TABLE IF NOT EXISTS exam_reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    patient_id INTEGER NOT NULL,
    admission_no TEXT NOT NULL,
    report_no TEXT UNIQUE NOT NULL,
    exam_type TEXT NOT NULL,
    body_part TEXT,
    status TEXT DEFAULT 'pending',
    findings TEXT,
    conclusion TEXT,
    impression TEXT,
    reported_by INTEGER,
    reported_at TEXT,
    created_by INTEGER,
    created_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (reported_by) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- 外科记录
CREATE TABLE IF NOT EXISTS surgeries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    patient_id INTEGER NOT NULL,
    admission_no TEXT NOT NULL,
    surgery_name TEXT NOT NULL,
    surgeon INTEGER,
    anesthesia_type TEXT,
    surgery_date TEXT,
    incision_healing TEXT,
    complications TEXT,
    created_by INTEGER,
    created_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (surgeon) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- 住院病案首页
CREATE TABLE IF NOT EXISTS case_front_page (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    patient_id INTEGER NOT NULL,
    admission_no TEXT NOT NULL,
    content_delta TEXT,
    content_html TEXT,
    created_by INTEGER,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- 外部接口配置
CREATE TABLE IF NOT EXISTS api_configs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    api_type TEXT NOT NULL,
    base_url TEXT,
    auth_type TEXT DEFAULT 'none',
    auth_config TEXT,
    is_active INTEGER DEFAULT 1,
    last_sync TEXT,
    created_at TEXT DEFAULT (datetime('now'))
);

-- 出院记录
CREATE TABLE IF NOT EXISTS discharge_records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    patient_id INTEGER NOT NULL,
    admission_no TEXT NOT NULL,
    discharge_diagnosis TEXT,
    discharge_icd_code TEXT,
    discharge_advice TEXT,
    discharged_at TEXT,
    discharged_by INTEGER,
    created_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (discharged_by) REFERENCES users(id)
);
