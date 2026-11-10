/**
 * plugins/training/assets/js/presentation.js: Live-Vortrag an einem Termin
 * (templates/presentation/present.php und follow.php). Port aus Lex.
 *
 * Host: zeichnet, blättert und wechselt den Modus. Lokale Ereignisse gehen
 * gesammelt alle 250 ms an `/events`, nie zwei POSTs gleichzeitig, damit
 * sie in der Reihenfolge ankommen. `/state` alle 2 s liefert Zuschauer
 * und Übergabe.
 *
 * Teilnehmer: fragt `/state?after=seq` mit ETag ab und spielt neue
 * Ereignisse 500 ms später im Abstand ihres `t` nach, damit Striche und
 * Laser im Tempo des Hosts erscheinen.
 *
 * Koordinaten sind 0..1 relativ zur Folie, so passt jede Bildschirmgröße.
 *
 * Bildschirm teilen: der Host holt den Stream per getDisplayMedia und meldet
 * `/screen`, der Server wählt den Weg. Über LiveKit lädt der Host einmal hoch,
 * Zuschauer holen ihr Token über `/screen/token`. Direkt baut der Host je
 * Zuschauer eine WebRTC-Verbindung, die Signale laufen über `/signal` und die
 * normale Abfrage. livekit-client kommt erst per import(), wenn er gebraucht wird.
 *
 * Bewusst kein ES-Modul: der Desk führt ein Modul nur einmal je Dokument
 * aus, ein klassisches Skript dagegen je Fenster.
 */
