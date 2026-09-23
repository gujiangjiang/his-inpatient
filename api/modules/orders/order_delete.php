<?php
// api/modules/orders/order_delete.php - placeholder for 未来实现
Auth::requireRole(['admin', 'doctor']);
$id = Validator::get('id');
if (!$id) Response::error('请输入医嘱ID');
DB::delete('orders', 'id = ?', [$id]);
Response::success();
