<!DOCTYPE html>
<html lang='en-GB'>

<head>
    <title>PHP 09D</title>
</head>

<body>
    <?php include 'dbconn.php';
    echo "<h2>Data pada Tabel Publikasi (While loop)</h2>\n";
    // $result = $pdo->query("select * from publikasi");
    $result = $pdo->query("select * from publikasi ORDER BY no ASC");
    echo "Rows retrieved: " . $result->rowcount() . "<br><br>\n";
    while ($row = $result->fetch()) {
        echo "No: " . $row["no"] . "<br>\n";
        echo "Judul: " . $row["judul"] . "<br>\n";
        echo "Tanggal Rilis: " . $row["tanggal_rilis"] . "<br>\n";
        echo "Sampul: " . $row["sampul"] . "<br><br>\n";
    }
    echo "<h2>Data pada Tabel Publikasi (Foreach loop)</h2>\n";
    // $result = $pdo->query("select * from publikasi");
    $result = $pdo->query("select * from publikasi ORDER BY no ASC");
    foreach ($result as $row) {
        echo "No: " . $row["no"] . "<br>\n";
        echo "Judul: " . $row["judul"] . "<br>\n";
        echo "Tanggal Rilis: " . $row["tanggal_rilis"] . "<br>\n";
        echo "Sampul: " . $row["sampul"] . "<br><br>\n";
    }
    ?>
</body>

</html>