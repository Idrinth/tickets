(() => {
    const getNotifications = async() => {
        const notifications = await (await fetch('/api/notifications')).json();
        const wrapper = document.getElementById('notifications');
        while (wrapper.firstChild) {
            wrapper.removeChild(wrapper.firstChild);
        }
        for (const notification of notifications) {
            const element = document.createElement('li');
            element.appendChild(document.createElement('a'));
            element.lastChild.setAttribute('href', notification.url);
            element.lastChild.appendChild(document.createElement('strong'));
            element.lastChild.lastChild.appendChild(document.createTextNode(notification.created));
            element.lastChild.appendChild(document.createElement('span'));
            element.lastChild.lastChild.appendChild(document.createTextNode(notification.content));
            wrapper.appendChild(element);
        }
    };
    getNotifications();
    window.setInterval(getNotifications, 60000);
})();