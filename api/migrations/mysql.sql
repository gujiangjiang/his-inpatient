-- MySQL 迁移文件

CREATE TABLE IF NOT EXISTS system_config (
    config_key VARCHAR(100) PRIMARY KEY,
    config_value TEXT,
    config_group VARCHAR(50),
    description TEXT,
    updated_at DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS departments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active TINYINT DEFAULT 1,
    created_at DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS wards (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    floor VARCHAR(20),
    bed_count INT DEFAULT 0,
    is_active TINYINT DEFAULT 1,
    created_at DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS beds (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ward_id INT NOT NULL,
    bed_no VARCHAR(20) NOT NULL,
    status VARCHAR(20) DEFAULT 'free',
    patient_id INT,
    FOREIGN KEY (ward_id) REFERENCES wards(id),
    FOREIGN KEY (patient_id) REFERENCES patients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(256) NOT NULL,
    name VARCHAR(100) NOT NULL,
    role VARCHAR(20) NOT NULL,
    department_id INT,
    ward_id INT,
    is_active TINYINT DEFAULT 1,
    created_at DATETIME,
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (ward_id) REFERENCES wards(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS patients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_no VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    gender VARCHAR(10),
    birth_date DATE,
    id_card VARCHAR(18),
    admission_no VARCHAR(50) UNIQUE NOT NULL,
    department_id INT,
    ward_id INT,
    bed_no VARCHAR(20),
    admission_date DATETIME,
    admission_diagnosis TEXT,
    admission_icd_code VARCHAR(20),
    status VARCHAR(20) DEFAULT 'active',
    created_at DATETIME,
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (ward_id) REFERENCES wards(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    admission_no VARCHAR(50) NOT NULL,
    parent_id INT,
    is_group_main TINYINT DEFAULT 0,
    order_type VARCHAR(50),
    content TEXT,
    category VARCHAR(50),
    status VARCHAR(20) DEFAULT 'draft',
    dosage VARCHAR(100),
    frequency VARCHAR(50),
    duration VARCHAR(100),
    created_by INT,
    verified_by INT,
    verified_at DATETIME,
    start_date DATETIME,
    end_date DATETIME,
    notes TEXT,
    created_at DATETIME,
    updated_at DATETIME,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (parent_id) REFERENCES orders(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (verified_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS emr_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    admission_no VARCHAR(50) NOT NULL,
    record_type VARCHAR(50) NOT NULL,
    content_delta TEXT,
    content_html LONGTEXT,
    diagnosis TEXT,
    icd_code VARCHAR(20),
    created_by INT,
    created_at DATETIME,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS emr_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    record_type VARCHAR(50) NOT NULL,
    content_delta TEXT,
    content_html LONGTEXT,
    usage_count INT DEFAULT 0,
    created_by INT,
    created_at DATETIME,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS nursing_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    patient_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    executed_by INT NOT NULL,
    executed_at DATETIME,
    notes TEXT,
    created_at DATETIME,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (executed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vital_signs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    admission_no VARCHAR(50) NOT NULL,
    recorded_by INT,
    recorded_at DATETIME,
    temperature DECIMAL(5,2),
    pulse DECIMAL(5,2),
    blood_pressure_systolic DECIMAL(5,2),
    blood_pressure_diastolic DECIMAL(5,2),
    respiratory_rate DECIMAL(5,2),
    oxygen_saturation DECIMAL(5,2),
    height DECIMAL(5,2),
    weight DECIMAL(5,2),
    notes TEXT,
    created_at DATETIME,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (recorded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS medications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(200) NOT NULL,
    specification VARCHAR(200),
    unit VARCHAR(20),
    price DECIMAL(10,2),
    stock_quantity INT DEFAULT 0,
    created_at DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dispensing_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    admission_no VARCHAR(50) NOT NULL,
    order_id INT,
    medication_id INT NOT NULL,
    quantity INT NOT NULL,
    dispensed_by INT NOT NULL,
    dispensed_at DATETIME,
    status VARCHAR(20) DEFAULT 'dispensed',
    notes TEXT,
    created_at DATETIME,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (medication_id) REFERENCES medications(id),
    FOREIGN KEY (dispensed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lab_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    admission_no VARCHAR(50) NOT NULL,
    report_no VARCHAR(50) UNIQUE NOT NULL,
    exam_name VARCHAR(200) NOT NULL,
    specimen_type VARCHAR(50),
    status VARCHAR(20) DEFAULT 'pending',
    is_critical TINYINT DEFAULT 0,
    reported_by INT,
    reported_at DATETIME,
    created_by INT,
    created_at DATETIME,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (reported_by) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lab_report_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    report_id INT NOT NULL,
    item_name VARCHAR(200) NOT NULL,
    result VARCHAR(200),
    unit VARCHAR(50),
    reference_range VARCHAR(200),
    flag VARCHAR(20),
    created_at DATETIME,
    FOREIGN KEY (report_id) REFERENCES lab_reports(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS exam_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    admission_no VARCHAR(50) NOT NULL,
    report_no VARCHAR(50) UNIQUE NOT NULL,
    exam_type VARCHAR(50) NOT NULL,
    body_part VARCHAR(200),
    status VARCHAR(20) DEFAULT 'pending',
    findings TEXT,
    conclusion TEXT,
    impression TEXT,
    reported_by INT,
    reported_at DATETIME,
    created_by INT,
    created_at DATETIME,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (reported_by) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS surgeries (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    admission_no VARCHAR(50) NOT NULL,
    surgery_name VARCHAR(200) NOT NULL,
    surgeon INT,
    anesthesia_type VARCHAR(50),
    surgery_date DATETIME,
    incision_healing VARCHAR(200),
    complications TEXT,
    created_by INT,
    created_at DATETIME,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (surgeon) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS case_front_page (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    admission_no VARCHAR(50) NOT NULL,
    content_delta TEXT,
    content_html LONGTEXT,
    created_by INT,
    created_at DATETIME,
    updated_at DATETIME,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS api_configs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    api_type VARCHAR(50) NOT NULL,
    base_url VARCHAR(500),
    auth_type VARCHAR(20) DEFAULT 'none',
    auth_config TEXT,
    is_active TINYINT DEFAULT 1,
    last_sync DATETIME,
    created_at DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS discharge_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    admission_no VARCHAR(50) NOT NULL,
    discharge_diagnosis TEXT,
    discharge_icd_code VARCHAR(20),
    discharge_advice TEXT,
    discharged_at DATETIME,
    discharged_by INT,
    created_at DATETIME,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (discharged_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
