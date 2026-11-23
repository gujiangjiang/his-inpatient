// public/js/core/print/print_snapshot.js - 不可变打印快照前端读取
const PrintSnapshot = {
    data(snapshot, fallback) {
        if (!snapshot || !snapshot.data || typeof snapshot.data !== 'object') return fallback || {};
        return Object.assign({}, snapshot.data, {
            _snapshot: {
                key: snapshot.snapshot_key || '',
                at: snapshot.snapshot_at || '',
                hash: snapshot.content_hash || ''
            }
        });
    },

    exists(snapshot) {
        return !!(snapshot && snapshot.data && snapshot.content_hash);
    }
};
