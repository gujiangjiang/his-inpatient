-- PostgreSQL 迁移文件

CREATE TABLE IF NOT EXISTS system_config (
    config_key VARCHAR(100) PRIMARY KEY,
    config_value TEXT,
    config_group VARCHAR(50),
    description TEXT,
    updated_at TIMESTAMP
);

CREATE TABLE IF NOT EXISTS departments (
    id SERIAL PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP
);

CREATE TABLE IF NOT EXISTS wards (
    id SERIAL PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    floor VARCHAR(20),
    bed_count INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP
);

CREATE TABLE IF NOT EXISTS beds (
    id SERIAL PRIMARY KEY,
    ward_id INTEGER NOT NULL REFERENCES wards(id),
    bed_no VARCHAR(20) NOT NULL,
    status VARCHAR(20) DEFAULT 'free',
    patient_id INTEGER REFERENCES patients(id)
);

CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(256) NOT NULL,
    name VARCHAR(100) NOT NULL,
    role VARCHAR(20) NOT NULL,
    department_id INTEGER REFERENCES departments(id),
    ward_id INTEGER REFERENCES wards(id),
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP
);

CREATE TABLE IF NOT EXISTS patients (
    id SERIAL PRIMARY KEY,
    patient_no VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    gender VARCHAR(10),
    birth_date DATE,
    id_card VARCHAR(18),
    admission_no VARCHAR(50) UNIQUE NOT NULL,
    department_id INTEGER REFERENCES departments(id),
    ward_id INTEGER REFERENCES wards(id),
    bed_no VARCHAR(20),
    admission_date TIMESTAMP,
    admission_diagnosis TEXT,
    admission_icd_code VARCHAR(20),
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP
);

