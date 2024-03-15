<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class GeneratePkliController extends CI_Controller {

public function __construct() {
    parent::__construct();
    // $this->load->model('M_generate');
    $this->cekOtentikasi();
}

public function cekOtentikasi() {
    if (!$this->session->userdata('username')) {
    redirect('home');
    }
}

public function generate() {
    // Load view generate.php
    $data['page'] = "generate";
	$data['judul'] = "Generate Packing List";
	$data['deskripsi'] = "For Generate Packing List";
    $data['error'] = $this->session->flashdata('error');
    $this->load->view('generate_pkli/generate',$data);
    // $this->template->views('generate_pkli/generate',$data);
}

// Fungsi validasi
public function validateInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Fungsi Copy to tbl_pkli
function copyToTblPkli($vKPNo, $vBuyerNo) {
// Periksa koneksi
    $db_error = $this->db->error();
    if ($db_error['code'] !== 0) {
        die("Koneksi ke tbl_pkli gagal: " . $db_error['message']);
    }

    $requestData = $this->handleRequest();
    $vKPNo = $requestData['vKPNo'];
    $vBuyerNo = $requestData['vBuyerNo'];
    $colors = $this->getColor();
    $id_karton = $requestData['id_karton'];
    $vDestNya = $this->getDest();
    $buyerCode = $this->getBuyerCode();
    // $itemCode = $this->getItem();

$check = "SELECT * FROM tbl_pkli WHERE kpno=? AND buyerno=?";
$resultCheck = $this->db->query($check, array($vKPNo, $vBuyerNo));

if ($resultCheck->num_rows() > 0) {
    $sqlDelete = "DELETE FROM tbl_pkli WHERE kpno=? AND buyerno=?";
    $this->db->query($sqlDelete, array($vKPNo, $vBuyerNo));
} else {
$sqlSize = "SELECT DISTINCT cart_no, size1, qty1, size2, qty2, size3, qty3, size4, qty4, size5, qty5, size6, qty6, size7, qty7, size8, qty8, size9, qty9, size10, qty10
            FROM tmpexppacklist
            WHERE kpno='$vKPNo' AND articleno IN ('$colors') AND buyerno='$vBuyerNo'
            ORDER BY CAST(SUBSTRING_INDEX(cart_no, '-', -1) AS UNSIGNED) ASC, cart_no ASC";

$resultSqlSize = $this->db->query($sqlSize);

if (!$resultSqlSize) {
    die("Error dalam mengeksekusi query get sizes and qty: " . $this->db->error());
}

foreach ($resultSqlSize->result_array() as $row) {
    $cartNo = $row['cart_no'];

    $noKartonRange = null;
    $noKarton = null;

    if (strpos($cartNo, '-') !== false) {
        list($start, $end) = explode('-', $cartNo);
        $start = (int)$start;
        $end = (int)$end;

        $noKartonRange = $cartNo;
        $noKarton = "(@counter := @counter + 1)";

        for ($i = $start; $i <= $end; $i++) {
            for ($j = 1; $j <= 10; $j++) {
                $sizeCol = "size" . $j;
                $qtyCol = "qty" . $j;

                $size = $row[$sizeCol];
                $qty = $row[$qtyCol];

                if ($size !== null && $qty > 0) {
                    $item = $this->getItemBySize($vKPNo, $vBuyerNo, $colors, $size);
                    $sqlCopy = "INSERT INTO tbl_pkli (kpno, no_karton_range, no_karton, buyerno, color, size, buyercode, item, dest, id_jenis_karton, qty_pack) 
                                VALUES ('$vKPNo', '$noKartonRange', $i, '$vBuyerNo', '$colors', '$size', '$buyerCode', '$item', '$vDestNya', '$id_karton', '$qty')";
                    $resultCopy = $this->db->query($sqlCopy);

                    if (!$resultCopy) {
                            $db_error = $this->db->error();
                            die("Error dalam mengeksekusi query copy to tbl_pkli: " . $db_error['message']);
                            }
                }
            }
        }
    } else {
        $noKartonRange = null;
        $noKarton = 1;

        for ($j = 1; $j <= 10; $j++) {
            $sizeCol = "size" . $j;
            $qtyCol = "qty" . $j;

            $size = $row[$sizeCol];
            $qty = $row[$qtyCol];
            

            if ($size !== null && $qty > 0) {
                $item = $this->getItemBySize($vKPNo, $vBuyerNo, $colors, $size);
                $sqlCopy = "INSERT INTO tbl_pkli (kpno, no_karton_range, no_karton, buyerno, color, size, buyercode, item, dest, id_jenis_karton, qty_pack) 
                            VALUES ('$vKPNo', '$noKartonRange', $noKarton, '$vBuyerNo', '$colors', '$size', '$buyerCode', '$item', '$vDestNya', '$id_karton', '$qty')";
                $resultCopy = $this->db->query($sqlCopy);

                if (!$resultCopy) {
                    die("Error dalam mengeksekusi query copy to tbl_pkli: " . $this->db->error);
                }
            }
        }
    }
}
}    
        $sqlUpdateNoKarton = "UPDATE tbl_pkli SET no_karton = (@counter := @counter + 1) WHERE kpno='$vKPNo' AND buyerno='$vBuyerNo' AND no_karton_range IS NOT NULL";
        $this->db->query("SET @counter = 0"); // Inisialisasi counter
        $this->db->query($sqlUpdateNoKarton);

        $this->db->close();
    }

function getBuyerCode() {
    $requestData = $this->handleRequest();
    $vKPNo = $requestData['vKPNo'];

    // Periksa koneksi
    $db_error = $this->db->error();
    if ($db_error['code'] !== 0) {
        die("Koneksi gagal: " . $db_error['message']);
    }

    $sql = "SELECT buyercode FROM sap_cfm WHERE kpno='$vKPNo'";
    $result = $this->db->query($sql);

    if (!$result) {
        die("Error dalam mengeksekusi query getBuyerCode: " . $this->db->error());
    }

    $row = $result->row_array();
    $buyerCode = $row['buyercode'];
    // $this->db->close();

    return $buyerCode;
}

public function getColor() {
    $requestData = $this->handleRequest();
    $vKPNo = $requestData['vKPNo'];

    $db_error = $this->db->error();
    if ($db_error['code'] !== 0) {
        die("Koneksi ke gagal: " . $db_error['message']);
    }

    $sql = "SELECT color FROM sap_cfm WHERE kpno='$vKPNo'";
    $result = $this->db->query($sql);

    if (!$result) {
        die("Error dalam mengeksekusi query getBuyerCode: " . $this->db->error());
    }

    $row = $result->row_array();
    $colors = $row['color'];
    // $this->db->close();

    return $colors;
}

private function getItemBySize($vKPNo, $vBuyerNo, $colors, $size) {
    $sql = "SELECT item FROM sap_cfm WHERE kpno='$vKPNo' AND buyerno='$vBuyerNo' AND color='$colors' AND size='$size'";
    $result = $this->db->query($sql);

    if (!$result) {
        die("Error dalam mengeksekusi query getItemBySize: " . $this->db->error());
    }

    $row = $result->row_array();
    $item = $row['item'];

    return $item;
}
public function getDest() {
$requestData = $this->handleRequest();
$vKPNo = $requestData['vKPNo'];
$vBuyerNo = $requestData['vBuyerNo'];

$db_error = $this->db->error();
if ($db_error['code'] !== 0) {
    die("Koneksi gagal: " . $db_error['message']);
}

$sql = "SELECT dest FROM sap_cfm WHERE kpno='$vKPNo' AND buyerno = '$vBuyerNo'";
$result = $this->db->query($sql);

if (!$result) {
    die("Error dalam mengeksekusi query getItem: " . $this->db->error());
}

$row = $result->row_array();
$dest = $row['dest'];

// $this->db->close();

return $dest;
}

public function handleRequest() {
    $vKPNo = $this->input->post('vKPNo');
    $vBuyerNo = $this->input->post('vBuyerNo');
    $vMaxPcsKarton = $this->input->post('vMaxPcsKarton');
    $selectedKarton = $this->input->post('karton');

    $pecah = explode("-", $selectedKarton);

    $jenis_karton = $pecah[3];
    $id_karton = $pecah[0];

    if (empty($vKPNo) || empty($vBuyerNo)) {
        die("Harap isi KP# dan No. Buyer dengan benar.");
    }

    return [
        'vKPNo' => $vKPNo,
        'vBuyerNo' => $vBuyerNo,
        'vMaxPcsKarton' => $vMaxPcsKarton,
        'jenis_karton' => $jenis_karton,
        'id_karton' => $id_karton
    ];
}

public function proses_hapus_data ()
{
    $requestData = $this->handleRequest();
    $vKPNo = $requestData['vKPNo'];
    $vBuyerNo = $requestData['vBuyerNo'];
    $sqlDelete = "DELETE FROM tmpexppacklist WHERE kpno=? AND buyerno=?";
    $this->db->query($sqlDelete, array($vKPNo, $vBuyerNo));
}

public function checkSize() {
    $txtKP = $this->input->post('txtKP');
    $txtPOBuyer = $this->input->post('txtPOBuyer');

    if (empty($txtKP)) {
        echo json_encode(array('error' => 'KP tidak boleh kosong'));
        return;
    }

    if (empty($txtPOBuyer)) {
        echo json_encode(array('error' => 'PO Buyer tidak boleh kosong'));
        return;
    }

    $sql = "SELECT a.size, s.urut, SUM(a.qty_order) AS qty_order FROM sap_cfm a LEFT JOIN mastersize s ON a.size = s.size " .
           "WHERE kpno = '$txtKP' AND buyerno = '$txtPOBuyer' " .
           "GROUP BY a.size ORDER BY s.urut";

    $result = $this->db->query($sql);

    if (!$result) {
        echo json_encode(array('error' => 'Error dalam mengeksekusi query'));
        return;
    }

    if ($result->num_rows() == 0) {
        echo json_encode(array('error' => 'Tidak ada ukuran yang ditemukan untuk KP dan PO Buyer yang diberikan'));
        return;
    }

    $sizes = $result->result_array();

    $modalContent = '<div class="modal fade" id="sizeModal" tabindex="-1" role="dialog" aria-labelledby="sizeModalLabel" aria-hidden="true">';
    $modalContent .= '<div class="modal-dialog" role="document">';
    $modalContent .= '<div class="modal-content">';
    $modalContent .= '<div class="modal-header">';
    $modalContent .= '<h5 class="modal-title" id="sizeModalLabel">Ukuran</h5>';
    $modalContent .= '<button type="button" class="close" data-dismiss="modal" aria-label="Close">';
    $modalContent .= '<span aria-hidden="true">&times;</span>';
    $modalContent .= '</button>';
    $modalContent .= '</div>';
    $modalContent .= '<div class="modal-body"><ul>';

    foreach ($sizes as $size) {
        $modalContent .= '<li>' . $size['size'] . '</li>';
    }

    $modalContent .= '</ul></div>'; // Close modal-body
    $modalContent .= '<div class="modal-footer">';
    $modalContent .= '<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>';
    // $modalContent .= '<button type="button" class="btn btn-secondary closeModal">Close</button>';
    $modalContent .= '</div>'; // Close modal-footer
    $modalContent .= '</div>'; // Close modal-content
    $modalContent .= '</div>'; // Close modal-dialog
    $modalContent .= '</div>'; // Close modal

    echo json_encode(array('modalContent' => $modalContent));
}

public function getSizeData($vKPNo, $vBuyerNo) {
    $sql = "SELECT s.size, s.urut, SUM(sc.qty_order) AS qty_order 
            FROM sap_cfm sc 
            LEFT JOIN mastersize s ON sc.size = s.size 
            WHERE sc.kpno = '$vKPNo' AND sc.buyerno = '$buyerno' 
            GROUP BY s.size 
            ORDER BY s.urut";

    $result = $this->db->query($sql);

    if (!$result) {
        return array('error' => 'Error dalam mengeksekusi query');
    }

    if ($result->num_rows() == 0) {
        return array('error' => 'Tidak ada ukuran yang ditemukan untuk KP dan PO Buyer yang diberikan');
    }

    $sizes = array();

    foreach ($result->result_array() as $row) {
        $sizes[] = array(
            'size' => $row['size'],
            'urut' => $row['urut'],
            'qty_order' => $row['qty_order']
        );
    }

    return $sizes;
}

public function LvSize($vKPNo, $vBuyerNo) {
    $sql = "SELECT s.size, s.urut, SUM(sc.qty_order) AS qty_order 
            FROM sap_cfm sc 
            LEFT JOIN mastersize s ON sc.size = s.size 
            WHERE sc.kpno = '$vKPNo' AND sc.buyerno = '$vBuyerNo' 
            GROUP BY s.size 
            ORDER BY s.urut";

    $result = $this->db->query($sql);

    if (!$result) {
        return array('error' => 'Error dalam mengeksekusi query');
    }

    if ($result->num_rows() == 0) {
        return array('error' => 'Tidak ada ukuran yang ditemukan untuk KP dan PO Buyer yang diberikan');
    }

    $LvSize = array();

    foreach ($result->result_array() as $row) {
        $LvSize[] = array(
            'size' => $row['size'],
            'urut' => $row['urut'],
            'qty_order' => $row['qty_order']
        );
    }

    return $LvSize;
}


public function process()
{
    $requestData = $this->handleRequest();
    $vKPNo = $requestData['vKPNo'];
    $vBuyerNo = $requestData['vBuyerNo'];
    $vMaxPcsKarton = $requestData['vMaxPcsKarton'];
    $jenis_karton = $requestData['jenis_karton'];
    // Pengecekan data sebelum eksekusi query
    $sqlCheck = "SELECT * FROM tmpexppacklist WHERE kpno=? AND buyerno=? AND shipmode='10'";
    $resultCheck = $this->db->query($sqlCheck, array($vKPNo, $vBuyerNo));

    if (!$resultCheck) {
        die("Error in query: " . $this->db->error());
    }

if ($resultCheck->num_rows() > 0) {
    echo '<script>';
    echo 'if (confirm("Data sudah ada. Apakah Anda ingin membuat data baru?")) {';

    echo '  var xhr = new XMLHttpRequest();';
    echo '  xhr.open("POST", "proses_hapus_data", true);';
    echo '  xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");';
    echo '  xhr.onreadystatechange = function() {';
    echo '    if (xhr.readyState == 4 && xhr.status == 200) {';
    echo '      window.location.href = "generate";';
    echo '    }';
    echo '  };';
    echo '  xhr.send("vKPNo=' . $vKPNo . '&vBuyerNo=' . $vBuyerNo . '");';
    
    echo '} else {';
    echo '  alert("Proses pembuatan data baru dibatalkan.");';
    echo '      window.location.href = "generate";';  // 
    echo '}';
    echo '</script>';
    } else {
        if ($resultCheck === false) {
            echo '<script>';
            echo 'alert("Gagal membuat data baru.");';
            echo '</script>';
        } else {
            $LvSize = $this->LvSize($vKPNo, $vBuyerNo);
            // Mulai Hitung
            $sqlColor = "SELECT DISTINCT color FROM sap_cfm WHERE kpno='$vKPNo' AND buyerno='$vBuyerNo' ORDER BY color";
            // echo "Query Color: $sqlColor";
            $resultColor = $this->db->query($sqlColor);
            // var_dump($resultColor);

            if (!$resultColor) {
                die("Error dalam query: " . $this->db->error());
            }

            $dataPerhitungan = "";

            //Start Perhitungan
            foreach ($resultColor->result_array() as $rowColor) {
                $vColorNya = $rowColor['color'];
                $colors = array();
                // echo "Inside first loop: $vColorNya";

                $colors[] = $vColorNya;
                // echo "Before query execution";
                $sqlSize = "SELECT a.*, SUM(a.qty_order) AS qty FROM sap_cfm a
                            INNER JOIN mastersize s ON a.size = s.size
                            WHERE a.kpno='$vKPNo' AND a.buyerno='$vBuyerNo' AND a.color='$vColorNya'
                            GROUP BY a.size ORDER BY s.urut";
                $resultSize = $this->db->query($sqlSize);
                // var_dump($resultSize);
                
                if ($resultSize === false) {
                    die("Error dalam query: " . $this->db->error());
                }
                // echo "After query execution";

                if (!$resultSize) {
                    die("Error dalam query: " . $this->db->error());
                }

                $vCartNo = 0;
                $vCartNo2 = 0;
                $Number_Size = 1;
                $vSize = "";

                $lvExpPackList = [];
                // $A = 0;
                foreach ($resultSize->result_array() as $rowSize) {
                    $vQty = $rowSize['qty'];
                    if (($vQty) >= ($vMaxPcsKarton)) {
                        $vCartNo = $vCartNo2 + 1;
                        $jml_karton = 0;


                        while (($vQty) >= ($vMaxPcsKarton)) {
                            $vQty = ($vQty) - ($vMaxPcsKarton);
                            $vCartNo2 = $vCartNo2 + 1;
                            $jml_karton = $jml_karton + 1;
                        
                            // Tambahkan pernyataan echo untuk menampilkan informasi
                            echo "Iterasi: vQty = $vQty, vCartNo2 = $vCartNo2, jml_karton = $jml_karton<br>";
                        }
                        

                        if (($vCartNo2) == ($vCartNo)) {
                            $vCartNo3 = $vCartNo2;
                            // Tambahkan pernyataan echo untuk menampilkan informasi
                            echo "Kondisi if terpenuhi: vCartNo3 = $vCartNo3<br>";
                        } else {
                            $vCartNo3 = $vCartNo . "-" . $vCartNo2;
                            // Tambahkan pernyataan echo untuk menampilkan informasi
                            echo "Kondisi else terpenuhi: vCartNo3 = $vCartNo3<br>";
                        }                        

                        $vQty1 = "qty" . $Number_Size;
                        $vDestNya = $rowSize['dest'];
                        $vNoPackList = 0;
                        $vShipMode = 10;
                        // global $vMaxPcsKarton;

                        $sqlInsert = "INSERT INTO tmpexppacklist (kpno, " . $vQty1 . ", jml_karton, cart_no, ip, buyerno, articleno, nopacklist, shipmode, dest, jenis_karton,maxpcs)

                            VALUES ('$vKPNo', $vMaxPcsKarton, $jml_karton, '$vCartNo3', '127.0.0.1', '$vBuyerNo', '$vColorNya', $vNoPackList, '$vShipMode', '$vDestNya', '$jenis_karton','$vMaxPcsKarton')";

                        var_dump("Sql Insert Mulai Hitung",$sqlInsert);

                        $this->db->query($sqlInsert);
                        // var_dump("Sql Insert Setelah Eksekusi", $sqlInsert);

                        if ($this->db->error()) {
                            $errorMessage = $this->db->error();
                            echo "Error executing query: " . $errorMessage['message'];
                        } else {
                            echo "Query executed successfully!";
                        }

                        // // Hitung sisaan dengan menyimpan nilai desimal
                        // $sisaan = fmod($vQty, $vMaxPcsKarton);

                        // $lvExpPackList[] = $sisaan;
                        // Ambil data ukuran dari database

                        // // $LvSize = $this->LvSize($vKPNo, $vBuyerNo);
                        $resultSizeArray = $resultSize->result_array();
                        // $resultSizeArray[0]['size'];
                        // var_dump('ini isi dari',$resultSizeArray);

                        if (($vQty) < ($vMaxPcsKarton)) {
                            $A = 0;
                            while ($A < count($LvSize)) {
                                if ($LvSize[$A]['size'] == $resultSizeArray[0]['size']) {
                                    $vSize = $LvSize[$A]['size'];
                                    $qtyOrder = $LvSize[$A]['qty_order'];
                                    var_dump("vSize Looping 1", $vSize);
                                    var_dump("Qty Order 1", $qtyOrder);
                                }
                                $A++;
                            }
                        } else {
                            $A = 0;
                            while ($A < count($LvSize)) {
                                if ($LvSize[$A]['size'] == $resultSizeArray[$A]['size']) {
                                    $vSize = $LvSize[$A]['size'];
                                    var_dump("vSize Looping ke-2", $vSize);
                                }
                                $A++;
                            }
                        }

                        // // Isi array $lvExpPackList dengan data
                        // for ($i = 0; $i < 5; $i++) {
                        //     $vSize = "Size " . ($i + 1);
                        //     $vQty = $i + 1;

                        //     // Membuat array asosiatif untuk setiap iterasi
                        //     $sisaan = array(
                        //         'vSize' => $vSize,
                        //         'vQty' => $vQty
                        //     );

                        //     // Menambahkan array $sisaan ke dalam $lvExpPackList
                        //     $lvExpPackList[] = $sisaan;
                        // }
                        // var_dump("sisaan lvexpacklist",$lvExpPackList);
                        // Membuat array asosiatif untuk setiap iterasi
                        $sisaan = array(
                            'vSize' => $vSize,
                            'vQty' => $vQty
                        );

                        // Menambahkan array $sisaan ke dalam $lvExpPackList
                        $lvExpPackList[] = $sisaan;
                        // var_dump("sisaan lvexpacklist",$lvExpPackList);

                        // bagian sini

                        // // Hitung sisaan dengan menyimpan nilai desimal
                        // $sisaan = fmod($vQty , $vMaxPcsKarton);
                        // echo "Sisaan Perhitungan disini: " . $sisaan;

                        // // Simpan nilai sisaan ke dalam lvExpPackList
                        // $lvExpPackList[] = $sisaan;
                        // var_dump("LvExpPacklist sisaan",$lvExpPackList);

                        // // Update nilai size pada tabel tmpexppacklist
                        // $vSizeNya = "size" . $Number_Size;
                        // //var_dump("Value of vSizeNya:", $vSizeNya);
                        // $sqlUpdateSize = "UPDATE tmpexppacklist SET $vSizeNya = '$vSize' WHERE kpno='$vKPNo' AND buyerno='$vBuyerNo' AND articleno='$vColorNya'";
                        // // echo "SQL Update Query: $sqlUpdateSize";
                        // $resultUpdateSize = $this->db->query($sqlUpdateSize);

                        // if (!$resultUpdateSize) {
                        //     die("Error dalam mengeksekusi query update size: " . $this->db->error());
                        // }
                        
                        // $dataPerhitungan .= "Color: $vColorNya, Size: {$rowSize['size']}, Qty: $vQty1, Dest: $vDestNya\n";
                    }
                    // $LvSize[] = ['Text' => $rowSize['size'], 'Value' => $rowSize['size']];
                    $A++;
                    $Number_Size = $Number_Size + 1;
                }
//Sisaan
// Inisialisasi variabel $A
$A = 0;

// Penggunaan sintaksis yang benar untuk loop foreach
// Inisialisasi variabel $A
$A = 1;

// Iterasi melalui array $lvExpPackList menggunakan foreach
foreach ($lvExpPackList as $sisaan) {
    // Debug setelah iterasi pertama
    var_dump("after looping first sisaan", $lvExpPackList);

    // Assign nilai ke variabel $Number_Size
    $Number_Size = $A;

    // Menampilkan informasi iterasi
    echo "Inside second loop: $A\n";

    // Periksa apakah elemen dengan indeks $A - 1 ada dalam array $lvExpPackList
    if (isset($lvExpPackList[$A - 1])) {
        // Mengakses nilai 'vSize' dari array $sisaan
        $vSize = $sisaan['vSize'];
        echo "Size: $vSize\n";

        // Mengakses nilai 'vQty' dari array $sisaan
        $vQty = $sisaan['vQty'];
        echo "Quantity: $vQty\n";

        // Periksa apakah nilai 'vSize' adalah angka yang valid
        if (($vSize) > 0) {
            // Jika ya, lakukan operasi berikut
            $vMaxPcsKartonSisa = $vSize; // Contoh operasi dengan menggunakan nilai 'vSize'
            echo "Max Pcs Karton Sisa: $vMaxPcsKartonSisa\n";

            $vCartNo = $vCartNo2++;
            echo "Ini vcartno2: $vCartNo2\n";

            $vQty1 = "qty" . $A;
            echo "didalam vqty1 sisaan: $vQty1\n";

            // Bangun query SQL untuk INSERT
            $sqlInsertSisaan = "INSERT INTO tmpexppacklist (kpno, $vQty1, jml_karton, cart_no, ip, buyerno, articleno, nopacklist, shipmode, dest, jenis_karton, maxpcs)
                VALUES ('$vKPNo', $vQty, 1, '$vCartNo2', '127.0.0.1', '$vBuyerNo', '$vColorNya', $vNoPackList, '$vShipMode', '$vDestNya', '$jenis_karton', '$vMaxPcsKartonSisa')";

            // Debug SQL INSERT
            var_dump("sql insert sisaan", $sqlInsertSisaan);

            // Eksekusi query INSERT ke database
            $this->db->query($sqlInsertSisaan);

            // Periksa apakah ada kesalahan saat menjalankan query
            if ($this->db->error()) {
                $errorMessage = $this->db->error();
                echo "Error executing query: " . $errorMessage['message'];
            } else {
                echo "Query executed successfully!\n";
            }
            $LvSize[$A - 1]['size'];
            var_dump("Ahaaa",$LvSize);

            // Bangun query SQL untuk UPDATE
            $vSizeNya = "size" . $A;
            $sqlUpdateSizeSisaan = "UPDATE tmpexppacklist SET $vSizeNya = '{$LvSize[$A - 1]['size']}' WHERE kpno='$vKPNo' AND buyerno='$vBuyerNo' AND articleno='$vColorNya' AND cart_no='$vCartNo2'";

            // Debug SQL UPDATE
            // var_dump("size sisaan", $sqlUpdateSizeSisaan);

            // Eksekusi query UPDATE ke database
            $resultUpdateSizeSisaan = $this->db->query($sqlUpdateSizeSisaan);

            // Periksa apakah ada kesalahan saat menjalankan query UPDATE
            if (!$resultUpdateSizeSisaan) {
                die("Error dalam mengeksekusi query update size sisaan: " . $this->db->error());
            }
        }
    } else {
        // Tampilkan pesan jika elemen dengan indeks $A - 1 tidak ditemukan di dalam $lvExpPackList
        echo "Elemen dengan indeks " . ($A - 1) . " tidak ditemukan di dalam \$lvExpPackList\n";
        break;
    }

    // Increment variabel $A untuk iterasi selanjutnya
    $A++;
}


foreach ($LvSize as $key => $value) {
    $vSz = "size" . ($key + 1); // Memperoleh nama kolom dari indeks array
    $IPx = '127.0.0.1';
    $vShipMode = 10;
    $vShipModeWIP = 10;

    $vSizeText = $value['size'];
    echo "sizeText", $vSizeText;

    $sql = "UPDATE tmpexppacklist SET $vSz = '$vSizeText' WHERE ip='$IPx'" .
        " AND kpno='$vKPNo' AND buyerno='$vBuyerNo' AND articleno='$vColorNya' AND shipmode='" . ($vShipMode === "" ? $vShipModeWIP : $vShipMode) . "'";

    // var_dump("SQL UPDATE", $sql);

    // Eksekusi query ke database PHP di sini
    $result = $this->db->query($sql);

    // Tambahkan penanganan kesalahan jika perlu
    if (!$result) {
        die("Error executing query: " . $this->db->error());
    } else {
        // Log bahwa elemen ditemukan dan diupdate
        echo "SQL UPDATE berhasil: " . $sql . "\n";
        // echo "SQL UPDATE berhasil:";
    }
    $key++;
}

            }

            // $resultColor->close();
            
            $this->copyToTblPkli($vKPNo, $vBuyerNo);

            $this->db->close();
            
            echo '<script>';
            echo 'var dataPerhitungan = ' . json_encode($dataPerhitungan) . ';';
            echo 'alert("Proses perhitungan berhasil!\\n\\nHasil Perhitungan:\\n" + dataPerhitungan);';
            // echo '  window.location.href = "generate";';
            echo '</script>';
            $this->session->set_flashdata('success', 'Data Berhasil dibuat!');
            }
        }
    }
}
