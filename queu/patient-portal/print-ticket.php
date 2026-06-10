<?php
// patient-portal/print-ticket.php - Print Queue Ticket
// Camp Evangelista Station Hospital

require_once dirname(__DIR__) . '/config/database.php';

$database = new Database();
$db = $database->getConnection();

$transaction_token = isset($_GET['token']) ? $_GET['token'] : '';

// Get queue entry details
$query = "SELECT q.*, p.first_name, p.last_name, p.mrn, p.patient_type, p.is_pwd, p.is_senior, p.is_pregnant, c.name as clinic_name
          FROM queue_entries q
          JOIN patients p ON q.patient_id = p.id
          JOIN clinics c ON q.clinic_id = c.id
          WHERE q.transaction_token = :token";

$stmt = $db->prepare($query);
$stmt->bindParam(':token', $transaction_token);
$stmt->execute();
$queue = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$queue) {
    die("Invalid ticket request.");
}

// Generate QR code URL - Redirects to track-queue.php in the SAME patient-portal folder
$tracking_url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/track-queue.php?token=" . urlencode($queue['transaction_token']);
$qr_code_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($tracking_url);

// Generate Priority Label
$priority_label = "Standard";
if ($queue['priority_level'] == 'PR1') $priority_label = "Priority 1 (Active Mil)";
if ($queue['priority_level'] == 'PR2') {
    $reasons = [];
    if ($queue['is_senior']) $reasons[] = "Senior";
    if ($queue['is_pwd']) $reasons[] = "PWD";
    if ($queue['is_pregnant']) $reasons[] = "Pregnant";
    $priority_label = "Priority 2 (" . implode(', ', $reasons) . ")";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Ticket - <?php echo htmlspecialchars($queue['queue_number']); ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', '-apple-system', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'monospace']
                    }
                }
            }
        }
    </script>
    <style>
        @media print {
            body { 
                background: white !important; 
                margin: 0 !important; 
                padding: 0 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print { display: none !important; }
            .ticket-wrapper { 
                box-shadow: none !important; 
                border: none !important; 
                border-radius: 0 !important; 
                margin: 0 !important; 
                padding: 0 !important; 
                width: 100% !important;
                max-width: 100% !important;
            }
            @page { 
                margin: 0; 
                /* Typical thermal printer widths are 58mm or 80mm */
                size: 80mm auto; 
            }
        }
    </style>
</head>
<body class="bg-slate-50 dark:bg-[#111827] font-sans antialiased min-h-screen flex items-center justify-center p-6 transition-colors duration-200">

    <div class="max-w-md w-full mx-auto flex flex-col gap-6">

        <div class="no-print flex justify-between items-center px-2">
            <h1 class="text-xl font-extrabold text-slate-900 dark:text-white tracking-tight">Kiosk Ticket Dispenser</h1>
            <button onclick="document.documentElement.classList.toggle('dark')" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white dark:bg-[#1f2937] border border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 shadow-sm transition-colors">
                <i class="fas fa-moon dark:hidden"></i>
                <i class="fas fa-sun hidden dark:block text-amber-400"></i>
            </button>
        </div>

        <div class="ticket-wrapper bg-white text-black p-8 rounded-3xl shadow-xl border border-slate-200 dark:border-slate-700/50 mx-auto w-full max-w-[380px]">
            
            <div class="text-center pb-5 border-b-2 border-dashed border-gray-300">
                <img src="../assets/images/logo.png" alt="CESH Logo" class="w-14 h-14 mx-auto mb-2 grayscale" onerror="this.style.display='none'">
                <h2 class="text-[13px] font-extrabold tracking-widest uppercase leading-tight">4ID Station Hospital</h2>
                <p class="text-[10px] font-bold text-gray-500 uppercase tracking-widest mt-1">Camp Evangelista</p>
                <div class="mt-4 bg-gray-100 py-1.5 px-3 rounded text-[11px] font-bold uppercase tracking-widest">
                    Official Queue Ticket
                </div>
            </div>

            <div class="text-center py-6 border-b-2 border-dashed border-gray-300">
                <p class="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Your Queue Number</p>
                <div class="text-5xl font-black font-mono tracking-tighter my-2 text-black">
                    <?php echo htmlspecialchars($queue['queue_number']); ?>
                </div>
                <p class="text-xs font-bold uppercase mt-2">
                    <?php echo htmlspecialchars($queue['clinic_name']); ?>
                </p>
            </div>

            <div class="py-5 space-y-2 border-b-2 border-dashed border-gray-300">
                <div class="flex justify-between items-start text-[11px]">
                    <span class="font-bold text-gray-500 uppercase">Patient Name:</span>
                    <span class="font-extrabold text-right ml-4"><?php echo htmlspecialchars($queue['last_name'] . ', ' . $queue['first_name']); ?></span>
                </div>
                <div class="flex justify-between items-start text-[11px]">
                    <span class="font-bold text-gray-500 uppercase">MRN:</span>
                    <span class="font-bold font-mono text-right ml-4"><?php echo htmlspecialchars($queue['mrn']); ?></span>
                </div>
                <div class="flex justify-between items-start text-[11px]">
                    <span class="font-bold text-gray-500 uppercase">Category:</span>
                    <span class="font-bold text-right ml-4"><?php echo htmlspecialchars($queue['patient_type']); ?></span>
                </div>
                <div class="flex justify-between items-start text-[11px]">
                    <span class="font-bold text-gray-500 uppercase">Priority:</span>
                    <span class="font-bold text-right ml-4"><?php echo $priority_label; ?></span>
                </div>
            </div>

            <div class="pt-5 text-center">
                <img src="<?php echo $qr_code_url; ?>" alt="Live Tracker QR Code" class="mx-auto w-24 h-24 mb-3 border border-gray-200 p-1 rounded-lg shadow-sm">
                
                <p class="text-[9px] font-bold text-gray-500 uppercase tracking-wide leading-tight mb-4">
                    Scan with your phone to<br>track your queue status live.
                </p>
                <p class="text-[10px] font-bold text-gray-800 uppercase tracking-wide leading-tight mb-2">
                    <i class="fas fa-bell mr-1"></i> Please wait for your number<br>to be called at the clinic.
                </p>
                <div class="text-[9px] text-gray-500 font-mono mt-4">
                    Date: <?php echo date('m/d/Y h:i A', strtotime($queue['registered_at'])); ?><br>
                    Token: <?php echo substr($queue['transaction_token'], 0, 12); ?>...
                </div>
            </div>

        </div>

        <div class="no-print grid grid-cols-1 sm:grid-cols-2 gap-3 mt-2">
            <button onclick="window.print()" class="flex items-center justify-center gap-2 w-full py-3.5 bg-teal-600 hover:bg-teal-700 text-white rounded-xl font-bold text-sm uppercase tracking-wider shadow-md shadow-teal-600/20 transition-all active:scale-[0.98]">
                <i class="fas fa-print"></i> Print Ticket
            </button>
            <button onclick="window.location.href='self-register.php'" class="flex items-center justify-center gap-2 w-full py-3.5 bg-white dark:bg-[#1f2937] hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-xl font-bold text-sm uppercase tracking-wider shadow-sm transition-all active:scale-[0.98]">
                <i class="fas fa-user-plus"></i> New Register
            </button>
        </div>
        
    </div>

    <script>
        // Ensure screen theme matches standard dark mode toggles without affecting the physical print properties
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }

        // Auto open print dialog when page loads for seamless kiosk behavior
        window.addEventListener('DOMContentLoaded', (event) => {
            setTimeout(function() {
                window.print();
                
                // Kiosk reset flow: Optional - automatically redirects to self-register after 15 seconds to prepare for the next patient in line.
                setTimeout(function() {
                    window.location.href = 'self-register.php';
                }, 15000);

            }, 800);
        });
    </script>
</body>
</html>