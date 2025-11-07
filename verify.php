<?php
session_start();
require('vendor/autoload.php');
require('config.php');

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

if (!isset($_POST['razorpay_payment_id'], $_POST['razorpay_order_id'], $_POST['razorpay_signature'])) {
    die("Payment details missing. Please try again.");
}

$api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);

$razorpay_payment_id = $_POST['razorpay_payment_id'];
$razorpay_order_id   = $_POST['razorpay_order_id'];
$razorpay_signature  = $_POST['razorpay_signature'];

try {
    // Verify payment signature
    $api->utility->verifyPaymentSignature([
        'razorpay_order_id' => $razorpay_order_id,
        'razorpay_payment_id' => $razorpay_payment_id,
        'razorpay_signature' => $razorpay_signature
    ]);

    // ✅ Signature verified — mark payment success
    $_SESSION['paid'] = true;

    // Optional: you can store payment info in database
    $conn = new mysqli("localhost", "root", "", "miniproject");
    if (!$conn->connect_error) {
        $stmt = $conn->prepare("INSERT INTO payments (order_id, payment_id, hospital_id, patient_name, amount, status) VALUES (?, ?, ?, ?, ?, ?)");
        $status = 'success';
        $amount = 5;
        $hospital_id = $_SESSION['hospital_id'] ?? 0;
        $patient_name = $_SESSION['temp_form']['name'] ?? 'Unknown';
        $stmt->bind_param("ssisis", $razorpay_order_id, $razorpay_payment_id, $hospital_id, $patient_name, $amount, $status);
        $stmt->execute();
    }

    // Redirect to print.php
    header("Location: print.php");
    exit();

} catch (SignatureVerificationError $e) {
    // ❌ Invalid payment
    echo "<h2>Payment verification failed.</h2>";
    echo "<p>Please try again. Error: " . $e->getMessage() . "</p>";
}
?>
