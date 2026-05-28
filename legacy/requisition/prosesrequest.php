
<?php
require "../verifications/auth.php";
require "../konak/conn.php";
require "requestnumber.php";

// Fungsi untuk membersihkan format angka
function normalizeNumber($number)
{
    $lastCommaPos = strrpos($number, ',');
    $lastDotPos = strrpos($number, '.');

    $decimalSeparator = $lastCommaPos > $lastDotPos ? ',' : '.';
    $thousandSeparator = $lastCommaPos < $lastDotPos ? ',' : '.';

    $number = str_replace($thousandSeparator, '', $number);
    if ($decimalSeparator != '.') {
        $number = str_replace($decimalSeparator, '.', $number);
    }

    return (float) $number;
}

$iduser = $_SESSION['idusers'] ?? null;
if (!$iduser) {
    die("Error: User ID is missing from the session.");
}

// Get data from the form
$duedate = mysqli_real_escape_string($conn, $_POST['duedate'] ?? null);
$tax = mysqli_real_escape_string($conn, $_POST['tax'] ?? null);
$idsupplier = mysqli_real_escape_string($conn, $_POST['idsupplier'] ?? null);
$note = mysqli_real_escape_string($conn, $_POST['note'] ?? null);
$taxrp = normalizeNumber($_POST['taxrp'] ?? 0); // Normalize taxrp
$idrawmate = $_POST['idrawmate'] ?? [];
$weight = $_POST['weight'] ?? [];
$price = $_POST['price'] ?? [];
$notes = $_POST['notes'] ?? [];

// Validate required fields
if (empty($duedate) || empty($idsupplier) || count($idrawmate) === 0) {
    die("Error: Missing required fields.");
}

// Calculate xamount
$xamount = $taxrp + array_sum(array_map(function ($weight, $price) {
    return normalizeNumber($weight) * normalizeNumber($price);
}, $weight, $price));

$stat = 'Request';

mysqli_begin_transaction($conn);

try {
    // Insert data into the request table
    $query_request = "INSERT INTO request (norequest, duedate, iduser, idsupplier, note, stat, taxrp, xamount, tax) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query_request);
    mysqli_stmt_bind_param($stmt, "sssissdds", $norequest, $duedate, $iduser, $idsupplier, $note, $stat, $taxrp, $xamount, $tax);
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Error inserting into request table: " . mysqli_stmt_error($stmt));
    }

    $idrequest = mysqli_insert_id($conn);

    // Insert data into the requestdetail table for each item
    $query_requestdetail = "INSERT INTO requestdetail (idrequest, idrawmate, qty, price, notes) VALUES (?, ?, ?, ?, ?)";
    $stmt_detail = mysqli_prepare($conn, $query_requestdetail);

    foreach ($idrawmate as $i => $rawmate) {
        $qty = normalizeNumber($weight[$i] ?? 0); // Normalize weight
        $product_price = normalizeNumber($price[$i] ?? 0); // Normalize price
        $product_note = mysqli_real_escape_string($conn, $notes[$i] ?? '');

        mysqli_stmt_bind_param($stmt_detail, "iiids", $idrequest, $rawmate, $qty, $product_price, $product_note);
        if (!mysqli_stmt_execute($stmt_detail)) {
            throw new Exception("Error inserting into requestdetail table: " . mysqli_stmt_error($stmt_detail));
        }
    }

    mysqli_commit($conn);

    // Close statements
    mysqli_stmt_close($stmt);
    mysqli_stmt_close($stmt_detail);

    // Redirect to index.php on success
    header("Location: index.php");
    exit;
} catch (Exception $e) {
    mysqli_rollback($conn);
    mysqli_close($conn);
    header("Location: error.php?msg=" . urlencode($e->getMessage()));
    exit;
}
