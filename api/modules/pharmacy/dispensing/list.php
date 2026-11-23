<?php
// api/modules/pharmacy/dispensing_list.php
Auth::requireRole(['admin', 'pharmacist']);
$sql = "SELECT dr.*, p.name as patient_name, p.bed_no, w.name as ward_name, m.name as med_name, u.name as dispenser_name
        FROM dispensing_records dr
        LEFT JOIN patients p ON dr.patient_id = p.id
        LEFT JOIN wards w ON p.ward_id = w.id
        LEFT JOIN medications m ON dr.medication_id = m.id
        LEFT JOIN users u ON dr.dispensed_by = u.id
        ORDER BY dr.created_at DESC";
$stmt = DB::getPDO()->query($sql);
Response::success($stmt->fetchAll(PDO::FETCH_ASSOC));
