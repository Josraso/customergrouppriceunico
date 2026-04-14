(function () {
    function escHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function escAttr(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;');
    }

    function initCgpu() {
        var wrap = document.querySelector('.cgpu-wrap');
        if (!wrap || wrap.dataset.cgpuInit) return;
        wrap.dataset.cgpuInit = '1';

        var curEl = wrap.querySelector('.cgpu-cur');
        var cur   = curEl ? curEl.textContent.trim() : '';

        var heading = document.createElement('h4');
        heading.style.cssText = 'font-size:14px;font-weight:700;margin:0 0 12px;';
        heading.textContent = 'Precio por grupo de cliente';
        wrap.insertBefore(heading, wrap.firstChild);

        wrap.querySelectorAll('.cgpu-group').forEach(function (g) {
            var gidEl   = g.querySelector('.cgpu-gid');
            var gnameEl = g.querySelector('.cgpu-gname');
            var gvalEl  = g.querySelector('.cgpu-gval');
            if (!gidEl || !gnameEl || !gvalEl) return;

            var gid  = gidEl.textContent.trim();
            var name = gnameEl.textContent.trim();
            var val  = gvalEl.textContent.trim();

            g.innerHTML =
                '<div style="margin-bottom:12px;">' +
                '<label style="display:block;font-weight:600;font-size:13px;margin-bottom:5px;">' +
                    escHtml(name) +
                '</label>' +
                '<div style="display:flex;align-items:stretch;width:220px;border:1px solid #ced4da;border-radius:4px;overflow:hidden;">' +
                '<span style="padding:0 10px;background:#e9ecef;color:#495057;border-right:1px solid #ced4da;display:flex;align-items:center;white-space:nowrap;font-size:14px;">' +
                    escHtml(cur) +
                '</span>' +
                '<input type="number" step="0.000001" min="0" placeholder="0.00"' +
                ' name="unico_group_price[' + escAttr(gid) + '][product_price]"' +
                ' value="' + escAttr(val) + '"' +
                ' style="border:none;outline:none;padding:7px 10px;flex:1;min-width:0;font-size:14px;background:#fff;color:#212529;">' +
                '</div>' +
                '</div>';
        });
    }

    // Intento inicial
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCgpu);
    } else {
        initCgpu();
    }

    // PS8/9: el contenido de las pestanas puede cargarse de forma diferida.
    // MutationObserver detecta cuando .cgpu-wrap aparece en el DOM.
    if (typeof MutationObserver !== 'undefined') {
        var observer = new MutationObserver(function () {
            if (document.querySelector('.cgpu-wrap')) {
                initCgpu();
            }
        });
        observer.observe(document.body || document.documentElement, {
            childList: true,
            subtree: true
        });
    }
}());
