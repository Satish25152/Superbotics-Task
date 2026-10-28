
<?php
class Report {
    public float $total = 0;
}

$r = new Report();

$conn = mysqli_connect("localhost", "root", "", "shop");

if (!$conn) {
    die("Database connection failed");
}

$cat = $_GET['cat'] ?? '';

$stmt = mysqli_prepare(
    $conn,
    "SELECT amount, note FROM orders WHERE category = ?"
);

mysqli_stmt_bind_param($stmt, "s", $cat);
mysqli_stmt_execute($stmt);

$res = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($res)) {
    $r->total += (float) $row['amount'];

    echo strlen($row['note'] ?? '') . "<br>";
}

$sorted = function ($a, $b) {
    return $a <=> $b;
};

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
