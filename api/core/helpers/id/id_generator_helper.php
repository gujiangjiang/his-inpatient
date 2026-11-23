<?php
// api/core/helpers/id/id_generator_helper.php - 业务单号/流水号统一生成
// 两类编号（格式与既有实现逐字节一致，仅收敛实现与随机源）：
//  1. 随机型单号（医嘱号 O / 处方号 RX）：前缀 + YmdHis + 3 位随机（0-999，前导零）
//     随机源统一 random_int（CSPRNG）；orders.order_no 无唯一索引，靠随机低位不撞
//  2. 当日流水号（患者号 P / 住院号 A / 检验单 LAB / 检查单 EXAM）：前缀 + Ymd + 4 位序号
//     序号取「当日前缀最大尾号 + 1」，替代原 COUNT(*)+1——
//     消除「删除中间记录后 COUNT 回退复用已存在尾号」的撞唯一索引隐患；
//     正常无删除路径两算法输出完全一致。并发同刻取号仍可能撞唯一索引，
//     由调用方 INSERT 捕获重试兜底（与既有行为一致，后续统一接入）。
// 说明：单号中的 date() 为编号编码（本地墙钟语义），非存储时间列写入，
//       不违反「存储时间一律 TimeHelper/DateHelper」铁律。

/** 随机型单号：{prefix} + YmdHis + 3 位随机 */
function doc_no($prefix = 'O') {
    return $prefix . date('YmdHis') . str_pad((string)random_int(0, 999), 3, '0', STR_PAD_LEFT);
}

/** 当日流水号：{prefix} + Ymd + 4 位序号（当日前缀最大尾号 + 1，起始 0001）
 *  $table/$column 仅限调用方字面量（同 DB::insert 等表名插值约定），不接收用户输入 */
function seq_serial($table, $column, $prefix) {
    $dayPrefix = $prefix . date('Ymd');
    $row = DB::selectOne("SELECT MAX({$column}) AS m FROM {$table} WHERE {$column} LIKE ?", [$dayPrefix . '%']);
    $last = 0;
    if ($row && is_string($row['m']) && strpos($row['m'], $dayPrefix) === 0) {
        $tail = substr($row['m'], strlen($dayPrefix));
        if (ctype_digit($tail)) $last = (int)$tail;
    }
    return $dayPrefix . str_pad((string)($last + 1), 4, '0', STR_PAD_LEFT);
}

/** 取当日流水号尾号（供多列共用同尾号，如患者号 P/住院号 A） */
function seq_serial_tail($table, $column, $prefix) {
    $serial = seq_serial($table, $column, $prefix);
    return substr($serial, strlen($prefix . date('Ymd')));
}
