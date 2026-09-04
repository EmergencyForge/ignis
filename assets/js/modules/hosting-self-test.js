const banner = document.getElementById('hosting-self-test');

if (banner) {
  const basePath = banner.dataset.basePath || '/';
  const base = basePath.endsWith('/') ? basePath : `${basePath}/`;
  const title = banner.querySelector('[data-hosting-self-test-title]');
  const message = banner.querySelector('[data-hosting-self-test-message]');

  const fetchJson = async (path) => {
    const response = await fetch(`${base}${path}`, {
      cache: 'no-store',
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    });
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    return response.json();
  };

  const showWarning = (heading, detail) => {
    title.textContent = heading;
    message.textContent = `${detail} `;
    banner.hidden = false;
  };

  // /api/health ist eine Router-URL. Kommt sie nicht an, greift der
  // Front-Controller nicht, und nichts außer echten Dateien funktioniert.
  fetchJson('api/health').then((health) => {
    if (!health || typeof health.checks !== 'object') {
      throw new Error('unexpected payload');
    }

    const rewrite = health.checks.rewrite;
    if (rewrite?.document_root === 'fallback') {
      showWarning(
        'Document-Root zeigt noch auf das Projektverzeichnis.',
        'Die Root-.htaccess reicht Anfragen nach public/ durch. Sicherer ist ein Document-Root direkt auf public/.',
      );
      return;
    }

    const labels = {
      outbound_http: 'ausgehende HTTP-Verbindungen',
      process_control: 'Prozessfunktionen für Updates/Cron',
      php_extensions: 'benötigte PHP-Erweiterungen',
    };
    const degraded = Object.entries(health.checks)
      .filter(([key, check]) => labels[key] && check?.status !== 'ok')
      .map(([key]) => labels[key]);

    if (degraded.length > 0) {
      showWarning(
        'Hosting-Funktionen sind eingeschränkt.',
        `Bitte prüfen: ${degraded.join(', ')}.`,
      );
    }
  }).catch(() => {
    showWarning(
      'URL-Rewriting funktioniert nicht.',
      'Der Front-Controller (public/index.php) ist nicht erreichbar. Document-Root, .htaccess, mod_rewrite und AllowOverride müssen für diese Installation stimmen.',
    );
  });
}
