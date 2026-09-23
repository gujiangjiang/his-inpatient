<?php
// api/modules/emr/document_list.php
Auth::requireRole(['admin', 'doctor']);
$patientId = Validator::get('patient_id');
$categoryId = Validator::get('category_id');
$admissionNo = Validator::get('admission_no');

if (!$patientId && !$admissionNo) Response::error('请输入患者ID或住院号');

$sql = "SELECT d.*, c.name as category_name, t.name as template_name,
        u.name as created_name, s.name as signer_name
        FROM emr_documents d
        LEFT JOIN emr_categories c ON d.category_id = c.id
        LEFT JOIN emr_templates t ON d.template_id = t.id
        LEFT JOIN users u ON d.created_by = u.id
        LEFT JOIN users s ON d.signer_id = s.id
        WHERE 1=1";
$params = [];
if ($patientId) { $sql .= " AND d.patient_id = ?"; $params[] = $patientId; }
if ($admissionNo) { $sql .= " AND d.admission_no = ?"; $params[] = $admissionNo; }
if ($categoryId) { $sql .= " AND d.category_id = ?"; $params[] = $categoryId; }

$sql .= " ORDER BY d.category_id ASC, d.updated_at DESC";
$stmt = DB::getPDO()->prepare($sql);
$stmt->execute($params);
Response::success($stmt->fetchAll(PDO::FETCH_ASSOC));