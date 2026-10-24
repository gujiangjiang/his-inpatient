# 部署指南（宝塔面板 / Nginx / Apache）

本系统为「`public/` 为 Web 根 + 单入口 `public/index.php`」架构：

- **Web 服务器根目录必须指向项目的 `public/`**（`api/`、`data/` 位于其外，天然不可访问）；
- `public/index.php` 是唯一入口：`/api/*` 在其内部转发到项目根 `api/index.php`，
  其余路径输出 SPA 外壳——**所有不存在的路径（含 `/api/*`）必须兜底到 `/index.php`**，
  否则 `/api/login` 之类请求会被 Nginx 当静态文件找 → 404；
- 首次访问 `http://<地址>/` 自动进入系统初始化向导（创建管理员）；
  也可用 CLI 初始化：`npm run init`（下载依赖 + 建库 + 导入 ICD-10 + 演示数据）。

完整 Nginx 参考配置见 `docs/nginx.conf.example`。

## 一、Nginx 站点配置

```nginx
server {
    listen 80;
    server_name his.example.com;

    root /path/to/HIS/public;      # 必须指向 public 目录
    index index.php;

    client_max_body_size 20m;      # 上传限制（患者照片 / 附件，按需调整）

    # 主路由：所有动态请求（含 /api/*）兜底到唯一入口
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM（按实际监听地址修改）
    location ~ \.php$ {
        try_files $uri =404;
        include fastcgi_params;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_read_timeout 120;
    }
}
```

## 二、宝塔面板（BT Panel）部署步骤

1. **创建站点**：网站 → 添加站点，PHP 版本选择 **7.4 / 8.0 / 8.2**
   （代码保持 PHP 7.x 兼容，PHP 8.x 可运行；需开启 `pdo_sqlite` + `sqlite3` 扩展，
   宝塔默认已开启）。域名 / 端口按实际填写。
2. **站点根目录**：两种方式任选其一——
   - 手动编辑站点配置，把 `root` 指向 `.../public`（推荐，与示例一致）；或
   - 网站目录保持项目根，用宝塔「**运行目录**」设置为 `/public`，
     这样面板的 `open_basedir` 会自动覆盖整个项目目录。
3. **伪静态**（关键）：网站 → 设置 → 伪静态，填入：

   ```nginx
   location / {
       try_files $uri $uri/ /index.php?$query_string;
   }
   ```

   也可直接写入 `/www/server/panel/vhost/rewrite/<站点名>.conf`。

4. **注释 404 错误页**：宝塔默认配置里的 `error_page 404 /404.html;` 指向
   `public/404.html`——该文件不存在，会在错误日志刷
   `open() ".../public/404.html" failed`。进入 网站 → 设置 → 配置文件，
   把 `error_page 404 /404.html;` 注释掉（应用自身会输出错误页）。
5. **关闭防跨站攻击（open_basedir）**：网站 → 设置 → 网站目录 →
   关闭「防跨站攻击」。PHP 需要读写 `public/` **上一级**的 `data/`（SQLite、会话），
   open_basedir 仅限 `public` 会导致 500 / 白屏。
   如不想关闭，请确保其路径包含项目根目录。
6. **目录权限**：宝塔 PHP 以 `www` 用户运行，确保项目目录属主与写权限：

   ```bash
   chown -R www:www /www/wwwroot/HIS
   # data/（数据库/会话）与 public/uploads/（上传）需可写
   ```

7. **上传大小**：如患者照片 / 附件上传失败，确认 `client_max_body_size 20m;`
   （Nginx 配置）与 PHP `upload_max_filesize` / `post_max_size` 一致。
8. **访问初始化**：浏览器打开 `http://<地址>/`，首次自动进入初始化向导。

### 内网（无外网）部署

先在有外网的机器上（或部署前）执行 `npm run download-libs`，将 Quill.js /
html2pdf.js 下载到 `public/lib/`；未下载时页面会回退 CDN，内网将加载失败。

## 三、Apache 配置（宝塔 Apache 环境同理）

站点根目录指向 `public/`，伪静态填入：

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ /index.php [L,QSA]
```

或使用 httpd 2.4 的 `FallbackResource /index.php`（放在站点 Directory 中）。
确认站点 `AllowOverride All`（宝塔默认开启）。

## 四、常见问题排查

| 现象 | 原因 | 处理 |
| --- | --- | --- |
| 访问 `/api/xxx` 或任意路径 404，错误日志出现 `open() ".../public/xxx" failed` | 缺少主路由兜底 | 按上文添加 `location / try_files` 伪静态 |
| 错误日志出现 `open() ".../public/404.html" failed` | 宝塔默认 404 页指向不存在的文件 | 注释 `error_page 404 /404.html;` |
| 500 / 白屏，日志出现 `open_basedir restriction in effect` | 防跨站攻击限制在 `public` | 关闭「防跨站攻击」或将其路径改为项目根 |
| 安装向导可打开但初始化失败 | `data/` 不可写 | `chown -R www:www` 项目目录 |
| 页面样式 / 图表异常 | `public/lib/` 未下载且无外网 | 执行 `npm run download-libs` 后同步到服务器 |
| 数据库连接失败 | 未开启 SQLite 扩展 | 宝塔 PHP 设置中开启 `pdo_sqlite` / `sqlite3` |
