<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/QueueManager.php';

$database = new Database();
$db = $database->getConnection();
$queueManager = new QueueManager($db);

// Get recent announcements
$announcements = $queueManager->getRecentAnnouncements(date('Y-m-d H:i:s', strtotime('-5 minutes')));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - Camp Evangelista Hospital</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #0A0F1E;
            color: #FFFFFF;
            min-height: 100vh;
            padding: 20px;
        }

        .announcement-container {
            max-width: 800px;
            margin: 50px auto;
        }

        .announcement-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .announcement-header h1 {
            font-size: 48px;
            font-weight: 800;
            color: #2DD4BF;
            margin-bottom: 10px;
        }

        .announcement-header p {
            font-size: 18px;
            color: #64748B;
        }

        .current-announcement {
            background: linear-gradient(135deg, #0B1E33 0%, #1A2C40 100%);
            border: 2px solid #2DD4BF;
            border-radius: 30px;
            padding: 40px;
            text-align: center;
            margin-bottom: 40px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(45, 212, 191, 0.4); }
            70% { box-shadow: 0 0 0 20px rgba(45, 212, 191, 0); }
            100% { box-shadow: 0 0 0 0 rgba(45, 212, 191, 0); }
        }

        .now-serving {
            font-size: 24px;
            color: #2DD4BF;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 20px;
        }

        .queue-number {
            font-size: 120px;
            font-weight: 800;
            color: #FFFFFF;
            margin-bottom: 20px;
            text-shadow: 0 0 20px rgba(45, 212, 191, 0.5);
        }

        .clinic-name {
            font-size: 36px;
            color: #2DD4BF;
            font-weight: 600;
        }

        .recent-announcements {
            background: #141B2B;
            border: 1px solid #2D3748;
            border-radius: 20px;
            padding: 30px;
        }

        .recent-title {
            font-size: 24px;
            color: #FFFFFF;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .recent-title i {
            color: #2DD4BF;
        }

        .announcement-list {
            list-style: none;
        }

        .announcement-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #2D3748;
            font-size: 18px;
        }

        .announcement-item:last-child {
            border-bottom: none;
        }

        .announcement-time {
            width: 100px;
            color: #64748B;
            font-size: 14px;
        }

        .announcement-number {
            width: 150px;
            font-weight: 700;
            color: #2DD4BF;
            font-size: 20px;
        }

        .announcement-clinic {
            flex: 1;
            color: #FFFFFF;
        }

        .speaker-icon {
            width: 40px;
            height: 40px;
            background: #1E2639;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 15px;
        }

        .speaker-icon i {
            color: #2DD4BF;
            font-size: 18px;
        }

        .speaker-icon.speaking {
            animation: speaking 1s infinite;
        }

        @keyframes speaking {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .no-announcements {
            text-align: center;
            padding: 40px;
            color: #64748B;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="announcement-container">
        <div class="announcement-header">
            <h1>CAMP EVANGELISTA HOSPITAL</h1>
            <p>Patient Announcement System</p>
        </div>

        <!-- Current Announcement Area -->
        <div class="current-announcement" id="currentAnnouncement">
            <div class="now-serving">NOW SERVING</div>
            <div class="queue-number" id="currentQueueNumber">---</div>
            <div class="clinic-name" id="currentClinicName">Waiting for next patient</div>
        </div>

        <!-- Recent Announcements -->
        <div class="recent-announcements">
            <div class="recent-title">
                <i class="fas fa-history"></i>
                Recent Announcements
            </div>
            
            <ul class="announcement-list" id="announcementList">
                <?php if (empty($announcements)): ?>
                    <li class="no-announcements">No recent announcements</li>
                <?php else: ?>
                    <?php foreach ($announcements as $ann): ?>
                    <li class="announcement-item">
                        <span class="announcement-time"><?php echo date('h:i A', strtotime($ann['called_at'])); ?></span>
                        <span class="announcement-number"><?php echo $ann['queue_number']; ?></span>
                        <span class="announcement-clinic"><?php echo $ann['clinic_name']; ?></span>
                        <div class="speaker-icon">
                            <i class="fas fa-volume-up"></i>
                        </div>
                    </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <script>
        let lastAnnouncementTime = '<?php echo date('Y-m-d H:i:s', strtotime('-1 minute')); ?>';
        let audioContext = null;

        // Initialize audio context on user interaction
        document.addEventListener('click', function initAudio() {
            if (!audioContext) {
                audioContext = new (window.AudioContext || window.webkitAudioContext)();
            }
            document.removeEventListener('click', initAudio);
        });

        // Text-to-speech function
        function speak(text) {
            if ('speechSynthesis' in window) {
                window.speechSynthesis.cancel();
                
                const utterance = new SpeechSynthesisUtterance(text);
                utterance.lang = 'en-US';
                utterance.rate = 0.9;
                utterance.pitch = 1;
                utterance.volume = 1;
                
                // Add emphasis to the queue number
                utterance.onstart = function() {
                    document.querySelectorAll('.speaker-icon').forEach(icon => {
                        icon.classList.add('speaking');
                    });
                };
                
                utterance.onend = function() {
                    document.querySelectorAll('.speaker-icon').forEach(icon => {
                        icon.classList.remove('speaking');
                    });
                };
                
                window.speechSynthesis.speak(utterance);
            }
        }

        // Check for new announcements
        function checkAnnouncements() {
            fetch(`../api/announce.php?action=get_announcements&since=${encodeURIComponent(lastAnnouncementTime)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.announcements.length > 0) {
                        // Update last announcement time
                        lastAnnouncementTime = data.announcements[0].called_at;
                        
                        // Process each new announcement
                        data.announcements.forEach((ann, index) => {
                            setTimeout(() => {
                                // Update current announcement display
                                document.getElementById('currentQueueNumber').textContent = ann.queue_number;
                                document.getElementById('currentClinicName').textContent = ann.clinic_name;
                                
                                // Make announcement
                                const announcement = `Attention please. Patient number ${ann.queue_number}, please proceed to ${ann.clinic_name}.`;
                                speak(announcement);
                                
                                // Add to recent list
                                addToRecentList(ann);
                            }, index * 5000); // Space out announcements by 5 seconds
                        });
                    }
                })
                .catch(error => console.error('Error checking announcements:', error));
        }

        // Add announcement to recent list
        function addToRecentList(announcement) {
            const list = document.getElementById('announcementList');
            const time = new Date(announcement.called_at).toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
            
            const newItem = document.createElement('li');
            newItem.className = 'announcement-item';
            newItem.innerHTML = `
                <span class="announcement-time">${time}</span>
                <span class="announcement-number">${announcement.queue_number}</span>
                <span class="announcement-clinic">${announcement.clinic_name}</span>
                <div class="speaker-icon">
                    <i class="fas fa-volume-up"></i>
                </div>
            `;
            
            // Remove "no announcements" if present
            if (list.children.length === 1 && list.children[0].classList.contains('no-announcements')) {
                list.innerHTML = '';
            }
            
            list.insertBefore(newItem, list.firstChild);
            
            // Keep only last 10 announcements
            while (list.children.length > 10) {
                list.removeChild(list.lastChild);
            }
        }

        // Check for new announcements every 5 seconds
        setInterval(checkAnnouncements, 5000);

        // Initial check
        setTimeout(checkAnnouncements, 1000);
    </script>
</body>
</html>