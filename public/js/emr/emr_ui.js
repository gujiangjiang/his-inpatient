// public/js/emr/emr_ui.js - EMR 通用 UI 小部件 (浮动菜单等)
// 供病历站页面片段与设计器共用; 统一处理定位回拢/外点关闭/Esc/二级菜单
(function () {
  'use strict';

  /**
   * 浮动右键菜单
   * @param items [{ a, label, danger, sub: [item...] }]  a=动作标识; sub=二级菜单
   * @param x, y  页面坐标 (clientX/clientY)
   * @param onPick(action, item)  点击项回调 (二级项同样回调其 a); 菜单随即关闭
   * @returns close() 手动关闭
   */
  const floatMenu = (items, x, y, onPick) => {
    document.querySelectorAll('.emr-ctx-float').forEach((m) => m.remove());
    const menu = document.createElement('div');
    menu.className = 'emr-context-menu emr-ctx-float';
    menu.style.cssText = 'position:fixed;z-index:5200;left:' + x + 'px;top:' + y + 'px;min-width:132px;';

    const itemHTML = (it) => {
      const cls = 'ctx-item' + (it.danger ? ' danger' : '') + (Array.isArray(it.sub) && it.sub.length ? ' has-sub' : '');
      const sub = (Array.isArray(it.sub) && it.sub.length)
        ? '<div class="ctx-sub">' + it.sub.map(itemHTML).join('') + '</div>'
        : '';
      return '<div class="' + cls + '" data-a="' + String(it.a || '') + '">'
        + '<span>' + String(it.label == null ? '' : it.label) + '</span>'
        + (sub ? '<span class="ctx-caret">›</span>' + sub : '')
        + '</div>';
    };
    menu.innerHTML = items.map(itemHTML).join('');
    document.body.appendChild(menu);

    const r = menu.getBoundingClientRect();
    if (r.right > window.innerWidth - 8) menu.style.left = Math.max(8, x - r.width) + 'px';
    if (r.bottom > window.innerHeight - 8) menu.style.top = Math.max(8, y - r.height) + 'px';

    const close = () => {
      menu.remove();
      document.removeEventListener('mousedown', onDoc, true);
      document.removeEventListener('keydown', onEsc, true);
    };
    const onDoc = (ev) => { if (!menu.contains(ev.target)) close(); };
    const onEsc = (ev) => { if (ev.key === 'Escape') close(); };
    setTimeout(() => {
      document.addEventListener('mousedown', onDoc, true);
      document.addEventListener('keydown', onEsc, true);
    }, 0);

    menu.querySelectorAll('.ctx-item').forEach((el) => {
      if (el.querySelector('.ctx-sub')) return; /* 含二级菜单的父项: 仅悬浮展开, 不响应点击动作 */
      el.addEventListener('click', () => {
        const a = el.dataset.a;
        close();
        if (typeof onPick === 'function') onPick(a, el);
      });
    });
    return close;
  };

  /** 转义文本 (标签等动态值写入 innerHTML 前必用) */
  const esc = (s) => String(s == null ? '' : s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

  /**
   * 通用「管理列表」模态框外壳 (模板管理/内容库共用):
   * 本科室 = 左分类 / 右列表; 全院 = 左科室 / 中分类 / 右列表 (三栏)
   * cfg: {
   *   title, listUrl, catsUrl,
   *   myDeptId, deptNameOf(item), isOwn(item), deptKeyOf(item), catKeyOf(item), nameOf(item),
   *   tagsHtml(item, own, tab), actionsHtml(item, own), unitLabel,
   *   emptyText(catName, deptName, tab), newLabel, onNew(api, defCat), onRowAction(action, item, api)
   * }
   * 返回 open(preCatId) —— 打开管理模态框
   */
  const managedList = (cfg) => {
    const esc2 = esc;
    return async function openManager(preCatId) {
      const [elemRes, catRes] = await Promise.all([
        AjaxLoader.api(cfg.listUrl, { silent: true }),
        cfg.catsUrl ? AjaxLoader.api(cfg.catsUrl, { silent: true }) : Promise.resolve({ data: [] })
      ]);
      let items = elemRes.data || [];
      const cats = catRes.data || [];
      let activeTab = 'mine';
      let activeCat = (typeof preCatId === 'string' || typeof preCatId === 'number') ? String(preCatId) : (cfg.defCat || 'all');
      let activeDept = '0';

      const mineFilter = cfg.mineFilter || cfg.isOwn;
      const tabItems = () => activeTab === 'mine'
        ? items.filter((t) => mineFilter(t))
        : items.filter((t) => cfg.deptKeyOf(t) === activeDept);
      const catTpls = (key) => key === 'all'
        ? tabItems()
        : tabItems().filter((t) => cfg.catKeyOf(t) === key);

      const content = '<div class="emr-tpm">'
        + '<div class="emr-tpl-tabs"><span class="emr-tpl-tab active" data-tab="mine">本科室</span><span class="emr-tpl-tab" data-tab="all">全院</span></div>'
        + '<div class="emr-tpm-body">'
        + '<div class="emr-tpm-depts" id="emr-mgr-depts" style="display:none"></div>'
        + '<div class="emr-tpm-cats" id="emr-mgr-cats"></div>'
        + '<div class="emr-tpm-main" id="emr-mgr-main"></div>'
        + '</div>'
        + '<div class="emr-tpl-foot"><button type="button" class="btn btn-small btn-primary" id="emr-mgr-new">' + esc2(cfg.newLabel) + '</button><span class="emr-tb-spacer"></span><button type="button" class="btn" id="emr-mgr-close">关闭</button></div>'
        + '</div>';
      const overlay = Dom.modal(cfg.title, content, []);
      overlay.querySelector('.modal').classList.add('modal-lg');
      const deptsEl = overlay.querySelector('#emr-mgr-depts');
      const catsEl = overlay.querySelector('#emr-mgr-cats');
      const mainEl = overlay.querySelector('#emr-mgr-main');

      const api = {
        overlay, cats,
        get items() { return items; },
        get state() { return { activeTab, activeCat, activeDept }; },
        close: () => overlay.remove(),
        async reload() {
          const rr = await AjaxLoader.api(cfg.listUrl, { silent: true });
          items = rr.data || [];
        },
        rerender() { renderDepts(); renderCats(); renderMain(); }
      };

      /** 全院 Tab 左栏: 科室列表 (计数=该科室条目数; 本科室优先/全院通用置顶/名称排序) */
      function renderDepts() {
        const map = new Map();
        items.forEach((t) => {
          const k = cfg.deptKeyOf(t);
          if (!map.has(k)) map.set(k, { key: k, name: cfg.deptNameOf(t), n: 0 });
          map.get(k).n++;
        });
        const list = Array.from(map.values()).sort((a, b) => {
          if (a.key === '0') return -1;
          if (b.key === '0') return 1;
          if ((a.key === cfg.myDeptId) !== (b.key === cfg.myDeptId)) return a.key === cfg.myDeptId ? -1 : 1;
          return a.name.localeCompare(b.name, 'zh');
        });
        if (!list.some((x) => x.key === activeDept)) activeDept = '0';
        deptsEl.innerHTML = list.map((x) => {
          const tag = x.key === cfg.myDeptId ? '<span class="emr-tpm-dtag">本科室</span>' : '';
          return '<div class="emr-tpm-cat' + (x.key === activeDept ? ' active' : '') + '" data-dept="' + x.key + '">'
            + '<span class="emr-tpm-cat-name" title="' + esc2(x.name) + '">' + esc2(x.name) + '</span>'
            + tag + '<span class="emr-tpm-cat-count">' + x.n + '</span></div>';
        }).join('');
        deptsEl.querySelectorAll('[data-dept]').forEach((el) => {
          el.addEventListener('click', () => { activeDept = el.dataset.dept; renderDepts(); renderCats(); renderMain(); });
        });
      }

      function renderCats() {
        const list = [{ key: 'all', name: '全部' }].concat(cats.map((c) => ({ key: String(c.id), name: c.name })));
        if (tabItems().some((t) => cfg.catKeyOf(t) === 'none')) list.push({ key: 'none', name: '未分类' });
        if (!list.some((x) => x.key === activeCat)) activeCat = 'all';
        catsEl.innerHTML = list.map((x) => {
          return '<div class="emr-tpm-cat' + (x.key === activeCat ? ' active' : '') + '" data-cat="' + x.key + '">'
            + '<span class="emr-tpm-cat-name">' + esc2(x.name) + '</span>'
            + '<span class="emr-tpm-cat-count">' + catTpls(x.key).length + '</span></div>';
        }).join('');
        catsEl.querySelectorAll('.emr-tpm-cat').forEach((el) => {
          el.addEventListener('click', () => { activeCat = el.dataset.cat; renderCats(); renderMain(); });
        });
      }

      function renderMain() {
        const list = catTpls(activeCat);
        const catName = activeCat === 'all' ? '全部' : activeCat === 'none' ? '未分类'
          : (((cats.find((c) => String(c.id) === activeCat)) || {}).name || '');
        let deptName = '';
        if (activeTab === 'all') {
          const dt = items.find((x) => cfg.deptKeyOf(x) === activeDept);
          deptName = dt ? cfg.deptNameOf(dt) : '全院通用';
        }
        const head = '<div class="emr-tpm-mhead"><span class="emr-tpm-mtitle">' + esc2(catName) + '</span>'
          + (deptName ? '<span class="emr-tpm-mdept">· ' + esc2(deptName) + '</span>' : '')
          + '<span class="emr-tpm-mcount">' + list.length + ' ' + esc2(cfg.unitLabel) + '</span></div>';
        let body;
        if (!list.length) {
          body = '<div class="emr-tpl-empty">' + cfg.emptyText(catName, deptName, activeTab) + '</div>';
        } else {
          body = list.map((t) => {
            const own = cfg.isOwn(t);
            return '<div class="emr-tpm-row" data-id="' + t.id + '">'
              + '<span class="emr-tpm-rname" title="' + esc2(cfg.nameOf(t)) + '">' + esc2(cfg.nameOf(t)) + '</span>'
              + (cfg.statusTag ? cfg.statusTag(t) : '')
              + (cfg.tagsHtml ? cfg.tagsHtml(t, own, activeTab) : '')
              + '<span class="emr-tpm-ract">' + cfg.actionsHtml(t, own) + '</span></div>';
          }).join('');
        }
        mainEl.innerHTML = head + body;
        mainEl.querySelectorAll('.emr-tpm-row').forEach((row) => {
          row.querySelectorAll('.mini').forEach((a) => {
            a.addEventListener('click', () => {
              const t = items.find((x) => String(x.id) === String(row.dataset.id));
              if (t) cfg.onRowAction(a.dataset.a, t, api);
            });
          });
        });
      }

      overlay.querySelectorAll('.emr-tpl-tab').forEach((tab) => {
        tab.addEventListener('click', () => {
          overlay.querySelectorAll('.emr-tpl-tab').forEach((x) => x.classList.remove('active'));
          tab.classList.add('active');
          activeTab = tab.dataset.tab;
          deptsEl.style.display = activeTab === 'all' ? '' : 'none';
          renderDepts(); renderCats(); renderMain();
        });
      });
      overlay.querySelector('#emr-mgr-close').addEventListener('click', () => overlay.remove());
      overlay.querySelector('#emr-mgr-new').addEventListener('click', () => {
        const defCat = cats.some((c) => String(c.id) === activeCat) ? activeCat : (cats.length ? String(cats[0].id) : '');
        cfg.onNew(api, defCat);
      });
      renderDepts();
      renderCats();
      renderMain();
    };
  };

  window.EmrUI = { floatMenu, esc, managedList };
})();
