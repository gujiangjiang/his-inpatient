<?php
// api/modules/notify/list.php - 我的通知列表 (含未读数)
Auth::requireRole(['admin', 'doctor', 'nurse', 'pharmacist', 'lab_tech']);
Response::success(notify_list((int)Auth::user()['id']));
