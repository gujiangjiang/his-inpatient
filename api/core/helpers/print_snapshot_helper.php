<?php
// api/core/helpers/print_snapshot_helper.php - 不可变医疗打印快照存取与公共上下文

function print_snapshot_key($type, $businessId) {
    return trim((string)$type) . ':' . trim((string)$businessId);
}

function print_snapshot_capture($type, $businessId, array $payload, $patientId = null, $admissionNo = '', $userId = null) {
    $key = print_snapshot_key($type, $businessId);
    if ($key === ':') throw new InvalidArgumentException('快照类型和业务ID不能为空');
    $existing = DB::selectOne("SELECT * FROM print_snapshots WHERE snapshot_key = ?", [$key]);
    if ($existing) return print_snapshot_decode($existing);
    $now = DateHelper::now();
    $envelope = [
        'schema_version' => 1,
        'document_type' => (string)$type,
        'business_id' => (string)$businessId,
        'snapshot_at' => $now,
        'data' => $payload,
    ];
    $json = json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    if ($json === false) throw new RuntimeException('打印快照序列化失败');
    $hash = hash('sha256', $json);
    try {
        DB::insert('print_snapshots', [
            'snapshot_key' => $key,
            'document_type' => (string)$type,
            'business_id' => (string)$businessId,
            'patient_id' => $patientId === null ? null : (int)$patientId,
            'admission_no' => (string)$admissionNo,
            'snapshot_json' => $json,
            'content_hash' => $hash,
            'created_by' => $userId === null ? null : (int)$userId,
            'created_at' => $now,
        ]);
    } catch (PDOException $e) {
        $row = DB::selectOne("SELECT * FROM print_snapshots WHERE snapshot_key = ?", [$key]);
        if ($row) return print_snapshot_decode($row);
        throw $e;
    }
    return array_merge($envelope, ['snapshot_key' => $key, 'content_hash' => $hash]);
}

function print_snapshot_get($type, $businessId) {
    return print_snapshot_get_by_key(print_snapshot_key($type, $businessId));
}

function print_snapshot_get_by_key($key) {
    $row = DB::selectOne("SELECT * FROM print_snapshots WHERE snapshot_key = ?", [(string)$key]);
    return $row ? print_snapshot_decode($row) : null;
}

function print_snapshot_decode(array $row) {
    $json = (string)$row['snapshot_json'];
    $actualHash = hash('sha256', $json);
    if (!hash_equals((string)$row['content_hash'], $actualHash)) {
        throw new RuntimeException('打印快照完整性校验失败：' . $row['snapshot_key']);
    }
    $value = json_decode($json, true);
    if (!is_array($value)) return null;
    $value['snapshot_key'] = $row['snapshot_key'];
    $value['content_hash'] = $row['content_hash'];
    return $value;
}

function print_snapshot_brand() {
    $values = ['hospital_name' => '', 'hospital_name2' => ''];
    foreach (DB::select("SELECT config_key, config_value FROM system_config WHERE config_key IN ('hospital_name','hospital_name2')") as $row) {
        $values[$row['config_key']] = (string)$row['config_value'];
    }
    if ($values['hospital_name'] === '') $values['hospital_name'] = '开源通用医院信息系统';
    return $values;
}

function print_snapshot_patient($patientId, $at = null) {
    $patient = DB::selectOne(
        "SELECT p.*, d.name AS department_name, w.name AS ward_name
         FROM patients p
         LEFT JOIN departments d ON d.id = p.department_id
         LEFT JOIN wards w ON w.id = p.ward_id
         WHERE p.id = ?", [(int)$patientId]
    );
    if (!$patient) return [];
    $patient['patient_name'] = $patient['name'];
    $patient['age'] = AgeHelper::display($patient['birth_date'] ?? '', $at);
    $patient['age_snapshot'] = $patient['age'];
    return $patient;
}

function print_snapshot_with_context(array $data, $patientId, $at = null) {
    return array_merge($data, print_snapshot_patient($patientId, $at), print_snapshot_brand());
}
