<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if (!isset($_SESSION['idusers'])) {
   header("location: login.php");
   exit();
}

if (isset($_GET['id']) && isset($_GET['idso'])) {
   $idtally = intval($_GET['id']);
   $idso = intval($_GET['idso']);
   $iduser = $_SESSION['idusers'];

   // Cek status tally
   $stmtCheck = $conn->prepare("SELECT stat, notally FROM tally WHERE idtally = ?");
   if (!$stmtCheck) {
      die("Prepare failed: " . $conn->error);
   }
   $stmtCheck->bind_param("i", $idtally);
   $stmtCheck->execute();
   $resultCheck = $stmtCheck->get_result();
   $rowCheck = $resultCheck->fetch_assoc();
   $stmtCheck->close();

   if (!$rowCheck) {
      header("location: index.php?message=not_found");
      exit();
   }

   if ($rowCheck['stat'] === "Approved" || $rowCheck['stat'] === "DO") {
      header("location: index.php?message=status_invalid");
      exit();
   }

   $notally = $rowCheck['notally'];

   if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
      $sealnumb = !empty($_POST['sealnumb']) ? htmlspecialchars(trim($_POST['sealnumb']), ENT_QUOTES, 'UTF-8') : NULL;

      $conn->autocommit(false);

      try {
         // Ambil deliverydate dari salesorder berdasar idso
         $stmtDelivery = $conn->prepare("SELECT deliverydate FROM salesorder WHERE idso = ?");
         if (!$stmtDelivery) {
            throw new Exception("Prepare failed (deliverydate): " . $conn->error);
         }
         $stmtDelivery->bind_param("i", $idso);
         $stmtDelivery->execute();
         $stmtDelivery->bind_result($deliverydate);
         $stmtDelivery->fetch();
         $stmtDelivery->close();

         // Update status dan deliverydate di tally
         $stmt = $conn->prepare("UPDATE tally SET stat = 'Approved', sealnumb = ?, deliverydate = ? WHERE idtally = ?");
         if (!$stmt) {
            throw new Exception("Prepare failed (update tally): " . $conn->error);
         }
         $stmt->bind_param("ssi", $sealnumb, $deliverydate, $idtally);
         if (!$stmt->execute()) {
            throw new Exception("Execute failed (update tally): " . $stmt->error);
         }
         $stmt->close();

         // Update progress di salesorder
         $stmt_so = $conn->prepare("UPDATE salesorder SET progress = 'DRAFT' WHERE idso = ?");
         if (!$stmt_so) {
            throw new Exception("Prepare failed (update salesorder): " . $conn->error);
         }
         $stmt_so->bind_param("i", $idso);
         if (!$stmt_so->execute()) {
            throw new Exception("Execute failed (update salesorder): " . $stmt_so->error);
         }
         $stmt_so->close();

         // Simpan log aktivitas
         $event = "Approved Tally";
         $stmtLog = $conn->prepare("INSERT INTO logactivity (iduser, event, docnumb) VALUES (?, ?, ?)");
         if (!$stmtLog) {
            throw new Exception("Prepare failed (logactivity): " . $conn->error);
         }
         $stmtLog->bind_param("iss", $iduser, $event, $notally);
         if (!$stmtLog->execute()) {
            throw new Exception("Execute failed (logactivity): " . $stmtLog->error);
         }
         $stmtLog->close();

         $conn->commit();

         header("location: index.php?message=success");
         exit();
      } catch (Exception $e) {
         $conn->rollback();
         echo "Terjadi kesalahan: " . $e->getMessage();
         exit();
      } finally {
         $conn->autocommit(true);
      }
   } else {
?>
      <!DOCTYPE html>
      <html>

      <head>
         <title>Approve Tally</title>
         <script>
            function showPopup() {
               document.getElementById('popupForm').style.display = 'block';
               document.getElementById('sealnumb').focus();
            }

            function hidePopup() {
               document.getElementById('popupForm').style.display = 'none';
               window.location.href = 'index.php';
            }
         </script>
         <style>
            #popupForm {
               display: none;
               position: fixed;
               top: 50%;
               left: 50%;
               transform: translate(-50%, -50%);
               border: 1px solid #ccc;
               padding: 20px;
               background: #fff;
               z-index: 1000;
               box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);
               max-width: 90%;
            }

            #popupFormOverlay {
               position: fixed;
               top: 0;
               left: 0;
               width: 100%;
               height: 100%;
               background: rgba(0, 0, 0, 0.5);
               z-index: 999;
            }

            button {
               margin-right: 10px;
            }
         </style>
      </head>

      <body onload="showPopup()">
         <div id="popupFormOverlay"></div>
         <div id="popupForm">
            <form method="post" action="">
               <label for="sealnumb">Isi Nomor Segel (Jika Ada):</label><br>
               <input type="text" id="sealnumb" name="sealnumb" autocomplete="off" /><br><br>
               <button type="submit" name="submit" class="btn btn-sm btn-primary">Submit</button>
               <button type="button" onclick="hidePopup()">Cancel</button>
            </form>
         </div>
      </body>

      </html>
<?php
      exit();
   }
} else {
   header("location: index.php?message=invalid_id");
   exit();
}

$conn->close();
