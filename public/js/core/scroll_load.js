// public/js/core/scroll_load.js - 通用滚动加载组件 (触底自动加载更多, 一次编写多处复用)
// 用法:
//   const state = ScrollLoad.attach(scrollEl, () => loadMorePage());  // loadMorePage 返回 Promise<hasMore:boolean>
//   ScrollLoad.check(state)   // 内容不足一屏时主动补载
//   ScrollLoad.reset(state)   // 重置 (重新筛选后)
const ScrollLoad = {
    _states: [],

    /**
     * 绑定滚动容器
     * @param {HTMLElement} scrollEl 滚动容器
     * @param {Function} loadMore  加载更多回调, 返回 Promise<boolean> (false = 已全部加载)
     * @param {Object} opts        { offset: 触底阈值px, autoCheck: 绑定后自动补载 }
     */
    attach(scrollEl, loadMore, opts) {
        opts = opts || {};
        const offset = opts.offset || 80;
        const state = { el: scrollEl, loadMore: loadMore, lock: false, done: false, offset: offset };
        state.handler = function() {
            if (state.lock || state.done || !state.el) return;
            const el = state.el;
            if (el.scrollTop + el.clientHeight >= el.scrollHeight - state.offset) {
                state.lock = true;
                Promise.resolve(state.loadMore()).then(function(more) {
                    state.lock = false;
                    if (more === false) state.done = true;
                    else state.handler(); // 继续检查 (可能加载后仍不满一屏)
                }).catch(function() { state.lock = false; });
            }
        };
        scrollEl.addEventListener('scroll', state.handler);
        this._states.push(state);
        if (opts.autoCheck !== false) setTimeout(function() { state.handler(); }, 60);
        return state;
    },

    /** 主动检查一次 (数据更新后调用, 内容未满一屏会继续补载) */
    check(state) {
        if (state) setTimeout(function() { state.handler(); }, 60);
    },

    /** 重置状态 (筛选条件变化后调用) */
    reset(state) {
        if (!state) return;
        state.done = false;
        state.lock = false;
    }
};

window.ScrollLoad = ScrollLoad;
