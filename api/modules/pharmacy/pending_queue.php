<?php
// api/modules/pharmacy/pending_queue.php
Auth::requireRole(['admin', 'pharmacist']);
$sql = "SELECT o.*, p.name as patient_name, p.bed_no, p.ward_id, w.name as ward_name, d.name as department_name, u.name as doctor_name
        FROM orders o
        LEFT JOIN patients p ON o.patient_id = p.id
        LEFT JOIN wards w ON p.ward_id = w.id
        LEFT JOIN departments d ON p.department_id = d.id
        LEFT JOIN users u ON o.created_by = u.id
        WHERE o.category = 'medication' AND o.status = 'verified'
        ORDER BY o.created_at ASC";
$stmt = DB::getPDO()->query($sql);
Response::success($stmt->fetchAll(PDO::FETCH_ASSOC));
