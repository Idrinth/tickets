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
            const hour = Math.floor(diff/1000/60/60);
            const min = Math.floor(diff/1000/60)%60;
            const sec = Math.floor(diff/1000)%60;
            if (min < 10 && sec < 10 && hour < 10) {
                duration.value = `0${hour}:0${min}:0${sec}`;
            } else if (min < 10 && sec < 10) {
                duration.value = `${hour}:0${min}:0${sec}`;
            } else if (min < 10 && hour < 10) {
                duration.value = `0${hour}:0${min}:${sec}`;
            } else if (sec < 10 && hour < 10) {
                duration.value = `0${hour}:${min}:0${sec}`;
            } else if (sec < 10) {
                duration.value = `${hour}:${min}:0${sec}`;
            } else if (min < 10) {
                duration.value = `${hour}:0${min}:${sec}`;
            } else if (hour < 10) {
                duration.value = `0${hour}:${min}:${sec}`;
            } else {
                duration.value = `${hour}:${min}:${sec}`;
            }
        }, 100);
    }
})();


