<script>
    let lastTime = '';
    let lastDate = '';

    function updateTimeAndDate() {
        const now = new Date();
        const berlinTime = new Date(now.toLocaleString("en-US", {
            timeZone: "Europe/Berlin"
        }));
        const time = berlinTime.toLocaleTimeString('de-DE', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        });
        const date = berlinTime.toLocaleDateString('de-DE', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });

        if (time !== lastTime) {
            document.getElementById('current-time').textContent = time;
            lastTime = time;
        }
        if (date !== lastDate) {
            document.getElementById('current-date').textContent = date;
            lastDate = date;
        }
    }

    setInterval(updateTimeAndDate, 1000);
    updateTimeAndDate();
</script>