// Notification Logic

// 2. Request Notification Permission
function requestNotificationPermission() {
    if (!("Notification" in window)) {
        console.log("This browser does not support desktop notification");
    } else if (Notification.permission !== "denied") {
        Notification.requestPermission();
    }
}

// 3. Poll for Notifications
// Removed lastMessages check to ensure alerts are shown every cycle as requested

function checkNotifications() {
    // Determine path to check_notifications.php
    const apiPath = 'check_notifications.php';

    fetch(apiPath)
        .then(response => response.json())
        .then(data => {
            if (data.has_notification && data.messages.length > 0) {
                data.messages.forEach(msg => {
                    // Only show if we haven't shown this EXACT message in this session loop recently
                    // A simple de-dupe: if it's not in the lastMessages array.
                    // But for "Crisis", we might want to be persistent.
                    // For now, let's just show it.
                    
                    // Check if permission granted
                    if (Notification.permission === "granted") {
                        // System Notification
                        new Notification("RM Flow Alert", {
                            body: msg,
                            icon: 'https://via.placeholder.com/192.png?text=Alert' // Replace with real icon if avail
                        });
                    } else {
                        // Fallback to console or maybe a toast if we had one
                        console.log("Notification:", msg);
                        // Optional: Request permission again? No, annoying.
                    }
                });
            }
        })
        .catch(err => console.error("Error checking notifications:", err));
}

// Init
document.addEventListener('DOMContentLoaded', () => {
    requestNotificationPermission();
    
    // Initial check
    checkNotifications();
    
    // Poll every 60 seconds (or 30s as user wants "on the spot")
    setInterval(checkNotifications, 30000); 
});
