function showToast(message, type = 'info', duration = 3000) {
    var container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }

    var toast = document.createElement('div');
    toast.className = 'toast ' + type;

    var text = document.createElement('span');
    text.innerText = message;

    var closeBtn = document.createElement('button');
    closeBtn.className = 'toast-close';
    closeBtn.innerHTML = '&times;';
    closeBtn.onclick = function () {
        container.removeChild(toast);
    };

    toast.appendChild(text);
    toast.appendChild(closeBtn);
    container.appendChild(toast);

    // Trigger reflow
    void toast.offsetWidth;

    // Show
    toast.classList.add('show');

    // Auto remove
    if (duration > 0) {
        setTimeout(function () {
            toast.classList.remove('show');
            setTimeout(function () {
                if (toast.parentNode === container) {
                    container.removeChild(toast);
                }
            }, 500); // Wait for transition
        }, duration);
    }
}
