<style>
    /* ASU-Komponenten im Brand-Style (Zifferblatt, Zeiger, Progressbar).
       `.asu-progress` ist `position:relative`, damit der %-Text als absoluter
       Overlay unabhängig vom Füllstand zentriert bleibt. */
    .asu-clock {
        width: 190px;
        height: 190px;
        margin-inline: auto;
        position: relative;
        filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.35));
    }

    .asu-clock svg {
        width: 100%;
        height: 100%;
        display: block;
    }

    .asu-clock-center {
        position: absolute;
        inset: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        pointer-events: none;
        gap: 0.15rem;
    }

    .asu-clock-time {
        font-size: 1.9rem;
        font-weight: 700;
        font-family: 'Inconsolata', 'JetBrains Mono', Consolas, monospace;
        font-variant-numeric: tabular-nums;
        letter-spacing: 0.04em;
        color: var(--main-color);
        line-height: 1;
    }

    .asu-clock-label {
        font-size: 0.65rem;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: rgba(255, 255, 255, 0.45);
        font-weight: 500;
    }

    /* Warning/Danger-States (triggert aus JS via classList) */
    .asu-clock-time.text-warning { color: #ffc107; }
    .asu-clock-time.text-danger  { color: #ef4444; }

    /* Progressbar */
    .asu-progress {
        background-color: rgba(255, 255, 255, 0.08);
        border-radius: 4px;
        overflow: hidden;
    }

    .asu-progress-bar {
        background-color: var(--main-color);
        transition: background-color 0.3s, width 0.3s;
    }

    .asu-progress-bar.asu-warning { background-color: #ffc107; }
    .asu-progress-bar.asu-danger  { background-color: #ef4444; }
</style>

<!-- 3 Trupps nebeneinander -->
<div class="grid grid-cols-1 gap-3 lg:grid-cols-3">
    <!-- Trupp 1 -->
    <div>
        <div class="ignis-card bg-[rgba(0,0,0,0.3)] h-full">
            <div class="ignis-card__header flex items-center justify-between">
                <h5 class="mb-0">1. Trupp</h5>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="ignis-btn ignis-btn--success" onclick="startTrupp(1)">
                        <i class="fa-solid fa-play"></i>
                    </button>
                    <button type="button" class="ignis-btn ignis-btn--danger" onclick="stopTrupp(1)">
                        <i class="fa-solid fa-stop"></i>
                    </button>
                </div>
            </div>
            <div class="ignis-card__body">
                <!-- Eieruhr -->
                <div class="text-center mb-3">
                    <div class="asu-clock">
                        <svg viewBox="0 0 180 180">
                            <!-- Ziffernblatt: dunkler inset-Look mit feinem inneren Highlight -->
                            <circle cx="90" cy="90" r="85" fill="#17161c" stroke="rgba(255,255,255,0.04)" stroke-width="1" />
                            <circle cx="90" cy="90" r="84" fill="none" stroke="rgba(0,0,0,0.4)" stroke-width="1" />
                            <circle cx="90" cy="90" r="80" fill="none" stroke="rgba(255,255,255,0.025)" stroke-width="1" />
                            <!-- 12 Stundenmarkierungen -->
                            <line x1="90" y1="10" x2="90" y2="20" stroke="rgba(255,255,255,0.22)" stroke-width="2" />
                            <line x1="155.9" y1="34.1" x2="148.3" y2="41.7" stroke="rgba(255,255,255,0.22)" stroke-width="1.5" />
                            <line x1="170" y1="90" x2="160" y2="90" stroke="rgba(255,255,255,0.22)" stroke-width="2" />
                            <line x1="155.9" y1="145.9" x2="148.3" y2="138.3" stroke="rgba(255,255,255,0.22)" stroke-width="1.5" />
                            <line x1="90" y1="170" x2="90" y2="160" stroke="rgba(255,255,255,0.22)" stroke-width="2" />
                            <line x1="24.1" y1="145.9" x2="31.7" y2="138.3" stroke="rgba(255,255,255,0.22)" stroke-width="1.5" />
                            <line x1="10" y1="90" x2="20" y2="90" stroke="rgba(255,255,255,0.22)" stroke-width="2" />
                            <line x1="24.1" y1="34.1" x2="31.7" y2="41.7" stroke="rgba(255,255,255,0.22)" stroke-width="1.5" />
                            <!-- Progress Circle -->
                            <circle cx="90" cy="90" r="75" fill="none" stroke="rgba(255,255,255,0.07)" stroke-width="6" stroke-linecap="round" transform="rotate(-90 90 90)" />
                            <circle cx="90" cy="90" r="75" fill="none" stroke="var(--main-color)" stroke-width="6" stroke-linecap="round"
                                stroke-dasharray="471.24" stroke-dashoffset="471.24"
                                id="trupp1ProgressCircle" style="transition: stroke-dashoffset 0.3s;" transform="rotate(-90 90 90)" />
                            <!-- Zeiger: nur im Außenring zwischen Text und Progress-Arc.
                                 Inner y=40 → Radius 50 vom Center, lässt die Zeit-Ziffern frei.
                                 Outer y=18 → Radius 72, direkt vor dem Progress-Arc (r=75).
                                 `stroke-linecap="round"` erzeugt abgerundete Kappen. -->
                            <line x1="90" y1="40" x2="90" y2="18" stroke="var(--main-color)" stroke-width="5" stroke-linecap="round" filter="drop-shadow(0 0 4px rgba(var(--main-color-rgb), 0.7))"
                                id="trupp1Hand" style="transition: transform 0.3s; transform-origin: 90px 90px;" />
                        </svg>
                        <div class="asu-clock-center">
                            <div id="trupp1Time" class="asu-clock-time">00:00</div>
                            <small class="asu-clock-label">Einsatzzeit</small>
                        </div>
                    </div>
                    <!-- Progressbar -->
                    <div class="progress asu-progress relative mt-2" style="height: 20px;">
                        <div class="progress-bar asu-progress-bar" id="trupp1ProgressBar" role="progressbar" style="width: 0%; transition: width 0.3s;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                        <span id="trupp1Percent" class="absolute inset-0 flex items-center justify-center text-xs font-bold text-white" style="pointer-events:none;mix-blend-mode:difference;">0%</span>
                    </div>
                </div>

                <!-- Personal -->
                <div class="mb-2">
                    <label class="ignis-field__label text-sm">Truppführer (TF) *</label>
                    <input type="text" class="ignis-input ignis-input--sm" id="trupp1TF" placeholder="Name">
                </div>
                <div class="mb-2">
                    <label class="ignis-field__label text-sm">Truppmann 1 (TM1) *</label>
                    <input type="text" class="ignis-input ignis-input--sm" id="trupp1TM1" placeholder="Name">
                </div>
                <div class="mb-2">
                    <label class="ignis-field__label text-sm">Truppmann 2 (TM2)</label>
                    <input type="text" class="ignis-input ignis-input--sm" id="trupp1TM2" placeholder="Name">
                </div>

                <hr>

                <!-- Einsatzinfo -->
                <div class="mb-2 grid grid-cols-2 gap-2">
                    <div>
                        <label class="ignis-field__label text-sm">Anfangsdruck (bar)</label>
                        <input type="number" class="ignis-input ignis-input--sm" id="trupp1StartPressure" placeholder="300" min="0" max="400">
                    </div>
                    <div>
                        <label class="ignis-field__label text-sm">Einsatzbeginn</label>
                        <input type="time" class="ignis-input ignis-input--sm" id="trupp1StartTime">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="ignis-field__label text-sm">Auftrag</label>
                    <input type="text" class="ignis-input ignis-input--sm" id="trupp1Mission" placeholder="z.B. Brandbekämpfung, Personensuche">
                </div>

                <!-- Kontrollen -->
                <div class="mb-2">
                    <label class="ignis-field__label text-sm">1. Kontrolle (10 Min / 1/3)</label>
                    <input type="text" class="ignis-input ignis-input--sm" id="trupp1Check1" placeholder="Druck / Status">
                </div>
                <div class="mb-2">
                    <label class="ignis-field__label text-sm">2. Kontrolle (20 Min / 2/3)</label>
                    <input type="text" class="ignis-input ignis-input--sm" id="trupp1Check2" placeholder="Druck / Status">
                </div>

                <!-- Einsatzende -->
                <div class="mb-2">
                    <label class="ignis-field__label text-sm">Einsatzziel</label>
                    <input type="text" class="ignis-input ignis-input--sm" id="trupp1Objective" placeholder="z.B. 2. OG Zimmer 5">
                </div>
                <div class="mb-2 grid grid-cols-2 gap-2">
                    <div>
                        <label class="ignis-field__label text-sm">Rückzug</label>
                        <input type="time" class="ignis-input ignis-input--sm" id="trupp1Retreat">
                    </div>
                    <div>
                        <label class="ignis-field__label text-sm">Einsatzende</label>
                        <input type="time" class="ignis-input ignis-input--sm" id="trupp1End">
                    </div>
                </div>
                <div class="mb-0">
                    <label class="ignis-field__label text-sm">Bemerkungen</label>
                    <textarea class="ignis-input ignis-input--sm" id="trupp1Remarks" rows="2" placeholder="Zusätzliche Informationen"></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- Trupp 2 -->
    <div>
        <div class="ignis-card bg-[rgba(0,0,0,0.3)] h-full">
            <div class="ignis-card__header flex items-center justify-between">
                <h5 class="mb-0">2. Trupp</h5>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="ignis-btn ignis-btn--success" onclick="startTrupp(2)">
                        <i class="fa-solid fa-play"></i>
                    </button>
                    <button type="button" class="ignis-btn ignis-btn--danger" onclick="stopTrupp(2)">
                        <i class="fa-solid fa-stop"></i>
                    </button>
                </div>
            </div>
            <div class="ignis-card__body">
                <!-- Eieruhr -->
                <div class="text-center mb-3">
                    <div class="asu-clock">
                        <svg viewBox="0 0 180 180">
                            <!-- Ziffernblatt: dunkler inset-Look mit feinem inneren Highlight -->
                            <circle cx="90" cy="90" r="85" fill="#17161c" stroke="rgba(255,255,255,0.04)" stroke-width="1" />
                            <circle cx="90" cy="90" r="84" fill="none" stroke="rgba(0,0,0,0.4)" stroke-width="1" />
                            <circle cx="90" cy="90" r="80" fill="none" stroke="rgba(255,255,255,0.025)" stroke-width="1" />
                            <!-- 12 Stundenmarkierungen -->
                            <line x1="90" y1="10" x2="90" y2="20" stroke="rgba(255,255,255,0.22)" stroke-width="2" />
                            <line x1="155.9" y1="34.1" x2="148.3" y2="41.7" stroke="rgba(255,255,255,0.22)" stroke-width="1.5" />
                            <line x1="170" y1="90" x2="160" y2="90" stroke="rgba(255,255,255,0.22)" stroke-width="2" />
                            <line x1="155.9" y1="145.9" x2="148.3" y2="138.3" stroke="rgba(255,255,255,0.22)" stroke-width="1.5" />
                            <line x1="90" y1="170" x2="90" y2="160" stroke="rgba(255,255,255,0.22)" stroke-width="2" />
                            <line x1="24.1" y1="145.9" x2="31.7" y2="138.3" stroke="rgba(255,255,255,0.22)" stroke-width="1.5" />
                            <line x1="10" y1="90" x2="20" y2="90" stroke="rgba(255,255,255,0.22)" stroke-width="2" />
                            <line x1="24.1" y1="34.1" x2="31.7" y2="41.7" stroke="rgba(255,255,255,0.22)" stroke-width="1.5" />
                            <!-- Progress Circle -->
                            <circle cx="90" cy="90" r="75" fill="none" stroke="rgba(255,255,255,0.07)" stroke-width="6" stroke-linecap="round" transform="rotate(-90 90 90)" />
                            <circle cx="90" cy="90" r="75" fill="none" stroke="var(--main-color)" stroke-width="6" stroke-linecap="round"
                                stroke-dasharray="471.24" stroke-dashoffset="471.24"
                                id="trupp2ProgressCircle" style="transition: stroke-dashoffset 0.3s;" transform="rotate(-90 90 90)" />
                            <!-- Zeiger: nur im Außenring zwischen Text und Progress-Arc.
                                 Inner y=40 → Radius 50 vom Center, lässt die Zeit-Ziffern frei.
                                 Outer y=18 → Radius 72, direkt vor dem Progress-Arc (r=75).
                                 `stroke-linecap="round"` erzeugt abgerundete Kappen. -->
                            <line x1="90" y1="40" x2="90" y2="18" stroke="var(--main-color)" stroke-width="5" stroke-linecap="round" filter="drop-shadow(0 0 4px rgba(var(--main-color-rgb), 0.7))"
                                id="trupp2Hand" style="transition: transform 0.3s; transform-origin: 90px 90px;" />
                        </svg>
                        <div class="asu-clock-center">
                            <div id="trupp2Time" class="asu-clock-time">00:00</div>
                            <small class="asu-clock-label">Einsatzzeit</small>
                        </div>
                    </div>
                    <!-- Progressbar -->
                    <div class="progress asu-progress relative mt-2" style="height: 20px;">
                        <div class="progress-bar asu-progress-bar" id="trupp2ProgressBar" role="progressbar" style="width: 0%; transition: width 0.3s;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                        <span id="trupp2Percent" class="absolute inset-0 flex items-center justify-center text-xs font-bold text-white" style="pointer-events:none;mix-blend-mode:difference;">0%</span>
                    </div>
                </div>

                <!-- Personal -->
                <div class="mb-2">
                    <label class="ignis-field__label text-sm">Truppführer (TF) *</label>
                    <input type="text" class="ignis-input ignis-input--sm" id="trupp2TF" placeholder="Name">
                </div>
                <div class="mb-2">
                    <label class="ignis-field__label text-sm">Truppmann 1 (TM1) *</label>
                    <input type="text" class="ignis-input ignis-input--sm" id="trupp2TM1" placeholder="Name">
                </div>
                <div class="mb-2">
                    <label class="ignis-field__label text-sm">Truppmann 2 (TM2)</label>
                    <input type="text" class="ignis-input ignis-input--sm" id="trupp2TM2" placeholder="Name">
                </div>

                <hr>

                <!-- Einsatzinfo -->
                <div class="mb-2 grid grid-cols-2 gap-2">
                    <div>
                        <label class="ignis-field__label text-sm">Anfangsdruck (bar)</label>
                        <input type="number" class="ignis-input ignis-input--sm" id="trupp2StartPressure" placeholder="300" min="0" max="400">
                    </div>
                    <div>
                        <label class="ignis-field__label text-sm">Einsatzbeginn</label>
                        <input type="time" class="ignis-input ignis-input--sm" id="trupp2StartTime">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="ignis-field__label text-sm">Auftrag</label>
                    <input type="text" class="ignis-input ignis-input--sm" id="trupp2Mission" placeholder="z.B. Brandbekämpfung, Personensuche">
                </div>

                <!-- Kontrollen -->
                <div class="mb-2">
                    <label class="ignis-field__label text-sm">1. Kontrolle (10 Min / 1/3)</label>
                    <input type="text" class="ignis-input ignis-input--sm" id="trupp2Check1" placeholder="Druck / Status">
                </div>
                <div class="mb-2">
                    <label class="ignis-field__label text-sm">2. Kontrolle (20 Min / 2/3)</label>
                    <input type="text" class="ignis-input ignis-input--sm" id="trupp2Check2" placeholder="Druck / Status">
                </div>

                <!-- Einsatzende -->
                <div class="mb-2">
                    <label class="ignis-field__label text-sm">Einsatzziel</label>
                    <input type="text" class="ignis-input ignis-input--sm" id="trupp2Objective" placeholder="z.B. 2. OG Zimmer 5">
                </div>
                <div class="mb-2 grid grid-cols-2 gap-2">
                    <div>
                        <label class="ignis-field__label text-sm">Rückzug</label>
                        <input type="time" class="ignis-input ignis-input--sm" id="trupp2Retreat">
                    </div>
                    <div>
                        <label class="ignis-field__label text-sm">Einsatzende</label>
                        <input type="time" class="ignis-input ignis-input--sm" id="trupp2End">
                    </div>
                </div>
                <div class="mb-0">
                    <label class="ignis-field__label text-sm">Bemerkungen</label>
                    <textarea class="ignis-input ignis-input--sm" id="trupp2Remarks" rows="2" placeholder="Zusätzliche Informationen"></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- Trupp 3 (Sicherheitstrupp) -->
    <div>
        <div class="ignis-card bg-[rgba(0,0,0,0.3)] h-full">
            <div class="ignis-card__header flex items-center justify-between">
                <h5 class="mb-0">Sicherheitstrupp</h5>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="ignis-btn ignis-btn--success" onclick="startTrupp(3)">
                        <i class="fa-solid fa-play"></i>
                    </button>
                    <button type="button" class="ignis-btn ignis-btn--danger" onclick="stopTrupp(3)">
                        <i class="fa-solid fa-stop"></i>
                    </button>
                </div>
            </div>
            <div class="ignis-card__body">
                <!-- Eieruhr -->
                <div class="text-center mb-3">
                    <div class="asu-clock">
                        <svg viewBox="0 0 180 180">
                            <!-- Ziffernblatt: dunkler inset-Look mit feinem inneren Highlight -->
                            <circle cx="90" cy="90" r="85" fill="#17161c" stroke="rgba(255,255,255,0.04)" stroke-width="1" />
                            <circle cx="90" cy="90" r="84" fill="none" stroke="rgba(0,0,0,0.4)" stroke-width="1" />
                            <circle cx="90" cy="90" r="80" fill="none" stroke="rgba(255,255,255,0.025)" stroke-width="1" />
                            <!-- 12 Stundenmarkierungen -->
                            <line x1="90" y1="10" x2="90" y2="20" stroke="rgba(255,255,255,0.22)" stroke-width="2" />
                            <line x1="155.9" y1="34.1" x2="148.3" y2="41.7" stroke="rgba(255,255,255,0.22)" stroke-width="1.5" />
                            <line x1="170" y1="90" x2="160" y2="90" stroke="rgba(255,255,255,0.22)" stroke-width="2" />
                            <line x1="155.9" y1="145.9" x2="148.3" y2="138.3" stroke="rgba(255,255,255,0.22)" stroke-width="1.5" />
                            <line x1="90" y1="170" x2="90" y2="160" stroke="rgba(255,255,255,0.22)" stroke-width="2" />
                            <line x1="24.1" y1="145.9" x2="31.7" y2="138.3" stroke="rgba(255,255,255,0.22)" stroke-width="1.5" />
                            <line x1="10" y1="90" x2="20" y2="90" stroke="rgba(255,255,255,0.22)" stroke-width="2" />
                            <line x1="24.1" y1="34.1" x2="31.7" y2="41.7" stroke="rgba(255,255,255,0.22)" stroke-width="1.5" />
                            <!-- Progress Circle -->
                            <circle cx="90" cy="90" r="75" fill="none" stroke="rgba(255,255,255,0.07)" stroke-width="6" stroke-linecap="round" transform="rotate(-90 90 90)" />
                            <circle cx="90" cy="90" r="75" fill="none" stroke="var(--main-color)" stroke-width="6" stroke-linecap="round"
                                stroke-dasharray="471.24" stroke-dashoffset="471.24"
                                id="trupp3ProgressCircle" style="transition: stroke-dashoffset 0.3s;" transform="rotate(-90 90 90)" />
                            <!-- Zeiger: nur im Außenring zwischen Text und Progress-Arc.
                                 Inner y=40 → Radius 50 vom Center, lässt die Zeit-Ziffern frei.
                                 Outer y=18 → Radius 72, direkt vor dem Progress-Arc (r=75).
                                 `stroke-linecap="round"` erzeugt abgerundete Kappen. -->
                            <line x1="90" y1="40" x2="90" y2="18" stroke="var(--main-color)" stroke-width="5" stroke-linecap="round" filter="drop-shadow(0 0 4px rgba(var(--main-color-rgb), 0.7))"
                                id="trupp3Hand" style="transition: transform 0.3s; transform-origin: 90px 90px;" />
                        </svg>
                        <div class="asu-clock-center">
                            <div id="trupp3Time" class="asu-clock-time">00:00</div>
                            <small class="asu-clock-label">Einsatzzeit</small>
                        </div>
                    </div>
                    <!-- Progressbar -->
                    <div class="progress asu-progress relative mt-2" style="height: 20px;">
                        <div class="progress-bar asu-progress-bar" id="trupp3ProgressBar" role="progressbar" style="width: 0%; transition: width 0.3s;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                        <span id="trupp3Percent" class="absolute inset-0 flex items-center justify-center text-xs font-bold text-white" style="pointer-events:none;mix-blend-mode:difference;">0%</span>
                    </div>
                </div>

                <!-- Personal -->
                <div class="mb-2">
                    <label class="ignis-field__label text-sm">Truppführer (TF) *</label>
                    <input type="text" class="ignis-input ignis-input--sm" id="trupp3TF" placeholder="Name">
                </div>
                <div class="mb-2">
                    <label class="ignis-field__label text-sm">Truppmann 1 (TM1) *</label>
                    <input type="text" class="ignis-input ignis-input--sm" id="trupp3TM1" placeholder="Name">
                </div>
                <div class="mb-2">
                    <label class="ignis-field__label text-sm">Truppmann 2 (TM2)</label>
                    <input type="text" class="ignis-input ignis-input--sm" id="trupp3TM2" placeholder="Name">
                </div>

                <hr>

                <!-- Einsatzinfo -->
                <div class="mb-2 grid grid-cols-2 gap-2">
                    <div>
                        <label class="ignis-field__label text-sm">Anfangsdruck (bar)</label>
                        <input type="number" class="ignis-input ignis-input--sm" id="trupp3StartPressure" placeholder="300" min="0" max="400">
                    </div>
                    <div>
                        <label class="ignis-field__label text-sm">Einsatzbeginn</label>
                        <input type="time" class="ignis-input ignis-input--sm" id="trupp3StartTime">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="ignis-field__label text-sm">Auftrag</label>
                    <input type="text" class="ignis-input ignis-input--sm" id="trupp3Mission" placeholder="z.B. Brandbekämpfung, Personensuche">
                </div>

                <!-- Kontrollen -->
                <div class="mb-2">
                    <label class="ignis-field__label text-sm">1. Kontrolle (10 Min / 1/3)</label>
                    <input type="text" class="ignis-input ignis-input--sm" id="trupp3Check1" placeholder="Druck / Status">
                </div>
                <div class="mb-2">
                    <label class="ignis-field__label text-sm">2. Kontrolle (20 Min / 2/3)</label>
                    <input type="text" class="ignis-input ignis-input--sm" id="trupp3Check2" placeholder="Druck / Status">
                </div>

                <!-- Einsatzende -->
                <div class="mb-2">
                    <label class="ignis-field__label text-sm">Einsatzziel</label>
                    <input type="text" class="ignis-input ignis-input--sm" id="trupp3Objective" placeholder="z.B. 2. OG Zimmer 5">
                </div>
                <div class="mb-2 grid grid-cols-2 gap-2">
                    <div>
                        <label class="ignis-field__label text-sm">Rückzug</label>
                        <input type="time" class="ignis-input ignis-input--sm" id="trupp3Retreat">
                    </div>
                    <div>
                        <label class="ignis-field__label text-sm">Einsatzende</label>
                        <input type="time" class="ignis-input ignis-input--sm" id="trupp3End">
                    </div>
                </div>
                <div class="mb-0">
                    <label class="ignis-field__label text-sm">Bemerkungen</label>
                    <textarea class="ignis-input ignis-input--sm" id="trupp3Remarks" rows="2" placeholder="Zusätzliche Informationen"></textarea>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Action Buttons -->
<div class="flex justify-between mt-3">
    <button type="button" class="ignis-btn" onclick="clearAll()">
        <i class="fa-solid fa-eraser mr-1"></i>Alle Felder leeren
    </button>
    <button type="button" class="ignis-btn ignis-btn--primary" onclick="sendData()">
        <i class="fa-solid fa-save mr-1"></i>Protokoll speichern
    </button>
</div>