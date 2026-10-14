(() => {
    const $ = (sel, root = document) => root.querySelector(sel);
    const BACEN_SAMPLE = '00020126580014br.gov.bcb.pix0136123e4567-e12b-12d1-a456-4266554400005204000053039865802BR5913Fulano de Tal6008BRASILIA62070503***63041D3D';

    const COLOR = {
        '00': 'format', '01': 'format',
        '26': 'account',
        '52': 'meta', '53': 'meta', '58': 'meta',
        '54': 'amount',
        '59': 'merchant', '60': 'merchant', '61': 'merchant',
        '62': 'extra',
        '63': 'crc',
    };

    const colorOf = (field, parentId) => {
        const id = parentId ?? field.id;
        const n = Number(id);
        const family = COLOR[id] ?? (n >= 26 && n <= 51 ? 'account' : 'meta');
        return `var(--f-${family})`;
    };

    const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const money = (v) => v == null ? 'Em aberto' : v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

    async function call(action, body) {
        const res = await fetch(`api.php?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });
        return res.json();
    }

    /* Tema */
    $('#theme').addEventListener('click', () => {
        const root = document.documentElement;
        const current = root.dataset.theme
            ?? (matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
        const next = current === 'light' ? 'dark' : 'light';
        root.dataset.theme = next;
        try { localStorage.setItem('theme', next); } catch (e) {}
    });

    /* Abas */
    const tabs = [...document.querySelectorAll('[role="tab"]')];
    tabs.forEach((tab) => tab.addEventListener('click', () => {
        tabs.forEach((t) => {
            const active = t === tab;
            t.setAttribute('aria-selected', String(active));
            document.getElementById(t.getAttribute('aria-controls')).hidden = !active;
        });
    }));

    /* Resumo */
    function summary(p) {
        const rows = [
            ['Valor', `<span class="amount">${esc(money(p.amount))}</span>`],
            ['Recebedor', esc(p.merchantName ?? '—')],
            ['Cidade', esc(p.merchantCity ?? '—')],
            ['Chave', `${esc(p.key ?? '—')}${p.keyTypeLabel ? ` <span class="badge">${esc(p.keyTypeLabel)}</span>` : ''}`],
            ['txid', esc(p.txid ?? '—')],
        ];
        if (p.description) rows.push(['Mensagem', esc(p.description)]);
        return rows.map(([k, v]) => `<div><dt>${k}</dt><dd>${v}</dd></div>`).join('');
    }

    /* Anatomia: pinta o código por campo e liga cada pedaço à sua linha na tabela */
    function anatomy(container, parsed) {
        const segs = [];
        const rows = [];

        for (const f of parsed.fields) {
            const c = colorOf(f);
            const key = f.id;
            if (f.children.length) {
                segs.push(`<span class="seg" data-k="${key}" style="--c:${c}"><span class="tl">${f.id}${String(f.length).padStart(2, '0')}</span></span>`);
                rows.push(row(f, c, key, false, `${f.children.length} subcampos`));
                for (const ch of f.children) {
                    const ck = `${f.id}.${ch.id}`;
                    segs.push(seg(ch, c, ck));
                    rows.push(row(ch, c, ck, true));
                }
            } else {
                segs.push(seg(f, c, key));
                rows.push(row(f, c, key, false));
            }
        }

        container.innerHTML = `
            <h2>Anatomia do código</h2>
            <p>Cada campo tem ID, tamanho e valor. Passe o mouse em um pedaço do código para ver o que ele significa. O CRC no final é calculado sobre todo o resto: mude um caractere e ele deixa de bater.</p>
            <div class="code">${segs.join('')}</div>
            <div class="fields">${rows.join('')}</div>`;

        container.querySelectorAll('[data-k]').forEach((el) => {
            const k = el.dataset.k;
            const group = () => container.querySelectorAll(`[data-k="${k}"]`);
            el.addEventListener('mouseenter', () => group().forEach((g) => g.classList.add('on')));
            el.addEventListener('mouseleave', () => group().forEach((g) => g.classList.remove('on')));
        });
    }

    function seg(f, c, key) {
        const len = String(f.length).padStart(2, '0');
        return `<span class="seg" data-k="${key}" style="--c:${c}" title="${esc(f.name)}"><span class="tl">${f.id}${len}</span>${esc(f.value)}</span>`;
    }

    function row(f, c, key, child, valueOverride) {
        const value = valueOverride ?? f.value;
        return `<div class="frow${child ? ' child' : ''}" data-k="${key}" style="--c:${c}">
            <span class="id">${f.id}</span><span class="name">${esc(f.name)}</span>
            <span class="len">${String(f.length).padStart(2, '0')}</span><span class="val">${esc(value)}</span></div>`;
    }

    /* Gerador */
    const form = $('#form');
    const qr = $('#qr');
    const state = { payload: null, svg: null };
    let timer;
    let seq = 0;

    function counters() {
        ['name', 'city'].forEach((id) => {
            $(`[data-count="${id}"]`).textContent = form[id].value.normalize('NFD').replace(/[̀-ͯ]/g, '').length;
        });
    }

    function setErrors(errors) {
        document.querySelectorAll('[data-for]').forEach((el) => {
            el.textContent = errors[el.dataset.for] ?? '';
        });
        const map = { key: ['key'], merchant: ['name', 'city'], amount: ['amount'], txid: ['txid'], description: ['description'] };
        Object.entries(map).forEach(([err, ids]) => ids.forEach((id) => form[id].setAttribute('aria-invalid', String(Boolean(errors[err])))));
    }

    async function generate() {
        const mine = ++seq;
        const body = {
            key: form.key.value,
            name: form.name.value,
            city: form.city.value,
            amount: form.amount.value.trim() || null,
            txid: form.txid.value,
            description: form.description.value,
            singleUse: form.singleUse.checked,
        };

        let data;
        try {
            data = await call('generate', body);
        } catch (e) {
            setErrors({ key: 'Não consegui falar com o servidor. Confira se o php -S está rodando.' });
            return;
        }
        if (mine !== seq) return;

        const badge = $('#key-type');
        badge.hidden = !data.keyType;
        badge.textContent = data.keyType ?? '';

        setErrors(data.errors ?? {});

        if (!data.ok) {
            qr.classList.add('stale');
            if (!state.svg) qr.innerHTML = '<p class="qr-empty">Preencha os campos para gerar o QR Code.</p>';
            ['copy', 'download-svg', 'download-png'].forEach((id) => { $(`#${id}`).disabled = true; });
            return;
        }

        state.payload = data.payload;
        state.svg = data.svg;
        qr.classList.remove('stale');
        qr.innerHTML = data.svg;
        qr.querySelector('svg')?.setAttribute('aria-label', 'QR Code do Pix');
        $('#summary').innerHTML = summary(data.parsed);
        ['copy', 'download-svg', 'download-png'].forEach((id) => { $(`#${id}`).disabled = false; });
        anatomy($('#anatomy-generate'), data.parsed);
    }

    form.addEventListener('input', () => {
        counters();
        clearTimeout(timer);
        timer = setTimeout(generate, 220);
    });
    form.addEventListener('submit', (e) => e.preventDefault());

    function toast(msg) {
        const t = $('#toast');
        t.textContent = msg;
        clearTimeout(toast.timer);
        toast.timer = setTimeout(() => { t.textContent = ''; }, 2400);
    }

    $('#copy').addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(state.payload);
            toast('Código copiado.');
        } catch (e) {
            toast('Seu navegador bloqueou a cópia. Selecione o código na anatomia abaixo.');
        }
    });

    $('#download-svg').addEventListener('click', () => {
        const url = URL.createObjectURL(new Blob([state.svg], { type: 'image/svg+xml' }));
        const a = Object.assign(document.createElement('a'), { href: url, download: 'pix.svg' });
        a.click();
        URL.revokeObjectURL(url);
    });

    $('#download-png').addEventListener('click', () => {
        window.location.href = `api.php?action=png&payload=${encodeURIComponent(state.payload)}`;
    });

    /* Leitor */
    async function read() {
        const out = $('#read-result');
        const value = $('#paste').value;
        const data = await call('parse', { payload: value });

        if (!data.ok) {
            out.innerHTML = `<div class="verdict bad"><div><h2>Não deu para ler este código</h2><ul><li>${esc(data.error)}</li></ul></div></div>`;
            $('#anatomy-read').innerHTML = '';
            return;
        }

        const p = data.parsed;
        const title = p.valid ? 'Código válido' : 'Código com problemas';
        const errors = p.errors.length ? `<ul>${p.errors.map((e) => `<li>${esc(e)}</li>`).join('')}</ul>` : '';
        const kind = p.dynamic ? '<p class="hint">Pix dinâmico: o valor e os dados vêm da URL do banco.</p>' : '';

        out.innerHTML = `<div class="verdict${p.valid ? '' : ' bad'}">
            <div><h2>${title}</h2>${errors}${kind}<dl class="summary">${summary(p)}</dl></div>
            ${data.svg ? `<div class="qr">${data.svg}</div>` : ''}
        </div>`;
        anatomy($('#anatomy-read'), p);
    }

    $('#read').addEventListener('click', read);
    $('#sample').addEventListener('click', () => { $('#paste').value = BACEN_SAMPLE; read(); });

    counters();
    generate();
})();
