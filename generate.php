<!-- generate.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Pengolahan Data</title>
    <link rel="stylesheet" href="<?= base_url();?>assets/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h2 class="mb-4">Form Pengolahan Data</h2>

        <!-- Tambahkan bagian untuk menangkap pesan flash -->
        <?php if ($error = $this->session->flashdata('error')): ?>
            <div class="alert alert-danger" role="alert">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <?php
        // Tampilkan flash message jika ada
        if ($this->session->flashdata('success')) {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">';
            echo $this->session->flashdata('success');
            echo '<button type="button" class="close" data-dismiss="alert" aria-label="Close">';
            echo '<span aria-hidden="true">&times;</span>';
            echo '</button>';
            echo '</div>';
        }
        ?>


        <form action="<?= base_url('GeneratePkliController/process')?>" method="post"onsubmit="return validateForm()">
            <div class="form-group">
                <label for="vKPNo">KP.NO:</label>
                <input type="text" class="form-control" name="vKPNo" id="vKPNo" autocomplete="off" required>
            </div>
            <div class="form-group">
                <label for="vBuyerNo">Po. Buyer:</label>
                <input type="text" class="form-control" name="vBuyerNo" id="vBuyerNo" autocomplete="off" required>
            </div>
            <div class="form-group">
                <label for="vMaxPcsKarton">Masukkan Max Pcs Per Karton:</label>
                <input type="text" class="form-control" name="vMaxPcsKarton" id="vMaxPcsKarton" autocomplete="off" required>
            </div>
            <div class="form-group">
                <label for="choose_karton">Choose a karton:</label>
                <select class="form-control" name="karton" id="vKarton">
                    <?php
                    // Ambil data karton dari database
                    $servername = "localhost";
                    $username = "root";
                    $password = "";
                    $dbname = "convert_vb";
                    $conn = new mysqli($servername, $username, $password, $dbname);

                    if ($conn->connect_error) {
                        die("Koneksi gagal: " . $conn->connect_error);
                    }

                    $sqlKarton = "SELECT id_karton, buyer, jenis_karton, berat_karton, cbm_karton FROM tbl_karton";
                    $resultKarton = $conn->query($sqlKarton);
                    if ($resultKarton->num_rows > 0) {
                        while ($rowKarton = $resultKarton->fetch_assoc()) {
                            echo '<option value="' . $rowKarton['id_karton'] . '-' . $rowKarton['buyer'] . '-' . $rowKarton['jenis_karton'] . '-' . $rowKarton['berat_karton'] . '-' . $rowKarton['cbm_karton'] . '">'
                                .$rowKarton['buyer'] . ' : ' . $rowKarton['jenis_karton'] . ', Berat Karton: ' . $rowKarton['berat_karton'] . ', CBM Karton: ' . $rowKarton['cbm_karton']
                                . '</option>';
                        }
                    }

                    // $conn->close();
                    ?>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Proses Data</button>
            <button type="button" class="btn btn-primary" id="btnCheckSize">Check Size</button>
        </form>
        <a href="<?= base_url('Home'); ?>">Back</a>
    </div>


    <script src="<?= base_url();?>assets/plugins/jQuery/jquery-3.3.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
    <script src="<?=base_url();?>assets/js/bootstrap.min.js"></script>
    <script>
        // Tambahkan event listener untuk tombol Check Size
    $('#btnCheckSize').click(function() {
        // Panggil fungsi checkSize saat tombol ditekan
        checkSize();
    });

    // Definisikan fungsi checkSize
