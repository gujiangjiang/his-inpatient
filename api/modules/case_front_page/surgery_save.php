<?php
// api/modules/case_front_page/surgery_save.php
Auth::requireRole(['admin', 'doctor']);
$data = Validator::all();
$admissionNo = Validator::get('admission_no');

if (!$admissionNo) Response::error('请输入住院号');

$pdo = DB::getPDO();
$id = isset($data['id']) && $data['id'] ? $data['id'] : null;

if ($id) {
    $stmt = $pdo->prepare("UPDATE surgeries SET surgery_name=?, surgeon=?, anesthesia_type=?, surgery_date=?, incision_healing=?, complications=?, created_at=? WHERE id=?");
    $stmt->execute([
        $data['surgery_name'], $data['surgeon'], $data['anesthesia_type'],
        $data['surgery_date'], $data['incision_healing'], $data['complications'],
        DateHelper::now(), $id
    ]);
} else {
    $stmt = $pdo->prepare("INSERT INTO surgeries (patient_id, admission_no, surgery_name, surgeon, anesthesia_type, surgery_date, incision_healing, complications, created_by, created_at) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $data['patient_id'], $admissionNo, $data['surgery_name'], $data['surgeon'],
        $data['anesthesia_type'], $data['surgery_date'], $data['incision_healing'],
        $data['complications'], Auth::user()['id'], DateHelper::now()
    ]);
    $id = $pdo->lastInsertId();
}
Response::success(['id' => $id]);
