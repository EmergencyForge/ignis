<!-- 3 Trupps nebeneinander -->
<div class="row g-3">
    <!-- Trupp 1 -->
    <div class="col-lg-4">
        <div class="card bg-dark h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">1. Trupp</h5>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-success" onclick="startTrupp(1)">
                        <i class="fa-solid fa-play"></i>
                    </button>
                    <button type="button" class="btn btn-danger" onclick="stopTrupp(1)">
                        <i class="fa-solid fa-stop"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <!-- Eieruhr -->
                <div class="text-center mb-3">
                    <div class="asu-clock mx-auto" style="width: 180px; height: 180px; position: relative;">
                        <svg width="180" height="180" viewBox="0 0 180 180">
                            <!-- Ziffernblatt mit Markierungen -->
                            <circle cx="90" cy="90" r="85" fill="#25242c" stroke="#3d3a44" stroke-width="2" />
                            <!-- 12 Stundenmarkierungen -->
                            <line x1="90" y1="10" x2="90" y2="20" stroke="#666" stroke-width="2" />
                            <line x1="155.9" y1="34.1" x2="148.3" y2="41.7" stroke="#666" stroke-width="1.5" />
                            <line x1="170" y1="90" x2="160" y2="90" stroke="#666" stroke-width="2" />
                            <line x1="155.9" y1="145.9" x2="148.3" y2="138.3" stroke="#666" stroke-width="1.5" />
                            <line x1="90" y1="170" x2="90" y2="160" stroke="#666" stroke-width="2" />
                            <line x1="24.1" y1="145.9" x2="31.7" y2="138.3" stroke="#666" stroke-width="1.5" />
                            <line x1="10" y1="90" x2="20" y2="90" stroke="#666" stroke-width="2" />
                            <line x1="24.1" y1="34.1" x2="31.7" y2="41.7" stroke="#666" stroke-width="1.5" />
                            <!-- Progress Circle -->
                            <circle cx="90" cy="90" r="75" fill="none" stroke="#3d3a44" stroke-width="6" transform="rotate(-90 90 90)" />
                            <circle cx="90" cy="90" r="75" fill="none" stroke="#d10000" stroke-width="6"
                                stroke-dasharray="471.24" stroke-dashoffset="471.24"
                                id="trupp1ProgressCircle" style="transition: stroke-dashoffset 0.3s;" transform="rotate(-90 90 90)" />
                            <!-- Zeiger -->
                            <line x1="90" y1="90" x2="90" y2="25" stroke="#d10000" stroke-width="3" stroke-linecap="round"
                                id="trupp1Hand" style="transition: transform 0.3s; transform-origin: 90px 90px; opacity: 0.4;" />
                            <!-- Mittelpunkt -->
                            <circle cx="90" cy="90" r="5" fill="#d10000" style="opacity: 0.4;" />
                        </svg>
                        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; pointer-events: none;">
                            <div id="trupp1Time" style="font-size: 1.8rem; font-weight: bold; font-family: monospace; color: #d10000;">00:00</div>
                            <small class="text-muted" style="font-size: 0.75rem;">Einsatzzeit</small>
                        </div>
                    </div>
                    <!-- Progressbar -->
                    <div class="progress mt-2" style="height: 20px;">
                        <div class="progress-bar bg-danger" id="trupp1ProgressBar" role="progressbar" style="width: 0%; transition: width 0.3s;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                            <span id="trupp1Percent" style="font-weight: bold;">0%</span>
                        </div>
                    </div>
                </div>

                <!-- Personal -->
                <div class="mb-2">
                    <label class="form-label small">Truppführer (TF) *</label>
                    <input type="text" class="form-control form-control-sm" id="trupp1TF" placeholder="Name">
                </div>
                <div class="mb-2">
                    <label class="form-label small">Truppmann 1 (TM1) *</label>
                    <input type="text" class="form-control form-control-sm" id="trupp1TM1" placeholder="Name">
                </div>
                <div class="mb-2">
                    <label class="form-label small">Truppmann 2 (TM2)</label>
                    <input type="text" class="form-control form-control-sm" id="trupp1TM2" placeholder="Name">
                </div>

                <hr>

                <!-- Einsatzinfo -->
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small">Anfangsdruck (bar)</label>
                        <input type="number" class="form-control form-control-sm" id="trupp1StartPressure" placeholder="300" min="0" max="400">
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Einsatzbeginn</label>
                        <input type="time" class="form-control form-control-sm" id="trupp1StartTime">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label small">Auftrag</label>
                    <input type="text" class="form-control form-control-sm" id="trupp1Mission" placeholder="z.B. Brandbekämpfung, Personensuche">
                </div>

                <!-- Kontrollen -->
                <div class="mb-2">
                    <label class="form-label small">1. Kontrolle (10 Min / 1/3)</label>
                    <input type="text" class="form-control form-control-sm" id="trupp1Check1" placeholder="Druck / Status">
                </div>
                <div class="mb-2">
                    <label class="form-label small">2. Kontrolle (20 Min / 2/3)</label>
                    <input type="text" class="form-control form-control-sm" id="trupp1Check2" placeholder="Druck / Status">
                </div>

                <!-- Einsatzende -->
                <div class="mb-2">
                    <label class="form-label small">Einsatzziel</label>
                    <input type="text" class="form-control form-control-sm" id="trupp1Objective" placeholder="z.B. 2. OG Zimmer 5">
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small">Rückzug</label>
                        <input type="time" class="form-control form-control-sm" id="trupp1Retreat">
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Einsatzende</label>
                        <input type="time" class="form-control form-control-sm" id="trupp1End">
                    </div>
                </div>
                <div class="mb-0">
                    <label class="form-label small">Bemerkungen</label>
                    <textarea class="form-control form-control-sm" id="trupp1Remarks" rows="2" placeholder="Zusätzliche Informationen"></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- Trupp 2 -->
    <div class="col-lg-4">
        <div class="card bg-dark h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">2. Trupp</h5>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-success" onclick="startTrupp(2)">
                        <i class="fa-solid fa-play"></i>
                    </button>
                    <button type="button" class="btn btn-danger" onclick="stopTrupp(2)">
                        <i class="fa-solid fa-stop"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <!-- Eieruhr -->
                <div class="text-center mb-3">
                    <div class="asu-clock mx-auto" style="width: 180px; height: 180px; position: relative;">
                        <svg width="180" height="180" viewBox="0 0 180 180">
                            <!-- Ziffernblatt mit Markierungen -->
                            <circle cx="90" cy="90" r="85" fill="#25242c" stroke="#3d3a44" stroke-width="2" />
                            <!-- 12 Stundenmarkierungen -->
                            <line x1="90" y1="10" x2="90" y2="20" stroke="#666" stroke-width="2" />
                            <line x1="155.9" y1="34.1" x2="148.3" y2="41.7" stroke="#666" stroke-width="1.5" />
                            <line x1="170" y1="90" x2="160" y2="90" stroke="#666" stroke-width="2" />
                            <line x1="155.9" y1="145.9" x2="148.3" y2="138.3" stroke="#666" stroke-width="1.5" />
                            <line x1="90" y1="170" x2="90" y2="160" stroke="#666" stroke-width="2" />
                            <line x1="24.1" y1="145.9" x2="31.7" y2="138.3" stroke="#666" stroke-width="1.5" />
                            <line x1="10" y1="90" x2="20" y2="90" stroke="#666" stroke-width="2" />
                            <line x1="24.1" y1="34.1" x2="31.7" y2="41.7" stroke="#666" stroke-width="1.5" />
                            <!-- Progress Circle -->
                            <circle cx="90" cy="90" r="75" fill="none" stroke="#3d3a44" stroke-width="6" transform="rotate(-90 90 90)" />
                            <circle cx="90" cy="90" r="75" fill="none" stroke="#d10000" stroke-width="6"
                                stroke-dasharray="471.24" stroke-dashoffset="471.24"
                                id="trupp2ProgressCircle" style="transition: stroke-dashoffset 0.3s;" transform="rotate(-90 90 90)" />
                            <!-- Zeiger -->
                            <line x1="90" y1="90" x2="90" y2="25" stroke="#d10000" stroke-width="3" stroke-linecap="round"
                                id="trupp2Hand" style="transition: transform 0.3s; transform-origin: 90px 90px; opacity: 0.4;" />
                            <!-- Mittelpunkt -->
                            <circle cx="90" cy="90" r="5" fill="#d10000" style="opacity: 0.4;" />
                        </svg>
                        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; pointer-events: none;">
                            <div id="trupp2Time" style="font-size: 1.8rem; font-weight: bold; font-family: monospace; color: #d10000;">00:00</div>
                            <small class="text-muted" style="font-size: 0.75rem;">Einsatzzeit</small>
                        </div>
                    </div>
                    <!-- Progressbar -->
                    <div class="progress mt-2" style="height: 20px;">
                        <div class="progress-bar bg-danger" id="trupp2ProgressBar" role="progressbar" style="width: 0%; transition: width 0.3s;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                            <span id="trupp2Percent" style="font-weight: bold;">0%</span>
                        </div>
                    </div>
                </div>

                <!-- Personal -->
                <div class="mb-2">
                    <label class="form-label small">Truppführer (TF) *</label>
                    <input type="text" class="form-control form-control-sm" id="trupp2TF" placeholder="Name">
                </div>
                <div class="mb-2">
                    <label class="form-label small">Truppmann 1 (TM1) *</label>
                    <input type="text" class="form-control form-control-sm" id="trupp2TM1" placeholder="Name">
                </div>
                <div class="mb-2">
                    <label class="form-label small">Truppmann 2 (TM2)</label>
                    <input type="text" class="form-control form-control-sm" id="trupp2TM2" placeholder="Name">
                </div>

                <hr>

                <!-- Einsatzinfo -->
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small">Anfangsdruck (bar)</label>
                        <input type="number" class="form-control form-control-sm" id="trupp2StartPressure" placeholder="300" min="0" max="400">
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Einsatzbeginn</label>
                        <input type="time" class="form-control form-control-sm" id="trupp2StartTime">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label small">Auftrag</label>
                    <input type="text" class="form-control form-control-sm" id="trupp2Mission" placeholder="z.B. Brandbekämpfung, Personensuche">
                </div>

                <!-- Kontrollen -->
                <div class="mb-2">
                    <label class="form-label small">1. Kontrolle (10 Min / 1/3)</label>
                    <input type="text" class="form-control form-control-sm" id="trupp2Check1" placeholder="Druck / Status">
                </div>
                <div class="mb-2">
                    <label class="form-label small">2. Kontrolle (20 Min / 2/3)</label>
                    <input type="text" class="form-control form-control-sm" id="trupp2Check2" placeholder="Druck / Status">
                </div>

                <!-- Einsatzende -->
                <div class="mb-2">
                    <label class="form-label small">Einsatzziel</label>
                    <input type="text" class="form-control form-control-sm" id="trupp2Objective" placeholder="z.B. 2. OG Zimmer 5">
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small">Rückzug</label>
                        <input type="time" class="form-control form-control-sm" id="trupp2Retreat">
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Einsatzende</label>
                        <input type="time" class="form-control form-control-sm" id="trupp2End">
                    </div>
                </div>
                <div class="mb-0">
                    <label class="form-label small">Bemerkungen</label>
                    <textarea class="form-control form-control-sm" id="trupp2Remarks" rows="2" placeholder="Zusätzliche Informationen"></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- Trupp 3 (Sicherheitstrupp) -->
    <div class="col-lg-4">
        <div class="card bg-dark h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Sicherheitstrupp</h5>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-success" onclick="startTrupp(3)">
                        <i class="fa-solid fa-play"></i>
                    </button>
                    <button type="button" class="btn btn-danger" onclick="stopTrupp(3)">
                        <i class="fa-solid fa-stop"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <!-- Eieruhr -->
                <div class="text-center mb-3">
                    <div class="asu-clock mx-auto" style="width: 180px; height: 180px; position: relative;">
                        <svg width="180" height="180" viewBox="0 0 180 180">
                            <!-- Ziffernblatt mit Markierungen -->
                            <circle cx="90" cy="90" r="85" fill="#25242c" stroke="#3d3a44" stroke-width="2" />
                            <!-- 12 Stundenmarkierungen -->
                            <line x1="90" y1="10" x2="90" y2="20" stroke="#666" stroke-width="2" />
                            <line x1="155.9" y1="34.1" x2="148.3" y2="41.7" stroke="#666" stroke-width="1.5" />
                            <line x1="170" y1="90" x2="160" y2="90" stroke="#666" stroke-width="2" />
                            <line x1="155.9" y1="145.9" x2="148.3" y2="138.3" stroke="#666" stroke-width="1.5" />
                            <line x1="90" y1="170" x2="90" y2="160" stroke="#666" stroke-width="2" />
                            <line x1="24.1" y1="145.9" x2="31.7" y2="138.3" stroke="#666" stroke-width="1.5" />
                            <line x1="10" y1="90" x2="20" y2="90" stroke="#666" stroke-width="2" />
                            <line x1="24.1" y1="34.1" x2="31.7" y2="41.7" stroke="#666" stroke-width="1.5" />
                            <!-- Progress Circle -->
                            <circle cx="90" cy="90" r="75" fill="none" stroke="#3d3a44" stroke-width="6" transform="rotate(-90 90 90)" />
                            <circle cx="90" cy="90" r="75" fill="none" stroke="#d10000" stroke-width="6"
                                stroke-dasharray="471.24" stroke-dashoffset="471.24"
                                id="trupp3ProgressCircle" style="transition: stroke-dashoffset 0.3s;" transform="rotate(-90 90 90)" />
                            <!-- Zeiger -->
                            <line x1="90" y1="90" x2="90" y2="25" stroke="#d10000" stroke-width="3" stroke-linecap="round"
                                id="trupp3Hand" style="transition: transform 0.3s; transform-origin: 90px 90px; opacity: 0.4;" />
                            <!-- Mittelpunkt -->
                            <circle cx="90" cy="90" r="5" fill="#d10000" style="opacity: 0.4;" />
                        </svg>
                        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; pointer-events: none;">
                            <div id="trupp3Time" style="font-size: 1.8rem; font-weight: bold; font-family: monospace; color: #d10000;">00:00</div>
                            <small class="text-muted" style="font-size: 0.75rem;">Einsatzzeit</small>
                        </div>
                    </div>
                    <!-- Progressbar -->
                    <div class="progress mt-2" style="height: 20px;">
                        <div class="progress-bar bg-danger" id="trupp3ProgressBar" role="progressbar" style="width: 0%; transition: width 0.3s;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                            <span id="trupp3Percent" style="font-weight: bold;">0%</span>
                        </div>
                    </div>
                </div>

                <!-- Personal -->
                <div class="mb-2">
                    <label class="form-label small">Truppführer (TF) *</label>
                    <input type="text" class="form-control form-control-sm" id="trupp3TF" placeholder="Name">
                </div>
                <div class="mb-2">
                    <label class="form-label small">Truppmann 1 (TM1) *</label>
                    <input type="text" class="form-control form-control-sm" id="trupp3TM1" placeholder="Name">
                </div>
                <div class="mb-2">
                    <label class="form-label small">Truppmann 2 (TM2)</label>
                    <input type="text" class="form-control form-control-sm" id="trupp3TM2" placeholder="Name">
                </div>

                <hr>

                <!-- Einsatzinfo -->
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small">Anfangsdruck (bar)</label>
                        <input type="number" class="form-control form-control-sm" id="trupp3StartPressure" placeholder="300" min="0" max="400">
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Einsatzbeginn</label>
                        <input type="time" class="form-control form-control-sm" id="trupp3StartTime">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label small">Auftrag</label>
                    <input type="text" class="form-control form-control-sm" id="trupp3Mission" placeholder="z.B. Brandbekämpfung, Personensuche">
                </div>

                <!-- Kontrollen -->
                <div class="mb-2">
                    <label class="form-label small">1. Kontrolle (10 Min / 1/3)</label>
                    <input type="text" class="form-control form-control-sm" id="trupp3Check1" placeholder="Druck / Status">
                </div>
                <div class="mb-2">
                    <label class="form-label small">2. Kontrolle (20 Min / 2/3)</label>
                    <input type="text" class="form-control form-control-sm" id="trupp3Check2" placeholder="Druck / Status">
                </div>

                <!-- Einsatzende -->
                <div class="mb-2">
                    <label class="form-label small">Einsatzziel</label>
                    <input type="text" class="form-control form-control-sm" id="trupp3Objective" placeholder="z.B. 2. OG Zimmer 5">
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small">Rückzug</label>
                        <input type="time" class="form-control form-control-sm" id="trupp3Retreat">
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Einsatzende</label>
                        <input type="time" class="form-control form-control-sm" id="trupp3End">
                    </div>
                </div>
                <div class="mb-0">
                    <label class="form-label small">Bemerkungen</label>
                    <textarea class="form-control form-control-sm" id="trupp3Remarks" rows="2" placeholder="Zusätzliche Informationen"></textarea>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Action Buttons -->
<div class="d-flex justify-content-between mt-3">
    <button type="button" class="btn btn-secondary" onclick="clearAll()">
        <i class="fa-solid fa-eraser me-1"></i>Alle Felder leeren
    </button>
    <button type="button" class="btn btn-primary" onclick="sendData()">
        <i class="fa-solid fa-save me-1"></i>Protokoll speichern
    </button>
</div>