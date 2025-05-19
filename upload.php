<?php
require_once __DIR__ . '/vendor/autoload.php';

session_start();

$chunk = $_FILES['chunk']['tmp_name'];
$index = $_POST['index'];
$total = $_POST['total'];
$filename = $_POST['filename'];
$filesize = $_POST['filesize'];

$tempDir = __DIR__ . '/tmp/' . md5($filename . $filesize);
if (!file_exists($tempDir)) mkdir($tempDir, 0777, true);

// Lưu chunk vào thư mục tạm
move_uploaded_file($chunk, "$tempDir/$index.part");

// Nếu chưa đủ chunk thì chờ tiếp
if (count(glob("$tempDir/*.part")) < $total) {
  echo json_encode(["status" => "ok", "message" => "Chunk $index received"]);
  exit;
}

// Gộp chunk thành 1 file
$combined = "$tempDir/combined_" . time();
$fp = fopen($combined, 'w');
for ($i = 0; $i < $total; $i++) {
  $part = file_get_contents("$tempDir/$i.part");
  fwrite($fp, $part);
}
fclose($fp);

// Tải lên Google Drive
try {
  $client = new Google_Client();
  $client->setAuthConfig(__DIR__ . '/service-account.json');
  $client->addScope(Google_Service_Drive::DRIVE);
  $drive = new Google_Service_Drive($client);

  $file = new Google_Service_Drive_DriveFile([
    'name' => $filename,
    'parents' => ['1l01tNgJzzCBIDGUaH2CONuNPJDOUVCZG'] // Thay bằng ID thư mục Drive bạn đã chia sẻ
  ]);

  $content = file_get_contents($combined);
  $drive->files->create($file, [
    'data' => $content,
    'mimeType' => mime_content_type($combined),
    'uploadType' => 'media'
  ]);

  // Xoá file tạm
  array_map('unlink', glob("$tempDir/*.part"));
  unlink($combined);
  rmdir($tempDir);

  echo json_encode(["status" => "ok", "message" => "File uploaded"]);
} catch (Exception $e) {
  echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
