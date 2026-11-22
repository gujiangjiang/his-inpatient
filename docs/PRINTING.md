# 统一打印模块

## 分层

- `public/js/core/print_document.js`：识别 A4、A5、半 A4，构建完整 HTML，集中维护通用单据样式与页码。
- `public/js/core/print_preview.js`：唯一允许创建打印窗口、写入文档和调用 `window.print()` 的位置；负责 iframe 原尺寸渲染、整体缩放、真实高度和打印介质规则。
- `public/js/core/print_preview_content.js`：业务入口（片段、页面元素、多文档拼接）及标准病历页眉。
- `public/js/core/print_barcode.js`：统一生成 Code 128B 矢量条形码。
- `public/js/core/print_label_templates.js`：腕带、试管条码、药品执行贴模板。
- `public/js/core/print_helper.js`：仅提供医院品牌抬头，不承载打印或条形码流程。

## 调用方式

完整打印文档：

```js
PrintPreview.open('电子病历 · 打印预览', documentHtml, { canPrint: true });
```

标准单据片段（`.sheet` 可带 `a5` 或 `half-a4`）：

```js
PrintPreview.openBody('住院处方笺', '<div class="sheet a5">...</div>');
```

页面已有元素：

```js
PrintPreview.openElement('入院记录', 'editor-container');
```

只读预览：

```js
PrintPreview.open('体温单 · 打印预览', documentHtml, { canPrint: false });
```

多份完整文档：

```js
PrintPreview.combine('医嘱单 · 打印预览', documents, { canPrint: true });
```

非标标签纸：

```js
PrintPreview.openLabels('标签', labelHtml, 'LAB_LABEL', { pageNumbers: false });

// 运行时注册其他热敏纸，单位为毫米
PrintDocument.registerPaper('CUSTOM_LABEL', 73, 41);
```

内置非标纸张：

| ID | 尺寸 | 用途 |
| --- | --- | --- |
| `WRISTBAND` | 250×25mm | 成人患者腕带 |
| `LAB_LABEL` | 50×30mm | 检验试管标签 |
| `MED_LABEL` | 60×60mm | 药品医嘱执行贴 |

条形码：

```js
PrintBarcode.svg(admissionNo, { height: 38, fontSize: 11 });
```

## 约束

1. 业务页面不得直接调用 `window.open()`、`document.write()` 或 `window.print()`。
2. 文档构建器只输出纯 HTML，不得夹带自动打印脚本。
3. 纸张内部不使用缩放或重排；容器放不下时只能整体缩放 iframe。
4. 屏幕预览保留灰底、等宽边距、阴影与页间距；`@media print` 必须移除这些装饰。
5. 护士患者详情显示除草稿外的完整医嘱留痕；医嘱单打印必须排除草稿和已作废医嘱。
6. 腕带和药品执行贴编码住院号；检验试管标签编码申请单号。
7. 业务模板不得自行实现条形码，统一调用 `PrintBarcode.svg()`。