(() => {
    'use strict';

    // Die Wurzel neben diesem Skript, sonst die erste noch ungebundene; jede nur einmal binden.
    const own = document.currentScript && document.currentScript.parentElement;
    const free = '[data-tr-live]:not([data-tr-live-ready])';
    const root = (own && own.querySelector(free)) || document.querySelector(free);
    if (!root) return;
    root.setAttribute('data-tr-live-ready', '');

    const $ = (selector) => root.querySelector(selector);
    const $$ = (selector) => Array.from(root.querySelectorAll(selector));

    const base = root.dataset.presentation;
    const host = root.dataset.role === 'present';
    const me = Number(root.dataset.user);

    // Grenzen wie DrawEvent im Paket und PresentationController, sonst lehnt der Server das ganze Paket ab.
    const MAX_EVENTS = 200;
    const MAX_POINTS = 2000;
    const MAX_STROKES = 500;
    const MAX_T = 60000;
    const FLUSH_EVERY = 250;
    const HOST_POLL = 2000;
    const POLL_FAST = 700;
    const POLL_SLOW = 2000;
    const REPLAY_DELAY = 500;
    const SWIPE = 60;
    // Wie Presentations::SCREEN_LIMIT; mehr Verbindungen trägt die Leitung des Hosts nicht.
    const SCREEN_LIMIT = 15;
    const ICE_WAIT = 3000;
    const NO_PICTURE = 15000;
    const RTC = { iceServers: [{ urls: 'stun:stun.cloudflare.com:3478' }] };

    const slide = $('[data-live-slide]');
    const frame = $('[data-live-frame]');
    const pdfCanvas = $('[data-live-pdf]');
    const ink = $('[data-live-ink]');
    const ctx = ink.getContext('2d');
    const video = $('[data-live-video]');

    /* Stand der Folie: Seite, Modus, Striche je Seite, Laserpunkt. */
    const st = { page: 0, mode: 'slides', strokes: {}, laser: null };
    let doc = null;
    let pageCount = 0;
    let lastId = 0;
    let dirty = true;
    let ended = false;
    let ready = false;
    let pauseStart = 0;

    /*
     * Aufräumen: das Desk schließt ein Fenster mit remove() und navigiert mit
     * replaceChildren(). Liefe die Abfrage weiter, zählte ein geschlossenes
     * Fenster weiter Zuschauzeit oder hielte den Host am Leben.
     */
    const abort = new AbortController();
    const signal = abort.signal;
    const timers = [];
    const observers = [];
    let pollTimer = 0;
    let frameId = 0;
    let stopped = false;

    function stop() {
        if (stopped) return;
        stopped = true;
        // Vor dem Abbruch der Abfrage: ein Zuschauer verabschiedet sich noch beim Host.
        if (host) {
            if (share) screenOff();
            stopShare();
        } else unwatch(true);
        ended = true;
        timers.forEach(clearInterval);
        clearTimeout(pollTimer);
        cancelAnimationFrame(frameId);
        observers.forEach((o) => o.disconnect());
        abort.abort();
        if (renderTask) renderTask.cancel();
        // pdf.js 6 räumt nur über den Ladeauftrag auf, das Dokument selbst hat kein destroy() mehr.
        if (loading) loading.destroy();
    }

    /** Statt location.reload(): lädt nur dieses Fenster neu, nicht das ganze Desk. */
    function reloadView() {
        stop();
        const go = window.ignis && window.ignis.ui && window.ignis.ui.go;
        if (go) go(base, { from: root, replace: true });
        else location.reload();
    }

    const strokesOf = (page) => st.strokes[page] || (st.strokes[page] = []);
    const freehand = (tool) => tool === 'pen' || tool === 'marker';
    const clampPage = (page) => (pageCount ? Math.max(0, Math.min(pageCount - 1, page)) : Math.max(0, page));
    const seenId = (id) => { if (id > lastId) lastId = id; };

    /* ---------- Folie rendern (pdf.js) ---------- */

    let renderToken = 0;
    let renderTask = null;
    let loading = null;
    let rendered = '';
    let thumbsWanted = false;
    let thumbsBuilt = false;
    let slideRatio = 16 / 9;

    function note(text) {
        const el = $('[data-live-note]');
        el.textContent = text;
        el.hidden = false;
    }

    async function openPdf() {
        try {
            const pdfjs = await import(root.dataset.pdfjs);
            if (stopped) return;
            if (root.dataset.pdfjsWorker) pdfjs.GlobalWorkerOptions.workerSrc = root.dataset.pdfjsWorker;
            loading = pdfjs.getDocument({ url: root.dataset.slides, withCredentials: true });
            doc = await loading.promise;
            const first = (await doc.getPage(1)).getViewport({ scale: 1 });
            slideRatio = first.width / first.height;
        } catch (error) {
            // Auch ein gescheiterter Ladeauftrag hält einen Worker, der sonst bis zum Schließen des Desks lebt.
            if (loading) loading.destroy();
            loading = null;
            // Ohne Folien bleiben Pause, Laser und Übergabe nutzbar, nur Zeichnen und Blättern nicht.
            if (!stopped) note('Die Folien lassen sich hier nicht anzeigen.');
            return;
        }
        if (stopped) { loading.destroy(); return; }
        fitSlide();
        pageCount = doc.numPages;
        $$('[data-live-page-count]').forEach((el) => { el.textContent = String(pageCount); });
        showPage(st.page);
        if (thumbsWanted) buildThumbs();
    }

    /** Nur bei Seitenwechsel oder neuer Breite, das Rendern ist teuer. */
    async function renderPage() {
        const width = slide.clientWidth;
        const key = st.page + ':' + width;
        if (stopped || !doc || !width || key === rendered) return;
        rendered = key;
        const token = ++renderToken;
        if (renderTask) renderTask.cancel();
        try {
            const page = await doc.getPage(st.page + 1);
            if (stopped || token !== renderToken) return;
            const ratio = window.devicePixelRatio || 1;
            const viewport = page.getViewport({ scale: (width * ratio) / page.getViewport({ scale: 1 }).width });
            pdfCanvas.width = Math.floor(viewport.width);
            pdfCanvas.height = Math.floor(viewport.height);
            renderTask = page.render({ canvas: pdfCanvas, viewport });
            await renderTask.promise;
        } catch (error) {
            // Abgebrochen (neue Seite) oder Dokument beim Schließen zerstört: nichts zu tun.
            if (!stopped && (!error || error.name !== 'RenderingCancelledException')) throw error;
        }
    }

    function sizeInk() {
        const rect = slide.getBoundingClientRect();
        const ratio = window.devicePixelRatio || 1;
        ink.width = Math.round(rect.width * ratio);
        ink.height = Math.round(rect.height * ratio);
        dirty = true;
    }

    /*
     * Das Fenster füllen: die Wurzel bekommt die sichtbare Höhe ihres
     * Scrollbereichs (Desk-Fenster oder Browserfenster), dann passt die Folie
     * in den Rest. Container-Queries gibt es erst ab Chromium 105.
     */
    function layout() {
        if (!root.isConnected) return;
        const full = document.fullscreenElement === root;
        const narrow = !full && root.clientWidth < 760;
        root.classList.toggle('tr-live--narrow', narrow);
        // Schmal stapelt alles; dort scrollt die Seite, und die Folie nimmt die volle Breite (fitSlide()).
        if (narrow || full) { root.style.height = ''; fitSlide(); return; }
        const scroller = root.closest('[data-ignis-window-body]');
        const top = root.getBoundingClientRect().top;
        let room;
        if (scroller) {
            const css = getComputedStyle(scroller);
            const box = scroller.getBoundingClientRect();
            // Abstand zum Inhaltsanfang, auch wenn schon gescrollt wurde.
            room = scroller.clientHeight - parseFloat(css.paddingTop) - parseFloat(css.paddingBottom) - (top - box.top - parseFloat(css.paddingTop) + scroller.scrollTop);
        } else {
            room = window.innerHeight - (top + window.scrollY) - 16;
        }
        root.style.height = Math.max(420, Math.floor(room)) + 'px';
    }

    /**
     * Folie und Pausenbild so groß wie möglich in den freien Platz, Seitenverhältnis der PDF.
     * Schmal ist die Höhe frei, dann nimmt die Folie per CSS die volle Breite mit diesem Verhältnis.
     */
    function fitSlide() {
        frame.style.setProperty('--tr-live-ratio', String(slideRatio));
        const w = frame.clientWidth;
        const h = frame.clientHeight;
        if (!w || !h || root.classList.contains('tr-live--narrow')) return;
        const width = Math.floor(Math.min(w, h * slideRatio));
        frame.style.setProperty('--tr-live-w', width + 'px');
        frame.style.setProperty('--tr-live-h', Math.floor(width / slideRatio) + 'px');
    }

    /** Miniaturen einmal und klein, erst wenn der Reiter Folien offen ist. */
    async function buildThumbs() {
        thumbsWanted = true;
        if (!doc || thumbsBuilt) return;
        thumbsBuilt = true;
        const box = $('[data-live-thumbs]');
        const canvases = [];
        for (let i = 0; i < pageCount; i++) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'tr-live__thumb';
            button.setAttribute('aria-label', 'Folie ' + (i + 1));
            button.setAttribute('aria-current', i === st.page ? 'true' : 'false');
            const number = document.createElement('span');
            number.textContent = String(i + 1);
            const canvas = document.createElement('canvas');
            button.append(number, canvas);
            button.addEventListener('click', () => go(i));
            box.appendChild(button);
            canvases.push(canvas);
        }
        const ratio = window.devicePixelRatio || 1;
        try {
            for (let i = 0; i < canvases.length && !stopped; i++) {
                const page = await doc.getPage(i + 1);
                if (stopped) return;
                const viewport = page.getViewport({ scale: (220 * ratio) / page.getViewport({ scale: 1 }).width });
                canvases[i].width = Math.floor(viewport.width);
                canvases[i].height = Math.floor(viewport.height);
                await page.render({ canvas: canvases[i], viewport }).promise;
            }
        } catch (error) {
            if (!stopped) throw error;
        }
    }

    /* ---------- Zeichnen ---------- */

    function drawStroke(s, W, H) {
        const lw = s.width * 2.2 * (W / 960) * (s.tool === 'marker' ? 4 : 1);
        const p = s.points.map(([x, y]) => [x * W, y * H]);
        if (!p.length) return;
        ctx.save();
        ctx.strokeStyle = s.color;
        ctx.lineWidth = lw;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.globalAlpha = s.tool === 'marker' ? 0.35 : 1;
        ctx.beginPath();
        if (freehand(s.tool)) {
            ctx.moveTo(p[0][0], p[0][1]);
            for (let i = 1; i < p.length; i++) ctx.lineTo(p[i][0], p[i][1]);
            if (p.length === 1) ctx.lineTo(p[0][0] + 0.1, p[0][1]);
        } else {
            const a = p[0];
            const b = p[p.length - 1];
            if (s.tool === 'line' || s.tool === 'arrow') { ctx.moveTo(a[0], a[1]); ctx.lineTo(b[0], b[1]); }
            if (s.tool === 'rect') ctx.rect(a[0], a[1], b[0] - a[0], b[1] - a[1]);
            if (s.tool === 'ellipse') ctx.ellipse((a[0] + b[0]) / 2, (a[1] + b[1]) / 2, Math.abs(b[0] - a[0]) / 2, Math.abs(b[1] - a[1]) / 2, 0, 0, Math.PI * 2);
            if (s.tool === 'arrow') {
                const angle = Math.atan2(b[1] - a[1], b[0] - a[0]);
                const head = Math.max(12, lw * 4);
                ctx.moveTo(b[0], b[1]);
                ctx.lineTo(b[0] - head * Math.cos(angle - 0.45), b[1] - head * Math.sin(angle - 0.45));
                ctx.moveTo(b[0], b[1]);
                ctx.lineTo(b[0] - head * Math.cos(angle + 0.45), b[1] - head * Math.sin(angle + 0.45));
            }
        }
        ctx.stroke();
        ctx.restore();
    }

    function draw() {
        const W = ink.width;
        const H = ink.height;
        ctx.clearRect(0, 0, W, H);
        if (st.mode !== 'slides') return;
        strokesOf(st.page).forEach((s) => drawStroke(s, W, H));
        const l = st.laser;
        if (l) {
            // Laserpunkt in --danger, damit er dem Thema folgt.
            const r = 6 * (W / 960) + 2;
            ctx.save();
            ctx.fillStyle = getComputedStyle(root).getPropertyValue('--danger').trim() || 'red';
            ctx.globalAlpha = 0.18;
            ctx.beginPath();
            ctx.arc(l[0] * W, l[1] * H, r * 2.2, 0, Math.PI * 2);
            ctx.fill();
            ctx.globalAlpha = 0.85;
            ctx.beginPath();
            ctx.arc(l[0] * W, l[1] * H, r, 0, Math.PI * 2);
            ctx.fill();
            ctx.restore();
        }
    }

    /** Ein Ereignis wirkt auf beiden Seiten gleich: beim Host sofort, beim Teilnehmer verzögert. */
    function apply(e) {
        switch (e.kind) {
            case 'begin':
                seenId(e.id);
                strokesOf(e.page).push({ id: e.id, tool: e.tool, color: e.color, width: e.width, points: [e.p] });
                break;
            case 'point': {
                const s = strokesOf(e.page).find((x) => x.id === e.id);
                if (s) { if (freehand(s.tool)) s.points.push(e.p); else s.points[1] = e.p; }
                break;
            }
            case 'erase':
                st.strokes[e.page] = strokesOf(e.page).filter((s) => s.id !== e.id);
                break;
            case 'clear':
                st.strokes[e.page] = [];
                break;
            case 'restore':
                st.strokes[e.page] = e.strokes.map((s) => {
                    seenId(s.id);
                    return { id: s.id, tool: s.tool, color: s.color, width: s.width, points: s.points.map((p) => [p[0], p[1]]) };
                });
                break;
            case 'laser':
                st.laser = e.p || null;
                break;
            case 'page':
                showPage(e.page);
                break;
            case 'mode':
                showMode(e.mode);
                break;
        }
        dirty = true;
    }

    /* ---------- Seite und Modus anzeigen ---------- */

    function showPage(page) {
        st.page = clampPage(page);
        st.laser = null;
        const counter = $('[data-live-counter]');
        if (counter) counter.textContent = (st.page + 1) + ' / ' + (pageCount || 1);
        followCounter();
        $$('.tr-live__thumb').forEach((t, i) => t.setAttribute('aria-current', i === st.page ? 'true' : 'false'));
        dirty = true;
        renderPage();
    }

    function followCounter() {
        const follow = $('[data-live-follow-counter]');
        if (follow) follow.textContent = st.mode === 'screen' ? 'Bildschirm' : 'Folie ' + (st.page + 1) + (pageCount ? ' von ' + pageCount : '');
    }

    function showMode(mode) {
        st.mode = mode === 'pause' || mode === 'screen' ? mode : 'slides';
        st.laser = null;
        frame.dataset.mode = st.mode;
        pauseStart = st.mode === 'pause' ? (pauseStart || Date.now()) : 0;
        if (host) {
            $$('.ignis-segmented [data-live-mode]').forEach((b) => b.setAttribute('aria-pressed', b.dataset.liveMode === st.mode ? 'true' : 'false'));
            $('[data-live-dock]').hidden = st.mode !== 'slides';
            $('[data-live-share]').hidden = st.mode !== 'screen';
            screenButton();
        }
        followCounter();
        dirty = true;
    }

    function showEnded() {
        if (host) stopShare();
        else unwatch(false);
        ended = true;
        st.laser = null;
        replay.length = 0;
        $('[data-live-toast]').hidden = true;
        $('[data-live-ended]').hidden = false;
    }

    /* ---------- Server: POSTs nacheinander ---------- */

    let chain = Promise.resolve();

    // Das Token rotiert in ignis nicht, es steht einmal im Kopf der Seite.
    async function send(path, body) {
        const meta = document.querySelector('meta[name="csrf-token"]');
        const response = await fetch(base + path, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': meta ? meta.content : '' },
            body: JSON.stringify(body),
        });
        const data = await response.json().catch(() => ({}));
        return { status: response.status, data: data || {} };
    }

    /** Hängt sich hinten an, damit Ereignisse und Übergabe in ihrer Reihenfolge ankommen. */
    function post(path, body) {
        const run = chain.then(() => send(path, body));
        chain = run.catch(() => {});
        return run;
    }

    /* ---------- Host: Warteschlange ---------- */

    const queue = [];
    let inFlight = false;
    let pendingPage = null;
    let pendingMode = null;

    function emit(e) {
        apply(e);
        queue.push({ e, at: performance.now() });
    }

    async function flush() {
        if (!root.isConnected) return stop();
        if (inFlight || ended || !ready) return;
        if (!queue.length && pendingPage === null && pendingMode === null) return;
        const batch = queue.splice(0, MAX_EVENTS);
        const t0 = batch.length ? batch[0].at : 0;
        const body = {
            page_count: Math.max(1, pageCount),
            events: batch.map(({ e, at }) => Object.assign({}, e, { t: Math.min(MAX_T, Math.round(at - t0)) })),
        };
        const page = pendingPage;
        const mode = pendingMode;
        if (page !== null) body.page = page;
        if (mode !== null) body.mode = mode;
        pendingPage = null;
        pendingMode = null;
        inFlight = true;
        try {
            const { status, data } = await post('/events', body);
            if (stopped) return;
            // Beim Teilen nicht vorspringen: dazwischen kann ein Signal eines Zuschauers liegen.
            if (status === 200 && typeof data.seq === 'number') { if (!share) lastSeq = Math.max(lastSeq, data.seq); }
            else if (status === 409) showEnded();
            else if (status === 403 || status === 404) reloadView();
            else if (status === 422 && batch.length) {
                // Die Ereignisse waren schuld, sie bleiben weg; Seite und Modus gehen mit dem nächsten Paket.
                if (pendingPage === null) pendingPage = page;
                if (pendingMode === null) pendingMode = mode;
            }
        } catch (error) {
            // Netz weg: Paket vorn wieder einreihen, Laser ist bis dahin überholt.
            queue.unshift(...batch.filter((x) => x.e.kind !== 'laser'));
            if (pendingPage === null) pendingPage = page;
            if (pendingMode === null) pendingMode = mode;
        } finally {
            inFlight = false;
        }
    }

    /* ---------- Abfrage ---------- */

    let lastSeq = 0;
    let signalSeq = 0;
    let etag = null;
    let idle = 0;
    let first = true;
    const replay = [];
    let lastDue = 0;
    const target = { page: 0, mode: 'slides' };
    let talkId = null;
    let again = false;

    /** Neuer Lauf desselben Termins: alte Zeichnungen verwerfen und von vorn abfragen. */
    function resetTalk() {
        talkId = null;
        first = true;
        lastSeq = 0;
        etag = null;
        replay.length = 0;
        lastDue = 0;
        signalSeq = 0;
        unwatch(false);
        st.strokes = {};
        st.laser = null;
        dirty = true;
        again = true;
    }

    function schedule(due, e) {
        lastDue = Math.max(due, lastDue);
        replay.push({ due: lastDue, e });
    }

    function connected(ok) {
        const el = $('[data-live-conn]');
        if (!el) return;
        el.classList.toggle('is-lost', !ok);
        el.lastElementChild.textContent = ok ? 'Verbunden' : 'Verbindung unterbrochen';
    }

    async function poll() {
        if (!root.isConnected) return stop();
        if (ended) return;
        let delay = host ? (connecting() ? POLL_FAST : HOST_POLL) : POLL_FAST;
        try {
            const headers = { Accept: 'application/json' };
            if (etag && !host) headers['If-None-Match'] = etag;
            const response = await fetch(base + '/state?after=' + lastSeq, { credentials: 'same-origin', cache: 'no-store', headers, signal });
            if (stopped) return;
            if (response.status === 304) {
                idle++;
            } else if (response.status === 404) {
                // Termin gelöscht, Teilnahme beendet oder Lehrgang abgesagt: weiterfragen bringt nichts mehr.
                showEnded();
                return;
            } else if (!response.ok) {
                throw new Error('state ' + response.status);
            } else {
                etag = response.headers.get('ETag');
                idle = handleState(await response.json()) ? 0 : idle + 1;
            }
            connected(true);
            if (again) { again = false; delay = 0; }
            else if (!host && idle >= 3) delay = POLL_SLOW;
        } catch (error) {
            connected(false);
            delay = POLL_SLOW;
        }
        if (!ended) pollTimer = setTimeout(poll, delay);
    }

    /** @return {boolean} ob Neues kam */
    function handleState(s) {
        if (s.status === 'ended') { showEnded(); return false; }
        // Der Host hat gewechselt: der Server rendert dann die andere Sicht.
        if ((s.presenter.id === me) !== host) { reloadView(); return false; }
        if (!host && talkId !== null && s.id !== talkId) { resetTalk(); return true; }
        talkId = s.id;
        showHandover(s);

        if (first) {
            // Erster Abruf: alles sofort, ohne Laser (liefert der Server da nicht).
            first = false;
            s.events.forEach(apply);
            showPage(s.page);
            showMode(s.mode);
            target.page = s.page;
            target.mode = s.mode;
            lastSeq = s.seq;
            ready = true;
            if (host) {
                // Neu geladen, während geteilt wurde: der Stream ist weg, die Zuschauer warteten sonst ewig.
                if (st.mode === 'screen') { showMode('slides'); pendingMode = 'slides'; }
                showViewers(s.viewers || []);
            } else {
                setHost(s.presenter);
                follow(s);
            }
            return true;
        }
        lastSeq = Math.max(lastSeq, s.seq);
        // Derselbe Stand kann ein Signal zweimal liefern; ein zweites Angebot risse eine stehende Verbindung ab.
        const signals = s.events.filter((e) => e.kind === 'signal' && e.seq > signalSeq);
        signals.forEach((e) => { signalSeq = Math.max(signalSeq, e.seq); });
        if (host) {
            showViewers(s.viewers || []);
            signals.forEach(hostSignal);
            return true;
        }

        setHost(s.presenter);
        follow(s);
        signals.forEach(viewerSignal);
        const due = performance.now() + REPLAY_DELAY;
        let fresh = signals.length > 0;
        if (s.page !== target.page) { target.page = s.page; schedule(due, { kind: 'page', page: s.page }); fresh = true; }
        if (s.mode !== target.mode) { target.mode = s.mode; schedule(due, { kind: 'mode', mode: s.mode }); fresh = true; }
        // t zählt je Paket des Hosts ab 0; fällt es, beginnt das nächste Paket direkt dahinter.
        let offset = 0;
        let prevT = 0;
        s.events.forEach((e) => {
            if (e.kind === 'signal') return;
            if (e.t < prevT) offset += prevT;
            prevT = e.t;
            schedule(due + offset + e.t, e);
            fresh = true;
        });
        return fresh;
    }

    function setHost(presenter) {
        const name = $('[data-live-host-name]');
        if (name) name.textContent = presenter.name;
        const initials = $('[data-live-host-initials]');
        if (initials) initials.textContent = presenter.initials;
    }

    /* ---------- Übergabe ---------- */

    let answering = false;
    let waitingFor = null;

    function showHandover(s) {
        const h = s.handover;
        if (!answering) {
            const forMe = !!h && h.to_id === me;
            if (forMe) $('[data-live-toast-from]').textContent = s.presenter.name;
            $('[data-live-toast]').hidden = !forMe;
        }
        if (host) {
            waitingFor = h ? h.to_name : null;
            handButton();
        }
    }

    function handButton() {
        const button = $('[data-live-handover]');
        button.firstElementChild.className = waitingFor ? 'fa-solid fa-hourglass-half' : 'fa-solid fa-right-left';
        button.lastElementChild.textContent = waitingFor ? 'Wartet auf ' + waitingFor : 'Weiterreichen';
        $('[data-live-cancel]').hidden = !waitingFor;
        $('[data-live-cancel-label]').textContent = waitingFor ? 'Anfrage an ' + waitingFor + ' zurücknehmen' : '';
    }

    function togglePop(open) {
        const pop = $('[data-live-pop]');
        pop.hidden = !open;
        $('[data-live-handover]').setAttribute('aria-expanded', String(open));
    }

    async function answer(accept) {
        answering = true;
        $('[data-live-toast]').hidden = true;
        try {
            const { status } = await post(accept ? '/handover/accept' : '/handover/decline', {});
            if (accept && status === 200) { reloadView(); return; }
        } catch (error) {
            // Ohne Netz bleibt die Anfrage offen; die nächste Abfrage zeigt sie wieder.
        }
        answering = false;
    }

    function bindHandover() {
        $('[data-live-accept]').addEventListener('click', () => answer(true));
        $('[data-live-decline]').addEventListener('click', () => answer(false));
        if (!host) return;
        const button = $('[data-live-handover]');
        const pop = $('[data-live-pop]');
        // Auch während eine Anfrage wartet: zurücknehmen oder an jemand anderen geben.
        button.addEventListener('click', () => togglePop(pop.hidden));
        $('[data-live-cancel]').addEventListener('click', async () => {
            togglePop(false);
            try {
                const { status } = await post('/handover/cancel', {});
                if (status === 200) { waitingFor = null; handButton(); }
            } catch (error) {
                // Ohne Netz bleibt die Anfrage offen; erneut zurücknehmen geht.
            }
        });
        $$('[data-handover-to]').forEach((b) => b.addEventListener('click', async () => {
            togglePop(false);
            try {
                const { status } = await post('/handover', { user_id: Number(b.dataset.handoverTo) });
                if (status === 200) { waitingFor = b.dataset.name; handButton(); }
            } catch (error) {
                // Ohne Netz bleibt der Vortrag beim Host; erneut weiterreichen geht.
            }
        }));
        document.addEventListener('click', (ev) => {
            if (!pop.hidden && !pop.contains(ev.target) && !button.contains(ev.target)) togglePop(false);
        }, { signal });
        root.addEventListener('keydown', (ev) => { if (ev.key === 'Escape' && !pop.hidden) togglePop(false); });
    }

    /* ---------- Host: Zuschauer ---------- */

    let viewersShown = '';

    function personItem(v, detail) {
        const li = document.createElement('li');
        const avatar = document.createElement('span');
        avatar.className = 'ignis-avatar ignis-avatar--sm';
        avatar.setAttribute('aria-hidden', 'true');
        avatar.textContent = v.initials;
        const name = document.createElement('span');
        name.className = 'tr-live__name';
        const small = document.createElement('small');
        small.textContent = detail;
        name.append(v.name, small);
        li.append(avatar, name);
        return li;
    }

    function showViewers(list) {
        audience = list.filter((v) => v.id !== me).length;
        screenButton();
        const key = JSON.stringify(list);
        if (key === viewersShown) return;
        viewersShown = key;
        const inCourse = list.filter((v) => v.in_course);
        const others = list.filter((v) => !v.in_course);
        const time = (iso) => new Date(iso).toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
        $$('[data-live-viewer-count]').forEach((el) => { el.textContent = String(list.length); });
        $('[data-live-course-count]').textContent = String(inCourse.length);
        $('[data-live-others-count]').textContent = String(others.length);
        $('[data-live-course]').replaceChildren(...inCourse.map((v) => personItem(v, 'dabei seit ' + time(v.since))));
        $('[data-live-others]').replaceChildren(...others.map((v) => personItem(v, 'zählt nicht')));
        $('[data-live-others-block]').hidden = !others.length;
        $('[data-live-nobody]').hidden = list.length > 0;
    }

    /* ---------- Host: Werkzeuge und Eingabe ---------- */

    let tool = 'laser';
    let color = '';
    let width = 2;
    let drawing = null;
    let swipe = null;
    let lastLaser = 0;
    let lastPoint = 0;
    const undo = {};
    const undoOf = (page) => undo[page] || (undo[page] = []);
    const copy = (value) => JSON.parse(JSON.stringify(value));

    function pick(t) {
        tool = t;
        $$('[data-tool]').forEach((b) => b.setAttribute('aria-pressed', b.dataset.tool === t ? 'true' : 'false'));
        ink.style.cursor = t === 'laser' ? 'none' : t === 'eraser' ? 'cell' : 'crosshair';
    }

    function pos(ev) {
        const r = ink.getBoundingClientRect();
        const x = Math.min(1, Math.max(0, (ev.clientX - r.left) / r.width));
        const y = Math.min(1, Math.max(0, (ev.clientY - r.top) / r.height));
        return [Math.round(x * 10000) / 10000, Math.round(y * 10000) / 10000];
    }

    /** Oberster Strich in Fingerbreite um p, für den Radierer. */
    function hit(p) {
        const W = ink.width;
        const H = ink.height;
        const list = strokesOf(st.page);
        const reach = (14 * (window.devicePixelRatio || 1)) ** 2;
        for (let i = list.length - 1; i >= 0; i--) {
            const s = list[i];
            const a0 = s.points[0];
            const b0 = s.points[s.points.length - 1];
            let pts;
            if (freehand(s.tool)) pts = s.points;
            else if (s.tool === 'rect') pts = [a0, [b0[0], a0[1]], b0, [a0[0], b0[1]], a0];
            else if (s.tool === 'ellipse') {
                pts = Array.from({ length: 25 }, (_, k) => [
                    (a0[0] + b0[0]) / 2 + Math.cos((k / 24) * Math.PI * 2) * Math.abs(b0[0] - a0[0]) / 2,
                    (a0[1] + b0[1]) / 2 + Math.sin((k / 24) * Math.PI * 2) * Math.abs(b0[1] - a0[1]) / 2,
                ]);
            } else pts = [a0, b0];
            for (let k = 0; k < pts.length; k++) {
                const a = pts[k];
                const b = pts[k + 1] || a;
                const dx = (b[0] - a[0]) * W;
                const dy = (b[1] - a[1]) * H;
                const len = dx * dx + dy * dy;
                const t = len ? Math.max(0, Math.min(1, ((p[0] - a[0]) * W * dx + (p[1] - a[1]) * H * dy) / len)) : 0;
                const ex = a[0] * W + t * dx - p[0] * W;
                const ey = a[1] * H + t * dy - p[1] * H;
                if (ex * ex + ey * ey < reach) return s;
            }
        }
        return null;
    }

    function go(page) {
        if (!host || !ready || !pageCount || page < 0 || page >= pageCount || page === st.page) return;
        st.laser = null;
        showPage(page);
        pendingPage = page;
    }

    function setMode(mode) {
        if (!ready || starting) return;
        if (mode === 'screen') { startShare(); return; }
        if (mode === st.mode) return;
        // Pause oder Folien beenden das Teilen; der Server räumt den Weg mit dem Modus ab.
        stopShare();
        showMode(mode);
        pendingMode = st.mode;
    }

    function doUndo() {
        const last = undoOf(st.page).pop();
        if (!last) return;
        if (last.type === 'add') emit({ kind: 'erase', page: st.page, id: last.id });
        if (last.type === 'clear') emit({ kind: 'restore', page: st.page, strokes: last.strokes });
        if (last.type === 'erase') emit({ kind: 'restore', page: st.page, strokes: copy(strokesOf(st.page)).concat([last.stroke]) });
    }

    function clearPage() {
        if (!strokesOf(st.page).length) return;
        undoOf(st.page).push({ type: 'clear', strokes: copy(strokesOf(st.page)) });
        emit({ kind: 'clear', page: st.page });
    }

    function pointerDown(ev) {
        if (!ready || ended || !doc || st.mode !== 'slides') return;
        ink.setPointerCapture(ev.pointerId);
        const p = pos(ev);
        if (tool === 'laser') { swipe = { x: ev.clientX, y: ev.clientY }; return; }
        if (tool === 'eraser') {
            const s = hit(p);
            if (s) {
                undoOf(st.page).push({ type: 'erase', stroke: copy(s) });
                emit({ kind: 'erase', page: st.page, id: s.id });
            }
            return;
        }
        if (strokesOf(st.page).length >= MAX_STROKES) return;
        drawing = { id: ++lastId, page: st.page };
        emit({ kind: 'begin', page: st.page, id: drawing.id, tool, color, width, p });
        undoOf(st.page).push({ type: 'add', id: drawing.id });
    }

    function addPoint(p) {
        const s = strokesOf(drawing.page).find((x) => x.id === drawing.id);
        if (!s || (freehand(s.tool) && s.points.length >= MAX_POINTS)) return;
        emit({ kind: 'point', page: drawing.page, id: drawing.id, p });
    }

    function pointerMove(ev) {
        if (!ready || ended) return;
        const now = performance.now();
        if (tool === 'laser') {
            if (st.mode === 'slides' && now - lastLaser > 25) { lastLaser = now; emit({ kind: 'laser', p: pos(ev) }); }
            return;
        }
        if (drawing && now - lastPoint > 12) { lastPoint = now; addPoint(pos(ev)); }
    }

    function pointerUp(ev) {
        if (drawing) { addPoint(pos(ev)); drawing = null; }
        if (swipe) {
            // Wischen blättert nur mit dem Laser, sonst würde jeder Strich blättern.
            const dx = ev.clientX - swipe.x;
            const dy = ev.clientY - swipe.y;
            swipe = null;
            if (Math.abs(dx) >= SWIPE && Math.abs(dx) > Math.abs(dy)) go(st.page + (dx < 0 ? 1 : -1));
        }
    }

    function bindHost() {
        color = $('[data-color]').dataset.color;
        $$('[data-tool]').forEach((b) => b.addEventListener('click', () => pick(b.dataset.tool)));
        $$('[data-color]').forEach((b) => b.addEventListener('click', () => {
            color = b.dataset.color;
            $$('[data-color]').forEach((x) => x.setAttribute('aria-pressed', x === b ? 'true' : 'false'));
            if (tool === 'laser' || tool === 'eraser') pick('pen');
        }));
        $$('[data-width]').forEach((b) => b.addEventListener('click', () => {
            width = Number(b.dataset.width);
            $$('[data-width]').forEach((x) => x.setAttribute('aria-pressed', x === b ? 'true' : 'false'));
        }));
        $('[data-live-prev]').addEventListener('click', () => go(st.page - 1));
        $('[data-live-next]').addEventListener('click', () => go(st.page + 1));
        $('[data-live-undo]').addEventListener('click', doUndo);
        $('[data-live-clear]').addEventListener('click', clearPage);
        $$('[data-live-mode]').forEach((b) => b.addEventListener('click', () => setMode(b.dataset.liveMode)));

        ink.addEventListener('pointerdown', pointerDown);
        ink.addEventListener('pointermove', pointerMove);
        ink.addEventListener('pointerup', pointerUp);
        ink.addEventListener('pointercancel', pointerUp);
        ink.addEventListener('pointerleave', () => { if (tool === 'laser' && st.laser) emit({ kind: 'laser', p: null }); });

        root.addEventListener('keydown', (ev) => {
            const onControl = ev.target instanceof Element && ev.target.closest('button, a, input, textarea');
            if (ev.key === 'ArrowRight' || ev.key === 'PageDown' || (ev.key === ' ' && !onControl)) { ev.preventDefault(); go(st.page + 1); }
            if (ev.key === 'ArrowLeft' || ev.key === 'PageUp') { ev.preventDefault(); go(st.page - 1); }
            if ((ev.ctrlKey || ev.metaKey) && ev.key.toLowerCase() === 'z') { ev.preventDefault(); doUndo(); }
        });
        root.addEventListener('pointerdown', (ev) => {
            if (!(ev.target instanceof Element && ev.target.closest('button, a, input'))) root.focus({ preventScroll: true });
        });

        // Seitenleiste und Reiter
        const main = $('[data-live-main]');
        const side = $('[data-live-side]');
        side.addEventListener('click', () => {
            const bare = main.classList.toggle('tr-live__main--bare');
            side.setAttribute('aria-pressed', String(!bare));
        });
        $$('[data-live-tab]').forEach((b) => b.addEventListener('click', () => {
            $$('[data-live-tab]').forEach((x) => x.setAttribute('aria-selected', x === b ? 'true' : 'false'));
            $$('[data-live-panel]').forEach((panel) => { panel.hidden = panel.dataset.livePanel !== b.dataset.liveTab; });
            if (b.dataset.liveTab === 'slides') buildThumbs();
        }));

        // Nach der Bestätigung nichts mehr senden; was noch in der Schlange liegt, verwirft das Ende ohnehin.
        $('[data-live-end]').addEventListener('submit', (ev) => {
            if (!ev.defaultPrevented) ended = true;
        });

        timers.push(setInterval(flush, FLUSH_EVERY));
        pick('laser');
        screenButton();
    }

    /* ---------- Bildschirm teilen ---------- */

    const via = root.dataset.screenVia;
    // Das FiveM-Tablet kann nicht teilen, auch wenn es getDisplayMedia kennt; zuschauen geht dort.
    const canShare = !!(navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia) && !/CitizenFX/.test(navigator.userAgent);
    const MISSING = 'Der Bildschirm kommt bei dir nicht an. Lade die Seite neu oder schau später wieder rein.';
    let livekit = null;
    let share = null;
    let starting = false;
    let audience = 0;
    let watch = null;

    function lk() {
        if (!livekit) livekit = import(root.dataset.livekit).catch((error) => { livekit = null; throw error; });
        return livekit;
    }

    function hint(text) {
        const snack = window.ignis && window.ignis.snack;
        if (snack) snack.warning(text);
        else note(text);
    }

    function signalTo(body) {
        return post('/signal', body).catch(() => ({ status: 0, data: {} }));
    }

    /** Kein Trickle-ICE: Angebot und Antwort gehen mit allen Kandidaten, gesammelt höchstens ICE_WAIT lang. */
    function gathered(pc) {
        return new Promise((resolve) => {
            if (pc.iceGatheringState === 'complete') { resolve(); return; }
            const done = () => {
                clearTimeout(timer);
                pc.removeEventListener('icegatheringstatechange', check);
                signal.removeEventListener('abort', done);
                resolve();
            };
            const check = () => { if (pc.iceGatheringState === 'complete') done(); };
            const timer = setTimeout(done, ICE_WAIT);
            pc.addEventListener('icegatheringstatechange', check);
            signal.addEventListener('abort', done);
        });
    }

    /* Host */

    function screenButton() {
        const button = $('.ignis-segmented [data-live-mode="screen"]');
        let reason = '';
        if (!canShare) reason = 'Bildschirm teilen geht nur im Browser';
        else if (!share && via === 'direct' && audience > SCREEN_LIMIT) reason = 'Bildschirm teilen geht ohne EF Sync bis 15 Zuschauer';
        button.setAttribute('aria-disabled', reason ? 'true' : 'false');
        button.dataset.ignisTooltip = reason || ((share ? share.via : via) === 'livekit' ? 'Über den Medienserver von EmergencyForge' : 'Direkt an jeden Zuschauer, bis 15');
    }

    const connecting = () => !!share && Array.from(share.peers.values()).some((pc) => pc.connectionState !== 'connected');

    function leaveScreen(mode) {
        stopShare();
        showMode(mode);
        pendingMode = st.mode;
    }

    async function startShare() {
        if (share || starting || ended || !canShare || (via === 'direct' && audience > SCREEN_LIMIT)) return;
        starting = true;
        let stream = null;
        try {
            // Vor jeder Anfrage, solange der Klick noch als Nutzergeste gilt.
            stream = await navigator.mediaDevices.getDisplayMedia({ video: true, audio: false });
        } catch (error) {
            // Im Auswahldialog abgebrochen: nichts senden.
            starting = false;
            return;
        }
        const end = () => stream.getTracks().forEach((t) => t.stop());
        let mine = null;
        try {
            if (stopped) { end(); return; }
            pendingMode = null;
            const { status, data } = await post('/screen', {});
            if (stopped || status !== 200) {
                end();
                // Der Server teilt schon, das Fenster ist aber weg.
                if (stopped) { if (status === 200) screenOff(); return; }
                if (status === 409 && data.error === 'too_many') hint('Bildschirm teilen geht gerade nicht: zu viele Zuschauer für eine direkte Übertragung.');
                else if (status === 409 && data.error === 'ended') showEnded();
                else if (status === 403 || status === 404 || status === 409) reloadView();
                else hint('Bildschirm teilen hat gerade nicht geklappt.');
                return;
            }
            const track = stream.getVideoTracks()[0];
            const s = { via: data.via, stream, track, peers: new Map(), waits: new Map(), room: null };
            share = s;
            mine = s;
            // Auch der Browser-Knopf „Freigabe beenden“ endet hier.
            track.addEventListener('ended', () => { if (share === s) leaveScreen('slides'); });
            video.srcObject = stream;
            showMode('screen');
            // Ab hier läuft das Teilen; beenden muss auch gehen, während LiveKit noch verbindet.
            starting = false;
            if (s.via === 'livekit') await publish(s, data);
        } catch (error) {
            end();
            // Schon beendet, etwa mit „Teilen beenden“, während LiveKit verband.
            if (stopped || (mine && share !== mine)) return;
            if (mine) leaveScreen('slides');
            // Ohne Antwort weiß niemand, ob der Server schon teilt; der nächste Paket-POST stellt den Modus klar.
            else pendingMode = st.mode;
            hint('Bildschirm teilen hat gerade nicht geklappt.');
        } finally {
            starting = false;
        }
    }

    async function publish(s, data) {
        const { Room, RoomEvent, Track } = await lk();
        if (share !== s) return;
        const room = new Room();
        s.room = room;
        // Gibt LiveKit endgültig auf, sähe sonst niemand mehr etwas.
        room.on(RoomEvent.Disconnected, () => { if (share === s) leaveScreen('slides'); });
        await room.connect(data.url, data.token);
        if (share !== s) return;
        await room.localParticipant.publishTrack(s.track, { source: Track.Source.ScreenShare });
    }

    /** Ohne Warten und ohne Abbruchsignal: die Zuschauer sollen auf die Folie zurück statt auf ein schwarzes Bild. */
    function screenOff() {
        post('/events', { page_count: Math.max(1, pageCount), events: [], mode: 'slides' }).catch(() => {});
    }

    function stopShare() {
        const s = share;
        if (!s) return;
        share = null;
        s.stream.getTracks().forEach((t) => t.stop());
        s.waits.forEach(clearTimeout);
        s.peers.forEach((pc) => pc.close());
        if (s.room) s.room.disconnect();
        video.srcObject = null;
    }

    function drop(s, id) {
        clearTimeout(s.waits.get(id));
        s.waits.delete(id);
        const pc = s.peers.get(id);
        if (!pc) return;
        s.peers.delete(id);
        pc.close();
    }

    async function hostSignal(e) {
        const s = share;
        if (!s || s.via !== 'direct') return;
        const from = e.from;
        if (e.type === 'bye') { drop(s, from); return; }
        if (e.type === 'answer') {
            const pc = s.peers.get(from);
            if (pc && pc.signalingState === 'have-local-offer') pc.setRemoteDescription({ type: 'answer', sdp: e.sdp }).catch(() => drop(s, from));
            return;
        }
        if (e.type !== 'ready') return;
        // Ein zweites ready kommt nach dem Neuladen des Zuschauers; die alte Verbindung ist tot.
        drop(s, from);
        if (s.peers.size >= SCREEN_LIMIT) { signalTo({ to: from, type: 'full' }); return; }
        const pc = new RTCPeerConnection(RTC);
        s.peers.set(from, pc);
        // Ohne Antwort bliebe die Verbindung ewig in `new`, belegte einen der 15 Plätze und hielte die Abfrage schnell.
        s.waits.set(from, setTimeout(() => { if (s.peers.get(from) === pc && pc.connectionState !== 'connected') drop(s, from); }, NO_PICTURE));
        pc.addEventListener('connectionstatechange', () => {
            if (s.peers.get(from) !== pc) return;
            if (pc.connectionState === 'connected') { clearTimeout(s.waits.get(from)); s.waits.delete(from); }
            if (pc.connectionState === 'failed' || pc.connectionState === 'closed') drop(s, from);
        });
        try {
            pc.addTrack(s.track, s.stream);
            await pc.setLocalDescription(await pc.createOffer());
            await gathered(pc);
            if (share !== s || s.peers.get(from) !== pc) return;
            const { status } = await signalTo({ to: from, type: 'offer', sdp: pc.localDescription.sdp });
            if (status !== 200) drop(s, from);
        } catch (error) {
            drop(s, from);
        }
    }

    /* Zuschauer */

    function screenNote(text) {
        const el = $('[data-live-screen-note]');
        el.textContent = text;
        el.hidden = !text;
    }

    /** Verbinden, sobald der Stand `screen` meldet, nicht erst nach der Nachspiel-Verzögerung. */
    function follow(s) {
        const on = s.mode === 'screen' && !!s.screen;
        if (on && !watch) startWatch(s.screen.via);
        else if (!on && watch) unwatch(false);
    }

    function startWatch(way) {
        const w = { via: way, pc: null, room: null, timer: 0, shown: false };
        watch = w;
        screenNote('');
        w.timer = setTimeout(() => { if (watch === w && !w.shown) screenNote(MISSING); }, NO_PICTURE);
        // Scheitert der Weg, zeigt der Zeitgeber den Hinweis.
        if (way === 'livekit') subscribe(w).catch(() => {});
        else signalTo({ type: 'ready' });
    }

    async function subscribe(w) {
        const { status, data } = await post('/screen/token', {});
        if (watch !== w || status !== 200) return;
        const { Room, RoomEvent } = await lk();
        if (watch !== w) return;
        const room = new Room();
        w.room = room;
        room.on(RoomEvent.TrackSubscribed, (track) => { if (watch === w && track.kind === 'video') track.attach(video); });
        await room.connect(data.url, data.token);
    }

    async function viewerSignal(e) {
        const w = watch;
        if (!w || w.via !== 'direct') return;
        if (e.type === 'full') {
            clearTimeout(w.timer);
            screenNote('Für eine direkte Übertragung sind schon 15 Zuschauer da. Du siehst wieder mit, sobald das Teilen endet.');
            return;
        }
        if (e.type !== 'offer') return;
        if (w.pc) w.pc.close();
        const pc = new RTCPeerConnection(RTC);
        w.pc = pc;
        pc.addEventListener('track', (ev) => { if (w.pc === pc) video.srcObject = ev.streams[0] || new MediaStream([ev.track]); });
        pc.addEventListener('connectionstatechange', () => { if (w.pc === pc && pc.connectionState === 'failed') screenNote(MISSING); });
        try {
            await pc.setRemoteDescription({ type: 'offer', sdp: e.sdp });
            await pc.setLocalDescription(await pc.createAnswer());
            await gathered(pc);
            if (watch !== w || w.pc !== pc) return;
            await signalTo({ type: 'answer', sdp: pc.localDescription.sdp });
        } catch (error) {
            // Unbrauchbares Angebot: der Zeitgeber zeigt den Hinweis.
        }
    }

    function unwatch(leaving) {
        const w = watch;
        if (!w) return;
        watch = null;
        clearTimeout(w.timer);
        // Nach einem Moduswechsel hat der Host schon alles geschlossen; abmelden muss sich nur, wer mitten im Teilen geht.
        if (leaving && w.via === 'direct' && w.pc) signalTo({ type: 'bye' });
        if (w.pc) w.pc.close();
        if (w.room) w.room.disconnect();
        video.srcObject = null;
        screenNote('');
    }

    /* ---------- Teilnehmer: Vollbild ---------- */

    function bindFollow() {
        $('[data-live-fullscreen]').addEventListener('click', () => {
            if (document.fullscreenElement) document.exitFullscreen();
            else if (root.requestFullscreen) root.requestFullscreen();
        });
        video.addEventListener('playing', () => {
            if (!watch) return;
            watch.shown = true;
            clearTimeout(watch.timer);
            screenNote('');
        }, { signal });
    }

    /* ---------- Uhr und Zeichenschleife ---------- */

    const two = (n) => String(n).padStart(2, '0');
    const clock = (seconds) => two(Math.floor(seconds / 3600)) + ':' + two(Math.floor(seconds / 60) % 60) + ':' + two(seconds % 60);
    const startedAt = Date.now() - Number(root.dataset.elapsed || 0) * 1000;

    function tick() {
        if (!root.isConnected) return stop();
        $('[data-live-clock]').textContent = clock(Math.max(0, Math.floor((Date.now() - startedAt) / 1000)));
        const pause = $('[data-live-pause-clock]');
        if (pause && pauseStart) pause.textContent = clock(Math.floor((Date.now() - pauseStart) / 1000)).slice(3);
    }

    function loop() {
        if (!root.isConnected) return stop();
        const now = performance.now();
        while (replay.length && replay[0].due <= now) apply(replay.shift().e);
        if (dirty) { dirty = false; draw(); }
        frameId = requestAnimationFrame(loop);
    }

    /* ---------- Start ---------- */

    function observe(el, fn) {
        const o = new ResizeObserver(fn);
        o.observe(el);
        observers.push(o);
    }

    bindHandover();
    if (host) bindHost();
    else bindFollow();
    // Wurzel und Desk-Fenster bestimmen die Höhe, der freie Platz die Folie, die Folie Zeichenfläche und PDF.
    const scroller = root.closest('[data-ignis-window-body]');
    if (scroller) observe(scroller, layout);
    else window.addEventListener('resize', layout, { signal });
    observe(frame, fitSlide);
    observe(slide, () => { sizeInk(); renderPage(); });
    document.addEventListener('fullscreenchange', layout, { signal });
    layout();
    fitSlide();
    sizeInk();
    tick();
    timers.push(setInterval(tick, 1000));
    frameId = requestAnimationFrame(loop);
    openPdf();
    poll();
})();
