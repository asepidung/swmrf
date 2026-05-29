// Auto-logout script for inactivity (30 minutes)
(function() {
    const INACTIVITY_TIME = 30 * 60 * 1000; // 30 minutes in milliseconds
    let timeoutId;

    function resetTimer() {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(logout, INACTIVITY_TIME);
    }

    function logout() {
        console.log('User inactive. Logging out...');
        
        // Find CSRF token
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        // Create form programmatically
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/admin/logout';
        
        if (csrfToken) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = '_token';
            input.value = csrfToken;
            form.appendChild(input);
        }
        
        document.body.appendChild(form);
        form.submit();
    }

    // List of events to listen for (key presses capture barcode scanner input too)
    const events = ['mousemove', 'mousedown', 'click', 'scroll', 'keypress', 'touchstart'];

    events.forEach(name => {
        document.addEventListener(name, resetTimer, true);
    });

    // Start timer on load
    resetTimer();
})();
