<?php
// api/modules/emr/document/maintenance_list.php - 病历维护申请列表
// review=1 (管理员): 待审核清单 (含文书/患者/申请人信息); 默认: 本人申请记录
Auth::requireRole(['admin', 'doctor']);
$user = Auth::user();
$review = Validator::get('review');
$pdo = DB::getPDO();
$sql = "SELECT mr.*, d.title AS doc_title, d.status AS doc_status,
        p.name AS patient_name, p.admission_no,
        u.name AS requester_name, rv.name AS reviewer_name
        FROM emr_maintenance_requests mr
        LEFT JOIN emr_documents d ON d.id = mr.doc_id
        LEFT JOIN patients p ON p.id = d.patient_id
        LEFT JOIN users u ON u.id = mr.requested_by
        LEFT JOIN users rv ON rv.id = mr.reviewed_by
        WHERE 1=1";
$params = [];
if ($review === '1' && $user['role'] === 'admin') {
    $sql .= " AND mr.status = 'pending'";
} elseif ($user['role'] !== 'admin') {
    $sql .= " AND mr.requested_by = ?";
    $params[] = $user['id'];
}
$sql .= " ORDER BY mr.id DESC LIMIT 200";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
Response::success($stmt->fetchAll(PDO::FETCH_ASSOC));
