<?php
session_start();

// ✅ Load Razorpay SDK
require('vendor/autoload.php');
require('config.php');

// ✅ Load Twilio WhatsApp sender
require('send_sms.php');  // must contain sendTicketMessage()

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

// ✅ Mandatory Razorpay POST data check
if (
    !isset($_POST['razorpay_payment_id']) ||
    !isset($_POST['razorpay_order_id']) ||
    !isset($_POST['razorpay_signature'])
) {
    die("Payment details missing. Please try again.");
}

$api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);

$razorpay_payment_id = $_POST['razorpay_payment_id'];
$razorpay_order_id   = $_POST['razorpay_order_id'];
$razorpay_signature  = $_POST['razorpay_signature'];

try {
    // ✅ Step 1 — Verify payment signature
    $api->utility->verifyPaymentSignature([
        'razorpay_order_id'   => $razorpay_order_id,
        'razorpay_payment_id' => $razorpay_payment_id,
        'razorpay_signature'  => $razorpay_signature
    ]);

    // ✅ Step 2 — Mark payment success
    $_SESSION['paid'] = true;

    // ✅ Step 3 — Connect to database
    $conn = new mysqli("localhost", "root", "", "miniproject");
    if ($conn->connect_error) die("Database Error");

    // ✅ Step 4 — Get session IDs
    $hospital_id = $_SESSION['hospital_id'] ?? 0;
    $patient_id  = $_SESSION['patient_id'] ?? 0;

    if ($hospital_id <= 0 || $patient_id <= 0) {
        die("❌ Patient or hospital session expired. Please register again.");
    }

    // ✅ Step 5 — Fetch patient & hospital details
    $stmt = $conn->prepare("
        SELECT p.*, h.name AS hospital_name
        FROM patients p
        JOIN hospitals h ON p.hospital_id = h.id
        WHERE p.id=? AND p.hospital_id=?
    ");
    $stmt->bind_param("ii", $patient_id, $hospital_id);
    $stmt->execute();
    $patient = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$patient) {
        die("❌ Patient details not found. Please register again.");
    }

    // ✅ Step 6 — Insert payment record
    $stmt = $conn->prepare("
        INSERT INTO payments (order_id, payment_id, hospital_id, patient_name, amount, status)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $status = 'success';
    $amount = 5;
    $stmt->bind_param(
        "ssisis",
        $razorpay_order_id,
        $razorpay_payment_id,
        $hospital_id,
        $patient['name'],
        $amount,
        $status
    );
    $stmt->execute();
    $stmt->close();

    // ✅ Step 7 — Send WhatsApp message (IMPORTANT)
    // Extract details from DB
    $name          = $patient['name'];
    $phone         = $patient['phone'];
    $hospital_name = $patient['hospital_name'];
    $patientUid    = $patient['patient_uid'];
    $department    = $patient['department'];
    $token         = $patient['token'];
    $token_date    = $patient['token_date'];
    $age           = $patient['age'];
    $gender        = $patient['gender'];

    // ✅ Twilio WhatsApp message
    sendTicketMessage(
        $phone,
        $name,
        $hospital_name,
        $patientUid,
        $department,
        $token,
        $token_date,
        $age,
        $gender
    );

    // ✅ Step 8 — Redirect to printing page
    header("Location: print.php");
    exit();

} catch (SignatureVerificationError $e) {
    // ❌ Payment verification failed
    echo "<h2>Payment verification failed.</h2>";
    echo "<p>Please try again. Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