function checkSize() {
    console.log("Check Size button clicked");
    var txtKPValue = $('#vKPNo').val(); // Ambil nilai dari input KP NO
    var txtPOBuyerValue = $('#vBuyerNo').val(); // Ambil nilai dari input Buyer NO

    // Validasi input KP NO
    if (txtKPValue.trim() === "") {
        alert("KP.NO harus diisi sebelum memeriksa ukuran.");
        return; // Hentikan fungsi jika input KP NO kosong
    }

    // Validasi input Buyer NO
    if (txtPOBuyerValue.trim() === "") {
        alert("Po. Buyer harus diisi sebelum memeriksa ukuran.");
        return; // Hentikan fungsi jika input Buyer NO kosong
    }

    // Kirim permintaan Ajax untuk mendapatkan ukuran
    $.ajax({
        type: 'POST',
        url: '<?php echo base_url('GeneratePkliController/checkSize'); ?>',
        data: { txtKP: txtKPValue, txtPOBuyer: txtPOBuyerValue },
        dataType: 'json', // Tentukan tipe data yang diharapkan adalah JSON
        success: function(response) {
            console.log("Ajax request sent");
            console.log("Respons dari server:", response);
            
            // Cek jika respons dari server berisi pesan error
            if (response.error) {
                displayErrorModal(response.error);
            } else {
                // Tampilkan modal HTML yang diterima dari server
                $('body').append(response.modalContent);
                $('#sizeModal').modal('show');
            }
        },
        error: function() {
            // Tangani kesalahan jika ada
            alert('Terjadi kesalahan saat meminta ukuran.');
        }
    });
}

// Fungsi untuk menampilkan modal pesan kesalahan
function displayErrorModal(errorMessage) {
    // Buat modal HTML untuk pesan kesalahan
    var modalContent = '<div class="modal fade" id="errorModal" tabindex="-1" role="dialog" aria-labelledby="errorModalLabel" aria-hidden="true">';
    modalContent += '<div class="modal-dialog" role="document">';
    modalContent += '<div class="modal-content">';
    modalContent += '<div class="modal-header">';
    modalContent += '<h5 class="modal-title" id="errorModalLabel">Error</h5>';
    modalContent += '<button type="button" class="close" data-dismiss="modal" aria-label="Close">';
    modalContent += '<span aria-hidden="true">&times;</span>';
    modalContent += '</button>';
    modalContent += '</div>';
    modalContent += '<div class="modal-body">';
    modalContent += errorMessage;
    modalContent += '</div>'; // Close modal-body
    modalContent += '<div class="modal-footer">';
    modalContent += '<button type="button" class="btn btn-secondary closeModal" data-dismiss="modal">Close</button>';
    modalContent += '</div>'; // Close modal-footer
    modalContent += '</div>'; // Close modal-content
    modalContent += '</div>'; // Close modal-dialog
    modalContent += '</div>'; // Close modal

    // Hapus modal sebelumnya jika ada
    $('#errorModal').remove();

    // Tambahkan modal baru ke dalam body
    $('body').append(modalContent);

    // Tampilkan modal
    $('#errorModal').modal('show');
}

// Tangkap peristiwa klik pada tombol "Close" dengan class 'closeModal'
// $(document).on('click', '.closeModal', function() {
//     // Saat tombol "Close" diklik, lakukan reload halaman
//     location.reload();
// });



        // Fungsi validasi form
        function validateForm() {
            var vKPNo = document.getElementById('vKPNo').value;
            var vBuyerNo = document.getElementById('vBuyerNo').value;
            var vMaxPcsKarton = document.getElementById('vMaxPcsKarton').value;

            // Validasi vKPNo
            if (vKPNo.trim() === "") {
                alert("KP.NO harus diisi.");
                return false;
            }

            // Validasi vBuyerNo
            if (vBuyerNo.trim() === "") {
                alert("Po. Buyer harus diisi.");
                return false;
            }

            // Validasi vMaxPcsKarton
            if (isNaN(vMaxPcsKarton) || vMaxPcsKarton <= 0) {
                alert("Masukkan Max Pcs Per Karton dengan benar.");
                return false;
            }

            // Jika semua validasi berhasil, kembalikan true
            return true;
        }
    </script>
</body>
</html>
