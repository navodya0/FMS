<?php
session_start();

$myAccountPageId = 5;   // My Account page ID
$dashboardPageId = 11;  // Dashboard page ID

if (!isset($_SESSION['customer_id'])) {
    header("Location: " . $modx->makeUrl($myAccountPageId));
    exit;
}

if (isset($_POST['logout'])) {
    session_destroy();
    header("Location: " . $modx->makeUrl($myAccountPageId));
    exit;
}

require_once MODX_BASE_PATH . 'assets/includes/db_connect.php';

$name = htmlspecialchars($_SESSION['customer_name'] ?? 'Customer', ENT_QUOTES, 'UTF-8');
$emailRaw = $_SESSION['customer_email'] ?? '';
$email = htmlspecialchars($emailRaw, ENT_QUOTES, 'UTF-8');
$initial = strtoupper(substr($name, 0, 1));

function makeFileUrl($path) {
    if (!$path) return null;

    $path = str_replace('\\', '/', $path);

    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    if (isset($_SERVER['DOCUMENT_ROOT']) && strpos($path, $_SERVER['DOCUMENT_ROOT']) === 0) {
        $path = substr($path, strlen($_SERVER['DOCUMENT_ROOT']));
    }

    return '/' . ltrim($path, '/');
}

function getReceipts($conn, $bookingId) {
    $stmt = $conn->prepare("
        SELECT id, receipt_no, receipt_path, payment_amount, generated_at
        FROM payment_receipts
        WHERE reserved_slot_id = ?
        AND receipt_path IS NOT NULL
        ORDER BY generated_at DESC, id DESC
    ");
    $stmt->execute([$bookingId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function buildRows($bookings, $conn, $showEdit = false) {
    $rows = '';

    foreach ($bookings as $i => $b) {
        $bookingId = (int)$b['id'];
        $ref = htmlspecialchars($b['reference_number'] ?? '', ENT_QUOTES, 'UTF-8');
        $vehicle = htmlspecialchars(($b['vehicle_type'] ?? '') . ' - ' . ($b['vehicle_number'] ?? ''), ENT_QUOTES, 'UTF-8');
        $slot = htmlspecialchars($b['slot_number'] ?? '', ENT_QUOTES, 'UTF-8');

        $start = !empty($b['start_date']) ? date('d M Y h:i A', strtotime($b['start_date'])) : '';
        $endSource = !empty($b['end_date_edited']) ? $b['end_date_edited'] : $b['end_date'];
        $end = !empty($endSource) ? date('d M Y h:i A', strtotime($endSource)) : '';

        $amount = !empty($b['total_price_final']) ? (float)$b['total_price_final'] : (float)$b['total_price'];

        $invoiceBtn = '<span class="muted">N/A</span>';
        if (!empty($b['pdf_path'])) {
            $pdf = makeFileUrl($b['pdf_path']);
            $invoiceBtn = '<a href="' . htmlspecialchars($pdf, ENT_QUOTES, 'UTF-8') . '" target="_blank" class="small-btn">Invoice PDF</a>';
        }

        $receipts = getReceipts($conn, $bookingId);
        $receiptBtn = '<span class="muted">N/A</span>';

        if (count($receipts) === 1) {
            $receiptUrl = makeFileUrl($receipts[0]['receipt_path']);
            $receiptBtn = '<a href="' . htmlspecialchars($receiptUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" class="small-btn receipt-btn">Receipt PDF</a>';
        } elseif (count($receipts) > 1) {
            $receiptBtn = '<button type="button" class="small-btn receipt-btn view-receipts-btn" data-booking-id="' . $bookingId . '">Receipts (' . count($receipts) . ')</button>';
        }

        $editBtn = $showEdit
            ? '<button type="button" class="small-btn edit-booking-btn" data-id="' . $bookingId . '">Edit</button>'
            : '<span class="muted">Completed</span>';

        $rows .= '
            <tr>
                <td>' . ($i + 1) . '</td>
                <td>' . $ref . '</td>
                <td>' . $vehicle . '</td>
                <td>' . $start . '</td>
                <td>' . $end . '</td>
                <td>LKR ' . number_format($amount, 2) . '</td>
                <td>' . $invoiceBtn . ' ' . $receiptBtn . '</td>
                <td>' . $editBtn . '</td>
            </tr>
        ';
    }

    return $rows;
}

$ongoingBookings = [];
$completedBookings = [];

try {
    $stmt = $conn->prepare("
        SELECT 
            id,
            reference_number,
            slot_number,
            vehicle_type,
            vehicle_number,
            start_date,
            end_date,
            end_date_edited,
            total_price,
            total_price_final,
            pdf_path,
            status,
            vehicle_status
        FROM reserved_slots
        WHERE email = ?
        AND is_trashed = 0
        AND is_no_show = 0
        AND (vehicle_status IS NULL OR vehicle_status != 'completed')
        ORDER BY start_date DESC
    ");
    $stmt->execute([$emailRaw]);
    $ongoingBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conn->prepare("
        SELECT 
            id,
            reference_number,
            slot_number,
            vehicle_type,
            vehicle_number,
            start_date,
            end_date,
            end_date_edited,
            total_price,
            total_price_final,
            pdf_path,
            status,
            vehicle_status
        FROM reserved_slots
        WHERE email = ?
        AND is_trashed = 0
        AND is_no_show = 0
        AND vehicle_status = 'completed'
        ORDER BY start_date DESC
    ");
    $stmt->execute([$emailRaw]);
    $completedBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $ongoingBookings = [];
    $completedBookings = [];
}

$ongoingRows = buildRows($ongoingBookings, $conn, true);
$completedRows = buildRows($completedBookings, $conn, false);

return '
<link rel="stylesheet" href="https://cdn.datatables.net/2.1.2/css/dataTables.dataTables.min.css">

<style>
.customer-dashboard-wrap {
    display: flex;
    min-height: 80vh;
    background: #f4f6f8;
    font-family: Cambria, Cochin, Georgia, Times, "Times New Roman", serif;
}

.customer-sidebar {
    width: 260px;
    background: #003272;
    color: #fff;
    padding: 25px 20px;
}

.customer-logo {
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 35px;
}

.customer-user {
    text-align: center;
    margin-bottom: 30px;
}

.customer-avatar {
    width: 75px;
    height: 75px;
    background: #fff;
    color: #003272;
    border-radius: 50%;
    margin: 0 auto 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 30px;
    font-weight: bold;
}

.customer-user-name {
    font-size: 18px;
    font-weight: bold;
}

.customer-user-email {
    font-size: 13px;
    opacity: 0.85;
    word-break: break-word;
}

.customer-menu a,
.customer-menu button {
    display: block;
    width: 100%;
    background: transparent;
    border: none;
    color: #fff;
    text-align: left;
    padding: 12px 14px;
    border-radius: 8px;
    margin-bottom: 8px;
    text-decoration: none;
    cursor: pointer;
    font-size: 15px;
}

.customer-menu a.active,
.customer-menu a:hover,
.customer-menu button:hover {
    background: rgba(255,255,255,0.15);
}

.customer-main {
    flex: 1;
    padding: 30px;
}

.dashboard-header,
.bookings-panel {
    background: #fff;
    padding: 22px 25px;
    border-radius: 14px;
    box-shadow: 0 5px 18px rgba(0,0,0,0.06);
    margin-bottom: 25px;
}

.dashboard-header h2,
.bookings-panel h3 {
    color: #003272;
    margin-top: 0;
}

.bookings-table-wrap {
    overflow-x: auto;
}

.bookings-table {
    width: 100%;
    border-collapse: collapse;
}

.bookings-table th {
    background: #003272;
    color: #fff;
    padding: 12px;
    text-align: left;
}

.bookings-table td {
    padding: 12px;
    border-bottom: 1px solid #e5e7eb;
}

.small-btn {
    display: inline-block;
    background: #003272;
    color: #fff;
    border: none;
    padding: 7px 10px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 12px;
    cursor: pointer;
    margin: 2px;
}

.receipt-btn {
    background: #6f42c1;
}

.edit-booking-btn {
    background: #198754;
}

.muted {
    color: #999;
}

.empty-row {
    text-align: center;
    color: #777;
    padding: 24px !important;
}

.receipt-modal-overlay {
    display: none;
    position: fixed;
    z-index: 9999;
    inset: 0;
    background: rgba(0,0,0,0.5);
    padding: 40px 20px;
}

.receipt-modal {
    background: #fff;
    max-width: 800px;
    margin: 0 auto;
    border-radius: 12px;
    overflow: hidden;
}

.receipt-modal-header {
    background: #003272;
    color: #fff;
    padding: 16px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.receipt-modal-body {
    padding: 20px;
    max-height: 70vh;
    overflow-y: auto;
}

.close-receipt-modal {
    background: transparent;
    color: #fff;
    border: none;
    font-size: 24px;
    cursor: pointer;
}

.receipt-list-table {
    width: 100%;
    border-collapse: collapse;
}

.receipt-list-table th {
    background: #f1f5f9;
    padding: 10px;
    text-align: left;
}

.receipt-list-table td {
    padding: 10px;
    border-bottom: 1px solid #e5e7eb;
}

@media (max-width: 850px) {
    .customer-dashboard-wrap {
        flex-direction: column;
    }

    .customer-sidebar {
        width: 100%;
    }
}
</style>

<div class="customer-dashboard-wrap">
    <aside class="customer-sidebar">
        <div class="customer-logo">Airport Parking</div>

        <div class="customer-user">
            <div class="customer-avatar">' . $initial . '</div>
            <div class="customer-user-name">' . $name . '</div>
            <div class="customer-user-email">' . $email . '</div>
        </div>

        <div class="customer-menu">
            <a href="' . $modx->makeUrl($dashboardPageId) . '">Dashboard</a>
            <a href="#" class="active">My Bookings</a>
            <a href="#">My Receipts</a>
            <a href="#">Profile</a>

            <form method="post">
                <button type="submit" name="logout">Logout</button>
            </form>
        </div>
    </aside>

    <main class="customer-main">
        <div class="dashboard-header">
            <h2>My Bookings</h2>
            <p>Bookings matched using your registered email address: ' . $email . '</p>
        </div>

        <div class="bookings-panel">
            <h3>Ongoing Bookings</h3>
            <div class="bookings-table-wrap">
                <table id="ongoingBookingsTable" class="bookings-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Reference No</th>
                            <th>Vehicle</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Total</th>
                            <th>PDFs</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>' . $ongoingRows . '</tbody>
                </table>
            </div>
        </div>

        <div class="bookings-panel">
            <h3>Completed Bookings</h3>
            <div class="bookings-table-wrap">
                <table id="completedBookingsTable" class="bookings-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Reference No</th>
                            <th>Vehicle</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Total</th>
                            <th>PDFs</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>' . $completedRows . '</tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<div class="receipt-modal-overlay" id="receiptModalOverlay">
    <div class="receipt-modal">
        <div class="receipt-modal-header">
            <strong>Cash Receipts</strong>
            <button type="button" class="close-receipt-modal" id="closeReceiptModal">&times;</button>
        </div>
        <div class="receipt-modal-body" id="receiptModalBody">
            Loading...
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.1.2/js/dataTables.min.js"></script>

<script>
$(function () {
    $("#ongoingBookingsTable").DataTable({
        pageLength: 10,
                order: [ [4, "desc"] ]

    });

    $("#completedBookingsTable").DataTable({
        pageLength: 10,
                order: [ [4, "desc"] ]

    });

    $(".view-receipts-btn").on("click", function () {
        const bookingId = $(this).data("booking-id");

        $("#receiptModalOverlay").fadeIn(150);
        $("#receiptModalBody").html("Loading...");

        $.get("assets/includes/customer-receipts-modal.php", { booking_id: bookingId }, function (html) {
            $("#receiptModalBody").html(html);
        }).fail(function () {
            $("#receiptModalBody").html("<p style=\'color:red;\'>Failed to load receipts.</p>");
        });
    });

    $("#closeReceiptModal, #receiptModalOverlay").on("click", function (e) {
        if (e.target.id === "receiptModalOverlay" || e.target.id === "closeReceiptModal") {
            $("#receiptModalOverlay").fadeOut(150);
        }
    });

    $(".edit-booking-btn").on("click", function () {
        alert("Edit booking ID: " + this.dataset.id + "\\nConnect this to your edit booking page or modal.");
    });
});
</script>
';