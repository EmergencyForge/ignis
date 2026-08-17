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
    banner.classList.remove('d-none');
  };

  Promise.allSettled([
    fetchJson('api/health'),
    fetchJson('assets/functions/rewrite-probe'),
  ]).then(([healthResult, rewriteResult]) => {
    const healthOk = healthResult.status === 'fulfilled'
      && healthResult.value
      && typeof healthResult.value.checks === 'object';
    const rewriteOk = rewriteResult.status === 'fulfilled'
      && rewriteResult.value?.probe === 'extensionless-php-rewrite';

    if (!healthOk || !rewriteOk) {
      showWarning(
        'URL-Rewriting funktioniert nicht.',
        '.htaccess, mod_rewrite und AllowOverride müssen für diese Installation aktiv sein.',
      );
      return;
    }

    const labels = {
      outbound_http: 'ausgehende HTTP-Verbindungen',
      process_control: 'Prozessfunktionen für Updates/Cron',
      php_extensions: 'benötigte PHP-Erweiterungen',
    };
    const degraded = Object.entries(healthResult.value.checks)
      .filter(([key, check]) => labels[key] && check?.status !== 'ok')
      .map(([key]) => labels[key]);

    if (degraded.length > 0) {
      showWarning(
        'Hosting-Funktionen sind eingeschränkt.',
        `Bitte prüfen: ${degraded.join(', ')}.`,
      );
    }
  });
}
