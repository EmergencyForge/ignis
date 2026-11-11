/**
 * plugins/training/assets/js/presentation.js: Live-Vortrag an einem Termin
 * (templates/presentation/present.php und follow.php).
 *
 * Host: zeichnet, blättert und wechselt den Modus. Lokale Ereignisse gehen
 * gesammelt alle 250 ms an `/events` (ohne Raum alle 100 ms, solange Laser
 * oder Stift laufen), nie zwei POSTs gleichzeitig, damit sie in der
 * Reihenfolge ankommen. `/state` jede Sekunde liefert Zuschauer, Übergabe,
 * Fragen und Protokoll; eine neue Frage soll der Host gleich sehen.
 *
 * Teilnehmer: fragt `/state?after=seq` mit ETag ab und spielt neue
 * Ereignisse um einen Abfragetakt später im Abstand ihres `t` nach, damit
 * Striche und Laser im Tempo des Hosts erscheinen. Der Laserpunkt gleitet
 * zwischen zwei Positionen.
 *
 * Raum: hat der Vortrag einen LiveKit-Raum, treten Host und Zuschauer ihm
 * beim Laden bei. Laser und Striche gehen dann zusätzlich als Datennachricht
 * und erscheinen sofort; der Server bleibt die Quelle der Wahrheit, die
 * Abfrage ergänzt, was der Raum nicht brachte, und zeichnet nichts doppelt.
 *
 * Koordinaten sind 0..1 relativ zur Folie, so passt jede Bildschirmgröße.
 * Zoom, Ausschnitt und Drehung gibt der Host je Seite als `view` vor; Folie
 * und Zeichenfläche bekommen dieselbe CSS-Transformation, Striche bleiben in
 * Seitenkoordinaten. Auf dem geteilten Bildschirm liegt eine eigene
 * Zeichenfläche genau über dem Videobild, ihre Striche haben Seite -1.
 *
 * Bildschirm teilen: der Host holt Bild und, nur von einem Tab, Ton per
 * getDisplayMedia und meldet `/screen`, der Server wählt den Weg. Über LiveKit
 * lädt der Host einmal in den Raum des Vortrags hoch. Direkt baut der Host je
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
    const FLUSH_ACTIVE = 100;
    const HOST_POLL = 1000;
    const POLL_FAST = 700;
    const POLL_ACTIVE = 300;
    const POLL_SLOW = 2000;
    // So lange gilt Laser oder Stift als aktiv, und so lange schlägt Laser aus dem Raum den aus der Abfrage.
    const ACTIVE_WINDOW = 2000;
    const RESET_WINDOW = 6000;
    // Anteil des Abstands, den der Laserpunkt beim Zuschauer je Bild aufholt.
    const GLIDE = 0.35;
    // Neue Raum-Schlüssel höchstens so oft, auch nach 503 und 429.
    const ROOM_RETRY = 10000;
    // LiveKit empfiehlt höchstens 15 KiB je zuverlässiger Nachricht.
    const MAX_DATA = 15000;
    const SWIPE = 60;
    // Wie Presentations::SCREEN_LIMIT; mehr Verbindungen trägt die Leitung des Hosts nicht.
    const SCREEN_LIMIT = 15;
    const ICE_WAIT = 3000;
    const NO_PICTURE = 15000;
    const RTC = { iceServers: [{ urls: 'stun:stun.cloudflare.com:3478' }] };
    const ZOOM_STEP = 1.25;
    // Wie DrawEvent::MAX_ZOOM und SCREEN_PAGE im Paket.
    const MAX_ZOOM = 4;
    const SCREEN = -1;
    const VIEW_EVERY = 50;
    const RENDER_DELAY = 150;
    // Zoom 4 im Vollbild ergäbe eine Leinwand von Hunderten MB; darüber wird die Folie lieber etwas weicher.
    const MAX_PIXELS = 12e6;
    const FIT = { z: 1, x: 0.5, y: 0.5, r: 0 };

    const slide = $('[data-live-slide]');
    const frame = $('[data-live-frame]');
    const pdfCanvas = $('[data-live-pdf]');
    const ink = $('[data-live-ink]');
    const ctx = ink.getContext('2d');
    const video = $('[data-live-video]');
    const screenBox = $('[data-live-screen]');
    const screenInk = $('[data-live-screen-ink]');
    const sctx = screenInk.getContext('2d');
    const audio = $('[data-live-audio]');

    /* Stand der Folie: Seite, Modus, Striche je Seite (-1 ist der Bildschirm), Laserpunkt. */
    const st = { page: 0, mode: 'slides', strokes: {}, laser: null };
    // Ansicht je Seite {z, x, y, r}: x und y sind die Mitte des sichtbaren Ausschnitts in Seitenkoordinaten.
    let views = {};
    let hasSlides = !!root.dataset.slides;
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
    let flushTimer = 0;
    let frameId = 0;
    let stopped = false;

    function stop() {
        if (stopped) return;
        stopped = true;
        // Vor dem Abbruch der Abfrage: ein Zuschauer verabschiedet sich noch beim Host.
        if (host) {
            if (share) screenOff();
            stopShare();
        } else {
            unwatch(true);
            sound(null);
        }
        leaveRoom();
        ended = true;
        timers.forEach(clearInterval);
        clearTimeout(pollTimer);
        clearTimeout(flushTimer);
        clearTimeout(renderTimer);
        clearTimeout(viewTimer);
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
    // Seitenverhältnis je Seite, eine PDF kann Hoch- und Querformat mischen.
    const ratios = {};
    let renderTimer = 0;
    let replaying = false;
    let renderedPage = -1;
    // Pixel der Zeichenfläche je CSS-Pixel der eingepassten Seite.
    let inkScale = 1;
    let dpr = window.devicePixelRatio || 1;

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
            ratios[0] = slideRatio;
        } catch (error) {
            // Auch ein gescheiterter Ladeauftrag hält einen Worker, der sonst bis zum Schließen des Desks lebt.
            if (loading) loading.destroy();
            loading = null;
            // Ohne Folien bleiben Pause, Laser und Übergabe nutzbar, nur Zeichnen und Blättern nicht.
            if (!stopped) note('Die Folien lassen sich hier nicht anzeigen.');
            return;
        }
        if (stopped) { loading.destroy(); return; }
        pageCount = doc.numPages;
        $$('[data-live-page-count]').forEach((el) => { el.textContent = String(pageCount); });
        showPage(st.page);
        if (thumbsWanted) buildThumbs();
    }

    /**
     * Nur bei Seitenwechsel oder neuer Auflösung (Größe, Zoom), das Rendern ist teuer.
     * Die PDF bekommt dieselbe Auflösung wie die Zeichenfläche. Gerendert wird nebenher,
     * damit beim Zoomen das alte Bild stehen bleibt, bis das scharfe fertig ist.
     */
    async function renderPage() {
        const w = ink.width;
        const h = ink.height;
        const key = st.page + ':' + w + 'x' + h;
        if (stopped || !doc || !slide.clientWidth || key === rendered) return;
        rendered = key;
        const token = ++renderToken;
        if (renderTask) renderTask.cancel();
        // Neue Seite: das alte Bild darf nicht unter den neuen Strichen stehen.
        if (renderedPage !== st.page) pdfCanvas.getContext('2d').clearRect(0, 0, pdfCanvas.width, pdfCanvas.height);
        try {
            const page = await doc.getPage(st.page + 1);
            if (stopped || token !== renderToken) return;
            const viewport = page.getViewport({ scale: w / page.getViewport({ scale: 1 }).width });
            const off = document.createElement('canvas');
            off.width = w;
            off.height = h;
            renderTask = page.render({ canvas: off, viewport });
            await renderTask.promise;
            if (stopped || token !== renderToken) return;
            pdfCanvas.width = w;
            pdfCanvas.height = h;
            pdfCanvas.getContext('2d').drawImage(off, 0, 0);
            renderedPage = st.page;
            off.width = 0;
        } catch (error) {
            // Abgebrochen (neue Seite) oder Dokument beim Schließen zerstört: nichts zu tun.
            if (!stopped && (!error || error.name !== 'RenderingCancelledException')) throw error;
        }
    }

    /** Seitenverhältnis der aktuellen Seite holen, dann einpassen und sofort rendern. */
    async function layoutPage() {
        const page = st.page;
        if (doc && ratios[page] === undefined) {
            try {
                const viewport = (await doc.getPage(page + 1)).getViewport({ scale: 1 });
                ratios[page] = viewport.width / viewport.height;
            } catch (error) {
                // Dokument beim Schließen zerstört; sonst mit dem Verhältnis der ersten Seite weiter.
                if (stopped) return;
            }
            if (stopped || page !== st.page) return;
        }
        fitSlide();
        renderPage();
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
     * Folie so groß wie möglich in den freien Platz, im Verhältnis der aktuellen Seite,
     * gedreht um 90 oder 270 Grad hochkant statt quer. Bildschirm, Pause und Wartebild
     * bleiben 16:9. Schmal ist die Höhe frei, dann nimmt die Fläche per CSS die volle
     * Breite mit diesem Verhältnis.
     */
    function fitSlide() {
        const v = viewOf(st.page);
        const ratio = shown() === 'slides' ? (v.r % 180 ? 1 / ratioOf(st.page) : ratioOf(st.page)) : 16 / 9;
        frame.style.setProperty('--tr-live-ratio', String(ratio));
        const w = frame.clientWidth;
        const h = frame.clientHeight;
        if (w && h && !root.classList.contains('tr-live--narrow')) {
            const width = Math.floor(Math.min(w, h * ratio));
            frame.style.setProperty('--tr-live-w', width + 'px');
            frame.style.setProperty('--tr-live-h', Math.floor(width / ratio) + 'px');
        }
        place();
        fitScreenInk();
    }

    /* ---------- Ansicht: Zoom, Ausschnitt, Drehung ---------- */

    const viewOf = (page) => views[page] || FIT;
    const ratioOf = (page) => ratios[page] || slideRatio;
    const round = (n, steps) => Math.round(n * steps) / steps;

    /** Was das Frame zeigt: ohne Folien statt der Folie das Wartebild. */
    const shown = () => (st.mode === 'slides' && !hasSlides ? 'wait' : st.mode);

    /**
     * Die Seite füllt die Fläche immer ganz aus. Weil die Fläche das Verhältnis der gedrehten
     * Seite hat, ist ein halber sichtbarer Ausschnitt 0.5 / z, bei jedem Zuschauer gleich.
     */
    function clampView(v) {
        const z = Math.min(MAX_ZOOM, Math.max(1, v.z));
        const half = 0.5 / z;
        const fit = (c) => Math.min(1 - half, Math.max(half, c));
        return { z, x: fit(v.x), y: fit(v.y), r: v.r };
    }

    /** Fläche (Vw x Vh) und ungedrehte Seite bei Zoom 1 (Pw x Ph) in CSS-Pixeln. */
    function geom(v) {
        const Vw = slide.clientWidth;
        const Vh = slide.clientHeight;
        const turned = v.r % 180 !== 0;
        const ratio = turned ? 1 / ratioOf(st.page) : ratioOf(st.page);
        let w = Vw;
        let h = Vw / ratio;
        if (h > Vh) { h = Vh; w = Vh * ratio; }
        return { Vw, Vh, Pw: turned ? h : w, Ph: turned ? w : h };
    }

    /**
     * Folie und Zeichenfläche: Seitenmitte (x, y) in die Mitte der Fläche, gedreht und gezoomt.
     * Die Auflösung wächst mit dem Zoom, gedeckelt durch MAX_PIXELS; die PDF rendert erst neu,
     * wenn der Zoom eine Weile steht, Verschieben allein rendert nichts.
     */
    function place() {
        const v = viewOf(st.page);
        const g = geom(v);
        if (!g.Vw || !g.Vh) return;
        const transform = 'translate(' + g.Vw / 2 + 'px,' + g.Vh / 2 + 'px) rotate(' + v.r + 'deg) scale(' + v.z + ') translate(' + (-v.x * g.Pw) + 'px,' + (-v.y * g.Ph) + 'px)';
        [pdfCanvas, ink].forEach((c) => {
            c.style.width = g.Pw + 'px';
            c.style.height = g.Ph + 'px';
            c.style.transform = transform;
        });
        const sharp = Math.min(v.z, Math.sqrt(MAX_PIXELS / (g.Pw * g.Ph * dpr * dpr)));
        const w = Math.max(1, Math.round(g.Pw * dpr * sharp));
        const h = Math.max(1, Math.round(g.Ph * dpr * sharp));
        if (ink.width !== w || ink.height !== h) {
            ink.width = w;
            ink.height = h;
            dirty = true;
        }
        inkScale = w / g.Pw;
        // Auch ohne neue Größe: war die Folie versteckt (Pause, Bildschirm), konnte nichts rendern, und
        // inzwischen kann eine andere Seite dran sein. Nur ein neuer Zoom auf derselben Seite wartet.
        if (doc && st.page + ':' + w + 'x' + h !== rendered) {
            clearTimeout(renderTimer);
            renderTimer = setTimeout(renderPage, renderedPage === st.page ? RENDER_DELAY : 0);
        }
        if (host) viewButtons();
    }

    /** Bildschirmpunkt in Seitenkoordinaten, über die Umkehrung der Transformation aus place(). */
    function toPage(clientX, clientY, v) {
        const g = geom(v);
        const rect = slide.getBoundingClientRect();
        // Skaliert das Desk das Fenster, weicht das Rechteck von den CSS-Maßen ab.
        const k = g.Vw ? rect.width / g.Vw : 1;
        const dx = (clientX - rect.left) / k - g.Vw / 2;
        const dy = (clientY - rect.top) / k - g.Vh / 2;
        const a = (-v.r * Math.PI) / 180;
        const ux = dx * Math.cos(a) - dy * Math.sin(a);
        const uy = dx * Math.sin(a) + dy * Math.cos(a);
        return [v.x + ux / (v.z * g.Pw), v.y + uy / (v.z * g.Ph)];
    }

    /** Zeichenfläche genau über das Videobild; object-fit: contain lässt oben und unten oder links und rechts Ränder. */
    function fitScreenInk() {
        const W = screenBox.clientWidth;
        const H = screenBox.clientHeight;
        const vw = video.videoWidth;
        const vh = video.videoHeight;
        let w = 0;
        let h = 0;
        if (W && H && vw && vh) {
            const k = Math.min(W / vw, H / vh);
            w = vw * k;
            h = vh * k;
        }
        const s = screenInk.style;
        s.left = (W - w) / 2 + 'px';
        s.top = (H - h) / 2 + 'px';
        s.width = w + 'px';
        s.height = h + 'px';
        const bw = Math.round(w * dpr);
        const bh = Math.round(h * dpr);
        if (screenInk.width !== bw || screenInk.height !== bh) {
            screenInk.width = bw;
            screenInk.height = bh;
            dirty = true;
        }
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

    function drawStroke(c, s, W, H) {
        const lw = s.width * 2.2 * (W / 960) * (s.tool === 'marker' ? 4 : 1);
        const p = s.points.map(([x, y]) => [x * W, y * H]);
        if (!p.length) return;
        c.save();
        c.strokeStyle = s.color;
        c.lineWidth = lw;
        c.lineCap = 'round';
        c.lineJoin = 'round';
        c.globalAlpha = s.tool === 'marker' ? 0.35 : 1;
        c.beginPath();
        if (freehand(s.tool)) {
            c.moveTo(p[0][0], p[0][1]);
            for (let i = 1; i < p.length; i++) c.lineTo(p[i][0], p[i][1]);
            if (p.length === 1) c.lineTo(p[0][0] + 0.1, p[0][1]);
        } else {
            const a = p[0];
            const b = p[p.length - 1];
            if (s.tool === 'line' || s.tool === 'arrow') { c.moveTo(a[0], a[1]); c.lineTo(b[0], b[1]); }
            if (s.tool === 'rect') c.rect(a[0], a[1], b[0] - a[0], b[1] - a[1]);
            if (s.tool === 'ellipse') c.ellipse((a[0] + b[0]) / 2, (a[1] + b[1]) / 2, Math.abs(b[0] - a[0]) / 2, Math.abs(b[1] - a[1]) / 2, 0, 0, Math.PI * 2);
            if (s.tool === 'arrow') {
                const angle = Math.atan2(b[1] - a[1], b[0] - a[0]);
                const head = Math.max(12, lw * 4);
                c.moveTo(b[0], b[1]);
                c.lineTo(b[0] - head * Math.cos(angle - 0.45), b[1] - head * Math.sin(angle - 0.45));
                c.moveTo(b[0], b[1]);
                c.lineTo(b[0] - head * Math.cos(angle + 0.45), b[1] - head * Math.sin(angle + 0.45));
            }
        }
        c.stroke();
        c.restore();
    }

    function draw() {
        ctx.clearRect(0, 0, ink.width, ink.height);
        sctx.clearRect(0, 0, screenInk.width, screenInk.height);
        if (st.mode === 'slides') paint(ctx, ink, strokesOf(st.page), inkScale / viewOf(st.page).z, ink.width / inkScale);
        else if (st.mode === 'screen' && screenInk.width) paint(sctx, screenInk, strokesOf(SCREEN), dpr, screenInk.width / dpr);
    }

    /**
     * Striche wachsen mit dem Zoom wie Tinte auf dem Blatt, der Laserpunkt bleibt auf dem Bildschirm gleich groß.
     * unit: Pixel der Leinwand je Bildschirmpixel; base: Breite der Ebene in CSS-Pixeln ohne Zoom.
     */
    function paint(c, canvas, strokes, unit, base) {
        const W = canvas.width;
        const H = canvas.height;
        strokes.forEach((s) => drawStroke(c, s, W, H));
        const l = host ? st.laser : glide;
        if (!l) return;
        // Laserpunkt in --danger, damit er dem Thema folgt.
        const r = (6 * (base / 960) + 1) * unit;
        c.save();
        c.fillStyle = getComputedStyle(root).getPropertyValue('--danger').trim() || 'red';
        c.globalAlpha = 0.18;
        c.beginPath();
        c.arc(l[0] * W, l[1] * H, r * 2.2, 0, Math.PI * 2);
        c.fill();
        c.globalAlpha = 0.85;
        c.beginPath();
        c.arc(l[0] * W, l[1] * H, r, 0, Math.PI * 2);
        c.fill();
        c.restore();
    }

    /** Ein Ereignis wirkt auf beiden Seiten gleich: beim Host sofort, beim Teilnehmer verzögert. */
    function apply(e) {
        switch (e.kind) {
            case 'begin':
                seenId(e.id);
                // Dieselbe ID ein zweites Mal (nach einem Abriss des Raums bringt die Abfrage den Strich neu): ersetzen statt doppeln.
                st.strokes[e.page] = strokesOf(e.page).filter((s) => s.id !== e.id);
                st.strokes[e.page].push({ id: e.id, tool: e.tool, color: e.color, width: e.width, points: [e.p] });
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
            case 'view':
                // Beim Neuladen kommen alle Ansichten der Reihe nach, die letzte je Seite gilt.
                views[e.page] = { z: e.z, x: e.x, y: e.y, r: e.r };
                if (e.page === st.page && !replaying) fitSlide();
                break;
        }
        dirty = true;
    }

    /* ---------- Seite und Modus anzeigen ---------- */

    function showPage(page) {
        st.page = clampPage(page);
        st.laser = null;
        heldLaser = null;
        const counter = $('[data-live-counter]');
        if (counter) counter.textContent = (st.page + 1) + ' / ' + (pageCount || 1);
        followCounter();
        $$('.tr-live__thumb').forEach((t, i) => t.setAttribute('aria-current', i === st.page ? 'true' : 'false'));
        dirty = true;
        layoutPage();
    }

    function followCounter() {
        const follow = $('[data-live-follow-counter]');
        if (follow) follow.textContent = st.mode === 'screen' ? 'Bildschirm' : shown() === 'wait' ? 'Gleich geht es los' : 'Folie ' + (st.page + 1) + (pageCount ? ' von ' + pageCount : '');
    }

    function showMode(mode) {
        const before = st.mode;
        st.mode = mode === 'pause' || mode === 'screen' ? mode : 'slides';
        st.laser = null;
        heldLaser = null;
        const display = shown();
        frame.dataset.mode = display;
        pauseStart = st.mode === 'pause' ? (pauseStart || Date.now()) : 0;
        if (host) {
            $$('.ignis-segmented [data-live-mode]').forEach((b) => b.setAttribute('aria-pressed', b.dataset.liveMode === st.mode ? 'true' : 'false'));
            // Auf dem Bildschirm wird gezeichnet, geblättert und gezoomt aber nicht.
            $('[data-live-dock]').hidden = display !== 'slides' && display !== 'screen';
            $('[data-live-pages]').hidden = display !== 'slides';
            $('[data-live-zoom]').hidden = display !== 'slides';
            const clear = $('[data-live-clear]');
            const label = display === 'screen' ? 'Zeichnungen löschen' : 'Folie leeren';
            clear.setAttribute('aria-label', label);
            clear.dataset.ignisTooltip = label;
            $('[data-live-share]').hidden = st.mode !== 'screen';
            screenButton();
            // Striche auf dem Bildschirm gehören zu genau einem Teilen; danach passen sie zu keinem Bild mehr.
            if (ready && (before === 'screen') !== (st.mode === 'screen')) clearScreen();
        }
        followCounter();
        fitSlide();
        dirty = true;
    }

    function showEnded() {
        if (host) stopShare();
        else unwatch(false);
        leaveRoom();
        ended = true;
        st.laser = null;
        replay.length = 0;
        $('[data-live-toast]').hidden = true;
        $('[data-live-ended]').hidden = false;
        questionsEnded();
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
    let lastInk = -Infinity;

    function emit(e) {
        apply(e);
        const at = performance.now();
        queue.push({ e, at });
        if (e.kind === 'laser' || e.kind === 'begin' || e.kind === 'point') lastInk = at;
        sendLive(e);
    }

    /** Ohne Raum geht beim Zeichnen öfter ein Paket hinaus, sonst bleibt die Last auf dem Server wie bisher. */
    function flushLoop() {
        flush();
        if (stopped) return;
        const active = !roomLive && performance.now() - lastInk < ACTIVE_WINDOW;
        flushTimer = setTimeout(flushLoop, active ? FLUSH_ACTIVE : FLUSH_EVERY);
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
    // Eine Abfrage läuft gerade; endedNow() wartet sie ab.
    let polling = false;
    let first = true;
    const replay = [];
    let lastDue = 0;
    const target = { page: 0, mode: 'slides' };
    let talkId = null;
    let again = false;
    // Die letzte Antwort brachte Laser oder Striche.
    let busy = false;

    /** Neuer Lauf desselben Termins: alte Zeichnungen verwerfen und von vorn abfragen. */
    function resetTalk() {
        talkId = null;
        first = true;
        ownPending.clear();
        lastSeq = 0;
        etag = null;
        replay.length = 0;
        lastDue = 0;
        signalSeq = 0;
        unwatch(false);
        // Jeder Lauf hat seinen eigenen Raum.
        leaveRoom();
        roomRefused = false;
        roomAsked = -Infinity;
        heldLaser = null;
        liveStrokes.clear();
        heldPoints.clear();
        heldViews.clear();
        st.strokes = {};
        views = {};
        st.laser = null;
        fitSlide();
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

    /**
     * Takt des Zuschauers: schneller nur, solange gezeichnet wird und der Raum nichts bringt, und nie in einem
     * versteckten Tab. Nach diesem Takt spielt er auch nach, damit bis zur nächsten Antwort nichts stockt.
     */
    const pace = () => (busy && !roomFresh() && !document.hidden ? POLL_ACTIVE : POLL_FAST);

    async function poll() {
        if (!root.isConnected) return stop();
        if (ended) return;
        // Im versteckten Tab braucht der Host keine schnelle Liste; Zuschauer und Fragen holt er beim Zurückkommen.
        let delay = host ? (connecting() ? POLL_FAST : document.hidden ? POLL_SLOW : HOST_POLL) : POLL_FAST;
        polling = true;
        try {
            const headers = { Accept: 'application/json' };
            if (etag && !host) headers['If-None-Match'] = etag;
            const asked = performance.now();
            const response = await fetch(base + '/state?after=' + lastSeq, { credentials: 'same-origin', cache: 'no-store', headers, signal });
            if (stopped) return;
            if (response.status === 304) {
                idle++;
                busy = false;
            } else if (response.status === 404) {
                // Termin gelöscht, Teilnahme beendet oder Lehrgang abgesagt: weiterfragen bringt nichts mehr.
                showEnded();
                return;
            } else if (!response.ok) {
                throw new Error('state ' + response.status);
            } else {
                etag = response.headers.get('ETag');
                idle = handleState(await response.json(), asked) ? 0 : idle + 1;
            }
            connected(true);
            if (again) { again = false; delay = 0; }
            else if (!host && idle >= 3) delay = POLL_SLOW;
            else if (!host) delay = pace();
        } catch (error) {
            connected(false);
            delay = POLL_SLOW;
        }
        polling = false;
        if (!ended) pollTimer = setTimeout(poll, delay);
    }

    /**
     * @param {number} asked wann die Abfrage losging (performance.now())
     * @return {boolean} ob Neues kam
     */
    function handleState(s, asked) {
        if (s.status === 'ended') { showEnded(); return false; }
        // Der Host hat gewechselt: der Server rendert dann die andere Sicht.
        if ((s.presenter.id === me) !== host) { reloadView(); return false; }
        if (!host && talkId !== null && s.id !== talkId) { resetTalk(); return true; }
        talkId = s.id;
        presenterId = s.presenter.id;
        showHandover(s);
        // Für Ausbilder sind neue Fragen Neues, für Teilnehmer eine Antwort auf die eigene.
        const questionNews = showQuestions(s);
        wantRoom(s.room === true);
        // Folien braucht es am Termin und in der Seite; ohne sie zeigt der Folienmodus das Wartebild.
        const slides = s.slides !== false && !!root.dataset.slides;
        if (slides !== hasSlides) { hasSlides = slides; showMode(st.mode); }

        if (first) {
            // Erster Abruf: alles sofort, ohne Laser (liefert der Server da nicht).
            first = false;
            // Ohne Einpassen je Ansicht; showPage() unten passt einmal ein.
            replaying = true;
            s.events.forEach(apply);
            replaying = false;
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
            // Der Server hat das Teilen beendet, weil der Raum wegfiel: wie „Teilen beenden“, nur ohne eigene Meldung.
            // Erst eine Abfrage, die nach dem Start losging, kennt das Teilen überhaupt.
            if (share && asked > share.at && s.mode !== 'screen') { stopShare(); showMode(s.mode); }
            showViewers(s.viewers || []);
            signals.forEach(hostSignal);
            return true;
        }

        setHost(s.presenter);
        follow(s);
        signals.forEach(viewerSignal);
        busy = s.events.some((e) => e.kind === 'begin' || e.kind === 'point' || e.kind === 'laser');
        // Bringt der Raum gerade alles, braucht der Rest keinen Puffer.
        const due = performance.now() + (roomFresh() ? 0 : pace());
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
        return fresh || questionNews;
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

    // Kein Werkzeug beim Start: mit dem Zeiger blättert Wischen, bei Zoom verschiebt Ziehen.
    let tool = 'pointer';
    let color = '';
    let width = 2;
    let drawing = null;
    let swipe = null;
    let pan = null;
    // Zeiger, der Strich, Verschieben oder Wischen begonnen hat; ein zweiter Finger übernimmt nicht.
    let owner = null;
    let lastLaser = 0;
    let lastPoint = 0;
    const undo = {};
    const undoOf = (page) => undo[page] || (undo[page] = []);
    const copy = (value) => JSON.parse(JSON.stringify(value));

    function pick(t) {
        tool = t;
        $$('[data-tool]').forEach((b) => b.setAttribute('aria-pressed', b.dataset.tool === t ? 'true' : 'false'));
        cursor();
    }

    const zoomed = () => st.mode === 'slides' && viewOf(st.page).z > 1;

    function cursor() {
        let c = 'crosshair';
        if (tool === 'pointer') c = pan ? 'grabbing' : zoomed() ? 'grab' : '';
        else if (tool === 'laser') c = 'none';
        else if (tool === 'eraser') c = 'cell';
        slide.style.cursor = c;
        screenBox.style.cursor = c;
    }

    /** Worauf der Host gerade zeichnet: die Seite oder der Bildschirm (-1); null, solange dort nichts zu sehen ist. */
    function layer() {
        if (st.mode === 'screen') return screenInk.width ? SCREEN : null;
        return st.mode === 'slides' && doc ? st.page : null;
    }

    /** Punkt 0..1 auf der Seite (über Zoom und Drehung zurückgerechnet) oder auf dem Videobild. */
    function pos(ev) {
        let p;
        if (st.mode === 'screen') {
            const r = screenInk.getBoundingClientRect();
            p = [(ev.clientX - r.left) / r.width, (ev.clientY - r.top) / r.height];
        } else p = toPage(ev.clientX, ev.clientY, viewOf(st.page));
        const fit = (c) => round(Math.min(1, Math.max(0, c)), 10000);
        return [fit(p[0]), fit(p[1])];
    }

    /** Oberster Strich in Fingerbreite um p, für den Radierer. */
    function hit(p, page) {
        const canvas = page === SCREEN ? screenInk : ink;
        const W = canvas.width;
        const H = canvas.height;
        const list = strokesOf(page);
        // Fingerbreite auf dem Bildschirm, umgerechnet in Pixel der Zeichenfläche (beim Zoom weniger).
        const reach = (14 * (page === SCREEN ? dpr : inkScale / viewOf(page).z)) ** 2;
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
        if (!host || !ready || st.mode !== 'slides' || !pageCount || page < 0 || page >= pageCount || page === st.page) return;
        st.laser = null;
        pan = null;
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

    function clearScreen() {
        undo[SCREEN] = [];
        emit({ kind: 'clear', page: SCREEN });
    }

    function doUndo() {
        const page = layer();
        if (page === null) return;
        const last = undoOf(page).pop();
        if (!last) return;
        if (last.type === 'add') emit({ kind: 'erase', page, id: last.id });
        if (last.type === 'clear') emit({ kind: 'restore', page, strokes: last.strokes });
        if (last.type === 'erase') emit({ kind: 'restore', page, strokes: copy(strokesOf(page)).concat([last.stroke]) });
    }

    function clearPage() {
        const page = layer();
        if (page === null || !strokesOf(page).length) return;
        undoOf(page).push({ type: 'clear', strokes: copy(strokesOf(page)) });
        emit({ kind: 'clear', page });
    }

    /* Ansicht beim Host */

    let viewTimer = 0;
    let viewSent = 0;
    let viewPage = null;

    const canView = () => host && ready && !ended && !!doc && st.mode === 'slides';

    function viewButtons() {
        const z = viewOf(st.page).z;
        // aria-disabled statt disabled: Fokus und Tooltip bleiben, zoomBy() tut an der Grenze ohnehin nichts.
        $('[data-live-zoom-in]').setAttribute('aria-disabled', String(z >= MAX_ZOOM));
        $('[data-live-zoom-out]').setAttribute('aria-disabled', String(z <= 1));
        cursor();
    }

    function sendView() {
        clearTimeout(viewTimer);
        viewTimer = 0;
        const page = viewPage;
        if (page === null) return;
        viewPage = null;
        viewSent = performance.now();
        const v = viewOf(page);
        // Gerundet wie DrawEvent, sonst lehnt der Server das ganze Paket ab.
        emit({ kind: 'view', page, z: round(v.z, 100), x: round(v.x, 10000), y: round(v.y, 10000), r: v.r });
    }

    /** Höchstens alle VIEW_EVERY ms ein Ansichts-Ereignis, das letzte einer Geste geht immer hinaus. */
    function shareView(final) {
        if (viewPage !== null && viewPage !== st.page) sendView();
        viewPage = st.page;
        const wait = final ? 0 : VIEW_EVERY - (performance.now() - viewSent);
        clearTimeout(viewTimer);
        if (wait <= 0) sendView();
        else viewTimer = setTimeout(sendView, wait);
    }

    function setView(v, final) {
        const before = viewOf(st.page);
        views[st.page] = clampView(v);
        // Gedreht ändert sich das Seitenverhältnis der Fläche.
        if (views[st.page].r !== before.r) fitSlide();
        else place();
        shareView(final);
    }

    /** Zoom um die Mitte oder, mit Mausrad, um den Punkt unter der Maus. */
    function zoomBy(factor, ev) {
        if (!canView()) return;
        const v = viewOf(st.page);
        const z = Math.min(MAX_ZOOM, Math.max(1, v.z * factor));
        if (z === v.z) return;
        let x = v.x;
        let y = v.y;
        if (ev) {
            const p = toPage(ev.clientX, ev.clientY, v);
            x = p[0] - ((p[0] - v.x) * v.z) / z;
            y = p[1] - ((p[1] - v.y) * v.z) / z;
        }
        setView({ z, x, y, r: v.r }, !ev);
    }

    /* Zeiger */

    function pointerDown(ev) {
        const page = ready && !ended && owner === null ? layer() : null;
        if (page === null) return;
        ev.currentTarget.setPointerCapture(ev.pointerId);
        if (tool === 'pointer' && page !== SCREEN && zoomed()) {
            owner = ev.pointerId;
            pan = { x: ev.clientX, y: ev.clientY, v: viewOf(page), moved: false };
            cursor();
            return;
        }
        if (tool === 'pointer' || tool === 'laser') {
            // Wischen blättert nur mit Zeiger und Laser, sonst würde jeder Strich blättern.
            if (page !== SCREEN) { owner = ev.pointerId; swipe = { x: ev.clientX, y: ev.clientY }; }
            return;
        }
        const p = pos(ev);
        if (tool === 'eraser') {
            const s = hit(p, page);
            if (s) {
                undoOf(page).push({ type: 'erase', stroke: copy(s) });
                emit({ kind: 'erase', page, id: s.id });
            }
            return;
        }
        if (strokesOf(page).length >= MAX_STROKES) return;
        owner = ev.pointerId;
        drawing = { id: ++lastId, page };
        emit({ kind: 'begin', page, id: drawing.id, tool, color, width, p });
        undoOf(page).push({ type: 'add', id: drawing.id });
    }

    function addPoint(p) {
        const s = strokesOf(drawing.page).find((x) => x.id === drawing.id);
        if (!s || (freehand(s.tool) && s.points.length >= MAX_POINTS)) return;
        emit({ kind: 'point', page: drawing.page, id: drawing.id, p });
    }

    function pointerMove(ev) {
        if (!ready || ended || (owner !== null && ev.pointerId !== owner)) return;
        const now = performance.now();
        if (pan) {
            // Gegen die Ansicht beim Greifen gerechnet: der gegriffene Punkt bleibt unter dem Finger.
            const a = toPage(pan.x, pan.y, pan.v);
            const b = toPage(ev.clientX, ev.clientY, pan.v);
            pan.moved = true;
            setView({ z: pan.v.z, r: pan.v.r, x: pan.v.x - (b[0] - a[0]), y: pan.v.y - (b[1] - a[1]) }, false);
            return;
        }
        if (tool === 'laser') {
            if (layer() !== null && now - lastLaser > 25) { lastLaser = now; emit({ kind: 'laser', p: pos(ev) }); }
            return;
        }
        if (drawing && now - lastPoint > 12) { lastPoint = now; addPoint(pos(ev)); }
    }

    function pointerUp(ev) {
        if (ev.pointerId !== owner) return;
        owner = null;
        if (pan) {
            const moved = pan.moved;
            pan = null;
            if (moved) shareView(true);
            cursor();
        }
        if (drawing) { addPoint(pos(ev)); drawing = null; }
        if (swipe) {
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
            if (tool === 'pointer' || tool === 'laser' || tool === 'eraser') pick('pen');
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

        $('[data-live-zoom-in]').addEventListener('click', () => zoomBy(ZOOM_STEP));
        $('[data-live-zoom-out]').addEventListener('click', () => zoomBy(1 / ZOOM_STEP));
        $('[data-live-fit]').addEventListener('click', () => { if (canView()) setView(Object.assign({}, FIT, { r: viewOf(st.page).r }), true); });
        $('[data-live-rotate]').addEventListener('click', () => {
            const v = viewOf(st.page);
            if (canView()) setView({ z: v.z, x: v.x, y: v.y, r: (v.r + 90) % 360 }, true);
        });
        // Strg und Mausrad zoomen die Folie statt der ganzen Seite, nur über der Bühne.
        $('.tr-live__stage').addEventListener('wheel', (ev) => {
            if (!ev.ctrlKey || !canView()) return;
            ev.preventDefault();
            // Beim Verschieben rechnet die Geste gegen die Ansicht beim Greifen; ein Zoom dazwischen ließe sie springen.
            if (pan) return;
            const delta = ev.deltaMode ? ev.deltaY * 33 : ev.deltaY;
            zoomBy(ZOOM_STEP ** Math.max(-1, Math.min(1, -delta / 100)), ev);
        }, { passive: false });
        $('[data-live-wait-share]').hidden = !canShare;

        [slide, screenBox].forEach((el) => {
            el.addEventListener('pointerdown', pointerDown);
            el.addEventListener('pointermove', pointerMove);
            el.addEventListener('pointerup', pointerUp);
            el.addEventListener('pointercancel', pointerUp);
            el.addEventListener('lostpointercapture', pointerUp);
            el.addEventListener('pointerleave', () => { if (tool === 'laser' && st.laser) emit({ kind: 'laser', p: null }); });
        });

        root.addEventListener('keydown', (ev) => {
            const onControl = ev.target instanceof Element && ev.target.closest('button, a, input, textarea');
            // In Textfeldern gehören Pfeile, Leertaste und Strg+Z dem Text.
            if (ev.target instanceof Element && ev.target.closest('input, textarea, select, [contenteditable]:not([contenteditable="false"])')) return;
            if (ev.key === 'ArrowRight' || ev.key === 'PageDown' || (ev.key === ' ' && !onControl)) { ev.preventDefault(); go(st.page + 1); }
            if (ev.key === 'ArrowLeft' || ev.key === 'PageUp') { ev.preventDefault(); go(st.page - 1); }
            if ((ev.ctrlKey || ev.metaKey) && ev.key.toLowerCase() === 'z') { ev.preventDefault(); doUndo(); }
        });
        root.addEventListener('pointerdown', (ev) => {
            // Auch Textfelder (Antworten) behalten den Fokus, sonst ließe sich nicht hineintippen.
            if (!(ev.target instanceof Element && ev.target.closest('button, a, input, textarea'))) root.focus({ preventScroll: true });
        });

        // Seitenleiste; die Reiter bindet bindTabs(), die gibt es auch beim Mitverfolgen.
        const main = $('[data-live-main]');
        const side = $('[data-live-side]');
        side.addEventListener('click', () => {
            const bare = main.classList.toggle('tr-live__main--bare');
            side.setAttribute('aria-pressed', String(!bare));
        });

        // Nach der Bestätigung nichts mehr senden; was noch in der Schlange liegt, verwirft das Ende ohnehin.
        $('[data-live-end]').addEventListener('submit', (ev) => {
            if (!ev.defaultPrevented) ended = true;
        });

        flushTimer = setTimeout(flushLoop, FLUSH_EVERY);
        pick('pointer');
        screenButton();
    }

    /* ---------- Bildschirm teilen ---------- */

    const via = root.dataset.screenVia;
    // Das FiveM-Tablet kann nicht teilen, auch wenn es getDisplayMedia kennt; zuschauen geht dort.
    const canShare = !!(navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia) && !/CitizenFX/.test(navigator.userAgent);
    const MISSING = 'Der Bildschirm kommt bei dir nicht an. Lade die Seite neu oder schau später wieder rein.';
    const TOO_MANY = 'Bildschirm teilen geht gerade nicht: zu viele Zuschauer für eine direkte Übertragung.';
    let livekit = null;
    let share = null;
    let starting = false;
    let audience = 0;
    let watch = null;

    function lk() {
        if (!canRoom) return Promise.reject(new Error('livekit'));
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
        let reason = '';
        if (!canShare) reason = 'Bildschirm teilen geht nur im Browser';
        else if (!share && via === 'direct' && audience > SCREEN_LIMIT) reason = 'Bildschirm teilen geht ohne EF Sync bis 15 Zuschauer';
        const tip = reason || ((share ? share.via : via) === 'livekit' ? 'Über den Medienserver von EmergencyForge' : 'Direkt an jeden Zuschauer, bis 15');
        // Der Knopf im Wartebild sagt dasselbe wie der im Kopf, sonst täte er stumm nichts.
        [$('.ignis-segmented [data-live-mode="screen"]'), $('[data-live-wait-share]')].forEach((button) => {
            button.setAttribute('aria-disabled', reason ? 'true' : 'false');
            button.dataset.ignisTooltip = tip;
        });
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
            // Ton nur aus einem geteilten Tab: Systemton brächte Discord oder das Spiel zu allen Zuschauern.
            const wish = { video: true, audio: true, systemAudio: 'exclude', selfBrowserSurface: 'exclude' };
            try {
                stream = await navigator.mediaDevices.getDisplayMedia(wish);
            } catch (error) {
                // Ein Browser, der Ton beim Teilen nicht kennt, lehnt die Wünsche mit TypeError ab: dann nur das Bild.
                if (!error || error.name !== 'TypeError') throw error;
                delete wish.audio;
                delete wish.systemAudio;
                stream = await navigator.mediaDevices.getDisplayMedia(wish);
            }
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
                if (status === 409 && data.error === 'too_many') hint(TOO_MANY);
                else if (status === 409 && data.error === 'ended') showEnded();
                else if (status === 403 || status === 404 || status === 409) reloadView();
                else hint('Bildschirm teilen hat gerade nicht geklappt.');
                return;
            }
            const track = stream.getVideoTracks()[0];
            const s = { via: data.via, stream, track, audio: stream.getAudioTracks()[0] || null, peers: new Map(), waits: new Map(), room: null, at: performance.now() };
            share = s;
            mine = s;
            // Auch der Browser-Knopf „Freigabe beenden“ endet hier.
            track.addEventListener('ended', () => { if (share === s) leaveScreen('slides'); });
            video.srcObject = stream;
            $('[data-live-share-sound]').hidden = !s.audio;
            showMode('screen');
            // Ab hier läuft das Teilen; beenden muss auch gehen, während LiveKit noch verbindet.
            starting = false;
            if (s.via === 'livekit') {
                try {
                    await publish(s, data);
                } catch (error) {
                    if (stopped || share !== s) throw error;
                    await fallBack(s);
                }
            }
        } catch (error) {
            end();
            // Schon beendet, etwa mit „Teilen beenden“, während LiveKit verband.
            if (stopped || (mine && share !== mine)) return;
            if (mine) leaveScreen('slides');
            // Ohne Antwort weiß niemand, ob der Server schon teilt; der nächste Paket-POST stellt den Modus klar.
            else pendingMode = st.mode;
            hint(error && error.message === 'too_many' ? TOO_MANY : 'Bildschirm teilen hat gerade nicht geklappt.');
        } finally {
            starting = false;
        }
    }

    /**
     * In den Raum des Vortrags, nicht in einen zweiten: mit demselben Namen würfe LiveKit
     * die erste Verbindung hinaus, und mit ihr Laser und Striche. Den Schlüssel von `/screen`
     * braucht es nur, wenn der Host noch nicht im Raum ist.
     */
    async function publish(s, data) {
        const { Track } = await lk();
        if (share !== s) return;
        if (roomJoin) await roomJoin;
        if (!roomLive && share === s) await joinRoom(data);
        if (share !== s) return;
        if (!roomLive) throw new Error('room');
        const r = room;
        s.room = r;
        const local = r.localParticipant;
        await local.publishTrack(s.track, { source: Track.Source.ScreenShare });
        if (s.audio && share === s) {
            try {
                await local.publishTrack(s.audio, { source: Track.Source.ScreenShareAudio });
            } catch (error) {
                // Ton nicht erlaubt oder nicht angekommen: das Bild bleibt im Raum, nur ohne Ton.
                if (share === s) $('[data-live-share-sound]').hidden = true;
            }
        }
        // Während des Hochladens beendet: stopShare() fand die Spuren da noch nicht.
        if (share !== s) unpublish(r, s);
    }

    /** Ohne zu stoppen: LiveKit hält die aufgenommene Spur selbst, ein Rückfall auf direkt braucht sie weiter. */
    function unpublish(r, s) {
        [s.track, s.audio].forEach((t) => { if (t) r.localParticipant.unpublishTrack(t, false).catch(() => {}); });
    }

    /**
     * Die Konsole gab einen Schlüssel, LiveKit selbst kommt aber nicht zustande
     * (Dienst aus, Ports zu, Ton nicht erlaubt): einmal direkt, mit demselben Bild und ohne
     * zweite Auswahl. Der Raum bleibt für Laser und Striche.
     */
    async function fallBack(s) {
        if (s.room && s.room === room) unpublish(s.room, s);
        s.room = null;
        const { status, data } = await post('/screen', { via: 'direct' });
        if (share !== s) return;
        if (status !== 200) throw new Error(status === 409 && data.error === 'too_many' ? 'too_many' : 'direct');
        s.via = 'direct';
        screenButton();
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
        // Nur die Spuren herunter, der Raum trägt weiter Laser und Striche.
        if (s.room && s.room === room) unpublish(s.room, s);
        video.srcObject = null;
        $('[data-live-share-sound]').hidden = true;
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
            // Der Ton muss vor dem Angebot drin sein, sonst handeln beide Seiten nur das Bild aus.
            pc.addTrack(s.track, s.stream);
            if (s.audio) pc.addTrack(s.audio, s.stream);
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
        const way = s.mode === 'screen' && s.screen ? s.screen.via : null;
        // Ein anderer Weg heißt neu verbinden, etwa wenn der Host von LiveKit auf direkt ausweicht.
        if (watch && watch.via !== way) unwatch(false);
        if (way && !watch) startWatch(way);
    }

    function startWatch(way) {
        const w = { via: way, pc: null, timer: 0, shown: false };
        watch = w;
        screenNote('');
        w.timer = setTimeout(() => { if (watch === w && !w.shown) screenNote(MISSING); }, NO_PICTURE);
        // Scheitert der Weg, zeigt der Zeitgeber den Hinweis. Über LiveKit kommt das Bild im Raum des
        // Vortrags an; ist er noch nicht verbunden, bringt TrackSubscribed es später.
        if (way === 'livekit') roomTracks();
        else signalTo({ type: 'ready' });
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
        if (w.pc) { w.pc.close(); sound(null); }
        const pc = new RTCPeerConnection(RTC);
        w.pc = pc;
        pc.addEventListener('track', (ev) => {
            if (w.pc !== pc) return;
            // Das Video bleibt stumm, der Ton läuft über das eigene Element mit Regler.
            if (ev.track.kind === 'audio') sound(new MediaStream([ev.track]));
            else video.srcObject = ev.streams[0] || new MediaStream([ev.track]);
        });
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
        video.srcObject = null;
        sound(null);
        screenNote('');
    }

    /* ---------- Raum: Datenkanal und Bildschirm über LiveKit ---------- */

    // Ohne Pfad zum Client wird nichts von LiveKit versucht, auch wenn der Server einen Raum meldet.
    const canRoom = !!root.dataset.livekit;
    const encoder = new TextEncoder();
    const decoder = new TextDecoder();
    // Room-Objekt ab dem Beitritt; roomLive erst, wenn es verbunden ist.
    let room = null;
    let roomLive = false;
    let roomJoin = null;
    let roomAsked = -Infinity;
    // 409 no_room: erst wieder fragen, wenn der Stand den Raum einmal verloren und wieder hat.
    let roomRefused = false;
    /*
     * Zuschauer: Was der Raum schon brachte, damit die Abfrage es nicht noch einmal zeichnet.
     * Die Abfrage bleibt die Quelle der Wahrheit. Sie wird nur kurz zurückgehalten, solange
     * der Raum frisch ist (ACTIVE_WINDOW), danach gilt sie wieder. So holt sie alles nach,
     * was nicht über den Raum kam: der Host war nicht im Raum oder LiveKit verband neu.
     * Lehnte `/events` ein Paket ab, das schon über den Raum ging, bleiben dessen Striche (und ein
     * Leeren daraus) bei Zuschauern im Raum bis zum Neuladen bestehen, weil die Abfrage sie nie nennt.
     *
     * liveStrokes: Striche, deren Anfang über den Raum kam, id → { at: letzte Nachricht,
     * polled: wie viele ihrer Punkte die Abfrage schon nachreichte, cut: der Raum speist ihn
     * nicht mehr }. erased: Löschungen aus dem Raum, id → { at, page }. roomReset: Leeren oder
     * Ersetzen einer Seite über den Raum, page → { at, open: so viele hat die Abfrage noch
     * nicht nachgereicht, held: ihre Striche bis dahin }. roomView: page → Zeit der letzten Ansicht aus dem Raum.
     * heldPoints und heldViews: der letzte zurückgehaltene Endpunkt einer Form je Strich und
     * die letzte zurückgehaltene Ansicht je Seite; sie gelten, sobald der Raum dazu schweigt.
     */
    const liveStrokes = new Map();
    const erased = new Map();
    let roomReset = {};
    let roomView = {};
    let liveLaser = -Infinity;
    let roomData = -Infinity;
    // Laser aus der Abfrage, solange der Raum Laser bringt; gilt erst, wenn der Raum eine Weile schweigt.
    let heldLaser = null;
    const heldPoints = new Map();
    const heldViews = new Map();
    // Der Host heißt im Raum `u` und seine Nutzer-ID.
    let presenterId = 0;

    const recent = (at) => performance.now() - at < ACTIVE_WINDOW;
    // Kommt gerade etwas über den Raum? Nur dann braucht die Abfrage weder Puffer noch schnellen Takt.
    const roomFresh = () => recent(roomData);
    const isErased = (id) => { const x = erased.get(id); return !!x && recent(x.at); };
    // Länger als POLL_SLOW plus Sendetakt: das Gegenstück aus der Abfrage kommt sonst nach einer Pause zu spät.
    // Eine Uhr je Bild: läuft die Frist mitten in der Abfrage ab, käme sonst ein Punkt vor seinem zurückgehaltenen Anfang.
    let resetClock = 0;
    const resetOpen = (r) => !!r && r.open > 0 && resetClock - r.at < RESET_WINDOW;
    const resetting = (page) => resetOpen(roomReset[page]);

    /**
     * Ab hier gilt die Abfrage allein, der Raum bleibt verbunden. Die Zählung je Strich bleibt:
     * was der Raum schon brachte, steht im Strich, die Abfrage hängt nur den Rest an.
     */
    function trustPoll() {
        liveStrokes.forEach((l) => { l.at = -Infinity; l.cut = true; });
        erased.clear();
        flushResets(true);
        roomReset = {};
        roomView = {};
        liveLaser = -Infinity;
        roomData = -Infinity;
    }

    /**
     * Kam das Gegenstück zu einem Leeren aus dem Raum nicht in der Frist (Paket abgelehnt),
     * gilt, was die Abfrage solange zurückhielt; ein frisch geladener Zuschauer sähe es auch.
     */
    function flushResets(all) {
        Object.values(roomReset).forEach((r) => { if (r.held.length && (all || !resetOpen(r))) r.held.splice(0).forEach(apply); });
    }

    /** Beitreten, solange der Stand einen Raum meldet; höchstens alle ROOM_RETRY ms ein neuer Schlüssel. */
    function wantRoom(on) {
        if (!on) {
            roomRefused = false;
            leaveRoom();
            return;
        }
        if (!canRoom || room || roomJoin || roomRefused || ended || performance.now() - roomAsked < ROOM_RETRY) return;
        joinRoom(null);
    }

    /** @param {{url:string, token:string}|null} key schon vorhanden (vom Teilen) oder null für `/room/token` */
    function joinRoom(key) {
        roomAsked = performance.now();
        const run = connectRoom(key).catch(() => {}).then(() => { if (roomJoin === run) roomJoin = null; });
        roomJoin = run;
        return run;
    }

    async function connectRoom(key) {
        if (!key) {
            const { status, data } = await post('/room/token', {});
            if (stopped || ended) return;
            if (status === 409 && data.error === 'no_room') { roomRefused = true; return; }
            if (status === 409 && data.error === 'ended') { showEnded(); return; }
            // 503, 429 oder Netz: der nächste Versuch frühestens nach ROOM_RETRY.
            if (status !== 200) return;
            key = data;
        }
        const { Room, RoomEvent } = await lk();
        if (stopped || ended || room) return;
        const r = new Room();
        room = r;
        r.on(RoomEvent.Disconnected, () => roomGone(r));
        // Zuschauer dürfen nichts senden; der Host bekommt deshalb auch keine Daten.
        if (!host) {
            r.on(RoomEvent.DataReceived, (payload) => { if (room === r) received(payload); });
            r.on(RoomEvent.TrackSubscribed, (track) => { if (room === r) roomTrack(track, true); });
            r.on(RoomEvent.TrackUnsubscribed, (track) => { if (room === r) roomTrack(track, false); });
            // Was in der Lücke geschah, bringt nur die Abfrage.
            r.on(RoomEvent.Reconnecting, () => { if (room === r) trustPoll(); });
            r.on(RoomEvent.ParticipantDisconnected, (p) => { if (room === r && p.identity === 'u' + presenterId) trustPoll(); });
        }
        try {
            await r.connect(key.url, key.token);
        } catch (error) {
            if (room === r) forgetRoom();
            r.disconnect();
            return;
        }
        if (room !== r) { r.disconnect(); return; }
        roomLive = true;
    }

    /** Ab hier bringt die Abfrage wieder alles; was über den Raum kam, darf sie überschreiben. */
    function forgetRoom() {
        room = null;
        roomLive = false;
        trustPoll();
    }

    /** Ende des Vortrags, kein Raum mehr, Fenster zu. */
    function leaveRoom() {
        const r = room;
        if (!r) return;
        forgetRoom();
        r.disconnect();
    }

    /** LiveKit hat aufgegeben oder uns hinausgeworfen; die nächste Abfrage tritt neu bei. */
    function roomGone(r) {
        if (room !== r) return;
        forgetRoom();
        if (host) {
            if (share && share.room === r) leaveScreen('slides');
        } else if (watch && watch.via === 'livekit') {
            video.srcObject = null;
            sound(null);
            screenNote(MISSING);
        }
    }

    /** Host: dieselben Ereignisse wie für `/events`, nur sofort. Laser darf verloren gehen, Striche nicht. */
    function sendLive(e) {
        if (!roomLive) return;
        const laser = e.kind === 'laser';
        let bytes = encoder.encode(JSON.stringify(e));
        // Eine lange Wiederherstellung passt in kein Paket; die Zuschauer überlassen diese Seite dann der Abfrage.
        if (bytes.length > MAX_DATA) bytes = encoder.encode(JSON.stringify({ kind: 'gap', page: e.page }));
        room.localParticipant.publishData(bytes, { reliable: !laser, topic: laser ? 'laser' : 'ink' }).catch(() => {});
    }

    function received(payload) {
        if (ended || !ready) return;
        let e;
        try {
            e = JSON.parse(decoder.decode(payload));
        } catch (error) {
            return;
        }
        if (!e || typeof e !== 'object' || (e.kind !== 'laser' && !Number.isInteger(e.page))) return;
        try {
            live(e);
        } catch (error) {
            // Eine kaputte Nachricht darf den Raum nicht stören; die Abfrage bringt das Ereignis ohnehin.
        }
    }

    /** Zuschauer: Nachricht aus dem Raum, sofort angewandt. */
    function live(e) {
        const page = e.page;
        const now = performance.now();
        roomData = now;
        resetClock = now;
        switch (e.kind) {
            case 'laser':
                liveLaser = now;
                heldLaser = null;
                break;
            case 'begin':
                // Kam der Anfang schon aus der Abfrage, bleibt der ganze Strich bei ihr.
                if (strokesOf(page).some((s) => s.id === e.id)) return;
                liveStrokes.set(e.id, { at: now, polled: 0 });
                break;
            case 'point': {
                // Begann der Strich vor dem Beitritt, vervollständigt ihn die Abfrage.
                // Nach einer Lücke im Raum zählt nur noch die Abfrage, sonst fehlen Punkte mitten im Strich.
                const l = liveStrokes.get(e.id);
                if (!l || l.cut) return;
                l.at = now;
                heldPoints.delete(e.id);
                break;
            }
            case 'erase':
                erased.set(e.id, { at: now, page });
                break;
            case 'restore':
            case 'clear': {
                if (e.kind === 'restore' && !Array.isArray(e.strokes)) return;
                if (e.kind === 'restore') e.strokes.forEach((s) => erased.delete(s.id));
                flushResets(false);
                const r = roomReset[page];
                roomReset[page] = resetOpen(r) ? { at: now, open: r.open + 1, held: r.held } : { at: now, open: 1, held: [] };
                break;
            }
            case 'gap':
                // Diese Wiederherstellung bringt nur die Abfrage; bis dahin darf sie die Seite wieder ändern.
                delete roomReset[page];
                erased.forEach((x, id) => { if (x.page === page) erased.delete(id); });
                return;
            case 'view':
                roomView[page] = now;
                heldViews.delete(page);
                break;
            default:
                return;
        }
        apply(e);
    }

    /**
     * Zuschauer: Ereignis aus der Abfrage, ohne zu wiederholen oder zurückzuholen, was der Raum
     * gerade gebracht hat. Jede Sperre gilt nur, solange der Raum frisch ist; danach korrigiert
     * die Abfrage den Raum. Was sie in der Sperre überspringt, holt der Takt nach (heldPoints,
     * heldViews, heldLaser), wenn der Raum dazu nichts Neueres brachte.
     */
    function polled(e) {
        const page = e.page;
        switch (e.kind) {
            case 'begin': {
                const l = liveStrokes.get(e.id);
                // Der Strich kam über den Raum und steht: die Abfrage zählt nur mit.
                if (l && strokesOf(page).some((s) => s.id === e.id)) { l.polled = 1; return; }
                liveStrokes.delete(e.id);
                heldPoints.delete(e.id);
                if (isErased(e.id)) return;
                // Vor dem Gegenstück eines Leerens aus dem Raum: ein Strich von vor dem Leeren, also zurückhalten.
                if (resetting(page)) { roomReset[page].held.push(e); return; }
                break;
            }
            case 'point': {
                const l = liveStrokes.get(e.id);
                if (l) {
                    l.polled++;
                    const s = strokesOf(page).find((x) => x.id === e.id);
                    // Freihand kommt aus beiden Quellen in derselben Reihenfolge: nur anhängen, was der Raum
                    // nicht brachte. Formen ersetzen ihren Endpunkt, da gilt der Raum, solange er liefert.
                    if (!s) return;
                    if (freehand(s.tool)) {
                        if (l.polled <= s.points.length) return;
                    } else if (recent(l.at)) {
                        heldPoints.set(e.id, e);
                        return;
                    }
                    heldPoints.delete(e.id);
                    break;
                }
                if (resetting(page)) { roomReset[page].held.push(e); return; }
                heldPoints.delete(e.id);
                break;
            }
            case 'erase':
                erased.delete(e.id);
                if (resetting(page)) { roomReset[page].held.push(e); return; }
                break;
            case 'clear':
            case 'restore': {
                // Das Gegenstück zu einem Leeren aus dem Raum: schon angewandt.
                const r = roomReset[page];
                // Was bis dahin zurückgehalten war, stand vor diesem Leeren und bleibt weg.
                if (resetOpen(r)) { r.open--; r.held = []; return; }
                apply(e.kind === 'clear' ? e : { kind: 'restore', page, strokes: e.strokes.filter((s) => !isErased(s.id)) });
                return;
            }
            case 'view':
                if (roomView[page] !== undefined && recent(roomView[page])) { heldViews.set(page, e); return; }
                heldViews.delete(page);
                break;
            case 'laser':
                if (performance.now() - liveLaser < ACTIVE_WINDOW) { heldLaser = e; return; }
                break;
        }
        apply(e);
    }

    /** Zuschauer: Bildschirm über LiveKit, schon abonnierte Spuren beim Start des Zuschauens. */
    function roomTracks() {
        if (!roomLive) return;
        room.remoteParticipants.forEach((p) => p.trackPublications.forEach((pub) => { if (pub.track) roomTrack(pub.track, true); }));
    }

    function roomTrack(track, on) {
        if (!watch || watch.via !== 'livekit') return;
        if (track.kind === 'video') {
            if (on) track.attach(video);
            else track.detach(video);
        } else if (track.kind === 'audio') {
            sound(on ? new MediaStream([track.mediaStreamTrack]) : null);
        }
    }

    /* ---------- Zuschauer: Ton ---------- */

    const SOUND_KEY = 'ignis.live.sound';
    const soundPref = loadSound();
    // Der Browser ließ keinen Ton ohne Klick zu; die Wiedergabe läuft stumm.
    let blocked = false;

    function loadSound() {
        try {
            const v = JSON.parse(localStorage.getItem(SOUND_KEY) || 'null');
            if (v && typeof v.volume === 'number') return { muted: v.muted === true, volume: Math.min(1, Math.max(0, v.volume)) };
        } catch (error) {
            // Kein Speicher (privates Fenster, Tablet im Spiel): mit Ton und voller Lautstärke.
        }
        return { muted: false, volume: 1 };
    }

    function saveSound() {
        try {
            localStorage.setItem(SOUND_KEY, JSON.stringify(soundPref));
        } catch (error) {
            // Ohne Speicher gilt die Einstellung nur bis zum Neuladen.
        }
    }

    /** Tonspur des geteilten Tabs an das eigene Element; null nimmt sie weg und blendet die Leiste aus. */
    function sound(stream) {
        if (!audio) return;
        blocked = false;
        audio.srcObject = stream;
        $('[data-live-sound]').hidden = !stream;
        if (stream) {
            const track = stream.getAudioTracks()[0];
            if (track) track.addEventListener('ended', () => { if (audio.srcObject === stream) sound(null); });
            audio.volume = soundPref.volume;
            audio.muted = soundPref.muted;
            play();
        }
        soundButtons();
    }

    function play() {
        const started = audio.play();
        if (!started) return;
        started.catch((error) => {
            // Nur die Sperre ohne Klick; ein Abbruch durch eine neue Quelle ist kein Grund für stumm.
            if (!error || error.name !== 'NotAllowedError' || !audio.srcObject || audio.muted) return;
            // Kein Ton ohne Klick (auch im Tablet im Spiel): stumm weiter, „Ton einschalten“ holt ihn.
            blocked = true;
            audio.muted = true;
            soundButtons();
            audio.play().catch(() => {});
        });
    }

    function setMuted(muted) {
        soundPref.muted = muted;
        saveSound();
        blocked = false;
        audio.muted = muted;
        if (!muted) play();
        soundButtons();
    }

    function soundButtons() {
        if (!audio) return;
        const mute = $('[data-live-mute]');
        mute.setAttribute('aria-pressed', String(audio.muted));
        mute.firstElementChild.className = audio.muted ? 'fa-solid fa-volume-xmark' : 'fa-solid fa-volume-high';
        $('[data-live-unmute]').hidden = !blocked;
    }

    function bindSound() {
        const volume = $('[data-live-volume]');
        volume.value = String(soundPref.volume);
        volume.addEventListener('input', () => {
            soundPref.volume = Number(volume.value);
            audio.volume = soundPref.volume;
            saveSound();
        });
        $('[data-live-mute]').addEventListener('click', () => setMuted(!audio.muted));
        $('[data-live-unmute]').addEventListener('click', () => setMuted(false));
        soundButtons();
    }

    /* ---------- Teilnehmer: Vollbild ---------- */

    function bindFollow() {
        bindSound();
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

    /* ---------- Reiter, Fragen und Protokoll ---------- */

    /*
     * Ausbilder (Host und Mitverfolgende mit Lehrrecht) bekommen alle Fragen
     * und das Protokoll, ein Teilnehmer nur das Fragefeld und seine eigenen
     * Fragen; was fehlt, rendert der Server gar nicht erst. Zeilen bleiben je
     * Frage stehen und werden nur angepasst, damit eine angefangene Antwort
     * und ihr Fokus jede Abfrage überleben. Alles vom Nutzer nur als textContent.
     */

    // Wie Question::MAX_LENGTH im Paket, gezählt in Zeichen wie mb_strlen().
    const MAX_QUESTION = 500;
    const hhmm = (iso) => new Date(iso).toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
    const chars = (text) => Array.from(text.trim()).length;
    const questionRows = new Map();
    // Teilnehmer: eben gestellte Fragen, bis eine Abfrage sie bringt; Antworten, die er schon gesehen hat.
    const ownPending = new Map();
    const seenAnswers = new Set();
    let ownList = [];
    let ownShown = '';
    let visitsShown = '';
    let questionsKey = '[]';
    let asking = false;

    function make(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    function bindTabs() {
        $$('[data-live-tab]').forEach((b) => b.addEventListener('click', () => {
            $$('[data-live-tab]').forEach((x) => x.setAttribute('aria-selected', x === b ? 'true' : 'false'));
            $$('[data-live-panel]').forEach((panel) => { panel.hidden = panel.dataset.livePanel !== b.dataset.liveTab; });
            if (b.dataset.liveTab === 'slides') buildThumbs();
        }));
    }

    /** @return {boolean} ob eine Frage neu, beantwortet oder erledigt ist; das hält den Takt der Abfrage schnell */
    function showQuestions(s) {
        const list = Array.isArray(s.questions) ? s.questions : [];
        if ($('[data-live-questions]')) staffQuestions(list);
        if ($('[data-live-own]')) ownQuestions(list);
        if ($('[data-live-log]') && Array.isArray(s.visits)) showVisits(s.visits);
        const key = JSON.stringify(list.map((q) => [q.id, !!q.answer, !!q.done]));
        const news = key !== questionsKey;
        questionsKey = key;
        return news;
    }

    /**
     * Ein POST bekam 404: entweder ist die Frage weg oder der ganze Vortrag. Gleich abfragen,
     * statt auf den Takt zu warten; die Antwort zeigt ein Ende auch gleich an.
     * @return {Promise<boolean>} ob der Vortrag vorbei ist
     */
    async function endedNow() {
        // ponytail: kurz warten, bis eine laufende Abfrage durch ist; ein Versprechen je Abfrage wäre die genauere Lösung.
        while (polling && !stopped && !ended) await new Promise((r) => setTimeout(r, 50));
        if (!ended && !stopped) {
            clearTimeout(pollTimer);
            await poll();
        }
        return ended;
    }

    /** Vortrag vorbei: Fragen verschwinden, fragen geht nicht mehr. */
    function questionsEnded() {
        ownPending.clear();
        showQuestions({ questions: [] });
        const ask = $('[data-live-ask]');
        if (!ask) return;
        toggleAsk(false, false);
        ask.disabled = true;
    }

    /* Ausbilder: offene Fragen oben in der Reihenfolge des Eingangs, erledigte darunter. */

    function staffQuestions(list) {
        const open = $('[data-live-questions]');
        const done = $('[data-live-questions-done]');
        const ids = new Set(list.map((q) => q.id));
        questionRows.forEach((row, id) => { if (!ids.has(id)) { row.li.remove(); questionRows.delete(id); } });
        list.forEach((q) => {
            let row = questionRows.get(q.id);
            if (!row) {
                row = questionRow(q);
                questionRows.set(q.id, row);
                // Neue Fragen haben die höchste ID, sie gehören ans Ende der offenen.
                open.append(row.li);
            }
            updateRow(row, q);
        });
        countQuestions();
    }

    function countQuestions() {
        let open = 0;
        questionRows.forEach((row) => { if (!row.done) open++; });
        const badge = $('[data-live-question-count]');
        badge.textContent = String(open);
        badge.hidden = open === 0;
        const empty = $('[data-live-no-questions]');
        empty.textContent = questionRows.size ? 'Keine offenen Fragen.' : 'Noch keine Fragen.';
        empty.hidden = open > 0;
        $('[data-live-done-count]').textContent = String(questionRows.size - open);
        $('[data-live-questions-done-block]').hidden = questionRows.size === open;
    }

    function questionRow(q) {
        const row = { id: q.id, answer: null, done: false, note: '', shownNote: '' };
        row.li = make('li', 'tr-live__question');
        const head = make('div', 'tr-live__question-head');
        row.tag = make('span', 'tr-live__tag');
        head.append(make('strong', '', q.name), make('time', '', hhmm(q.asked_at)), row.tag);
        row.answerBox = make('div', 'tr-live__answer');
        row.answerText = make('p', '');
        row.answerBox.append(make('small', '', 'Antwort'), row.answerText);

        row.form = make('form', 'tr-live__reply');
        // Das Feld steckt im Label: kein ID-Zähler, der mit einem zweiten Fenster desselben Vortrags kollidiert.
        row.label = make('label', 'ignis-field');
        row.field = make('textarea', 'ignis-input');
        row.field.rows = 2;
        row.field.placeholder = 'Nur ' + q.name + ' sieht sie';
        row.label.append(make('span', 'ignis-field__label', 'Antwort an ' + q.name), row.field);
        row.send = make('button', 'ignis-btn ignis-btn--primary ignis-btn--sm', 'Senden');
        row.send.type = 'submit';
        row.doneButton = make('button', 'ignis-btn ignis-btn--secondary ignis-btn--sm');
        row.doneButton.type = 'button';
        const check = make('i', 'fa-solid fa-check');
        check.setAttribute('aria-hidden', 'true');
        row.doneButton.append(check, ' Beantwortet');
        const actions = make('div', 'tr-live__reply-row');
        actions.append(row.doneButton, row.send);
        row.msg = make('p', 'tr-live__msg');
        row.msg.setAttribute('role', 'status');
        row.form.append(row.label, actions, row.msg);
        row.form.addEventListener('submit', (ev) => { ev.preventDefault(); sendAnswer(row); });
        row.doneButton.addEventListener('click', () => markDone(row));

        row.li.append(head, make('p', 'tr-live__question-text', q.body), row.answerBox, row.form);
        return row;
    }

    /**
     * Antwort und Erledigt kehren nie zurück; eine ältere Abfrage darf sie nicht wieder öffnen.
     * Erledigt oder beantwortet jemand anderes die Frage, während hier eine Antwort entsteht,
     * bleibt das Feld samt Text und Fokus stehen, mit Hinweis; verwerfen oder trotzdem senden geht.
     * `own`: die Änderung kam von hier, dann gibt es nichts zu bewahren.
     */
    function updateRow(row, q) {
        const before = { answer: row.answer, done: row.done };
        if (q.answer) row.answer = q.answer;
        if (q.done) row.done = true;
        const keep = !q.own && (row.field.value.trim() !== '' || document.activeElement === row.field);
        if (keep && row.done && !before.done) row.note = 'Schon von jemand anderem erledigt.';
        else if (keep && row.answer && !before.answer) row.note = 'Schon von jemand anderem beantwortet.';
        else if (!keep) row.note = '';
        // Nur den eigenen Hinweis setzen oder wegnehmen, eine Fehlermeldung bleibt stehen.
        if (row.note !== row.shownNote) {
            if (row.note || row.msg.textContent === row.shownNote) rowMsg(row, row.note);
            row.shownNote = row.note;
        }
        // Verschieben nähme dem Feld den Fokus; erst wenn nichts mehr getippt wird.
        if (row.done && !keep && row.li.parentNode !== $('[data-live-questions-done]')) $('[data-live-questions-done]').append(row.li);
        row.li.classList.toggle('is-done', row.done);
        row.tag.textContent = row.done ? 'Erledigt' : row.answer ? 'Schriftlich beantwortet' : '';
        row.tag.hidden = !row.tag.textContent;
        row.answerText.textContent = row.answer || '';
        row.answerBox.hidden = !row.answer;
        // Nach einer schriftlichen Antwort bleibt nur noch abhaken.
        row.label.hidden = !!row.answer && !keep;
        row.send.hidden = !!row.answer && !keep;
        row.doneButton.hidden = row.done;
        row.form.hidden = row.done && !keep;
    }

    /** Ein deaktivierter Knopf gibt den Fokus an die Seite ab; dann zurück in die Frage, sonst greifen Escape und Tab ins Leere. */
    function refocus(el) {
        if (!document.activeElement || document.activeElement === document.body) el.focus();
    }

    function rowMsg(row, text) {
        row.msg.textContent = text;
    }

    async function sendAnswer(row) {
        const text = row.field.value.trim();
        if (row.send.disabled || ended) return;
        if (!text) { rowMsg(row, 'Schreib zuerst eine Antwort.'); row.field.focus(); return; }
        if (chars(text) > MAX_QUESTION) { rowMsg(row, 'Die Antwort ist zu lang: höchstens 500 Zeichen.'); return; }
        row.send.disabled = true;
        rowMsg(row, '');
        try {
            const { status } = await post('/questions/' + row.id + '/answer', { answer: text });
            if (status === 200) {
                row.field.value = '';
                rowMsg(row, '');
                updateRow(row, { answer: text, own: true });
                // Das Feld ist weg, der Fokus geht zum Abhaken derselben Frage, bei einer erledigten zum Reiter.
                (row.done ? $('[data-live-tab="questions"]') : row.doneButton).focus();
                countQuestions();
            } else if (status === 409 || (status === 404 && await endedNow())) {
                showEnded();
            } else {
                // Der Text bleibt im Feld, nochmal senden geht.
                rowMsg(row, status === 422 ? 'Die Antwort ist zu lang: höchstens 500 Zeichen.' : status === 404 ? 'Diese Frage gibt es nicht mehr.' : 'Senden ging nicht. Bitte noch einmal.');
            }
        } catch (error) {
            rowMsg(row, 'Keine Verbindung. Bitte noch einmal.');
        } finally {
            row.send.disabled = false;
            refocus(row.field);
        }
    }

    async function markDone(row) {
        if (row.doneButton.disabled || ended) return;
        row.doneButton.disabled = true;
        rowMsg(row, '');
        try {
            const { status } = await post('/questions/' + row.id + '/done', {});
            if (status === 200) {
                const tab = $('[data-live-tab="questions"]');
                updateRow(row, { done: true, own: true });
                countQuestions();
                if (tab) tab.focus();
            } else if (status === 409 || (status === 404 && await endedNow())) {
                showEnded();
            } else {
                rowMsg(row, status === 404 ? 'Diese Frage gibt es nicht mehr.' : 'Abhaken ging nicht. Bitte noch einmal.');
            }
        } catch (error) {
            rowMsg(row, 'Keine Verbindung. Bitte noch einmal.');
        } finally {
            row.doneButton.disabled = false;
            refocus(row.doneButton);
        }
    }

    /** Protokoll: jeder Besuch eine Zeile, die neueste oben, damit sie ohne Scrollen zu sehen ist. */
    function showVisits(list) {
        const key = JSON.stringify(list);
        if (key === visitsShown) return;
        visitsShown = key;
        $('[data-live-log]').replaceChildren(...list.slice().reverse().map((v) => {
            const li = make('li', v.left_at ? '' : 'is-here');
            const name = make('span', 'tr-live__name', v.name);
            name.append(make('small', '', 'gekommen ' + hhmm(v.joined_at) + ' · ' + (v.left_at ? 'gegangen ' + hhmm(v.left_at) : 'noch da')));
            li.append(name);
            return li;
        }));
        $('[data-live-no-visits]').hidden = list.length > 0;
    }

    /* Teilnehmer: Fragefeld und eigene Fragen. */

    function ownQuestions(list) {
        list.forEach((q) => ownPending.delete(q.id));
        ownList = list;
        renderOwn();
    }

    function renderOwn() {
        const all = ownList.concat(Array.from(ownPending.values()));
        const panelOpen = !$('[data-live-ask-panel]').hidden;
        let fresh = 0;
        all.forEach((q) => {
            if (!q.answer || seenAnswers.has(q.id)) return;
            if (panelOpen) seenAnswers.add(q.id);
            else fresh++;
        });
        const news = $('[data-live-ask-news]');
        news.textContent = String(fresh);
        news.hidden = fresh === 0;
        const key = JSON.stringify(all);
        if (key === ownShown) return;
        ownShown = key;
        $('[data-live-own]').replaceChildren(...all.slice().reverse().map(ownItem));
        $('[data-live-own-block]').hidden = all.length === 0;
    }

    function ownItem(q) {
        const li = make('li', 'tr-live__question');
        const head = make('div', 'tr-live__question-head');
        head.append(make('time', '', hhmm(q.asked_at)));
        if (q.answer || q.done) head.append(make('span', 'tr-live__tag', 'Beantwortet'));
        li.append(head, make('p', 'tr-live__question-text', q.body));
        if (q.answer) {
            const box = make('div', 'tr-live__answer');
            box.append(make('small', '', 'Antwort'), make('p', '', q.answer));
            li.append(box);
        }
        return li;
    }

    function toggleAsk(open, restoreFocus) {
        const panel = $('[data-live-ask-panel]');
        const button = $('[data-live-ask]');
        if (panel.hidden === !open) return;
        panel.hidden = !open;
        button.setAttribute('aria-expanded', String(open));
        if (open) {
            renderOwn();
            $('[data-live-ask-text]').focus();
        } else if (restoreFocus) {
            button.focus();
        }
    }

    function askMsg(text, ok) {
        const msg = $('[data-live-ask-msg]');
        msg.textContent = text;
        msg.classList.toggle('is-ok', !!ok);
    }

    function countAsk() {
        const n = chars($('[data-live-ask-text]').value);
        const counter = $('[data-live-ask-count]');
        counter.textContent = n + ' / ' + MAX_QUESTION;
        counter.classList.toggle('is-over', n > MAX_QUESTION);
    }

    async function ask() {
        const field = $('[data-live-ask-text]');
        const send = $('[data-live-ask-send]');
        const text = field.value.trim();
        if (asking || ended) return;
        if (!text) { askMsg('Schreib zuerst deine Frage.'); field.focus(); return; }
        if (chars(text) > MAX_QUESTION) { askMsg('Die Frage ist zu lang: höchstens 500 Zeichen. Kürze sie bitte.'); field.focus(); return; }
        asking = true;
        send.disabled = true;
        askMsg('');
        try {
            const { status, data } = await post('/questions', { body: text });
            if (status === 200) {
                field.value = '';
                countAsk();
                askMsg('Gesendet. Die Antwort erscheint hier.', true);
                if (typeof data.id === 'number') ownPending.set(data.id, { id: data.id, body: text, asked_at: new Date().toISOString(), answer: null, done: false });
                renderOwn();
            } else if (status === 409 || (status === 404 && await endedNow())) {
                showEnded();
            } else {
                // Der Text bleibt im Feld.
                askMsg(status === 429 ? 'Du hast gerade viele Fragen gestellt. Warte bitte eine Minute.'
                    : status === 422 ? 'Die Frage ist leer oder zu lang: höchstens 500 Zeichen.'
                        : 'Senden ging nicht. Versuch es bitte noch einmal.');
            }
        } catch (error) {
            askMsg('Keine Verbindung. Versuch es bitte noch einmal.');
        } finally {
            asking = false;
            send.disabled = false;
            refocus(field);
        }
    }

    function bindQuestions() {
        const button = $('[data-live-ask]');
        if (!button) return;
        const panel = $('[data-live-ask-panel]');
        button.addEventListener('click', () => toggleAsk(panel.hidden, true));
        $('[data-live-ask-text]').addEventListener('input', () => { countAsk(); askMsg(''); });
        $('[data-live-ask-form]').addEventListener('submit', (ev) => { ev.preventDefault(); ask(); });
        // Am Dokument: Escape soll auch schließen, wenn der Fokus gerade nirgends im Vortrag steht.
        // Nicht, wenn ein Dialog das Escape schon genommen hat oder der Fokus in einem anderen Fenster des Desks steht.
        document.addEventListener('keydown', (ev) => {
            if (ev.key !== 'Escape' || ev.defaultPrevented || panel.hidden) return;
            const at = document.activeElement;
            if (at && at !== document.body && !root.contains(at)) return;
            toggleAsk(false, true);
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
        // Auf einen anderen Monitor gezogen: gleiche Größe, aber andere Pixeldichte.
        if ((window.devicePixelRatio || 1) !== dpr) { dpr = window.devicePixelRatio || 1; fitSlide(); }
    }

    /* ---------- Zuschauer: Laser glätten ---------- */

    // Gezeigter Laserpunkt; st.laser ist nur das Ziel, zu dem er je Bild ein Stück aufholt.
    let glide = null;

    function glideLaser() {
        const l = st.laser;
        if (!l) {
            if (glide) { glide = null; dirty = true; }
            return;
        }
        // Taucht der Punkt neu auf, springt er hin, statt aus einer alten Ecke herüberzufliegen.
        if (!glide) { glide = [l[0], l[1]]; dirty = true; return; }
        const dx = l[0] - glide[0];
        const dy = l[1] - glide[1];
        if (!dx && !dy) return;
        glide = Math.abs(dx) + Math.abs(dy) < 0.0005 ? [l[0], l[1]] : [glide[0] + dx * GLIDE, glide[1] + dy * GLIDE];
        dirty = true;
    }

    function loop() {
        if (!root.isConnected) return stop();
        const now = performance.now();
        // Vor der Abfrage: Zurückgehaltenes kommt vor allem, was sie danach bringt.
        resetClock = now;
        if (!host) flushResets(false);
        while (replay.length && replay[0].due <= now) polled(replay.shift().e);
        if (!host) {
            if (heldLaser && now - liveLaser >= ACTIVE_WINDOW) { apply(heldLaser); heldLaser = null; }
            heldPoints.forEach((e, id) => { const l = liveStrokes.get(id); if (!l || !recent(l.at)) { heldPoints.delete(id); apply(e); } });
            heldViews.forEach((e, page) => { if (roomView[page] === undefined || !recent(roomView[page])) { heldViews.delete(page); apply(e); } });
            glideLaser();
        }
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
    bindTabs();
    bindQuestions();
    if (host) bindHost();
    else bindFollow();
    // Wurzel und Desk-Fenster bestimmen die Höhe, der freie Platz die Folie, die Folie Zeichenfläche und PDF.
    const scroller = root.closest('[data-ignis-window-body]');
    if (scroller) observe(scroller, layout);
    else window.addEventListener('resize', layout, { signal });
    observe(frame, fitSlide);
    observe(slide, place);
    observe(screenBox, fitScreenInk);
    // Das Videobild kennt seine Größe erst nach den Metadaten, und ein geteiltes Fenster kann sie ändern.
    ['loadedmetadata', 'resize', 'emptied'].forEach((type) => video.addEventListener(type, fitScreenInk));
    document.addEventListener('fullscreenchange', layout, { signal });
    window.addEventListener('pagehide', leaveRoom, { signal });
    frame.dataset.mode = shown();
    layout();
    fitSlide();
    tick();
    timers.push(setInterval(tick, 1000));
    frameId = requestAnimationFrame(loop);
    if (hasSlides) openPdf();
    poll();
})();
