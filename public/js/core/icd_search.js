// public/js/core/icd_search.js - ICD-10 诊断输入防抖自动补全
const IcdSearch = {
    init(inputId, codeFieldId, nameFieldId) {
        const input = document.getElementById(inputId);
        if (!input) return;

        let timer = null;
        let resultsBox = null;

        input.addEventListener('input', function(e) {
            clearTimeout(timer);
            const keyword = e.target.value.trim();
            if (keyword.length < 1) {
                if (resultsBox) { resultsBox.remove(); resultsBox = null; }
                return;
            }
            timer = setTimeout(() => {
                IcdSearch.search(keyword, (results) => {
                    if (resultsBox) resultsBox.remove();
                    if (!results.length) return;

                    resultsBox = document.createElement('div');
                    resultsBox.style.cssText = 'position:absolute;z-index:10;border:1px solid #dcdfe0;background:#fff;max-height:200px;overflow-y:auto;';
                    let html = '';
                    results.forEach(r => {
                        html += '<div style="padding:6px 10px;cursor:pointer;" data-code="' + Format.escape(r.code) + '" data-name="' + Format.escape(r.name) + '">' + Format.escape(r.code) + ' ' + Format.escape(r.name) + '</div>';
                    });
                    resultsBox.innerHTML = html;
                    resultsBox.querySelectorAll('div').forEach(div => {
                        div.addEventListener('click', () => {
                            const code = div.getAttribute('data-code');
                            const name = div.getAttribute('data-name');
                            input.value = name;
                            if (codeFieldId) document.getElementById(codeFieldId).value = code;
                            if (nameFieldId) document.getElementById(nameFieldId).value = name;
                            resultsBox.remove();
                            resultsBox = null;
                        });
                    });
                    input.parentNode.style.position = 'relative';
                    input.parentNode.appendChild(resultsBox);
                });
            }, 300);
        });

        document.addEventListener('click', (e) => {
            if (resultsBox && !resultsBox.contains(e.target) && e.target !== input) {
                resultsBox.remove();
                resultsBox = null;
            }
        });
    },

    search(keyword, callback) {
        AjaxLoader.api('emr/icd-search?keyword=' + encodeURIComponent(keyword), { silent: true })
            .then(result => {
                callback(result.data || []);
            })
            .catch(() => {
                callback([]);
            });
    }
};
