<?php
// api/modules/auth/logout.php
Auth::init();
Auth::logout();
Response::success();
