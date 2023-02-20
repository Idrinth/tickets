(() => {
    const getNotifications = async() => {
        const notifications = await (await fetch('/api/notifications')).json();
        const wrapper = document.getElementById('notifications');
        while (wrapper.firstChild) {
            wrapper.removeChild(wrapper.firstChild);
        }
        for (const notification of notifications) {
            const element = document.createElement('li');
            element.appendChild(document.createElement('strong'));
            element.lastChild.appendChild(document.createTextNode(notification.created));
            element.appendChild(document.createElement('span'));
            element.lastChild.appendChild(document.createTextNode(notification.content));
            element.appendChild(document.createElement('a'));
            element.lastChild.setAttribute('href', notification.url);
            element.lastChild.appendChild(document.createTextNode('->'));
            wrapper.appendChild(element);
        }
    };
    getNotifications();
    window.setInterval(getNotifications, 60000);
})();