CREATE TABLE IF NOT EXISTS orders (
    id SERIAL PRIMARY KEY,
    patient_id INTEGER NOT NULL REFERENCES patients(id),
    admission_no VARCHAR(50) NOT NULL,
    parent_id INTEGER REFERENCES orders(id),
    is_group_main BOOLEAN DEFAULT false,
    order_type VARCHAR(50),
    content TEXT,
    category VARCHAR(50),
    status VARCHAR(20) DEFAULT 'draft',
    dosage VARCHAR(100),
    frequency VARCHAR(50),
    duration VARCHAR(100),
    created_by INTEGER REFERENCES users(id),
    verified_by INTEGER REFERENCES users(id),
    verified_at TIMESTAMP,
    start_date TIMESTAMP,
    end_date TIMESTAMP,
    notes TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE TABLE IF NOT EXISTS emr_records (
    id SERIAL PRIMARY KEY,
    patient_id INTEGER NOT NULL REFERENCES patients(id),
    admission_no VARCHAR(50) NOT NULL,
    record_type VARCHAR(50) NOT NULL,
    content_delta TEXT,
    content_html TEXT,
    diagnosis TEXT,
    icd_code VARCHAR(20),
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP
);

CREATE TABLE IF NOT EXISTS emr_templates (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    record_type VARCHAR(50) NOT NULL,
    content_delta TEXT,
    content_html TEXT,
    usage_count INTEGER DEFAULT 0,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP
);

CREATE TABLE IF NOT EXISTS nursing_records (
    id SERIAL PRIMARY KEY,
    order_id INTEGER NOT NULL REFERENCES orders(id),
    patient_id INTEGER NOT NULL REFERENCES patients(id),
    action VARCHAR(100) NOT NULL,
    executed_by INTEGER NOT NULL REFERENCES users(id),
    executed_at TIMESTAMP,
    notes TEXT,
    created_at TIMESTAMP
);

CREATE TABLE IF NOT EXISTS vital_signs (
    id SERIAL PRIMARY KEY,
    patient_id INTEGER NOT NULL REFERENCES patients(id),
    admission_no VARCHAR(50) NOT NULL,
    recorded_by INTEGER REFERENCES users(id),
    recorded_at TIMESTAMP,
    temperature DECIMAL(5,2),
    pulse DECIMAL(5,2),
    blood_pressure_systolic DECIMAL(5,2),
    blood_pressure_diastolic DECIMAL(5,2),
    respiratory_rate DECIMAL(5,2),
    oxygen_saturation DECIMAL(5,2),
    height DECIMAL(5,2),
    weight DECIMAL(5,2),
    notes TEXT,
    created_at TIMESTAMP
);

CREATE TABLE IF NOT EXISTS medications (
    id SERIAL PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(200) NOT NULL,
    specification VARCHAR(200),
    unit VARCHAR(20),
    price DECIMAL(10,2),
    stock_quantity INTEGER DEFAULT 0,
    created_at TIMESTAMP
);

CREATE TABLE IF NOT EXISTS dispensing_records (
    id SERIAL PRIMARY KEY,
    patient_id INTEGER NOT NULL REFERENCES patients(id),
    admission_no VARCHAR(50) NOT NULL,
    order_id INTEGER REFERENCES orders(id),
    medication_id INTEGER NOT NULL REFERENCES medications(id),
    quantity INTEGER NOT NULL,
    dispensed_by INTEGER NOT NULL REFERENCES users(id),
    dispensed_at TIMESTAMP,
    status VARCHAR(20) DEFAULT 'dispensed',
    notes TEXT,
    created_at TIMESTAMP
);

CREATE TABLE IF NOT EXISTS lab_reports (
    id SERIAL PRIMARY KEY,
    patient_id INTEGER NOT NULL REFERENCES patients(id),
    admission_no VARCHAR(50) NOT NULL,
    report_no VARCHAR(50) UNIQUE NOT NULL,
    exam_name VARCHAR(200) NOT NULL,
    specimen_type VARCHAR(50),
    status VARCHAR(20) DEFAULT 'pending',
    is_critical BOOLEAN DEFAULT false,
    reported_by INTEGER REFERENCES users(id),
    reported_at TIMESTAMP,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP
);

CREATE TABLE IF NOT EXISTS lab_report_items (
    id SERIAL PRIMARY KEY,
    report_id INTEGER NOT NULL REFERENCES lab_reports(id),
    item_name VARCHAR(200) NOT NULL,
    result VARCHAR(200),
    unit VARCHAR(50),
    reference_range VARCHAR(200),
    flag VARCHAR(20),
    created_at TIMESTAMP
);

CREATE TABLE IF NOT EXISTS exam_reports (
    id SERIAL PRIMARY KEY,
    patient_id INTEGER NOT NULL REFERENCES patients(id),
    admission_no VARCHAR(50) NOT NULL,
    report_no VARCHAR(50) UNIQUE NOT NULL,
    exam_type VARCHAR(50) NOT NULL,
    body_part VARCHAR(200),
    status VARCHAR(20) DEFAULT 'pending',
    findings TEXT,
    conclusion TEXT,
    impression TEXT,
    reported_by INTEGER REFERENCES users(id),
    reported_at TIMESTAMP,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP
);

CREATE TABLE IF NOT EXISTS surgeries (
    id SERIAL PRIMARY KEY,
    patient_id INTEGER NOT NULL REFERENCES patients(id),
    admission_no VARCHAR(50) NOT NULL,
    surgery_name VARCHAR(200) NOT NULL,
    surgeon INTEGER REFERENCES users(id),
    anesthesia_type VARCHAR(50),
    surgery_date TIMESTAMP,
    incision_healing VARCHAR(200),
    complications TEXT,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP
);

CREATE TABLE IF NOT EXISTS case_front_page (
    id SERIAL PRIMARY KEY,
    patient_id INTEGER NOT NULL REFERENCES patients(id),
    admission_no VARCHAR(50) NOT NULL,
    content_delta TEXT,
    content_html TEXT,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE TABLE IF NOT EXISTS api_configs (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    api_type VARCHAR(50) NOT NULL,
    base_url VARCHAR(500),
    auth_type VARCHAR(20) DEFAULT 'none',
    auth_config TEXT,
    is_active BOOLEAN DEFAULT true,
    last_sync TIMESTAMP,
    created_at TIMESTAMP
);

CREATE TABLE IF NOT EXISTS discharge_records (
    id SERIAL PRIMARY KEY,
    patient_id INTEGER NOT NULL REFERENCES patients(id),
    admission_no VARCHAR(50) NOT NULL,
    discharge_diagnosis TEXT,
    discharge_icd_code VARCHAR(20),
    discharge_advice TEXT,
    discharged_at TIMESTAMP,
    discharged_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP
);
