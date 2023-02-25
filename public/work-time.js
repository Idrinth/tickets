(() => {
    const button = document.getElementById('time-track');
    const duration = document.getElementById('duration');
    if (!button) {
        return;
    }
    if (!duration) {
        return;
    }
    let started = 0;
    let interval = 0;
    button.onclick = () => {
        if (interval > 0) {
            window.clearInterval(interval);
            interval = 0;
            return;
        }
        started = Date.now();
        interval = window.setInterval(() => {
            const diff = Date.now() - started;
            const min = Math.floor(diff/1000/60);
            const sec = Math.floor(diff/1000)%60;
            duration.value = `${min}:${sec}`;
        }, 100);
    }
})();


