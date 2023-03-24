<?php
$conn = mysqli_connect($_SERVER["SERVER_NAME"], "root", "", "ekur");
// bulan
$_GET["bulan"] = isset($_GET["bulan"])?$_GET["bulan"]:date("Y-m");
$bulan = explode("-", $_GET["bulan"])[1]."/".explode("-", $_GET["bulan"])[0];

// kelas
$kelas = ["X DKV 1", "X DKV 2", "X DKV 3"];
$selectedKelas = in_array($_GET["kelas"], $kelas) ? $_GET["kelas"] : "";
$selectedKelas = mysqli_real_escape_string($conn, $selectedKelas);

// ambil kode pelanggaran
$data = mysqli_query($conn, "SELECT * FROM `kode pelanggaran`");
$kode_pelanggaran = [];
while ($row = mysqli_fetch_assoc($data)) {
  $kode_pelanggaran[] = $row;
}

// ambil data pelanggaran
$data = mysqli_query($conn, "SELECT * FROM pelanggaran WHERE kelas LIKE '%$selectedKelas%'");
$rows = [];
$idsiswa = [];
while ($row = mysqli_fetch_assoc($data)) {
  // ubah kode pelanggaran
  $kode = explode("#", $row["pelanggaran"]);
  $nkp = [];
  foreach ($kode as $k) {
    foreach ($kode_pelanggaran as $kp) {
      if ($k == $kp["id"]) {
        $nkp[] = $kp["pelanggaran"];
      }
    }
  }
  $row["pelanggaran"] = implode(" & ", $nkp);
  if (substr($row["tanggal"], 3) == $bulan) {
    $rows[] = $row;
    $idsiswa[] = $row["id siswa"];
  }
}

// Menghilangkan id siswa yang sama
$idsiswa = array_unique($idsiswa);
// filter
$pelanggaran = [];
$i = 0;
foreach ($idsiswa as $ids) {
  $pelanggaran[$i] = ["id siswa" => $ids,
    "kelas" => "",
    "pelanggaran" => []
  ];
  foreach ($rows as $r) {
    if ($r["id siswa"] == $ids) {
      // kembalikan data lainnya
      $pelanggaran[$i]["kelas"] = $r["kelas"];
      //
      $pelanggaran[$i]["pelanggaran"][] = $r["tanggal"] .": ".$r["pelanggaran"];
    }
  }
  $pelanggaran[$i]["total"] += count($pelanggaran[$i]["pelanggaran"]);
  // beri nilai per pelanggaran
  foreach ($pelanggaran[$i]["pelanggaran"] as $p) {
    $pelanggaran[$i]["rank"] += strlen($p);
  }
  $pelanggaran[$i]["rank"] += count($pelanggaran[$i]["pelanggaran"])*10;
  $i++;
}

// urutkan berdasarkan rank terbesar dan kelas
usort($pelanggaran, function ($a, $b) {
  if ($a["rank"] == $b["rank"]) {
    return strcmp($a["kelas"], $b["kelas"]);
  } else {
    return $b["rank"] - $a["rank"];
  }
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <title>Data Pelanggaran</title>
</head>
<body>
  <form action="" method="get" style="text-align:center;">
    <input type="month" name="bulan" value="<?=$_GET["bulan"]; ?>" id="bulan" onchange="this.form.submit()" style="display:inline-block;text-align:center;margin:18px auto;width:120px;height:30px;">
    <select name="kelas" id="kelas" onchange="this.form.submit()" style="display:inline-block;text-align:center;margin:18px auto;width:120px;height:30px;">
      <option value="semua">Semua</option>
      <?php foreach ($kelas as $k): ?>
      <option value="<?=$k; ?>" <?php if ($selectedKelas == $k): ?>selected<?php endif; ?>><?=$k; ?></option>
      <?php endforeach; ?>
    </select>
    <button onclick="convertToExcel(<?=str_replace('"', "'", json_encode($pelanggaran)); ?>,'pelanggaran_<?php if (!empty($selectedKelas)): echo $selectedKelas ?>_<?php endif; ?><?=str_replace("/", "-", $bulan); ?>.csv')">Excel</button>
  </form>
  <?php if (empty($pelanggaran)): ?>
  <center><h2>Data Masih Kosong...</h2></center>
  <?php else : ?>
  <table border="2px solid black" style="margin:auto;max-width:800px;">
    <tr>
      <th>No</th>
      <th>Nama</th>
      <th>Kelas</th>
      <th>Pelanggaran</th>
    </tr>
    <?php $i = 0; foreach ($pelanggaran as $r): $i++; ?>
    <tr>
      <td><?=$i; ?></td>
      <td><?=$r["id siswa"]; ?></td>
      <td><?=$r["kelas"]; ?></td>
      <td><?=implode(" | ", $r["pelanggaran"])." [".count($r["pelanggaran"])."]" ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
  <script type="text/javascript" charset="utf-8">
    function convertToExcel(data, filename) {
      // Membuat baris header
      const header = Object.keys(data[0]).join(",") + "\n";

      // Membuat baris data
      const rows = data.map(obj =>
        Object.values(obj)
        .map(value => `"${value}"`) // Menggunakan tanda kutip pada data
        .join(",")
      );
      const csv = header + rows.join("\n");

      // Membuat file Excel dengan tipe MIME "application/vnd.ms-excel"
      const blob = new Blob([csv], {
        type: "application/vnd.ms-excel"
      });

      // Membuat URL objek dari blob dan membuat hyperlink untuk download
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = filename;
      document.body.appendChild(link);
      link.click();

      // Membersihkan URL objek dan elemen hyperlink
      URL.revokeObjectURL(url);
      document.body.removeChild(link);
    }
  </script>
</body>
</html>