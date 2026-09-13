<?php
// Sesuai dengan path konfigurasi di repo Anda
include '../../config/dbconn.php';

try {
    $keyword = $_GET["keyword"] ?? '';
    $searchQuery = "%" . $keyword . "%";
    
    // Sesuaikan kueri ke tabel publikasi
    $sql = "SELECT judul FROM publikasi WHERE judul LIKE :keyword LIMIT 5";
    $stmt_query = $pdo->prepare($sql);
    $stmt_query->bindParam(':keyword', $searchQuery);
    $stmt_query->execute();
    
    $stmt = $stmt_query->fetchAll(PDO::FETCH_ASSOC);

    if ($stmt) {
        echo json_encode($stmt);
    } else {
        $response[] = array('judul' => 'no suggestion');
        echo json_encode($response);
    }
    
} catch (PDOException $e) {
    error_log('PDO Error (page11A_gethint.php): ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([]);
}
?>
