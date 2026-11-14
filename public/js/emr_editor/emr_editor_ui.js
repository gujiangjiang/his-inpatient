// public/js/emr_editor/emr_editor_ui.js - 编辑弹层 UI: 下拉单选/复选/日期 (文档级 DOM, 与编辑器实例解耦)
(function () {
  'use strict';
    /* 下拉选项弹层 (填写槽 opts) */
    let choiceBox = null;
    const closeChoice = () => {
      if (choiceBox) { choiceBox.remove(); choiceBox = null; }
      document.removeEventListener('mousedown', onDocDown, true);
      document.removeEventListener('keydown', onChoiceKey, true);
    };
    const onDocDown = (ev) => { if (choiceBox && !choiceBox.contains(ev.target)) closeChoice(); };
    const onChoiceKey = (ev) => { if (ev.key === 'Escape') { ev.preventDefault(); closeChoice(); } };

    /* 日期类填写槽: 借全局 DateTimePicker (core/datetime_picker.js) 弹出选择框 */
    let dtpInput = null;
    const openDatePicker = (slotEl, type, current, onPick) => {
      if (typeof window.DateTimePicker === 'undefined') return false;
      if (dtpInput) { dtpInput.remove(); dtpInput = null; }
      const inp = document.createElement('input');
      inp.type = 'text';
      inp.dataset.dtpicker = type; /* 'date' | 'datetime' */
      inp.value = current || '';
      inp.style.cssText = 'position:fixed;opacity:0;width:1px;height:1px;pointer-events:none;border:0;padding:0;margin:0;';
      const r = slotEl.getBoundingClientRect();
      inp.style.left = Math.round(r.left) + 'px';
      inp.style.top = Math.round(r.bottom) + 'px';
      document.body.appendChild(inp);
      dtpInput = inp;
      let done = false;
      const finish = () => { if (!done) { done = true; if (dtpInput === inp) dtpInput = null; inp.remove(); } };
      /* 选择/清空均回写 (清空 = 移除槽位值) */
      inp.addEventListener('change', () => { const v = inp.value; finish(); onPick(v); });
      try { window.DateTimePicker.open(inp); } catch (e) { finish(); return false; }
      setTimeout(finish, 300000); /* 兜底清理 */
      return true;
    };
    /* 复选弹层: options 多选, 确定后以顿号拼接 (当前值按分隔符回填勾选) */
    const openMultiChoice = (slotEl, options, current, onOk) => {
      closeChoice();
      const box = document.createElement('div');
      box.className = 'emr-choice emr-choice-multi';
      const r = slotEl.getBoundingClientRect();
      box.style.left = Math.round(r.left) + 'px';
      box.style.top = Math.round(r.bottom + 2) + 'px';
      const cur = String(current || '').split(/[、,，;；]\s*/).filter(Boolean);
      options.forEach((opt) => {
        const lab = document.createElement('label');
        lab.className = 'emr-choice-ck';
        const ck = document.createElement('input');
        ck.type = 'checkbox';
        ck.value = opt;
        ck.checked = cur.indexOf(opt) >= 0;
        lab.appendChild(ck);
        lab.appendChild(document.createTextNode(' ' + opt));
        box.appendChild(lab);
      });
      const ft = document.createElement('div');
      ft.className = 'emr-choice-ft';
      const ok = document.createElement('button');
      ok.type = 'button'; ok.className = 'mini-btn mini-ok'; ok.textContent = '确定';
      const clr = document.createElement('button');
      clr.type = 'button'; clr.className = 'mini-btn'; clr.textContent = '清空';
      ft.appendChild(ok); ft.appendChild(clr);
      box.appendChild(ft);
      document.body.appendChild(box);
      const rr = box.getBoundingClientRect();
      if (rr.right > window.innerWidth - 8) box.style.left = Math.max(8, rr.left - (rr.right - window.innerWidth + 8)) + 'px';
      choiceBox = box;
      const mousedownGuard = (ev) => { ev.preventDefault(); };
      box.addEventListener('mousedown', mousedownGuard);
      ok.addEventListener('click', () => {
        const vals = Array.from(box.querySelectorAll('input[type=checkbox]:checked')).map((x) => x.value);
        closeChoice();
        onOk(vals.join('、'));
      });
      clr.addEventListener('click', () => { closeChoice(); onOk(''); });
      setTimeout(() => {
        document.addEventListener('mousedown', onDocDown, true);
        document.addEventListener('keydown', onChoiceKey, true);
      }, 0);
    };
    const openChoice = (slotEl, options, onPick) => {
      closeChoice();
      const box = document.createElement('div');
      box.className = 'emr-choice';
      const r = slotEl.getBoundingClientRect();
      box.style.left = Math.round(r.left) + 'px';
      box.style.top = Math.round(r.bottom + 2) + 'px';
      options.forEach((opt) => {
        const item = document.createElement('div');
        item.className = 'emr-choice-item';
        item.textContent = opt;
        item.addEventListener('mousedown', (ev) => {
          ev.preventDefault(); ev.stopPropagation();
          closeChoice();
          onPick(opt);
        });
        box.appendChild(item);
      });
      document.body.appendChild(box);
      choiceBox = box;
      setTimeout(() => {
        document.addEventListener('mousedown', onDocDown, true);
        document.addEventListener('keydown', onChoiceKey, true);
      }, 0);
    };
  const dispose = () => { closeChoice(); if (dtpInput) { dtpInput.remove(); dtpInput = null; } if (window.DateTimePicker && window.DateTimePicker.close) { try { window.DateTimePicker.close(); } catch (e) {} } };
  window.EmrEditorUI = { closeChoice, openChoice, openMultiChoice, openDatePicker, dispose };
})();
